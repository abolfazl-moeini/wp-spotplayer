<?php
/**
 * Plugin Name: اسپات پلیر
 * Version: 17.0.1
 * Description:  ابتدا در تنظیمات اسپات پلیر کلید API و کد ساخت لایسنس و سپس شناسه دوره‌های هر محصول را وارد نمایید.
 * Author: SpotPlayer.ir
 * Author URI: https://spotplayer.ir/
 * Requires PHP: 7.1
 **/

define( 'SPOT_VERSION', '17.0.1' );
function spot_url_handler() {
    $p = str_replace( parse_url( get_home_url(), PHP_URL_PATH ), '', $_SERVER["REQUEST_URI"] );
    $s = substr( $p, 0, 6 );
    if ( $s == '/spotx' ) {
        spot_shop_x();
    }
    if ( $s == '/spdeb' ) {
        spot_debug();
    }
}

add_action( 'parse_request', 'spot_url_handler' );

function spot_debug() {
    current_user_can( 'administrator' ) or die( 'Access denied' );
    header( 'Content-Type: application/json' );
    if ( spot_woo_or_edd() == 1 ) {
        $o = wc_get_order( $_GET['id'] );
        header( 'Content-Disposition: attachment; filename=debug-' . $o->get_id() . '.json' );
        die( json_encode( [
                'code' => spot_license_code(),
                'user' => get_user_meta( $o->get_user_id() ),
                'data' => $o->get_data(),
                'meta' => $o->get_meta_data(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS | JSON_PRETTY_PRINT ) );
    } else {
        $p = edd_get_payment( $_GET['id'] );
        header( 'Content-Disposition: attachment; filename=debug-' . $p->ID . '.json' );
        die( json_encode( [
                'code' => spot_license_code(),
                'user' => get_user_meta( $p->user_id ),
                'data' => $p
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS | JSON_PRETTY_PRINT ) );
    }
}

// CSS -----------------------------------------------------------------------------------------
function spot_shop_css() {
    wp_enqueue_style( 'spot-shop', plugins_url( '/shop.css', __FILE__ ) );
    $c = @get_option( 'spotplayer' )['color'] ?: '#6611DD';
    if ( ! preg_match( '/^#[0-9A-F]{6}$/i', $c ) ) {
        $c = '#6611DD';
    }
    wp_add_inline_style( 'spot-shop',
            "#sp_license > BUTTON {background: $c} #sp B {color: $c} #sp_players > DIV {background: " . spot_hex2rgba( $c,
                    0.05 ) . "}" );
}

add_action( 'wp_enqueue_scripts', 'spot_shop_css' );

function spot_admin_css() {
    wp_enqueue_style( 'spot-admin', plugins_url( '/admin.css', __FILE__ ) );
}

add_action( 'admin_enqueue_scripts', 'spot_admin_css' );


// ADMIN  ------------------------------------------------------------------------------------------------
function spot_plugin_action_links( $links, $file ) {
    if ( strpos( $file, 'spotplayer' ) !== false ) {
        array_unshift( $links,
                '<a href="' . admin_url( 'admin.php?page=spotplayer' ) . '">تنظیمات</a>',
                '<a target="_blank" href="https://spotplayer.ir/help/api/wordpress">راهنما</a>' );
    }

    return $links;
}

add_filter( 'plugin_action_links', 'spot_plugin_action_links', 10, 2 );
//////////////////////////////////////////////
function add_capabilities_to_shop_manager() {
    $shop_manager = get_role( 'shop_manager' );
    $shop_manager->add_cap( 'manage_options' );
}

add_action( 'init', 'add_capabilities_to_shop_manager' );
//////////////////////////////////////////////
function spot_admin_menu() {
    register_setting( 'spotplayer', 'spotplayer' );
    add_menu_page( '', 'اسپات پلیر', 'manage_options', 'spotplayer', 'spot_admin_page',
            plugins_url( '/icon.svg', __FILE__ ) );
}

add_action( 'admin_menu', 'spot_admin_menu' );

function spot_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    if ( isset( $_GET['settings-updated'] ) ) {
        add_settings_error( 'spot_msgs', 'spot_msg', 'تنظیمات اسپات پلیر ذخیره شد.', 'updated' );
    }
    settings_errors( 'spot_msgs' );

    $p            = spot_woo_or_edd();
    $sp           = get_option( 'spotplayer' );
    $current_user = wp_get_current_user();
    ?>
    <div id="sp-settings" class="wrap">
        <h1>
            تنظیمات اسپات پلیر
            <a href="https://spotplayer.ir/help/api/wordpress" target="_blank">(راهنما)</a>
        </h1>
        <!--suppress HtmlUnknownTarget -->
        <form action="options.php" method="post">
            <?php settings_fields( 'spotplayer' ) ?>
            <table class="form-table" role="presentation">
                <tbody>
                <tr>
                    <?php
                    if ( in_array( 'administrator', $current_user->roles ) ) { ?>
                        <th scope="row">کلید API</th>
                        <td>
                            <input type="text" name="spotplayer[api]" value="<?= @$sp['api'] ?>" required
                                   pattern="^(?:[A-Za-z0-9+/]{4})*(?:[A-Za-z0-9+/]{2}==|[A-Za-z0-9+/]{3}=)?$">
                            <div class="description">
                                <div>کلید API که در داشبورد اسپات پلیر در دسترس است.</div>
                                <div><b style="color: #900">توجه داشته باشید تغییر کلمه عبور اسپات پلیر باعث تغییر کلید
                                        API خواهد شد.</b></div>
                            </div>
                        </td>
                    <?php } elseif ( in_array( 'shop_manager', $current_user->roles ) ) { ?>
                        <td>
                            <input type="hidden" name="spotplayer[api]" value="<?= @$sp['api'] ?>" required
                                   pattern="^(?:[A-Za-z0-9+/]{4})*(?:[A-Za-z0-9+/]{2}==|[A-Za-z0-9+/]{3}=)?$">
                        </td>
                    <?php } ?>
                </tr>
                <tr>
                    <th scope="row">دامنه ریبرندینگ</th>
                    <td>
                        <input type="text" name="spotplayer[domain]" value="<?= @$sp['domain'] ?>"
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
                        <input type="color" name="spotplayer[color]" value="<?= @$sp['color'] ?: '#6611DD' ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row">کد ساخت لایسنس</th>
                    <td>
                        <textarea name="spotplayer[code]"><?= spot_license_code() ?></textarea>
                        <div style="background: rgba(0,0,0,0.07); padding: 10px; border-radius: 5px; margin-bottom: 15px">
                            <div style="color: green;">خروجی کد برای آخرین سفارش ثبت شده:</div>
                            <div style="direction: ltr">
                                <?php
                                try {
                                    $j = spot_woo_or_edd() == 1 ? spot_woo_license_data_eval( @wc_get_orders( [ 'limit' => 1 ] )[0] ) : @spot_edd_license_data_eval( edd_get_payments( [ 'number' => 1 ] )[0] );
                                    if ( ! $j ) {
                                        echo '<div style="color: red; direction: rtl">هیچ سفارش فعالی وجود ندارد. برای تست لطفا یک سفارش ایجاد کنید.</div>';
                                    } else {
                                        echo '<pre>' . json_encode( $j,
                                                        JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) . '</pre>';
                                        if ( ! $j['name'] || ! $j['watermark']['texts'][0]['text'] ) {
                                            $id = spot_woo_or_edd() == 1 ? wc_get_orders( [ 'limit' => 1 ] )[0]->get_id() : edd_get_payments( [ 'number' => 1 ] )[0]->ID;
                                            $a  = '<div style="direction: rtl"><a target="_blank" href="' . parse_url( get_home_url(),
                                                            PHP_URL_PATH ) . '/spdeb?id=' . $id . '">' . 'اطلاعات دیباگ' . '</a></div>';
                                            if ( ! $j['name'] ) {
                                                echo '<div style="color: red; direction: rtl">مقدار نام خالی است. لطفا از یک فیلد دیگر برای تعیین مقدار نام استفاده کنید.</div>' . $a;
                                            }
                                            if ( ! $j['watermark']['texts'][0]['text'] ) {
                                                echo '<div style="color: red; direction: rtl">مقدار اولین واترمارک خالی است. لطفا از یک فیلد دیگر برای تعیین مقدار واترمارک استفاده کنید.</div>' . $a;
                                            }
                                        }
                                    }
                                } catch ( Error $e ) {
                                    echo '<div style="color: red">' . $e->getMessage() . '</div>';
                                    echo '<div style="color: red; direction: rtl">لطفا سینتکس کد وارد شده را بررسی و اصلاح کرده و تنظیمات را ذخیره نمایید.</div>';
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
                                   value="1" <?= @$sp['test'] ? 'checked="checked"' : '' ?>>
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
                                   value="<?= @$sp['time'] ?: time() ?>" <?= @$sp['time'] ? 'checked="checked"' : '' ?>>
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
                                   value="1" <?= @$sp['completed'] ? 'checked="checked"' : '' ?>>
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
                                   value="1" <?= @$sp['web'] ? 'checked="checked"' : '' ?>
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
                            <input id="webonly" <?= @$sp['web'] ? '' : 'disabled="disabled"' ?> type="checkbox"
                                   name="spotplayer[webonly]"
                                   value="1" <?= @$sp['webonly'] ? 'checked="checked"' : '' ?>
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
                            <input id="download" <?= @$sp['webonly'] ? 'disabled="disabled"' : '' ?> type="checkbox"
                                   name="spotplayer[download]"
                                   value="1" <?= @$sp['download'] ? 'checked="checked"' : '' ?>>
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
                                       value="1" <?= @$sp['wccrs'] ? 'checked="checked"' : '' ?>>
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
                                       value="1" <?= $sp['wcspc'] ? 'checked="checked"' : '' ?>>
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
    $texts   = @$data['watermark']['texts'];
    $disable = @$data['_id'] ? 'disabled readonly' : ''; ?>
    <table class="widefat" style="border: none">
        <tr>
            <td>شناسه:</td>
            <td>
                <input type="text" class="ltr" name="spot-id" value="<?= @$data['_id'] ?>" <?= $disable ?>/>
                <?php if ( ! $disable ) { ?>
                    <button type="submit" name="spot-retrieve" value="1">دریافت اطلاعات لایسنس با شناسه</button>
                <?php } ?>
            </td>
        </tr>
        <tr>
            <td>نام:</td>
            <td><input type="text" name="spot-name" value="<?= $data['name'] ?>" <?= $disable ?>/></td>
        </tr>
        <?php for ( $i = 0; $i < 3; $i ++ ) { ?>
            <tr>
                <td>واترمارک <?= $i + 1 ?>:</td>
                <td><input type="text" class="ltr" name="spot-text[<?= $i ?>]"
                           value="<?= @$texts[ $i ]['text'] ?>" <?= $disable ?>/></td>
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
    spot_woo_admin_product_save( $product, $_POST['_spotplayer_course'] );
}

add_action( 'woocommerce_admin_process_product_object', 'spot_woo_admin_product_update' );

function spot_woo_admin_variation_panel( int $i, $data ) { ?>
    <div id="spotplayer-product"><?php woocommerce_wp_textarea_input( [
                'id'            => "spotplayer_course$i",
                'name'          => "spotplayer_course[$i]",
                'value'         => $data['_spotplayer_course'][0],
                'label'         => 'شناسه های دوره اسپات پلیر',
                'wrapper_class' => 'form-row form-row-full',
                'class'         => 'ltr',
                'desc_tip'      => true,
                'description'   => 'شناسه دوره های مد نظر را از پنل اسپات پلیر کپی و با جدا کننده , در اینجا وارد کنید.',
        ] ) ?></div>
<?php }

add_action( 'woocommerce_product_after_variable_attributes', 'spot_woo_admin_variation_panel', 10, 2 );

function spot_woo_admin_variation_update( WC_Product_Variation $variation, int $i ) {
    spot_woo_admin_product_save( $variation, $_POST['spotplayer_course'][ $i ] );
}

add_action( 'woocommerce_admin_process_variation_object', 'spot_woo_admin_variation_update', 10, 2 );

function spot_woo_admin_product_save( $product, $course ) {
    if ( ! current_user_can( 'administrator' ) ) {
        return;
    }
    if ( preg_match( '/^[0-9a-f]{24}(,[0-9a-f]{24})*$/i', $course ) ) {
        $product->update_meta_data( '_spotplayer_course', $course );
        $product->set_virtual( true );
        $product->set_sold_individually( true );
    } else {
        $product->update_meta_data( '_spotplayer_course', '' );
    }
}


// WOO ADMIN ORDER --------------------------------------------------------------------------------------------
function spot_woo_admin_order() {
    if ( function_exists( 'wc_get_order' ) && count( spot_woo_order_items( wc_get_order() ?: null ) ) ) {
        add_meta_box(
                'sp-order',
                'اسپات پلیر',
                'spot_woo_admin_order_box',
                null,
                'normal',
                'high'
        );
    }

}

add_action( 'add_meta_boxes', 'spot_woo_admin_order', 0 );

function spot_woo_admin_order_box() {
    spot_admin_order_box( spot_woo_license_data( wc_get_order() ) );
}


function spot_woo_admin_order_save( int $oid ) {
    if ( ! current_user_can( 'administrator' ) ) {
        return;
    }
    $ord = wc_get_order( $oid );
    if ( ! count( spot_woo_order_items( $ord ) ) ) {
        return;
    }
    if ( @$_POST['spot-remove'] ) {
        $ord->delete_meta_data( '_spotplayer_data' );
        $ord->save_meta_data();
        $ord->add_order_note( 'اطلاعات لایسنس اسپات پلیر حذف شد.' );

        return;
    }
    if ( @( $data = spot_woo_license_data( $ord ) )['_id'] ) {
        return;
    }

    if ( $_POST['spot-retrieve'] ) {
        if ( ! preg_match( '/^[0-9a-f]{24}$/i', $id = $_POST['spot-id'] ) ) {
            return spot_admin_notice( 'شناسه لایسنس اسپات پلیر باید یک رشته هگز 24 کاراکتری باشد.', 'warning' );
        }

        try {
            $rep = spot_request_license_get( $id );
            if ( ! ( $id = @$rep['_id'] ) ) {
                throw new Exception( '909' );
            }
            $ord->update_meta_data( '_spotplayer_data', $rep );
            $ord->save_meta_data();
            $ord->add_order_note( $note = sprintf( 'اطلاعات لایسنس %s دریافت شد.',
                    '<a href="https://panel.spotplayer.ir/license/edit/' . $id . '" target="_blank">' . $id . '</a>' ) );
            spot_admin_notice( $note . ' <a href="' . get_edit_post_link( $ord->get_id() ) . '">' . 'سفارش ' . $ord->get_id() . '</a>',
                    'info' );
        } catch ( Exception $ex ) {
            spot_admin_notice( 'هنگام دریافت لایسنس  ' . $ex->getMessage() );
        }
    } elseif ( $_POST['spot-create'] ) {
        if ( ( $n = $_POST['spot-name'] ) && ( $t = $_POST['spot-text'] ) ) {
            try {
                $ord->update_meta_data( '_spotplayer_data', array_merge( $data, [
                        'name'      => $n,
                        'watermark' => [
                                'texts' => array_values( array_filter( [
                                        [ 'text' => $t[0] ],
                                        [ 'text' => $t[1] ],
                                        [ 'text' => $t[2] ]
                                ], function ( $e ) {
                                    return strlen( $e['text'] ) > 3;
                                } ) )
                        ]
                ] ) );
                $ord->save_meta_data();
                spot_woo_order_license_request( $ord, true );
            } catch ( Exception $ex ) {
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
        <textarea id="course" name="spot_course"><?= implode( ',',
                    get_post_meta( $dl_id, '_spot_course', true ) ?: [] ) ?></textarea>
        <div>شناسه یک دوره یا چند دوره که با , از هم جدا شده اند.</div>
    </div>
<?php }

add_action( 'edd_price_field', 'spot_edd_admin_dl', 10, 1 );

function spot_edd_admin_dl_save( $dl_id ) {
    update_post_meta( $dl_id, '_spot_course', array_filter( explode( ',', $_POST['spot_course'] ), function ( $id ) {
        return preg_match( '/^[0-9a-f]{24}$/i', $id );
    } ) );
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
    if ( ! current_user_can( 'administrator' ) ) {
        return;
    }

    $pay = edd_get_payment( $pid );
    if ( ! count( spot_edd_payment_items( $pay ) ) ) {
        return;
    }
    if ( $_POST['spot-remove'] ) {
        $pay->delete_meta( '_spot_data' );
        edd_insert_payment_note( $pay->ID, 'اطلاعات لایسنس اسپات پلیر حذف شد.' );

        return;
    }
    if ( @( $data = spot_edd_license_data( $pay ) )['_id'] ) {
        return;
    }

    if ( $_POST['spot-retrieve'] ) {
        if ( ! preg_match( '/^[0-9a-f]{24}$/i', $id = $_POST['spot-id'] ) ) {
            return spot_admin_notice( 'شناسه لایسنس اسپات پلیر باید یه رشته هگز 24 کاراکتری باشد.', 'warning' );
        }

        try {
            $rep = spot_request_license_get( $id );
            if ( ! ( $id = @$rep['_id'] ) ) {
                throw new Exception( '909' );
            }

            $pay->update_meta( '_spot_data', $rep );
            edd_insert_payment_note( $pay->ID, $note = sprintf( 'اطلاعات لایسنس %s دریافت شد.',
                    '<a href="https://panel.spotplayer.ir/license/edit/' . $id . '" target="_blank">' . $id . '</a>' ) );
            spot_admin_notice( $note . ' <a href="' . get_edit_post_link( $pay->ID ) . '">' . 'سفارش ' . $pay->ID . '</a>',
                    'info' );
        } catch ( Exception $ex ) {
            spot_admin_notice( 'هنگام دریافت لایسنس  خطای ' . $ex->getMessage() . ' روی داد.' );
        }
    } elseif ( $_POST['spot-create'] ) {
        if ( ( $n = $_POST['spot-name'] ) && ( $t = $_POST['spot-text'] ) ) {
            try {
                $pay->update_meta( '_spot_data', array_merge( $data, [
                        'name'      => $n,
                        'watermark' => [
                                'texts' => array_values( array_filter( [
                                        [ 'text' => $t[0] ],
                                        [ 'text' => $t[1] ],
                                        [ 'text' => $t[2] ]
                                ], function ( $e ) {
                                    return strlen( $e['text'] ) > 3;
                                } ) )
                        ]
                ] ) );
                spot_edd_payment_license_request( $pay, true );
            } catch ( Exception $ex ) {
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
            [ 'processing', 'completed', 'partial-payment', 'partially-paid' ] ) ) {
        return;
    }
    if ( ! count( spot_woo_order_items( $ord ) ) ) {
        return;
    }

    $sp        = get_option( 'spotplayer' );
    $completed = ( $status == 'completed' );
    if ( @$sp['completed'] && ! $completed ) {
        return;
    }
    // if (spot_woo_shop_order_legacy($ord)) return;

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

    } catch ( Exception $ex ) {
        spot_shop_failed( $ex->getMessage() );
    }
}

add_action( 'woocommerce_order_details_before_order_table', 'spot_woo_shop_order' );

function spot_woo_shop_order_legacy( WC_Order $ord ): bool { // Compatibility Code for Old Versions
    $legacy = false;
    foreach ( $ord->get_items() as $item ) {
        if ( ( $item instanceof WC_Order_Item_Product ) && @( $data = $item->get_meta( '_spotplayer_data' ) )['_id'] ) {
            spot_shop_success( $data, $item->get_product()->get_name() );
            $legacy = true;
        }
    }

    return $legacy;
}

function spot_woo_shortcode() {

    if ( ! ( $uid = get_current_user_id() ) || ( ( $o = @$_GET['spo'] ) && ( $ord = wc_get_order( $o ) )->get_customer_id() !== $uid ) ) {
        return '<script type="application/javascript">window.location.href = "' . get_home_url() . '"</script>';
    }

    ob_start();
    if ( isset( $ord ) ) {
        spot_shop_success( $ord->get_meta( '_spotplayer_data' ), wc_get_product( $_GET['spp'] )->get_name(),
                $_GET['spc'] );
    } else { ?>
        <div id="sp_courses">
            <?php foreach ( wc_get_orders( [ 'customer' => get_current_user_id() ] ) as $ord ) {
                if ( @$ord->get_meta( '_spotplayer_data' )['_id'] ) {
                    foreach ( spot_woo_order_items( $ord, true ) as $p ) { ?>
                        <a href=<?= "?spo={$ord->get_id()}&spp={$p->get_id()}&spc={$p->get_meta('_spotplayer_course')}" ?>><?= $p->get_image() ?>
                            <h2><?= $p->get_name() ?></h2></a>
                    <?php }
                }
            } ?>
        </div>
    <?php }

    return ob_get_clean();
}

//echo '<style>.woocommerce-MyAccount-navigation .woocommerce-MyAccount-navigation-link--courses:before { content: "\f501"; }</style>';
function spot_woo_shop_my_menu( $links ): array {
    $o = @get_option( 'spotplayer' );
    if ( class_exists( 'Studiare_Core' ) && @$o['wcspc'] ) {
        unset( $links['purchased-products'] );
    }
    if ( ! @$o['wccrs'] ) {
        return $links;
    }

    return array_slice( $links, 0, 1, true ) + [ 'licenses' => 'لایسنس‌های من' ] + array_slice( $links, 1, null, true );
}

add_filter( 'woocommerce_account_menu_items', 'spot_woo_shop_my_menu', 50 );

function spot_woo_shop_my_licenses_init() {
    add_rewrite_endpoint( 'licenses', EP_PAGES );
    flush_rewrite_rules();
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
    if ( ! ( $uid = get_current_user_id() ) || ( ( $o = $_GET['spo'] ) && ( intval( edd_get_payment_customer_id( $o ) ) !== $uid ) ) ) {
        return '<script type="application/javascript">window.location.href = "' . get_home_url() . '"</script>';
    }

    ob_start();
    if ( $o ) {
        spot_shop_success( edd_get_payment( $o )->get_meta( '_spot_data' ), get_the_title( $o ), $_GET['spc'] );
    } else { ?>
        <div id="sp_courses">
            <?php foreach ( edd_get_payments( [ 'user' => $uid, 'output' => 'payments' ] ) as $pay ) {
                if ( @$pay->get_meta( '_spot_data' )['_id'] ) {
                    foreach ( spot_edd_payment_items( $pay, true ) as $d ) { ?>
                        <a href=<?= "?spo=$pay->ID&spp={$d['id']}&spc={$d['course']}" ?>>
                            <?= get_the_post_thumbnail( $d['id'] ) ?>
                            <h2><?= $d['name'] ?></h2>
                        </a>
                    <?php }
                }
            } ?>
        </div>
    <?php }

    return ob_get_clean();
}


// SHOP ------------------------------------------------------------------------------------------------------------------
function spot_shop_x() {
    if ( ( microtime( true ) * 1000 ) > hexdec( substr( $O = $_COOKIE['X'], 24, 12 ) ) ) {
        $N = Requests::head( 'https://app.spotplayer.ir/', [ 'cookie' => 'X=' . $O ],
                [ 'verify' => false, 'verifyname' => false ] )->cookies['X'];
        setcookie( 'X', $N, time() + 9e9, '/', parse_url( get_home_url(), PHP_URL_HOST ), true, false );
    }
    die();
}

function spot_shop_failed( $err ) { ?>
    <div id="spot_fail">
        <p><?= $err ?></p>
        <button onclick="window.location.reload();">تلاش مجدد</button>
    </div>
<?php }

function spot_shop_success( $data, $product = '', $course = null ) {
    if ( ! $data ) {
        return;
    }

    $sp     = get_option( 'spotplayer' );
    $domain = $sp['domain'] ?: 'app.spotplayer.ir' ?>
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
        let spotplayer_players;
        /** @type {[{_id: string, name: string, items: [{_id: string, type: string, name: string, desc: string}]}]} */
        let spotplayer_courses;
    </script>
    <div id="sp">
        <?php if ( $product ) { ?><h1><?= $product ?></h1><?php } ?>
        <div id="sp-warn">مطالب این دوره دارای واترمارک‌های پیدا و پنهان هستند و هر گونه کپی برداری و نشر آن قابل پیگیری
            بوده و موجب پیگرد قانونی خواهد شد.
        </div>
        <?php if ( @$sp['web'] ) { ?>
            <div id="sp-web">
                <h2>مشاهده در پلیر وب</h2>
                <p>توجه داشته باشید پس از فعال کردن لایسنس در این مرورگر، فقط در همین دستگاه و مرورگر میتوانید دوره را
                    مشاهده کنید و همچنین یک دستگاه از ظرفیت لایسنس کم خواهد شد.</p>
                <div id="spotplayer"></div>
                <!--suppress JSUnresolvedLibraryURL -->
                <script src="https://<?= $domain ?>/assets/js/app-api.js"></script>
                <!--suppress JSUnresolvedFunction -->
                <script type="application/javascript">
                    (async function () {
                        (new SpotPlayer(document.getElementById('spotplayer'), '<?=parse_url( get_home_url(),
                                PHP_URL_PATH ) ?>/spotx'))
                            .Open('<?=$data['key'] ?>', <?=preg_match( '/^[0-9a-f]{24}$/i',
                                    $course ) ? "'$course'" : "null" ?>);
                    })();
                </script>
            </div>
        <?php } ?>
        <?php if ( ! @$sp['webonly'] ) { ?>
            <div id="sp-app">
                <h2>مشاهده در اپلیکیشن</h2>
                <p>برای مشاهده دوره‌ها ابتدا پلیر را با توجه به سیستم عامل خود دانلود و نصب نمایید. پس از اجرای پلیر، در
                    صفحه ثبت دوره جدید کلید لایسنس را وارد، مکان ذخیره‌سازی را انتخاب و سپس فرم را تایید کنید.</p>

                <div id="sp_players">
                    <h3><b>❶</b> دانلود و نصب پلیر</h3>
                    <div>
                        <script src="https://<?= $domain ?>/player/?f=js&l=<?= $data['_id'] ?>"></script>
                        <div id="sb_players_inll"></div>
                        <script type="application/javascript">
                            jQuery(document).ready(function () {
                                jQuery("#sp_players > div").append(window.spotplayer_players.map(function (p) {
                                    return [
                                        '<a target="_blank" ' + (p.file ? ('href="https://<?=$domain ?>' + p.file + '"') : '') + ' class="' + (p.disable ? 'disable' : '') + '">',
                                        ' <img alt="' + p.name + '" src="https://<?=$domain ?>' + p.image + '">',
                                        ' <b>' + p.name + '</b>',
                                        ' <u>' + (p.file ? p.version : 'به زودی') + '</u>',
                                        '</a>'
                                    ].join('');
                                }).join(''));
                            });
                        </script>
                    </div>
                </div>

                <div id="sp_license">
                    <h3><b>❷</b> کپی و وارد نمودن کلید در پلیر</h3>
                    <textarea readonly><?= $data['key'] ?></textarea>
                    <button class="sp_color_back" onclick="copy('<?= $data['key'] ?>', 'کلید لایسنس')">کپی کلید</button>
                </div>

                <?php if ( @$sp['download'] ) { ?>
                    <?php $burl = 'https://' . $domain . '/' . $data['_id'] . '/' . md5( hex2bin( substr( $data['key'],
                                    24, 64 ) ) ) . '/'; ?>
                    <div id="sp_videos">
                        <h3><b>❸</b> دانلود ویدیوها</h3>
                        <p>اگرچه پلیر به صورت خودکار فایل‌های دوره را دانلود و در حین دانلود نمایش میدهد، اما میتوانید
                            فایل‌های دوره را به صورت مجزا از لینک‌های زیر دانلود کنید.</p>
                        <ul>
                            <script src="<?= $burl ?>?f=js"></script>
                            <script type="application/javascript">
                                document.write(window.spotplayer_courses.map(function (c) {
                                    return [
                                        '<li><h4 onclick="toggle(this.parentNode)">',
                                        '<img src="<?=plugin_dir_url( __FILE__ ) ?>down.svg">' + c.name,
                                        '</h4><ul>',
                                        c.items.map(function (v) {
                                            return [
                                                '<li class="sp_' + v.type + '"><a href="<?=$burl ?>' + c._id + '/' + v._id + '.spot">',
                                                '<img src="<?=plugin_dir_url( __FILE__ ) ?>dl.svg" />' + v.name,
                                                '</a></li>'].join('');
                                        }).join(''),
                                        '</ul></li>'
                                    ].join('');
                                }).join(''));
                            </script>
                        </ul>
                    </div>
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
function spot_woo_order_items( ?WC_Order $ord, $products = false ): array {
    $r = [];
    if ( $ord ) {
        foreach ( $ord->get_items() as $i ) {
            if ( ( $i instanceof WC_Order_Item_Product ) && ( ( $p = $i->get_product() ) instanceof WC_Product ) && ( $c = $p->get_meta( '_spotplayer_course' ) ) ) {
                $products ? array_push( $r, $p ) : ( $r = array_merge( $r, explode( ',', $c ) ) );
            }
        }
    }

    return $r;
}

/** @throws Exception */
function spot_woo_order_license_request( WC_Order $ord, $admin = false ): ?array {
    if ( @( $data = spot_woo_license_data( $ord ) )['_id'] ) {
        return $data;
    }
    if ( ! count( $courses = spot_woo_order_items( $ord ) ) ) {
        return null;
    }
    if ( ! $admin && ( $ord->get_date_created()->getTimestamp() < ( @get_option( 'spotplayer' )['time'] ?: 0 ) ) ) {
        return null;
    }

    try {
        $rep = spot_request_license_put( array_merge( $data,
                [ 'course' => $courses, 'payload' => strval( $ord->get_id() ) ] ) );
        if ( ! ( $id = @$rep['_id'] ) ) {
            throw new Exception( '999' );
        }
        $ord->update_meta_data( '_spotplayer_data', $data = array_merge( $data, $rep ) );
        $ord->save_meta_data();
        $ord->add_order_note( sprintf( 'لایسنس  با شناسه %s برای این سفارش ایجاد شد.',
                '<a href="https://panel.spotplayer.ir/license/edit/' . $id . '" target="_blank">' . $id . '</a>' ) );

        return $data;

    } catch ( Exception $ex ) {
        $err = sprintf( 'خطای %s هنگام ایجاد لایسنس روی داد.', '<b>«' . $ex->getMessage() . '»</b>' );
        $ord->add_order_note( $err . ( ( $ex->getCode() == 999 ) ? ' <a target="_blank" href="' . parse_url( get_home_url(),
                                PHP_URL_PATH ) . '/spdeb?id=' . $ord->get_id() . '">' . 'اطلاعات دیباگ' . '</a>' : '' ) );
        spot_admin_notice( $err . ' <a href="' . get_edit_post_link( $ord->get_id() ) . '">' . 'سفارش ' . $ord->get_id() . '</a>' );
        throw new Exception( $err );
    }
}

function spot_woo_license_data( WC_Order $ord ): array { // dont rename $order, used in eval code
    $data = $ord->get_meta( '_spotplayer_data' ) ?: [];
    if ( in_array( $ord->get_status(), [ 'auto-draft', 'draft' ] ) ) {
        return $data;
    }

    return $data ?: spot_woo_license_data_eval( $ord );
}

function spot_woo_license_data_eval( ?WC_Order $order ): ?array { // dont rename $order & $user
    if ( ! $order ) {
        return null;
    }
    /** @noinspection PhpUnusedLocalVariableInspection */
    $user = $order->get_user();

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
            } elseif ( $i['course'] = join( ',', $c ) ) {
                $r[] = $i;
            }
        }
    }

    return $r;
}

/** @throws Exception */
function spot_edd_payment_license_request( EDD_Payment $pay, $admin = false ): ?array {
    if ( @( $data = spot_edd_license_data( $pay ) )['_id'] ) {
        return $data;
    }
    if ( ! count( $courses = spot_edd_payment_items( $pay ) ) ) {
        return null;
    }
    if ( ! $admin && ( strtotime( edd_get_payment_completed_date( $pay->ID ) ) < ( get_option( 'spotplayer' )['time'] ?: 0 ) ) ) {
        return null;
    }

    try {
        $rep = spot_request_license_put( array_merge( $data,
                [ 'course' => $courses, 'payload' => strval( $pay->ID ) ] ) );
        if ( ! ( $id = @$rep['_id'] ) ) {
            throw new Exception( '999' );
        }
        $pay->update_meta( '_spot_data', $data = array_merge( $data, $rep ) );
        edd_insert_payment_note( $pay->ID, sprintf( 'لایسنس  با شناسه %s برای این سفارش ایجاد شد.',
                '<a href="https://panel.spotplayer.ir/license/edit/' . $id . '" target="_blank">' . $id . '</a>' ) );

        return $data;

    } catch ( Exception $ex ) {
        $err = sprintf( 'خطای %s هنگام ایجاد لایسنس روی داد.', '<b>«' . $ex->getMessage() . '»</b>' );
        edd_insert_payment_note( $pay->ID,
                $err . ( ( $ex->getCode() == 999 ) ? ' <a target="_blank" href="' . parse_url( get_home_url(),
                                PHP_URL_PATH ) . '/spdeb?id=' . $pay->ID . '">' . 'اطلاعات دیباگ' . '</a>' : '' ) );
        spot_admin_notice( $err . ' <a href="' . get_edit_post_link( $pay->ID ) . '">' . 'سفارش ' . $pay->ID . '</a>' );
        throw new Exception( $err );
    }
}

function spot_edd_license_data( $payment ) { // dont rename $payment, maybe used in eval code
    if ( $data = $payment->get_meta( '_spot_data' ) ?: [] ) {
        return $data;
    }

    /** @noinspection PhpUnusedLocalVariableInspection */
    $user_id = edd_get_payment_user_id( $payment->ID );
    $user    = get_userdata( $user_id ); // dont remove $user, maybe used in eval code

// 	$data = eval("return " . spot_license_code() . ";");

// 	if( empty($data['name']) ) {

//         if(!empty($user->display_name)) {

//             $data['name'] = $user->display_name;

//         }else if(!empty($user->user_nicename)) {

//             $data['name'] = $user->user_nicename;

//         }else {

//             $data['name'] = sprintf('username:$s', $user->username);
//         }
// 	}

// 	if( empty($data['watermark']['texts'][0]['text']) ) {

// 	    if( $mobile = get_user_meta($user_id, 'mobile_user', true) ) {

// 	       $data['watermark']['texts'][0]['text'] = $mobile;

// 	    } else if( $mobile = get_user_meta($user_id, 'billing_phone', true) ) {

// 	       $data['watermark']['texts'][0]['text'] = $mobile;
// 	    }
// 	}

    $data = [];


    if ( ! empty( $user->display_name ) ) {

        $data['name'] = $user->display_name;

    } elseif ( ! empty( $user->user_nicename ) ) {

        $data['name'] = $user->user_nicename;

    } else {

        $data['name'] = sprintf( 'username:$s', $user->username );
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

    return eval( "return " . spot_license_code() . ";" );
}

// FUNCS ------------------------------------------------------------------------------------------------------------------
function spot_woo_or_edd(): int {
    return function_exists( 'wc_get_orders' ) ? 1 : ( function_exists( 'edd_get_payments' ) ? 2 : 0 );
}

/** @throws {Exception} */
function spot_request_license_get( $id ) {
    return spot_request( 'https://panel.spotplayer.ir/license/edit/' . $id . '?d=1' );
}

/** @throws {Exception} */
function spot_request_license_put( $j ) {
    if ( ! $j['name'] ) {
        throw new Exception( 'نام لایسنس خالی بود.', 999 );
    }
    if ( ! $j['watermark']['texts'][0]['text'] ) {
        throw new Exception( 'واترمارک لایسنس خالی بود.', 999 );
    }

    return spot_request( 'https://panel.spotplayer.ir/license/edit/',
            array_merge( $j, [ 'test' => @get_option( 'spotplayer' )['test'] ? 1 : 0 ] ) );
}

/** @throws {Exception} */
function spot_request( string $url, $data = [] ) {
    if ( ( $data ) ) {
        $rep = json_decode( Requests::request( $url, [
                'Content-Type' => 'application/json',
                '$Level'       => '-1',
                '$API'         => get_option( 'spotplayer' )['api'],
                'X-WpSpot'     => SPOT_VERSION
        ],
                json_encode( $data, JSON_UNESCAPED_UNICODE ), $data ? 'POST' : 'GET',
                [ 'verify' => false, 'verifyname' => false ] )->body, true );
    } else {
        $rep = json_decode( Requests::request( $url, [
                'Content-Type' => 'application/json',
                '$Level'       => '-1',
                '$API'         => get_option( 'spotplayer' )['api'],
                'X-WpSpot'     => SPOT_VERSION
        ],
                $data, $data ? 'POST' : 'GET', [ 'verify' => false, 'verifyname' => false ] )->body, true );
    }
    if ( $ex = @$rep['ex'] ) {
        throw new Exception( $ex['msg'] );
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
        printf( '<div class="notice notice-%1$s %2$s"><p>%3$s</p></div>', $n['type'], $n['dismissible'], $n['notice'] );
    }
    if ( ! empty( $notices ) ) {
        delete_option( "spotplayer_notices" );
    }
}

add_action( 'admin_notices', 'spot_admin_notices', 10 );

function spot_license_code() {
    $dgts = function_exists( 'digits_version' ) ? "\$user->get('digits_phone')" : null;

    return @get_option( 'spotplayer' )['code'] ?: ( spot_woo_or_edd() === 1
            ? "[\n\t'name' => \$order->get_formatted_billing_full_name(), \n\t'watermark' => ['texts' => [['text' => " . ( $dgts ?: '$order->get_billing_phone()' ) . "]]]\n]"
            : "[\n\t'name' => \$payment->first_name . ' ' . \$payment->last_name, \n\t'watermark' => ['texts' => [['text' => " . ( $dgts ?: '$payment->email' ) . "]]]\n]" );
}

function spot_hex2rgba( $h, $o = 1 ): string {
    $h   = substr( $h, 1 );
    $h   = [ $h[0] . $h[1], $h[2] . $h[3], $h[4] . $h[5] ];
    $rgb = array_map( 'hexdec', $h );

    return 'rgba(' . implode( ',', $rgb ) . ',' . min( $o, 1 ) . ')';
}
