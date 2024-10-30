<?php

/**
 * Plugin Name: Csomagpont
 * Description: Szállítási megoldások 4 futárszolgálattal, egy rendszerben kezelve
 * Developer: Csomagpont Logisztika Kft.
 * Developer URI: https://csomagpont.com
 * Text Domain: csomagpont
 * Version: 1.0.28
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

define('CSP_LICENCE', 'csomagpont');
$csp_enabled_pont_types = array('GLS CsomagPont', 'PostaPont Csomagautomata', 'PostaPont (MOL, COOP, MediaMarkt stb.)', 'PostaPont Postán maradó', 'Csomagküldő Magyarország');
define('CSP_SZALLMOD_MPL_PONT', 19);
define('CSP_SZALLMOD_GLS_PONT', 21);
define('CSP_SZALLMOD_MPL_ALTER', 2);
define('CSP_SZALLMOD_PACKETA', 85);

// is_plugin_active function miatt
include_once(ABSPATH . 'wp-admin/includes/plugin.php');

include_once __DIR__ . '/inc/helpers.php';
include_once __DIR__ . '/inc/csomagpont.class.php';
include_once __DIR__ . '/inc/csv-export.class.php';
include_once __DIR__ . '/inc/csomagpont-api.class.php';
include_once __DIR__ . '/inc/csomagpont-filter.class.php';
include_once __DIR__ . '/inc/csomagpont-settings.class.php';

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}


if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    define('CSOMAGPONT_DIR_URL', plugin_dir_url(__FILE__));

    $csomagpont_settings_obj = new Csomagpont_Settings();
    $csomagpont = new Csomagpont($csomagpont_settings_obj);
    $csomagpont_filter = new Csomagpont_Filter();
}



