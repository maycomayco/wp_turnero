<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
/*
Plugin Name: Bookly - KNX
Plugin URI: http://booking-wp-plugin.com
Description: Bookly Plugin
Version: 14.8
Author: KNX S.A.
Author URI: https://kinexo.com
Text Domain: bookly
Domain Path: /languages
License: Commercial
*/

if ( version_compare( PHP_VERSION, '5.3.7', '<' ) ) {
    function bookly_php_outdated()
    {
        echo '<div class="updated"><h3>Bookly</h3><p>To install the plugin - <strong>PHP 5.3.7</strong> or higher is required.</p></div>';
    }
    add_action( is_network_admin() ? 'network_admin_notices' : 'admin_notices', 'bookly_php_outdated' );
} else {
    include_once __DIR__ . '/autoload.php';

    call_user_func( array( '\Bookly\Lib\Plugin', 'run' ) );
    $app = is_admin() ? '\Bookly\Backend\Backend' : '\Bookly\Frontend\Frontend';
    new $app();
}