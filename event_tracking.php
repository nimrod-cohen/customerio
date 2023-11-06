<?php
/*
 * This file is meant for tracking Yinon related activities to our database.
 */

class EventTracking {

  const C_API_URL = 'https://query.bursa4u.com/event';
  const C_SESSION_KEY = 'tracked_visit';

  private static function log($message) {
    //CustomerIOAdmin::log($message);
  }

  public function init() {
    add_action('wp_ajax_save_event_tracking_settings', [$this, 'save_event_tracking_settings']);
    add_action('template_redirect', [$this, 'track_visit']);
  }

  public function save_event_tracking_settings() {
    $enable = $_POST["enable"];
    $apiToken = $_POST["api_token"];
    $companyId = $_POST["company_id"];

    update_option("event_tracking_enabled", $enable, true);
    update_option("event_tracking_token", $apiToken, true);
    update_option("event_tracking_company_id", $companyId, true);

    echo json_encode(["message" => "Event tracking setting saved successfully"]);
    die;
  }

  public function autotrack_enabled() {
    return get_option("event_tracking_enabled") === '1';
  }

  public function token() {
    return get_option("event_tracking_token");
  }

  public function company_id() {
    return get_option("event_tracking_company_id");
  }

  private function get_domain() {
    $protocols = ['http://', 'https://', 'http://www.', 'https://www.', 'www.'];
    return str_replace($protocols, '', site_url());
  }

  public function track_visit() {
    if (is_admin() ||
      !is_user_logged_in() ||
      !$this->autotrack_enabled()) {
      return;
    }

    EventTracking::log('starting to track visit');

    if (session_status() !== PHP_SESSION_ACTIVE) {
      session_start();
    }

    $user = wp_get_current_user();
    if (!($user instanceof WP_User)) {
      return;
    }
    EventTracking::log('tracking user ' . $user->ID());

    $email = $user->user_email;

    $sessionToken = EventTracking::C_SESSION_KEY . preg_replace('/[^a-zA-Z]/', '', $email);

    EventTracking::log('session token is ' . $sessionToken);

    if (isset($_SESSION[$sessionToken]) && $_SESSION[$sessionToken] === true) {
      EventTracking::log('session key already exists, visit already tracked');
      return;
    }

    EventTracking::log('should track visit, user is ' . $user->user_email);

    if ($this->track($user->user_email, null, 'visit')) {
      EventTracking::log('tracking success, marking session');
      $_SESSION[$sessionToken] = true;
    }
  }

  public function track($email, $phone, $event, $data = null, $value = null) {
    try {
      $json = !empty($data) ? json_encode($data) : null;

      $data = [
        "company_id" => intval($this->company_id()),
        "email" => $email,
        "phone" => $phone,
        "event" => $event,
        "domain" => $this->get_domain(),
        "data" => $json,
        "date" => date('d/m/Y H:i:s'),
        "value" => $value
      ];

      $data = json_encode($data);

      $headers = [
        'Content-Type' => 'application/json',
        '__token' => $this->token()
      ];

      $response = wp_remote_request(EventTracking::C_API_URL,
        [
          'headers' => $headers,
          'body' => $data,
          'method' => 'POST'
        ]);

      // Check for errors
      if (is_wp_error($response)) {
        throw new Exception("failed to report " . $event . "event for " . $email);
      }

      // Request was successful, and you can handle the API response here
      $response_code = wp_remote_retrieve_response_code($response);
      // Use the data as needed
      if ($response_code !== 200) {
        throw new Exception("failed to report " . $event . "event for " . $email . ' with error: ' . $response["body"]);
      }

      return true;

    } catch (Exception $ex) {
      //document the error
      EventTracking::log($ex->getMessage());
      return false;
    }
  }
}

$eventTracking = new EventTracking();
$eventTracking->init();

?>