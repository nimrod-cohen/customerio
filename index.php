<?php
/**
 * CustomerIO integration
 *
 * @wordpress-plugin
 * Plugin Name:       CustomerIO integration
 * Plugin URI:        http://wordpress.org/plugins/customerio
 * Description:       Integrate Wordpress with Customer IO
 * Version:           1.1.5
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
     add_action( 'admin_init', [$this, 'init_admin']);
     add_action('wp_ajax_save_customerio_settings', [$this, 'save_settings']);
     add_filter( 'plugin_action_links_customerio/index.php', [$this,'add_settings_link'] );
  }

  static function get_version() {
    $plugin_data = get_plugin_data( __FILE__ );
    return $plugin_data['Version'];
  }

  function add_settings_link( $links ) {
    // Build and escape the URL.
    $url = esc_url( add_query_arg(
      'page',
      'customerio',
      get_admin_url() . 'options-general.php'
    ) );

    $settings_link = "<a href='$url'>" . __( 'Settings' ) . '</a>';

    array_push($links, $settings_link);

    return $links;
  }

  function add_settings_page() {
    add_options_page('CustomerIO Settings', 'CustomerIO', 'manage_options', 'customerio', [$this,'show_settings_page']);
  }

  function show_settings_page() {
    include_once("settings.php");
  }

  function init_admin() {
    wp_enqueue_script("customerio_js", plugin_dir_url(__FILE__)."/customer.js",["wpjsutils"]);
		wp_localize_script('customerio_js',
		'customerIOData', [
			'ajax_url' => admin_url('admin-ajax.php'),
			'nonce' => wp_create_nonce('afm-nonce'),
		]);
  }

  function save_settings() {
    try {
      CustomerIO::saveSettings(
        $_POST["enabled"] == "true",
        $_POST["apiKey"],
        $_POST["siteId"],
        $_POST["broadcastKey"],
        $_POST["betaApiAppKey"],
        $_POST["region"]);

      echo json_encode([]);
      die;
    } catch(Exception $ex){
      echo json_encode(["error"=>true,"message" => $ex->getMessage()]);
      die;
    }
  }
}

include_once "customer.php";
 
$customerioAdmin = new CustomerIOAdmin();

?>