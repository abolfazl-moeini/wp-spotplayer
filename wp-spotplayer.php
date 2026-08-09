<?php
/**
 * Plugin Name: اسپات پلیر
 * Version: 17.1.0
 * Description: ابتدا در تنظیمات اسپات پلیر کلید API و کد ساخت لایسنس و سپس شناسه دوره‌های هر محصول را وارد نمایید.
 * Author: SpotPlayer.ir
 * Author URI: https://spotplayer.ir/
 * Requires PHP: 7.1
 **/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'SPOT_VERSION', '17.1.0' );

/**
 * Capability used for SpotPlayer admin UI.
 * WooCommerce: manage_woocommerce (shop_manager + admin). Otherwise manage_options.
 */
function spot_menu_capability(): string {
    return function_exists( 'wc_get_orders' ) ? 'manage_woocommerce' : 'manage_options';
}

function spot_user_can_manage(): bool {
    return current_user_can( spot_menu_capability() ) || current_user_can( 'manage_options' );
}

function spot_user_can_edit_api(): bool {
    return current_user_can( 'manage_options' );
}

/**
 * @return array<string, mixed>
 */
function spot_get_settings(): array {
    $settings = get_option( 'spotplayer', [] );

    return is_array( $settings ) ? $settings : [];
}

/**
 * @param array<string, mixed>|mixed $input
 * @return array<string, mixed>
 */
function spot_sanitize_settings( $input ): array {
    $prev = spot_get_settings();
    if ( ! is_array( $input ) ) {
        return $prev;
    }

    $out = [];

    if ( spot_user_can_edit_api() ) {
        $api = isset( $input['api'] ) ? sanitize_text_field( wp_unslash( $input['api'] ) ) : '';
        if ( $api === '' || preg_match( '/^(?:[A-Za-z0-9+\/]{4})*(?:[A-Za-z0-9+\/]{2}==|[A-Za-z0-9+\/]{3}=)?$/', $api ) ) {
            $out['api'] = $api;
        } else {
            $out['api'] = $prev['api'] ?? '';
            add_settings_error( 'spot_msgs', 'spot_api', 'فرمت کلید API نامعتبر بود و مقدار قبلی حفظ شد.', 'error' );
        }
    } else {
        $out['api'] = $prev['api'] ?? '';
    }

    $domain = isset( $input['domain'] ) ? sanitize_text_field( wp_unslash( $input['domain'] ) ) : '';
    $out['domain'] = ( $domain !== '' && preg_match( '/^app[0-9]?(\.[a-z0-9\-]+){2,}$/', $domain ) ) ? $domain : '';

    $color = isset( $input['color'] ) ? sanitize_hex_color( wp_unslash( $input['color'] ) ) : '';
    $out['color'] = $color ?: ( $prev['color'] ?? '#6611DD' );

    // License builder snippet is evaluated server-side; only admins with settings access can set it.
    $out['code'] = isset( $input['code'] ) ? (string) wp_unslash( $input['code'] ) : '';

    foreach ( [ 'test', 'completed', 'web', 'webonly', 'download', 'wccrs', 'wcspc' ] as $flag ) {
        $out[ $flag ] = ! empty( $input[ $flag ] ) ? 1 : 0;
    }

    if ( ! empty( $input['time'] ) ) {
        $time_val   = absint( $input['time'] );
        $out['time'] = $time_val > 0 ? $time_val : ( ! empty( $prev['time'] ) ? (int) $prev['time'] : time() );
    } else {
        $out['time'] = 0;
    }

    if ( empty( $out['web'] ) ) {
        $out['webonly'] = 0;
    }
    if ( ! empty( $out['webonly'] ) ) {
        $out['download'] = 0;
    }

    return $out;
}

function spot_url_handler() {
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    $p           = str_replace( parse_url( get_home_url(), PHP_URL_PATH ), '', $request_uri );
    $s           = substr( $p, 0, 6 );

    if ( $s === '/spotx' ) {
        spot_shop_x();
    }
    if ( $s === '/spdeb' ) {
        spot_debug();
    }
}

// SHOP ------------------------------------------------------------------------------------------------------------------
function spot_shop_x() {
    $cookie_x = $_COOKIE['X'] ?? '';
    if ( $cookie_x && ( microtime( true ) * 1000 ) > hexdec( substr( $cookie_x, 24, 12 ) ) ) {
        $new_cookie = null;
        if ( function_exists( 'wp_remote_head' ) ) {
            $response = wp_remote_head( 'https://app.spotplayer.ir/', [
                'headers'   => [ 'cookie' => 'X=' . $cookie_x ],
                'sslverify' => false,
            ] );
            if ( ! is_wp_error( $response ) ) {
                $cookies = wp_remote_retrieve_cookies( $response );
                foreach ( $cookies as $cookie ) {
                    if ( $cookie->name === 'X' ) {
                        $new_cookie = $cookie->value;
                        break;
                    }
                }
            }
        } elseif ( class_exists( 'WpOrg\Requests\Requests' ) ) {
            $req = \WpOrg\Requests\Requests::head( 'https://app.spotplayer.ir/', [ 'cookie' => 'X=' . $cookie_x ], [ 'verify' => false, 'verifyname' => false ] );
            $new_cookie = $req->cookies['X'] ?? null;
        } elseif ( class_exists( 'Requests' ) ) {
            $req = Requests::head( 'https://app.spotplayer.ir/', [ 'cookie' => 'X=' . $cookie_x ], [ 'verify' => false, 'verifyname' => false ] );
            $new_cookie = $req->cookies['X'] ?? null;
        }

        if ( $new_cookie ) {
            setcookie( 'X', $new_cookie, time() + 9e9, '/', parse_url( get_home_url(), PHP_URL_HOST ), true, false );
        }
    }
    die();
}

function spot_debug() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Access denied' );
    }
    $id = absint( $_GET['id'] ?? 0 );
    if ( ! $id ) {
        wp_die( 'شناسه سفارش نامعتبر است.' );
    }

    header( 'Content-Type: application/json' );
    if ( spot_woo_or_edd() == 1 ) {
        $o = wc_get_order( $id );
        if ( ! $o ) {
            wp_die( 'سفارش یافت نشد.' );
        }
        header( 'Content-Disposition: attachment; filename=debug-' . $o->get_id() . '.json' );
        die( json_encode( [
            'code' => spot_license_code(),
            'user' => get_user_meta( $o->get_user_id() ),
            'data' => $o->get_data(),
            'meta' => $o->get_meta_data(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS | JSON_PRETTY_PRINT ) );
    } else {
        $p = edd_get_payment( $id );
        if ( ! $p ) {
            wp_die( 'پرداخت یافت نشد.' );
        }
        header( 'Content-Disposition: attachment; filename=debug-' . $p->ID . '.json' );
        die( json_encode( [
            'code' => spot_license_code(),
            'user' => get_user_meta( $p->user_id ),
            'data' => $p
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS | JSON_PRETTY_PRINT ) );
    }
}

add_action( 'parse_request', 'spot_url_handler' );

// CSS -----------------------------------------------------------------------------------------
function spot_shop_css() {
    wp_enqueue_style( 'spot-shop', plugins_url( '/shop.css', __FILE__ ), [], SPOT_VERSION );
    $c = spot_get_settings()['color'] ?? '#6611DD';
    if ( ! is_string( $c ) || ! preg_match( '/^#[0-9A-F]{6}$/i', $c ) ) {
        $c = '#6611DD';
    }
    wp_add_inline_style( 'spot-shop',
        "#sp_license > BUTTON {background: $c} #sp B {color: $c} #sp_players > DIV {background: " . spot_hex2rgba( $c, 0.05 ) . "}" );
}

add_action( 'wp_enqueue_scripts', 'spot_shop_css' );

function spot_admin_css() {
    wp_enqueue_style( 'spot-admin', plugins_url( '/admin.css', __FILE__ ), [], SPOT_VERSION );
}

add_action( 'admin_enqueue_scripts', 'spot_admin_css' );

// ADMIN ------------------------------------------------------------------------------------------------
function spot_plugin_action_links( $links, $file ) {
    if ( strpos( $file, 'spotplayer' ) !== false ) {
        array_unshift( $links,
            '<a href="' . admin_url( 'admin.php?page=spotplayer' ) . '">تنظیمات</a>',
            '<a target="_blank" href="https://spotplayer.ir/help/api/wordpress">راهنما</a>' );
    }

    return $links;
}

add_filter( 'plugin_action_links', 'spot_plugin_action_links', 10, 2 );

/**
 * One-time cleanup: older 17.0.x builds granted shop_manager manage_options.
 */
function spot_revoke_elevated_shop_manager_cap() {
    if ( get_option( 'spot_revoked_sm_manage_options' ) === '1' ) {
        return;
    }
    $role = get_role( 'shop_manager' );
    if ( $role instanceof WP_Role && $role->has_cap( 'manage_options' ) ) {
        $role->remove_cap( 'manage_options' );
    }
    update_option( 'spot_revoked_sm_manage_options', '1', false );
}

add_action( 'admin_init', 'spot_revoke_elevated_shop_manager_cap' );

function spot_admin_menu() {
    register_setting( 'spotplayer', 'spotplayer', [
        'type'              => 'array',
        'sanitize_callback' => 'spot_sanitize_settings',
        'default'           => [],
    ] );
    add_menu_page( '', 'اسپات پلیر', spot_menu_capability(), 'spotplayer', 'spot_admin_page',
        plugins_url( '/icon.svg', __FILE__ ) );
}

add_action( 'admin_menu', 'spot_admin_menu' );

/**
 * Allow shop managers to save SpotPlayer settings without manage_options.
 */
function spot_option_page_capability( $capability ) {
    return spot_menu_capability();
}

add_filter( 'option_page_capability_spotplayer', 'spot_option_page_capability' );

function spot_admin_page() {
    if ( ! spot_user_can_manage() ) {
        return;
    }

    if ( isset( $_GET['settings-updated'] ) ) {
        add_settings_error( 'spot_msgs', 'spot_msg', 'تنظیمات اسپات پلیر ذخیره شد.', 'updated' );
    }
    settings_errors( 'spot_msgs' );

    $p  = spot_woo_or_edd();
    $sp = spot_get_settings();
    ?>
    <div id="sp-settings" class="wrap">
        <h1>
            تنظیمات اسپات پلیر
            <a href="https://spotplayer.ir/help/api/wordpress" target="_blank">(راهنما)</a>
        </h1>
        <form action="options.php" method="post">
            <?php settings_fields( 'spotplayer' ) ?>
            <table class="form-table" role="presentation">
                <tbody>
                <tr>
                    <?php if ( spot_user_can_edit_api() ) { ?>
                        <th scope="row">کلید API</th>
                        <td>
                            <input type="text" name="spotplayer[api]" value="<?= esc_attr( $sp['api'] ?? '' ) ?>" required
                                   pattern="^(?:[A-Za-z0-9+/]{4})*(?:[A-Za-z0-9+/]{2}==|[A-Za-z0-9+/]{3}=)?$">
                            <div class="description">
                                <div>کلید API که در داشبورد اسپات پلیر در دسترس است.</div>
                                <div><b style="color: #900">توجه داشته باشید تغییر کلمه عبور اسپات پلیر باعث تغییر کلید
                                        API خواهد شد.</b></div>
                            </div>
                        </td>
                    <?php } else { ?>
                        <th scope="row">کلید API</th>
                        <td>
                            <p class="description">فقط مدیر کل می‌تواند کلید API را مشاهده یا تغییر دهد.</p>
                        </td>
                    <?php } ?>
                </tr>
                <tr>
                    <th scope="row">دامنه ریبرندینگ</th>
                    <td>
                        <input type="text" name="spotplayer[domain]" value="<?= esc_attr( $sp['domain'] ?? '' ) ?>"
                               pattern="^app[0-9]?(\.[a-z0-9\-]+){2,}$">
                        <div class="description">
                            <div><b style="color: #900">تنها در صورتی که سرویس ریبرندینگ را فعال کرده اید، دامنه تنظیم
                                    شده را به صورت app.example.com وارد نمایید.</b></div>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th scope="row">رنگ اصلی</th>
                    <td>
                        <input type="color" name="spotplayer[color]" value="<?= esc_attr( ! empty( $sp['color'] ) ? $sp['color'] : '#6611DD' ) ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row">کد ساخت لایسنس</th>
                    <td>
                        <textarea name="spotplayer[code]"><?= esc_textarea( spot_license_code() ) ?></textarea>
                        <div style="background: rgba(0,0,0,0.07); padding: 10px; border-radius: 5px; margin-bottom: 15px">
                            <div style="color: green;">خروجی کد برای آخرین سفارش ثبت شده:</div>
                            <div style="direction: ltr">
                                <?php
                                try {
                                    $j = spot_woo_or_edd() == 1 ? spot_woo_license_data_eval( @wc_get_orders( [ 'limit' => 1 ] )[0] ) : @spot_edd_license_data_eval( edd_get_payments( [ 'number' => 1 ] )[0] );
                                    if ( ! $j ) {
                                        echo '<div style="color: red; direction: rtl">هیچ سفارش فعالی وجود ندارد. برای تست لطفا یک سفارش ایجاد کنید.</div>';
                                    } else {
                                        echo '<pre>' . esc_html( json_encode( $j, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) ) . '</pre>';
                                        if ( ! $j['name'] || ! $j['watermark']['texts'][0]['text'] ) {
                                            $id = spot_woo_or_edd() == 1 ? wc_get_orders( [ 'limit' => 1 ] )[0]->get_id() : edd_get_payments( [ 'number' => 1 ] )[0]->ID;
                                            $a  = '<div style="direction: rtl"><a target="_blank" href="' . parse_url( get_home_url(), PHP_URL_PATH ) . '/spdeb?id=' . $id . '">' . 'اطلاعات دیباگ' . '</a></div>';
                                            if ( ! $j['name'] ) {
                                                echo '<div style="color: red; direction: rtl">مقدار نام خالی است. لطفا از یک فیلد دیگر برای تعیین مقدار نام استفاده کنید.</div>' . $a;
                                            }
                                            if ( ! $j['watermark']['texts'][0]['text'] ) {
                                                echo '<div style="color: red; direction: rtl">مقدار اولین واترمارک خالی است. لطفا از یک فیلد دیگر برای تعیین مقدار واترمارک استفاده کنید.</div>' . $a;
                                            }
                                        }
                                    }
                                } catch ( Error $e ) {
                                    echo '<div style="color: red">' . esc_html( $e->getMessage() ) . '</div>';
                                    echo '<div style="color: red; direction: rtl">لطفا سینتکس کد وارد شده را بررسی و اصلاح کرده و تنظیمات را ذخیره نمایید.</div>';
                                } catch ( Exception $ex ) {
                                    echo '<div style="color: red">' . esc_html( $ex->getMessage() ) . '</div>';
                                } ?>
                            </div>
                        </div>
                        <div class="description">
                            <div>کدی که به منظور ساخت لایسنس استفاده میشود. برای بازیابی مقدار پیشفرض این فیلد را خالی
                                قرار داده و تنظیمات را ذخیره نمایید. برای ساخت لایسنس متغیرهای زیر در دسترس هستند:
                            </div>
                            <?php if ( $p == 1 ) { ?>
                                <div>متغیر order ووکامرس شامل اطلاعات سفارش است، که دسترسی به اطلاعات اصلی آن توسط
                                    متدهای پیشفرض و دسترسی به متادیتای آن توسط آن توسط متد get_meta امکان‌پذیر میباشد.
                                </div>
                                <ul style="direction: ltr">
                                    <li style="margin-top: 15px"><b>$order</b> <a target="_blank"
                                                                                  href="https://woocommerce.github.io/code-reference/classes/WC-Order.html"><small>https://woocommerce.github.io/code-reference/classes/WC-Order.html</small></a>
                                    </li>
                                    <li>$order-&gt;get_formatted_billing_full_name()</li>
                                    <li>$order-&gt;get_billing_phone()</li>
                                    <li>$order-&gt;get_billing_email()</li>
                                    <li>$order-&gt;get_meta("_meta_key")</li>
                                </ul>
                            <?php } elseif ( $p == 2 ) { ?>
                                <ul style="direction: ltr">
                                    <li style="margin-top: 15px"><b>$payment</b> <a target="_blank"
                                                                                    href="https://docs.easydigitaldownloads.com/article/1113-eddpayment"><small>https://docs.easydigitaldownloads.com/article/1113-eddpayment</small></a>
                                    </li>
                                    <li>$payment-&gt;first_name</li>
                                    <li>$payment-&gt;last_name</li>
                                    <li>$payment-&gt;email</li>
                                </ul>
                            <?php } else { ?>
                                <div style="color: red">هیچکدام از پلاگین‌های ووکامرس یا EDD فعال نیستند.</div>
                            <?php } ?>
                            <?php if ( $p ) { ?>
                                <div>متغیر user وردپرس شامل اطلاعات خریدار است، که دسترسی به اطلاعات آن توسط متد get و
                                    همچنین برای برخی از اطلاعات اصلی توسط فیلدهای پیشرفض امکان‌پذیر میباشد.
                                </div>
                                <ul style="direction: ltr">
                                    <li style="margin-top: 15px"><b>$user</b> <a target="_blank"
                                                                                 href="https://developer.wordpress.org/reference/classes/wp_user/"><small>https://developer.wordpress.org/reference/classes/wp_user/</small></a>
                                    </li>
                                    <li>$user-&gt;user_login</li>
                                    <li>$user-&gt;user_firstname</li>
                                    <li>$user-&gt;user_lastname</li>
                                    <li>$user-&gt;user_email</li>
                                    <li>$user-&gt;get('digits_phone')</li>
                                </ul>
                                <div>برای مثال digits_phone نامی است که پلاگین دیجیتس برای ذخیره شماره تایید شده کاربران
                                    استفاده میکند. در صورت استفاده از پلاگینی دیگر، باید فیلدی که برای ذخیره شماره
                                    استفاده میشود در دیتابیس یا تنظیمات پلاگین یافته و جایگزین این مقدار کنید.
                                </div>
                                <div><b style="color: #900">حتما از سیستم پیامک تایید شماره دیجیتس هنگام ثبت نام کاربران
                                        استفاده کنید تا واترمارک های ویدیو قابل ردگیری باشد. پلاگین به صورت خودکار
                                        دیجیتس را تشخیص و کد را تغییر میدهد.</b></div>
                            <?php } ?>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th scope="row">تنظیمات ساخت لایسنس</th>
                    <td>
                        <div>
                            <input type="checkbox" name="spotplayer[test]"
                                   value="1" <?= ! empty( $sp['test'] ) ? 'checked="checked"' : '' ?>>
                            <b>حالت تستی ایجاد لایسنس ←</b>
                            فعال بودن این گزینه باعث ایجاد شدن لایسنس های تستی پس از خریدها میشود. ایجاد هر لایسنس تستی
                            باعث حذف لایسنس تستی قبلی خواهد شد.
                            در صورت فعال کردن این گزینه، به منظور جلوگیری از بروز مشکل برای کاربران قبل از شروع به فروش
                            دوره ها حتما به خاطر داشته باشید که این گزینه را غیرفعال کنید.
                            <div><b style="color: #900">به یاد داشته باشید که پس از تست افزونه حتما این گزینه را غیرفعال
                                    نمایید زیرا باعث میشود لایسنس‌های جدید جایگزین لایسنس‌های قبلی شوند.</b></div>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th scope="row"></th>
                    <td>
                        <div>
                            <input type="checkbox" name="spotplayer[time]"
                                   value="<?= esc_attr( ! empty( $sp['time'] ) ? $sp['time'] : time() ) ?>" <?= ! empty( $sp['time'] ) ? 'checked="checked"' : '' ?>>
                            <b>عدم ایجاد لایسنس برای سفارشات قدیمی ←</b>
                            فعال کردن این گزینه باعث میشود لایسنس برای سفارشاتی که قبل از فعال کردن این گزینه ثبت
                            شده‌اند ایجاد نشود.
                        </div>
                    </td>
                </tr>
                <tr>
                    <th scope="row"></th>
                    <td>
                        <div>
                            <input type="checkbox" name="spotplayer[completed]"
                                   value="1" <?= ! empty( $sp['completed'] ) ? 'checked="checked"' : '' ?>>
                            <b>ایجاد لایسنس پس از تکمیل سفارش به صورت دستی ←</b>
                            به طور پیشفرض در صورتی که خریدی شامل محصولی با دوره اسپات پلیر باشد پس از پرداخت مبلغ سفارش
                            توسط کاربر، پلاگین به طور خودکار سفارش را تایید و لایسنس را ایجاد میکند.
                            فعال کردن این گزینه باعث میشود چنین سفارشی پس از پرداخت به حالت در حال انجام رفته و تا زمانی
                            که تایید نشده است لایسنس ایجاد نشود.
                            این حالت این امکان را به شما میدهد که نام و متن واترمارک‌ها را قبل از ساخته شدن لایسنس بررسی
                            نمایید.
                            <div><b style="color: #900">توجه داشته باشید در صورتی که محصول دانلودی باشد ووکامرس به صورت
                                    خودکار سفارش را تکمیل خواهد کرد.</b></div>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th scope="row">تنظیمات نمایش</th>
                    <td>
                        <div>
                            <input type="checkbox" name="spotplayer[web]"
                                   value="1" <?= ! empty( $sp['web'] ) ? 'checked="checked"' : '' ?>
                                   onchange="const w = document.getElementById('webonly'); (w.disabled = !this.checked) ? (w.checked = false) : null; w.onchange(null)">
                            <b>نمایش نسخه وب در سایت ←</b>
                            فعال کردن این گزینه باعث میشود در صورتی که نسخه وب برای لایسنس ساخته شده فعال باشد پلیر تحت
                            وب در سایت شما نمایش داده شود.
                        </div>
                    </td>
                </tr>
                <tr>
                    <th scope="row"></th>
                    <td>
                        <div>
                            <input id="webonly" <?= ! empty( $sp['web'] ) ? '' : 'disabled="disabled"' ?> type="checkbox"
                                   name="spotplayer[webonly]"
                                   value="1" <?= ! empty( $sp['webonly'] ) ? 'checked="checked"' : '' ?>
                                   onchange="const d = document.getElementById('download'); (d.disabled = this.checked) ? (d.checked = false) : null;">
                            <b>فقط نمایش نسخه وب ←</b>
                            فعال کردن این گزینه باعث میشود که فقط نسخه وب نمایش داده شده و نسخه های نیتیو و همچنین لیست
                            دانلود نمایش داده نشوند.
                        </div>
                    </td>
                </tr>
                <tr>
                    <th scope="row"></th>
                    <td>
                        <div>
                            <input id="download" <?= ! empty( $sp['webonly'] ) ? 'disabled="disabled"' : '' ?> type="checkbox"
                                   name="spotplayer[download]"
                                   value="1" <?= ! empty( $sp['download'] ) ? 'checked="checked"' : '' ?>>
                            <b>نمایش لیست دانلود ←</b>
                            از آنجایی که برنامه به طور خودکار فایل‌ها را دانلود کرده و نمایش می‌دهد نیازی به دانلود مجزا
                            نبوده و فعال کردن این گزینه پیشنهاد نمیشود.
                            با توجه به اینکه لیست دانلود باعث گیج شدن کاربران در نحوه استفاده از برنامه به خصوص در
                            نسخه‌های موبایل میشود این گزینه به طور پیشفرض غیرفعال است
                            و در صورت فعال کردن آن پشتیبانی کاربران در نحوه استفاده از فایل‌های دانلودی به عهده ناشر
                            میباشد.
                        </div>
                    </td>
                </tr>
                <?php if ( spot_woo_or_edd() == 1 ) { ?>
                    <tr>
                        <th scope="row"></th>
                        <td>
                            <div>
                                <input type="checkbox" name="spotplayer[wccrs]"
                                       value="1" <?= ! empty( $sp['wccrs'] ) ? 'checked="checked"' : '' ?>>
                                <b>نمایش گزینه لایسنس‌های من در منوی کاربری ووکامرس ←</b>
                                فعال کردن این گزینه باعث میشود در منوی حساب من ووکامرس گزینه لایسنس‌های من که به صفحه
                                شورت کد دوره‌ها لینک است نمایش داده شود.
                            </div>
                        </td>
                    </tr>
                <?php }
                if ( class_exists( 'Studiare_Core' ) ) { ?>
                    <tr>
                        <th scope="row"></th>
                        <td>
                            <div>
                                <input type="checkbox" name="spotplayer[wcspc]"
                                       value="1" <?= ! empty( $sp['wcspc'] ) ? 'checked="checked"' : '' ?>>
                                <b>حذف لینک دوره‌های خریداری شده قالب استادیار از منوی کاربری ووکامرس ←</b>
                                فعال کردن این گزینه باعث میشود در منوی حساب من ووکامرس گزینه لینک دوره‌های خریداری شده
                                استادیار نمایش داده نشود. برای حذف لینک‌های دیگر ووکامرس لطفا راهنمای پلاگین را در سایت
                                اسپات پلیر مطالعه بفرمایید.
                            </div>
                        </td>
                    </tr>
                <?php } ?>
                <tr>
                    <th scope="row">شورت کدها</th>
                    <td>
                        <div>
                            <b>spotplayer_courses</b>
                            با استفاده از این شورت کد، کل دوره‌های سفارش‌های لایسنس دار کاربر با امکان مشاهده آنلاین و
                            دریافت لایسنس نمایش داده میشود. توجه داشته باشید برای نمایش داده شدن یک دوره حتما در این
                            صفحه حتما لایسمس برای آن سفارش باید ایجاد شده باشد.
                        </div>
                    </td>
                </tr>
                </tbody>
            </table>
            <p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary"
                                     value="ذخیره تنظیمات"></p>
        </form>
    </div>
<?php }

function spot_admin_order_box( $data ) {
    $data    = is_array( $data ) ? $data : [];
    $texts   = $data['watermark']['texts'] ?? [];
    $disable = ! empty( $data['_id'] ) ? 'disabled readonly' : '';
    wp_nonce_field( 'spot_order_license', 'spot_order_nonce' );
    ?>
    <table class="widefat" style="border: none">
        <tr>
            <td>شناسه:</td>
            <td>
                <input type="text" class="ltr" name="spot-id" value="<?= esc_attr( $data['_id'] ?? '' ) ?>" <?= $disable ?>/>
                <?php if ( ! $disable ) { ?>
                    <button type="submit" name="spot-retrieve" value="1">دریافت اطلاعات لایسنس با شناسه</button>
                <?php } ?>
            </td>
        </tr>
        <tr>
            <td>نام:</td>
            <td><input type="text" name="spot-name" value="<?= esc_attr( $data['name'] ?? '' ) ?>" <?= $disable ?>/></td>
        </tr>
        <?php for ( $i = 0; $i < 3; $i ++ ) { ?>
            <tr>
                <td>واترمارک <?= $i + 1 ?>:</td>
                <td><input type="text" class="ltr" name="spot-text[<?= $i ?>]"
                           value="<?= esc_attr( $texts[ $i ]['text'] ?? '' ) ?>" <?= $disable ?>/></td>
            </tr>
        <?php } ?>
        <tr>
            <td></td>
            <td>
                <?php if ( $disable ) { ?>
                    <button class="remove" type="submit" name="spot-remove" value="1">حذف اطلاعات لایسنس از وردپرس
                    </button>
                <?php } else { ?>
                    <button type="submit" name="spot-create" value="1">ایجاد لایسنس</button>
                    <button class="remove" type="submit" name="spot-remove" value="1">ریست اطلاعات</button>
                <?php } ?>
            </td>
        </tr>
    </table>
    <?php
}

// WOO ADMIN PRODUCT -------------------------------------------------------------------------------------
function spot_woo_admin_product_tab( $tabs ) {
    $tabs['spotplayer-tab'] = [
        'label'  => 'اسپات پلیر',
        'target' => 'spotplayer-product',
        'class'  => 'show_if_simple'
    ];

    return $tabs;
}

add_filter( 'woocommerce_product_data_tabs', 'spot_woo_admin_product_tab' );

function spot_woo_admin_product_panel() { ?>
    <div id="spotplayer-product" class="panel woocommerce_options_panel">
        <?php woocommerce_wp_textarea_input( [
            'id'          => '_spotplayer_course',
            'name'        => '_spotplayer_course',
            'label'       => 'شناسه دوره‌ها',
            'class'       => 'ltr',
            'desc_tip'    => true,
            'description' => 'شناسه دوره های مد نظر را از پنل اسپات پلیر کپی و با جدا کننده , در اینجا وارد کنید.'
        ] ) ?>
    </div>
<?php }

add_action( 'woocommerce_product_data_panels', 'spot_woo_admin_product_panel' );

function spot_woo_admin_product_update( WC_Product $product ) {
    spot_woo_admin_product_save( $product, wp_unslash( $_POST['_spotplayer_course'] ?? '' ) );
}

add_action( 'woocommerce_admin_process_product_object', 'spot_woo_admin_product_update' );

function spot_woo_admin_variation_panel( int $i, $data ) { ?>
    <div id="spotplayer-product"><?php woocommerce_wp_textarea_input( [
            'id'            => "spotplayer_course$i",
            'name'          => "spotplayer_course[$i]",
            'value'         => $data['_spotplayer_course'][0] ?? '',
            'label'         => 'شناسه های دوره اسپات پلیر',
            'wrapper_class' => 'form-row form-row-full',
            'class'         => 'ltr',
            'desc_tip'      => true,
            'description'   => 'شناسه دوره های مد نظر را از پنل اسپات پلیر کپی و با جدا کننده , در اینجا وارد کنید.',
        ] ) ?></div>
<?php }

add_action( 'woocommerce_product_after_variable_attributes', 'spot_woo_admin_variation_panel', 10, 2 );

function spot_woo_admin_variation_update( WC_Product_Variation $variation, int $i ) {
    $courses = wp_unslash( $_POST['spotplayer_course'] ?? [] );
    spot_woo_admin_product_save( $variation, is_array( $courses ) ? ( $courses[ $i ] ?? '' ) : '' );
}

add_action( 'woocommerce_admin_process_variation_object', 'spot_woo_admin_variation_update', 10, 2 );

function spot_woo_admin_product_save( $product, $course ) {
    if ( ! $product instanceof WC_Product || ! current_user_can( 'edit_product', $product->get_id() ) ) {
        return;
    }
    $course = sanitize_text_field( $course );
    if ( preg_match( '/^[0-9a-f]{24}(,[0-9a-f]{24})*$/i', $course ) ) {
        $product->update_meta_data( '_spotplayer_course', $course );
        $product->set_virtual( true );
        $product->set_sold_individually( true );
    } else {
        $product->update_meta_data( '_spotplayer_course', '' );
    }
}

// WOO ADMIN ORDER --------------------------------------------------------------------------------------------
/**
 * Resolve the order currently being edited in classic or HPOS screens.
 *
 * @return WC_Order|false|null
 */
function spot_get_current_admin_order() {
    global $theorder, $post;

    if ( isset( $theorder ) && $theorder instanceof WC_Order ) {
        return $theorder;
    }
    if ( isset( $post->ID ) ) {
        return wc_get_order( $post->ID );
    }
    if ( function_exists( 'wc_get_order' ) ) {
        return wc_get_order();
    }

    return null;
}

function spot_woo_admin_order() {
    if ( ! function_exists( 'wc_get_order' ) ) {
        return;
    }

    $order = spot_get_current_admin_order();
    if ( ! $order instanceof WC_Order ) {
        return;
    }

    if ( ! spot_woo_order_items( $order ) && ! spot_check_order_has_any_license( $order->get_id() ) ) {
        return;
    }

    $screens = [ 'shop_order' ];
    if ( function_exists( 'wc_get_page_screen_id' ) ) {
        $screens[] = wc_get_page_screen_id( 'shop-order' );
    }

    foreach ( array_unique( array_filter( $screens ) ) as $screen ) {
        add_meta_box(
            'sp-order',
            'اسپات پلیر',
            'spot_woo_admin_order_box',
            $screen,
            'normal',
            'high'
        );
    }
}

add_action( 'add_meta_boxes', 'spot_woo_admin_order', 0 );

function spot_woo_admin_order_box() {
    $order = spot_get_current_admin_order();
    if ( $order instanceof WC_Order ) {
        spot_admin_order_box( spot_woo_license_data( $order ) );
    }
}

function spot_woo_admin_order_save( $oid ) {
    if ( ! spot_user_can_manage() && ! current_user_can( 'edit_shop_orders' ) ) {
        return;
    }
    if ( empty( $_POST['spot_order_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['spot_order_nonce'] ) ), 'spot_order_license' ) ) {
        return;
    }

    $ord = wc_get_order( $oid );
    if ( ! $ord ) {
        return;
    }
    if ( ! count( spot_woo_order_items( $ord ) ) && ! spot_check_order_has_any_license( $ord->get_id() ) ) {
        return;
    }
    if ( ! empty( $_POST['spot-remove'] ) ) {
        $ord->delete_meta_data( '_spotplayer_data' );
        $ord->save_meta_data();
        $ord->add_order_note( 'اطلاعات لایسنس اسپات پلیر حذف شد.' );

        return;
    }

    $data = spot_woo_license_data( $ord );
    if ( ! empty( $data['_id'] ) ) {
        return;
    }

    if ( ! empty( $_POST['spot-retrieve'] ) ) {
        $id = sanitize_text_field( wp_unslash( $_POST['spot-id'] ?? '' ) );
        if ( ! preg_match( '/^[0-9a-f]{24}$/i', $id ) ) {
            return spot_admin_notice( 'شناسه لایسنس اسپات پلیر باید یک رشته هگز 24 کاراکتری باشد.', 'warning' );
        }

        try {
            $rep = spot_request_license_get( $id );
            if ( empty( $rep['_id'] ) ) {
                throw new Exception( '909' );
            }
            $id = $rep['_id'];
            $ord->update_meta_data( '_spotplayer_data', $rep );
            $ord->save_meta_data();
            $ord->add_order_note( $note = sprintf( 'اطلاعات لایسنس %s دریافت شد.',
                '<a href="https://panel.spotplayer.ir/license/edit/' . $id . '" target="_blank">' . $id . '</a>' ) );
            spot_admin_notice( $note . ' <a href="' . get_edit_post_link( $ord->get_id() ) . '">' . 'سفارش ' . $ord->get_id() . '</a>',
                'info' );
        } catch ( Exception $ex ) {
            spot_admin_notice( 'هنگام دریافت لایسنس  ' . $ex->getMessage() );
        }
    } elseif ( ! empty( $_POST['spot-create'] ) ) {
        $n = sanitize_text_field( wp_unslash( $_POST['spot-name'] ?? '' ) );
        $t = array_map( 'sanitize_text_field', wp_unslash( (array) ( $_POST['spot-text'] ?? [] ) ) );
        if ( $n && ! empty( $t[0] ) ) {
            try {
                $ord->update_meta_data( '_spotplayer_data', array_merge( $data, [
                    'name'      => $n,
                    'watermark' => [
                        'texts' => array_values( array_filter( [
                            [ 'text' => $t[0] ?? '' ],
                            [ 'text' => $t[1] ?? '' ],
                            [ 'text' => $t[2] ?? '' ]
                        ], function ( $e ) {
                            return strlen( $e['text'] ) > 3;
                        } ) )
                    ]
                ] ) );
                $ord->save_meta_data();
                spot_woo_order_license_request( $ord, true );
            } catch ( Exception $ex ) {
                spot_admin_notice( 'هنگام ایجاد لایسنس  ' . $ex->getMessage() );
            }
        } else {
            spot_admin_notice( 'نام و متن واترمارک اول وارد نشده بود.', 'warning' );
        }
    }
}

add_action( 'woocommerce_process_shop_order_meta', 'spot_woo_admin_order_save', 10, 1 );

// EDD ADMIN DOWNLOAD -----------------------------------------------------------------------------------------
function spot_edd_admin_dl( $dl_id ) { ?>
    <div id="spot-course">
        <label for="course">شناسه دوره های اسپات پلیر</label>
        <textarea id="course" name="spot_course"><?= esc_textarea( implode( ',',
                get_post_meta( $dl_id, '_spot_course', true ) ?: [] ) ) ?></textarea>
        <div>شناسه یک دوره یا چند دوره که با , از هم جدا شده اند.</div>
    </div>
<?php }

add_action( 'edd_price_field', 'spot_edd_admin_dl', 10, 1 );

function spot_edd_admin_dl_save( $dl_id ) {
    if ( ! current_user_can( 'edit_post', $dl_id ) ) {
        return;
    }
    $raw_courses = sanitize_text_field( wp_unslash( $_POST['spot_course'] ?? '' ) );
    update_post_meta( $dl_id, '_spot_course', array_values( array_filter( array_map( 'trim', explode( ',', $raw_courses ) ), function ( $id ) {
        return (bool) preg_match( '/^[0-9a-f]{24}$/i', $id );
    } ) ) );
}

add_action( 'edd_save_download', 'spot_edd_admin_dl_save', 10, 2 );

// EDD ADMIN PAYMENT --------------------------------------------------------------------------------------------
function spot_edd_admin_payment_box( int $pid ) { ?>
    <div id="sp-order" class="postbox">
        <h3 class="hndle"><span>اطلاعات اسپات پلیر</span></h3>
        <div class="inside edd-clearfix"><?php spot_admin_order_box( spot_edd_license_data( edd_get_payment( $pid ) ) ) ?></div>
    </div>
<?php }

add_action( 'edd_view_order_details_main_before', 'spot_edd_admin_payment_box', 10, 1 );

function spot_edd_admin_payment_save( int $pid ) {
    if ( ! spot_user_can_manage() && ! current_user_can( 'edit_shop_payments' ) ) {
        return;
    }
    if ( empty( $_POST['spot_order_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['spot_order_nonce'] ) ), 'spot_order_license' ) ) {
        return;
    }

    $pay = edd_get_payment( $pid );
    if ( ! $pay || ! count( spot_edd_payment_items( $pay ) ) ) {
        return;
    }
    if ( ! empty( $_POST['spot-remove'] ) ) {
        $pay->delete_meta( '_spot_data' );
        edd_insert_payment_note( $pay->ID, 'اطلاعات لایسنس اسپات پلیر حذف شد.' );

        return;
    }

    $data = spot_edd_license_data( $pay );
    if ( ! empty( $data['_id'] ) ) {
        return;
    }

    if ( ! empty( $_POST['spot-retrieve'] ) ) {
        $id = sanitize_text_field( wp_unslash( $_POST['spot-id'] ?? '' ) );
        if ( ! preg_match( '/^[0-9a-f]{24}$/i', $id ) ) {
            return spot_admin_notice( 'شناسه لایسنس اسپات پلیر باید یه رشته هگز 24 کاراکتری باشد.', 'warning' );
        }

        try {
            $rep = spot_request_license_get( $id );
            if ( empty( $rep['_id'] ) ) {
                throw new Exception( '909' );
            }
            $id = $rep['_id'];

            $pay->update_meta( '_spot_data', $rep );
            edd_insert_payment_note( $pay->ID, $note = sprintf( 'اطلاعات لایسنس %s دریافت شد.',
                '<a href="https://panel.spotplayer.ir/license/edit/' . $id . '" target="_blank">' . $id . '</a>' ) );
            spot_admin_notice( $note . ' <a href="' . get_edit_post_link( $pay->ID ) . '">' . 'سفارش ' . $pay->ID . '</a>',
                'info' );
        } catch ( Exception $ex ) {
            spot_admin_notice( 'هنگام دریافت لایسنس  خطای ' . $ex->getMessage() . ' روی داد.' );
        }
    } elseif ( ! empty( $_POST['spot-create'] ) ) {
        $n = sanitize_text_field( wp_unslash( $_POST['spot-name'] ?? '' ) );
        $t = array_map( 'sanitize_text_field', wp_unslash( (array) ( $_POST['spot-text'] ?? [] ) ) );
        if ( $n && ! empty( $t[0] ) ) {
            try {
                $pay->update_meta( '_spot_data', array_merge( $data, [
                    'name'      => $n,
                    'watermark' => [
                        'texts' => array_values( array_filter( [
                            [ 'text' => $t[0] ?? '' ],
                            [ 'text' => $t[1] ?? '' ],
                            [ 'text' => $t[2] ?? '' ]
                        ], function ( $e ) {
                            return strlen( $e['text'] ) > 3;
                        } ) )
                    ]
                ] ) );
                spot_edd_payment_license_request( $pay, true );
            } catch ( Exception $ex ) {
                spot_admin_notice( 'هنگام ایجاد لایسنس  خطای ' . $ex->getMessage() . ' روی داد.' );
            }
        } else {
            spot_admin_notice( 'نام و متن واترمارک اول وارد نشده بود.', 'warning' );
        }
    }
}

add_action( 'edd_updated_edited_purchase', 'spot_edd_admin_payment_save', 10, 1 );

// WOO SHOP ------------------------------------------------------------------------------------------------------------------
function spot_woo_shop_order( WC_Order $ord ) {
    if ( $ord->get_customer_id() !== get_current_user_id() ) {
        return;
    }
    if ( ! in_array( $status = $ord->get_status(),
        [ 'processing', 'completed', 'partial-payment', 'partially-paid' ], true ) ) {
        return;
    }
    if ( ! count( spot_woo_order_items( $ord ) ) && ! spot_check_order_has_any_license( $ord->get_id() ) ) {
        return;
    }

    $sp        = spot_get_settings();
    $completed = ( $status === 'completed' );
    if ( ! empty( $sp['completed'] ) && ! $completed ) {
        return;
    }
    if ( spot_woo_shop_order_legacy( $ord ) ) {
        return;
    }

    try {
        spot_shop_success( spot_woo_order_license_request( $ord ) );

        if ( $completed ) {
            return;
        }
        foreach ( $ord->get_items() as $item ) {
            if ( ( $item instanceof WC_Order_Item_Product ) &&
                 ( ( $product = $item->get_product() ) instanceof WC_Product ) &&
                 ! ( $product->is_downloadable() || $product->get_meta( '_spotplayer_course' ) ) ) {
                return;
            }
        }
        $ord->update_status( 'completed' );

    } catch ( Exception $ex ) {
        spot_shop_failed( $ex->getMessage() );
    }
}

add_action( 'woocommerce_order_details_before_order_table', 'spot_woo_shop_order' );

function spot_woo_shop_order_legacy( WC_Order $ord ): bool { // Compatibility Code for Old Versions
    $legacy = false;
    foreach ( $ord->get_items() as $item ) {
        if ( ! ( $item instanceof WC_Order_Item_Product ) ) {
            continue;
        }
        $data = $item->get_meta( '_spotplayer_data' );
        if ( is_array( $data ) && ! empty( $data['_id'] ) ) {
            $product = $item->get_product();
            spot_shop_success( $data, $product ? $product->get_name() : '' );
            $legacy = true;
        }
    }

    return $legacy;
}

function spot_woo_shortcode() {
    $uid = get_current_user_id();
    $o   = absint( $_GET['spo'] ?? 0 );

    if ( ! $uid || ( $o && ( $ord = wc_get_order( $o ) ) && $ord->get_customer_id() !== $uid ) ) {
        return '<script type="application/javascript">window.location.href = "' . esc_url( get_home_url() ) . '"</script>';
    }

    ob_start();
    if ( isset( $ord ) && $ord ) {
        $spp = absint( $_GET['spp'] ?? 0 );
        $spc = sanitize_text_field( $_GET['spc'] ?? '' );
        $product = wc_get_product( $spp );
        $product_name = $product ? $product->get_name() : '';
        spot_shop_success( $ord->get_meta( '_spotplayer_data' ), $product_name, $spc );
    } else { ?>
        <div id="sp_courses">
            <?php foreach ( wc_get_orders( [ 'customer' => $uid, 'limit' => -1 ] ) as $ord ) {
                $license_meta = $ord->get_meta( '_spotplayer_data' );
                if ( is_array( $license_meta ) && ! empty( $license_meta['_id'] ) ) {
                    foreach ( spot_woo_order_items( $ord, true ) as $p ) { ?>
                        <a href="<?= esc_url( add_query_arg( [
                            'spo' => $ord->get_id(),
                            'spp' => $p->get_id(),
                            'spc' => $p->get_meta( '_spotplayer_course' ),
                        ] ) ) ?>"><?= $p->get_image() ?>
                            <h2><?= esc_html( $p->get_name() ) ?></h2></a>
                    <?php }
                }
            } ?>
        </div>
    <?php }

    return ob_get_clean();
}

function spot_woo_shop_my_menu( $links ): array {
    $o = spot_get_settings();
    if ( class_exists( 'Studiare_Core' ) && ! empty( $o['wcspc'] ) ) {
        unset( $links['purchased-products'] );
    }
    if ( empty( $o['wccrs'] ) ) {
        return $links;
    }

    return array_slice( $links, 0, 1, true ) + [ 'licenses' => 'لایسنس‌های من' ] + array_slice( $links, 1, null, true );
}

add_filter( 'woocommerce_account_menu_items', 'spot_woo_shop_my_menu', 50 );

function spot_woo_shop_my_licenses_init() {
    add_rewrite_endpoint( 'licenses', EP_PAGES );
    if ( get_option( 'spot_rewrite_version' ) !== SPOT_VERSION ) {
        flush_rewrite_rules( false );
        update_option( 'spot_rewrite_version', SPOT_VERSION, false );
    }
}

add_action( 'init', 'spot_woo_shop_my_licenses_init' );

function spot_woo_shop_my_licenses_content() {
    echo spot_shortcode();
}

add_action( 'woocommerce_account_licenses_endpoint', 'spot_woo_shop_my_licenses_content' );

// EDD SHOP ------------------------------------------------------------------------------------------------------------------
function spot_edd_shop_order( EDD_Payment $pay ) {
    if ( intval( edd_get_payment_user_id( $pay->ID ) ) !== get_current_user_id() ) {
        return;
    }
    if ( edd_get_payment_status( $pay ) !== 'complete' ) {
        return;
    }

    try {
        spot_shop_success( spot_edd_payment_license_request( $pay ) );
    } catch ( Exception $ex ) {
        spot_shop_failed( $ex->getMessage() );
    }
}

add_action( 'edd_payment_receipt_after_table', 'spot_edd_shop_order', 10, 1 );

function spot_edd_shortcode() {
    $uid = get_current_user_id();
    $o   = absint( $_GET['spo'] ?? 0 );

    if ( ! $uid || ( $o && ( intval( edd_get_payment_customer_id( $o ) ) !== $uid ) ) ) {
        return '<script type="application/javascript">window.location.href = "' . esc_url( get_home_url() ) . '"</script>';
    }

    ob_start();
    if ( $o ) {
        $spc = sanitize_text_field( $_GET['spc'] ?? '' );
        spot_shop_success( edd_get_payment( $o )->get_meta( '_spot_data' ), get_the_title( $o ), $spc );
    } else { ?>
        <div id="sp_courses">
            <?php foreach ( edd_get_payments( [ 'user' => $uid, 'output' => 'payments', 'number' => -1 ] ) as $pay ) {
                $license_meta = $pay->get_meta( '_spot_data' );
                if ( is_array( $license_meta ) && ! empty( $license_meta['_id'] ) ) {
                    foreach ( spot_edd_payment_items( $pay, true ) as $d ) { ?>
                        <a href="<?= esc_url( add_query_arg( [
                            'spo' => $pay->ID,
                            'spp' => $d['id'],
                            'spc' => $d['course'],
                        ] ) ) ?>">
                            <?= get_the_post_thumbnail( $d['id'] ) ?>
                            <h2><?= esc_html( $d['name'] ) ?></h2>
                        </a>
                    <?php }
                }
            } ?>
        </div>
    <?php }

    return ob_get_clean();
}

function spot_shop_failed( $err ) { ?>
    <div id="spot_fail">
        <p><?= esc_html( $err ) ?></p>
        <button onclick="window.location.reload();">تلاش مجدد</button>
    </div>
<?php }

function spot_shop_success( $data, $product = '', $course = null ) {
    if ( ! is_array( $data ) || empty( $data ) ) {
        return;
    }

    $sp     = spot_get_settings();
    $domain = ! empty( $sp['domain'] ) ? $sp['domain'] : 'app.spotplayer.ir';
    $domain = preg_replace( '/[^a-z0-9.\-]/i', '', $domain );
    if ( ! $domain ) {
        $domain = 'app.spotplayer.ir';
    }
    $plugin_url = plugin_dir_url( __FILE__ );
    ?>
    <script type="application/javascript">
        function copy(txt, lbl) {
            try {
                navigator.clipboard.writeText(txt).catch(function () {
                    copyLegacy(txt);
                });
            } catch (e) {
                copyLegacy(txt);
            } finally {
                alert(lbl + ' به کلیپ بورد کپی شد.');
            }
        }

        function copyLegacy(txt) {
            const el = document.createElement('textarea');
            el.value = txt;
            el.style.position = 'absolute';
            el.style.opacity = '0';
            document.body.appendChild(el);
            el.select();
            document.execCommand('copy');
            document.body.removeChild(el);
        }

        function toggle(el) {
            el.className = el.className === 'active' ? '' : 'active';
        }

        /** @type {[{name: string, file: string, image: string, version: number, disable: boolean}]} */
        var spotplayer_players;
        /** @type {[{_id: string, name: string, items: [{_id: string, type: string, name: string, desc: string}]}]} */
        var spotplayer_courses;
    </script>
    <div id="sp">
        <?php if ( $product ) { ?><h1><?= esc_html( $product ) ?></h1><?php } ?>
        <div id="sp-warn">مطالب این دوره دارای واترمارک‌های پیدا و پنهان هستند و هر گونه کپی برداری و نشر آن قابل پیگیری
            بوده و موجب پیگرد قانونی خواهد شد.
        </div>
        <?php if ( ! empty( $sp['web'] ) ) { ?>
            <div id="sp-web">
                <h2>مشاهده در پلیر وب</h2>
                <p>توجه داشته باشید پس از فعال کردن لایسنس در این مرورگر، فقط در همین دستگاه و مرورگر میتوانید دوره را
                    مشاهده کنید و همچنین یک دستگاه از ظرفیت لایسنس کم خواهد شد.</p>
                <div id="spotplayer"></div>
                <script src="https://<?= esc_attr( $domain ) ?>/assets/js/app-api.js"></script>
                <script type="application/javascript">
                    (async function () {
                        (new SpotPlayer(document.getElementById('spotplayer'), '<?= esc_js( parse_url( get_home_url(), PHP_URL_PATH ) ) ?>/spotx'))
                            .Open('<?= esc_js( $data['key'] ?? '' ) ?>', <?= preg_match( '/^[0-9a-f]{24}$/i', (string) $course ) ? "'" . esc_js( $course ) . "'" : 'null' ?>);
                    })();
                </script>
            </div>
        <?php } ?>
        <?php if ( empty( $sp['webonly'] ) ) { ?>
            <div id="sp-app">
                <h2>مشاهده در اپلیکیشن</h2>
                <p>برای مشاهده دوره‌ها ابتدا پلیر را با توجه به سیستم عامل خود دانلود و نصب نمایید. پس از اجرای پلیر، در
                    صفحه ثبت دوره جدید کلید لایسنس را وارد، مکان ذخیره‌سازی را انتخاب و سپس فرم را تایید کنید.</p>

                <div id="sp_players">
                    <h3><b>❶</b> دانلود و نصب پلیر</h3>
                    <div id="sp_players_list">
                        <script src="https://<?= esc_attr( $domain ) ?>/player/?f=js&l=<?= esc_attr( $data['_id'] ?? '' ) ?>"></script>
                        <script type="application/javascript">
                            (function () {
                                var root = document.getElementById('sp_players_list');
                                if (!root || !window.spotplayer_players || !window.spotplayer_players.map) {
                                    return;
                                }
                                var domain = <?= wp_json_encode( $domain ) ?>;
                                root.insertAdjacentHTML('beforeend', window.spotplayer_players.map(function (p) {
                                    return [
                                        '<a target="_blank" ' + (p.file ? ('href="https://' + domain + p.file + '"') : '') + ' class="' + (p.disable ? 'disable' : '') + '">',
                                        ' <img alt="' + p.name + '" src="https://' + domain + p.image + '">',
                                        ' <b>' + p.name + '</b>',
                                        ' <u>' + (p.file ? p.version : 'به زودی') + '</u>',
                                        '</a>'
                                    ].join('');
                                }).join(''));
                            })();
                        </script>
                    </div>
                </div>

                <div id="sp_license">
                    <h3><b>❷</b> کپی و وارد نمودن کلید در پلیر</h3>
                    <textarea readonly><?= esc_textarea( $data['key'] ?? '' ) ?></textarea>
                    <button class="sp_color_back" onclick="copy('<?= esc_js( $data['key'] ?? '' ) ?>', 'کلید لایسنس')">کپی کلید</button>
                </div>

                <?php if ( ! empty( $sp['download'] ) && ! empty( $data['_id'] ) && ! empty( $data['key'] ) ) { ?>
                    <?php
                    $key_bin = @hex2bin( substr( $data['key'], 24, 64 ) );
                    $burl    = $key_bin ? ( 'https://' . $domain . '/' . $data['_id'] . '/' . md5( $key_bin ) . '/' ) : '';
                    if ( $burl ) :
                        ?>
                    <div id="sp_videos">
                        <h3><b>❸</b> دانلود ویدیوها</h3>
                        <p>اگرچه پلیر به صورت خودکار فایل‌های دوره را دانلود و در حین دانلود نمایش میدهد، اما میتوانید
                            فایل‌های دوره را به صورت مجزا از لینک‌های زیر دانلود کنید.</p>
                        <ul id="sp_videos_list">
                            <script src="<?= esc_url( $burl ) ?>?f=js"></script>
                            <script type="application/javascript">
                                (function () {
                                    var root = document.getElementById('sp_videos_list');
                                    if (!root || !window.spotplayer_courses || !window.spotplayer_courses.map) {
                                        return;
                                    }
                                    var burl = <?= wp_json_encode( $burl ) ?>;
                                    var down = <?= wp_json_encode( $plugin_url . 'down.svg' ) ?>;
                                    var dl = <?= wp_json_encode( $plugin_url . 'dl.svg' ) ?>;
                                    root.insertAdjacentHTML('beforeend', window.spotplayer_courses.map(function (c) {
                                        return [
                                            '<li><h4 onclick="toggle(this.parentNode)">',
                                            '<img src="' + down + '">' + c.name,
                                            '</h4><ul>',
                                            (c.items || []).map(function (v) {
                                                return [
                                                    '<li class="sp_' + v.type + '"><a href="' + burl + c._id + '/' + v._id + '.spot">',
                                                    '<img src="' + dl + '" />' + v.name,
                                                    '</a></li>'].join('');
                                            }).join(''),
                                            '</ul></li>'
                                        ].join('');
                                    }).join(''));
                                })();
                            </script>
                        </ul>
                    </div>
                    <?php endif; ?>
                <?php } ?>
            </div>
        <?php } ?>
    </div>
    <?php
}

function spot_shortcode() {
    $p = spot_woo_or_edd();

    return $p == 1 ? spot_woo_shortcode() : ( $p == 2 ? spot_edd_shortcode() : 'ووکامرس یا EDD نصب نشده است.' );
}

add_shortcode( 'spotplayer_courses', 'spot_shortcode' );

// WOO FUNCS ------------------------------------------------------------------------------------------------------------------
/** @return WC_Product[] */
function spot_woo_order_items( ?WC_Order $order, $products = false ): array {
    $result = [];
    if ( ! $order ) {
        return $result;
    }

    foreach ( $order->get_items() as $item ) {
        if ( ! $item instanceof WC_Order_Item_Product ) {
            continue;
        }

        $product = $item->get_product();
        if ( ! $product instanceof WC_Product ) {
            continue;
        }

        $licenses = $product->get_meta( '_spotplayer_course' );
        if ( ! $licenses ) {
            continue;
        }

        if ( $products ) {
            $result[] = $product;
        } else {
            $result = array_merge( $result, explode( ',', $licenses ) );
        }
    }

    return $result;
}

function spot_check_order_has_any_license( int $oder_id ) {
    global $wpdb;

    $note = $wpdb->get_var( $wpdb->prepare(
        "SELECT comment_content FROM $wpdb->comments WHERE comment_post_ID = %d AND comment_type = 'order_note' AND comment_content LIKE %s ORDER BY comment_ID DESC LIMIT 1",
        $oder_id,
        '%license/edit/%'
    ) );

    if ( ! is_string( $note ) || $note === '' ) {
        return '';
    }

    if ( ! preg_match( '#license/edit/+([^/\'"\s]+)#i', $note, $matches ) ) {
        return '';
    }

    return $matches[1] ?? '';
}

/** @throws Exception */
function spot_woo_order_license_request( WC_Order $ord, $admin = false ): ?array {
    $data = spot_woo_license_data( $ord );
    if ( ! empty( $data['_id'] ) ) {
        return $data;
    }
    $courses = spot_woo_order_items( $ord );
    if ( ! count( $courses ) ) {
        return null;
    }
    $settings = spot_get_settings();
    $min_time = ! empty( $settings['time'] ) ? (int) $settings['time'] : 0;
    if ( ! $admin && $min_time && $ord->get_date_created() && $ord->get_date_created()->getTimestamp() < $min_time ) {
        return null;
    }

    try {
        $rep = spot_request_license_put( array_merge( $data,
            [ 'course' => $courses, 'payload' => strval( $ord->get_id() ) ] ) );
        if ( empty( $rep['_id'] ) ) {
            throw new Exception( '999' );
        }
        $id   = $rep['_id'];
        $data = array_merge( $data, $rep );
        $ord->update_meta_data( '_spotplayer_data', $data );
        $ord->save_meta_data();
        $ord->add_order_note( sprintf( 'لایسنس  با شناسه %s برای این سفارش ایجاد شد.',
            '<a href="https://panel.spotplayer.ir/license/edit/' . $id . '" target="_blank">' . $id . '</a>' ) );

        return $data;

    } catch ( Exception $ex ) {
        $err = sprintf( 'خطای %s هنگام ایجاد لایسنس روی داد.', '<b>«' . $ex->getMessage() . '»</b>' );
        $ord->add_order_note( $err . ( ( (int) $ex->getCode() === 999 ) ? ' <a target="_blank" href="' . parse_url( get_home_url(),
                PHP_URL_PATH ) . '/spdeb?id=' . $ord->get_id() . '">' . 'اطلاعات دیباگ' . '</a>' : '' ) );
        spot_admin_notice( $err . ' <a href="' . get_edit_post_link( $ord->get_id() ) . '">' . 'سفارش ' . $ord->get_id() . '</a>' );
        throw new Exception( $err );
    }
}

function spot_woo_license_data( WC_Order $ord ): array { // dont rename $order, used in eval code
    $data = $ord->get_meta( '_spotplayer_data' ) ?: [];
    if ( ! is_array( $data ) ) {
        $data = [];
    }
    if ( in_array( $ord->get_status(), [ 'auto-draft', 'draft' ], true ) ) {
        return $data;
    }

    return $data ?: ( spot_woo_license_data_eval( $ord ) ?: [] );
}

function spot_woo_license_data_eval( ?WC_Order $order ): ?array { // dont rename $order & $user
    if ( ! $order ) {
        return null;
    }
    /** @noinspection PhpUnusedLocalVariableInspection */
    if ( ! ( $user = $order->get_user() ) ) {
        $normalize_mobile_number = function( string $number ) {
            if ( ! preg_match( '#\+?(?:98|0)?(9\d{9})#', $number, $matched ) ) {
                return '';
            }

            return '0' . $matched[1];
        };

        if ( $billing_email = $order->get_billing_email() ) {
            if ( $maybe_user = get_user_by( 'email', $billing_email ) ) {
                $user_mobile = get_user_meta( $maybe_user->ID, 'nikamooz_mobile', true );
                if ( $normalize_mobile_number( $user_mobile ) === $normalize_mobile_number( $order->get_billing_phone() ) ) {
                    $order->set_customer_id( $maybe_user->ID );
                    $order->save();
                    $user = $maybe_user;
                }
            }
        }
    }

    return @eval( "return " . spot_license_code() . ";" );
}

// EDD FUNCS ------------------------------------------------------------------------------------------------------------------
function spot_edd_payment_items( ?EDD_Payment $pay, $downloads = false ): array {
    $r = [];
    if ( $pay ) {
        foreach ( edd_get_payment_meta_cart_details( $pay->ID ) as $i ) {
            $c = get_post_meta( $i['id'], '_spot_course', true );
            if ( ! $downloads ) {
                $r = array_merge( $r, $c ?: [] );
            } elseif ( $i['course'] = join( ',', $c ?: [] ) ) {
                $r[] = $i;
            }
        }
    }

    return $r;
}

/** @throws Exception */
function spot_edd_payment_license_request( EDD_Payment $pay, $admin = false ): ?array {
    $data = spot_edd_license_data( $pay );
    if ( ! empty( $data['_id'] ) ) {
        return $data;
    }
    $courses = spot_edd_payment_items( $pay );
    if ( ! count( $courses ) ) {
        return null;
    }
    $settings = spot_get_settings();
    $min_time = ! empty( $settings['time'] ) ? (int) $settings['time'] : 0;
    if ( ! $admin && $min_time ) {
        $completed = edd_get_payment_completed_date( $pay->ID );
        if ( $completed && strtotime( $completed ) < $min_time ) {
            return null;
        }
    }

    try {
        $rep = spot_request_license_put( array_merge( $data,
            [ 'course' => $courses, 'payload' => strval( $pay->ID ) ] ) );
        if ( empty( $rep['_id'] ) ) {
            throw new Exception( '999' );
        }
        $id   = $rep['_id'];
        $data = array_merge( $data, $rep );
        $pay->update_meta( '_spot_data', $data );
        edd_insert_payment_note( $pay->ID, sprintf( 'لایسنس  با شناسه %s برای این سفارش ایجاد شد.',
            '<a href="https://panel.spotplayer.ir/license/edit/' . $id . '" target="_blank">' . $id . '</a>' ) );

        return $data;

    } catch ( Exception $ex ) {
        $err = sprintf( 'خطای %s هنگام ایجاد لایسنس روی داد.', '<b>«' . $ex->getMessage() . '»</b>' );
        edd_insert_payment_note( $pay->ID,
            $err . ( ( (int) $ex->getCode() === 999 ) ? ' <a target="_blank" href="' . parse_url( get_home_url(),
                    PHP_URL_PATH ) . '/spdeb?id=' . $pay->ID . '">' . 'اطلاعات دیباگ' . '</a>' : '' ) );
        spot_admin_notice( $err . ' <a href="' . get_edit_post_link( $pay->ID ) . '">' . 'سفارش ' . $pay->ID . '</a>' );
        throw new Exception( $err );
    }
}

function spot_edd_license_data( $payment ) { // dont rename $payment, maybe used in eval code
    if ( ! $payment ) {
        return [];
    }
    if ( $data = $payment->get_meta( '_spot_data' ) ?: [] ) {
        return $data;
    }

    $eval_data = spot_edd_license_data_eval( $payment );
    if ( is_array( $eval_data ) && ! empty( $eval_data ) ) {
        return $eval_data;
    }

    $user_id = edd_get_payment_user_id( $payment->ID );
    $user    = get_userdata( $user_id );

    $data = [];

    if ( ! empty( $user->display_name ) ) {
        $data['name'] = $user->display_name;
    } elseif ( ! empty( $user->user_nicename ) ) {
        $data['name'] = $user->user_nicename;
    } elseif ( $user ) {
        $data['name'] = sprintf( 'username:%s', $user->user_login ?? '' );
    }

    if ( $mobile = get_user_meta( $user_id, 'mobile_user', true ) ) {
        $data['watermark']['texts'][0]['text'] = $mobile;
    } elseif ( $mobile = get_user_meta( $user_id, 'billing_phone', true ) ) {
        $data['watermark']['texts'][0]['text'] = $mobile;
    }

    return $data;
}

function spot_edd_license_data_eval( ?EDD_Payment $payment ) { // dont rename $payment & $user
    if ( ! $payment ) {
        return null;
    }
    /** @noinspection PhpUnusedLocalVariableInspection */
    $user = get_userdata( edd_get_payment_user_id( $payment->ID ) );

    return @eval( "return " . spot_license_code() . ";" );
}

// FUNCS ------------------------------------------------------------------------------------------------------------------
function spot_woo_or_edd(): int {
    return function_exists( 'wc_get_orders' ) ? 1 : ( function_exists( 'edd_get_payments' ) ? 2 : 0 );
}

/** @throws Exception */
function spot_request_license_get( $id ) {
    return spot_request( 'https://panel.spotplayer.ir/license/edit/' . $id . '?d=1' );
}

/** @throws Exception */
function spot_request_license_put( $j ) {
    if ( empty( $j['name'] ) ) {
        throw new Exception( 'نام لایسنس خالی بود.', 999 );
    }
    if ( empty( $j['watermark']['texts'][0]['text'] ) ) {
        throw new Exception( 'واترمارک لایسنس خالی بود.', 999 );
    }

    $settings = spot_get_settings();

    return spot_request( 'https://panel.spotplayer.ir/license/edit/',
        array_merge( $j, [ 'test' => ! empty( $settings['test'] ) ? 1 : 0 ] ) );
}

/** @throws Exception */
function spot_request( string $url, $data = [] ) {
    $settings = spot_get_settings();
    $api_key  = $settings['api'] ?? '';
    $headers  = [
        'Content-Type' => 'application/json',
        '$Level'       => '-1',
        '$API'         => $api_key,
        'X-WpSpot'     => SPOT_VERSION,
    ];
    $is_post  = ! empty( $data );
    $body_json = $is_post ? wp_json_encode( $data, JSON_UNESCAPED_UNICODE ) : null;

    if ( function_exists( 'wp_remote_request' ) ) {
        $args = [
            'method'    => $is_post ? 'POST' : 'GET',
            'headers'   => $headers,
            'sslverify' => false,
            'timeout'   => 15,
        ];
        if ( $is_post ) {
            $args['body'] = $body_json;
        }
        $response = wp_remote_request( $url, $args );
        if ( is_wp_error( $response ) ) {
            throw new Exception( $response->get_error_message() );
        }
        $body = wp_remote_retrieve_body( $response );
    } elseif ( class_exists( 'WpOrg\Requests\Requests' ) ) {
        $res  = \WpOrg\Requests\Requests::request( $url, $headers, $body_json, $is_post ? 'POST' : 'GET', [ 'verify' => false, 'verifyname' => false ] );
        $body = $res->body;
    } elseif ( class_exists( 'Requests' ) ) {
        $res  = Requests::request( $url, $headers, $body_json, $is_post ? 'POST' : 'GET', [ 'verify' => false, 'verifyname' => false ] );
        $body = $res->body;
    } else {
        throw new Exception( 'هیچ متد درخواست HTTP در دسترس نیست.' );
    }

    if ( $body === '' || $body === null ) {
        throw new Exception( 'پاسخ خالی از سرور اسپات پلیر دریافت شد.' );
    }

    $rep = json_decode( $body, true );
    if ( ! is_array( $rep ) ) {
        throw new Exception( 'پاسخ نامعتبر از سرور اسپات پلیر دریافت شد.' );
    }
    if ( ! empty( $rep['ex']['msg'] ) ) {
        throw new Exception( $rep['ex']['msg'] );
    }

    return $rep;
}

function spot_admin_notice( $notice = '', $type = 'error', $dismissible = true ) {
    $notices   = get_option( 'spotplayer_notices', [] );
    $notices[] = [ 'notice' => $notice, 'type' => $type, 'dismissible' => $dismissible ? 'is-dismissible' : '' ];
    update_option( "spotplayer_notices", $notices );
}

function spot_admin_notices() {
    $notices = get_option( 'spotplayer_notices', [] );
    foreach ( $notices as $n ) {
        printf( '<div class="notice notice-%1$s %2$s"><p>%3$s</p></div>', esc_attr( $n['type'] ), esc_attr( $n['dismissible'] ), $n['notice'] );
    }
    if ( ! empty( $notices ) ) {
        delete_option( "spotplayer_notices" );
    }
}

add_action( 'admin_notices', 'spot_admin_notices', 10 );

function spot_license_code() {
    $dgts = function_exists( 'digits_version' ) ? "\$user->get('digits_phone')" : null;
    $code = spot_get_settings()['code'] ?? '';

    if ( is_string( $code ) && trim( $code ) !== '' ) {
        return $code;
    }

    return spot_woo_or_edd() === 1
        ? "[\n\t'name' => \$order->get_formatted_billing_full_name(), \n\t'watermark' => ['texts' => [['text' => " . ( $dgts ?: '$order->get_billing_phone()' ) . "]]]\n]"
        : "[\n\t'name' => \$payment->first_name . ' ' . \$payment->last_name, \n\t'watermark' => ['texts' => [['text' => " . ( $dgts ?: '$payment->email' ) . "]]]\n]";
}

function spot_hex2rgba( $h, $o = 1 ): string {
    $h = ltrim( (string) $h, '#' );
    if ( strlen( $h ) !== 6 ) {
        return 'rgba(102,17,221,' . min( (float) $o, 1 ) . ')';
    }
    $parts = [ $h[0] . $h[1], $h[2] . $h[3], $h[4] . $h[5] ];
    $rgb   = array_map( 'hexdec', $parts );

    return 'rgba(' . implode( ',', $rgb ) . ',' . min( (float) $o, 1 ) . ')';
}
