<?php
/**
 * CustomerIO integration
 *
 * @wordpress-plugin
 * Plugin Name:       CustomerIO integration
 * Plugin URI:        http://wordpress.org/plugins/customerio
 * Description:       Integrate Wordpress with Customer IO
 * Version:           1.0.0
 * Author:            Nimrod Cohen
 * Author URI:        https://google.com?q=who+is+the+dude
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       customerio
 * Domain Path:       /languages
 */

 class CustomerIOAdmin {
  function __construct() {
     add_action( 'admin_menu', [$this,'add_settings_page'] );
  }

  function add_settings_page() {
    add_options_page('CustomerIO Settings', 'CustomerIO', 'manage_options', 'customerio', [$this,'show_settings_page']);
  }

  function show_settings_page() {
    include_once("settings.php");
  }
}

 $customerioAdmin = new CustomerIOAdmin();

?>