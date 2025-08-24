<?php
/**
 * Plugin Name: اسپات پلیر (بازنویسی‌شده)
 * Plugin URI: https://spotplayer.ir/
 * Description: نسخه بازطراحی‌شده (Refactor) از افزونه اسپات پلیر با ساختار ماژولار، امنیت بالاتر و نگه‌داری آسان‌تر.
 * Author: SpotPlayer.ir
 * Author URI: https://spotplayer.ir/
 * Version: 17.0.1-refactor
 * Requires PHP: 7.4
 * Text Domain: spotplayer
 * Domain Path: /languages
 */

defined('ABSPATH') || exit;

define('SPOTPLAYER_VERSION', '17.0.1-refactor');
define('SPOTPLAYER_SLUG', 'spotplayer');
define('SPOTPLAYER_FILE', __FILE__);
define('SPOTPLAYER_DIR', plugin_dir_path(__FILE__));
define('SPOTPLAYER_URL', plugin_dir_url(__FILE__));
define('SPOTPLAYER_OPTION', 'spotplayer');

// Simple PSR-4 autoloader for namespace SpotPlayer\
spotplayer\inc\Plugin::instance()->boot();
