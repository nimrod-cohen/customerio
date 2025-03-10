<?php
/**
 * CustomerIO integration
 *
 * @wordpress-plugin
 * Plugin Name:       CustomerIO integration
 * Plugin URI:        https://github.com/nimrod-cohen/customerio
 * Description:       Integrate Wordpress with Customer IO
 * Version:           2.5.3
 * Author:            nimrod-cohen
 * Author URI:        https://github.com/nimrod-cohen/customerio
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       customerio
 * Domain Path:       /languages
 */

class CustomerIOAdmin {
  function __construct() {
    add_action('admin_menu', [$this, 'add_settings_page']);
    add_action('admin_enqueue_scripts', [$this, 'init_admin']);
    add_action('wp_ajax_save_customerio_settings', [$this, 'save_settings']);
    add_action('wp_ajax_test_customer_email', [$this, 'test_customer_email']);
    add_action('wp_ajax_test_track_auth', [$this, 'test_track_auth']);
    add_action('wp_ajax_test_broadcast', [$this, 'test_broadcast']);
    add_filter('plugin_action_links_customerio/index.php', [$this, 'add_settings_link']);

    add_action('admin_init', function () {
      $updater = new GitHubPluginUpdater(__FILE__);
    });

    $commPrefs = CommPrefs::get_instance();
    add_shortcode("cio-comm-prefs", [$commPrefs, "show_comm_prefs"]);
  }

  static function get_version() {
    $plugin_data = get_plugin_data(__FILE__);
    return $plugin_data['Version'];
  }

  public static function log($msg) {
    if (is_array($msg) || is_object($msg)) {
      $msg = print_r($msg, true);
    }

    $date = date("Y-m-d");
    $datetime = date("Y-m-d H:i:s");
    file_put_contents(
      ABSPATH . DIRECTORY_SEPARATOR . "logs" . DIRECTORY_SEPARATOR . "debug-$date.log",
      "$datetime | $msg\r\n",
      FILE_APPEND);
  }

  function add_settings_link($links) {
    // Build and escape the URL.
    $url = esc_url(add_query_arg(
      'page',
      'customerio',
      get_admin_url() . 'options-general.php'
    ));

    $settings_link = "<a href='$url'>" . __('Settings') . '</a>';

    array_push($links, $settings_link);

    return $links;
  }

  function add_settings_page() {
    add_options_page('CustomerIO Settings', 'CustomerIO', 'manage_options', 'customerio', [$this, 'show_settings_page']);
  }

  function show_settings_page() {
    include_once "settings.php";
  }

  function init_admin($page) {
    if ($page !== "settings_page_customerio") {
      return;
    }

    wp_enqueue_script("customerio_js", plugin_dir_url(__FILE__) . "/customer.js", ["wpjsutils"]);
    wp_localize_script('customerio_js',
      'customerIOData', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('afm-nonce')
      ]);
  }

  function test_broadcast() {
    $cio = new CustomerIO();
    $broadcastId = $_POST["broadcast_id"];
    $email = $_POST["email"];
    $result = $cio->sendBroadcast($broadcastId, [
      "subject" => "CustomerIO plugin test mail",
      "content" => date("Y-m-d H:i:s")
    ], [$email]);
    echo json_encode(['error' => $result === false, "message" => $result ? 'Message sent successfully' : 'Failed to send message']);
    die;
  }

  function test_track_auth() {
    try {
      $cio = new CustomerIO();
      $result = $cio->testAuth();
      echo json_encode(['error' => false, "message" => $result ? 'Tracking Authenticated successfully' : 'Could not authenticate Tracking']);
      die;
    } catch (Exception $ex) {
      echo json_encode(["error" => true, "message" => $ex->getMessage()]);
      die;
    }
  }

  function test_customer_email() {
    try {
      $email = $_REQUEST["email"];

      if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new exception("Email address is not valid");
      }

      $cio = new CustomerIO();
      $result = $cio->customerExists($email);
      echo json_encode(['error' => false, "message" => $result ? 'Customer exists' : 'Customer does not exist']);
      die;
    } catch (Exception $ex) {
      echo json_encode(["error" => true, "message" => $ex->getMessage()]);
      die;
    }
  }

  function save_settings() {
    try {
      CustomerIO::saveSettings(
        $_POST["enabled"] == "true",
        $_POST["trackApiKey"],
        $_POST["siteId"],
        $_POST["apiKey"],
        $_POST["betaApiKey"],
        $_POST["defaultCountryCode"],
        $_POST["region"]);

      echo json_encode([]);
      die;
    } catch (Exception $ex) {
      echo json_encode(["error" => true, "message" => $ex->getMessage()]);
      die;
    }
  }
}

// Include the main plugin classes
$directory = plugin_dir_path(__FILE__) . '/includes';
$files = glob($directory . '/*.php');
foreach ($files as $file) {
  require_once $file;
}

$customerioAdmin = new CustomerIOAdmin();

?>