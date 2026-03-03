<?php
/**
 * Plugin Name: Oriel
 * Plugin URI:  https://tghp.co.uk
 * Description: A flexible WordPress forms plugin.
 * Version:     1.0.0
 * Author:      TGHP
 * Author URI:  https://tghp.co.uk
 * License:     GPL-2.0-or-later
 * Text Domain: oriel
 */

if (! defined('ABSPATH')) {
    exit;
}

define('ORIEL_VERSION', '1.0.0');
define('ORIEL_META_PREFIX', '_oriel_');
define('ORIEL_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ORIEL_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once ORIEL_PLUGIN_DIR . 'vendor/autoload.php';

\Oriel\Plugin::instance();
