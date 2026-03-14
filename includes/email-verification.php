<?php

class EmailVerification {
  const TRANSIENT_PREFIX = 'cio_verify_';
  const TRANSIENT_TTL = DAY_IN_SECONDS;

  private static $instance = null;

  static function get_instance() {
    if (self::$instance === null) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  function __construct() {
    add_action('init', [$this, 'handle_verification']);
  }

  /**
   * Initiate email verification - stores pending data and sends transactional email
   *
   * @param string $email
   * @param string $name
   * @param array $data Additional data to pass through on verification
   * @param int $transactional_message_id Customer.io transactional message ID
   * @return bool
   */
  function initiate($email, $name, $data, $transactional_message_id) {
    $token = bin2hex(random_bytes(32));

    $pending = [
      'email' => $email,
      'name' => $name,
      'data' => $data,
      'created_at' => time()
    ];

    set_transient(self::TRANSIENT_PREFIX . $token, $pending, self::TRANSIENT_TTL);

    $verify_url = site_url('?cio_verify=' . $token);

    $cio = new CustomerIO();

    // Create the customer with email_verified=false BEFORE sending the transactional email
    // so the attribute is set atomically and journeys can exclude unverified contacts
    $cio->createCustomer($email, $name, ['email_verified' => false]);

    $result = $cio->sendTransactionalEmail($email, $transactional_message_id, [
      'verify_url' => $verify_url,
      'name' => $name,
      'email' => $email
    ]);

    return $result;
  }

  /**
   * Handle verification link clicks
   */
  function handle_verification() {
    if (!isset($_GET['cio_verify'])) {
      return;
    }

    $token = sanitize_text_field($_GET['cio_verify']);
    $pending = get_transient(self::TRANSIENT_PREFIX . $token);

    if (!$pending) {
      $this->render_page(
        'הקישור אינו תקף או שפג תוקפו',
        'אנא הירשמ/י שוב באתר.'
      );
      return;
    }

    // Fire action for consumers (e.g. EUOP) to complete the registration
    do_action('customerio/email_verified', $pending['email'], $pending['name'], $pending['data']);

    // Clean up
    delete_transient(self::TRANSIENT_PREFIX . $token);

    $this->render_page(
      'האימייל שלך אומת בהצלחה!',
      'ברוך/ה הבא/ה לניוזלטר של בורסה4יו.'
    );
  }

  private function render_page($title, $message) {
    $site_url = home_url();
    $site_name = get_bloginfo('name');

    header('Content-Type: text/html; charset=utf-8');
    echo <<<HTML
<!DOCTYPE html>
<html dir="rtl" lang="he">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{$title} - {$site_name}</title>
  <style>
    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
      margin: 0;
      background: #f5f5f5;
      direction: rtl;
    }
    .container {
      background: white;
      padding: 3rem;
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      text-align: center;
      max-width: 480px;
    }
    h1 { font-size: 1.5rem; color: #333; margin: 0 0 1rem; }
    p { color: #666; margin: 0 0 1.5rem; }
    a {
      display: inline-block;
      padding: 0.75rem 2rem;
      background: #0073aa;
      color: white;
      text-decoration: none;
      border-radius: 4px;
    }
    a:hover { background: #005a87; }
  </style>
</head>
<body>
  <div class="container">
    <h1>{$title}</h1>
    <p>{$message}</p>
    <a href="{$site_url}">חזרה לאתר</a>
  </div>
</body>
</html>
HTML;
    exit;
  }
}

// Auto-instantiate so the init hook is always registered
EmailVerification::get_instance();
