<?php
/**
 * 019 SMS/WhatsApp integration for communication preferences
 *
 * Listens to customerio/comm_prefs_updated and removes the user's phone
 * from future webinar contact lists in 019 when they opt out of SMS/WhatsApp.
 *
 * Requires WP options: whatsapp_019_username, whatsapp_019_token
 * Silently skips if not configured.
 */

add_action('customerio/comm_prefs_updated', function ($cid, $prefs, $full_unsubscribe) {
  $username = get_option('whatsapp_019_username', '');
  $token = get_option('whatsapp_019_token', '');

  if (empty($username) || empty($token)) {
    return; // 019 not configured on this site
  }

  $sms_opted_out = $full_unsubscribe || (isset($prefs['webinar-invites-sms']) && $prefs['webinar-invites-sms'] === '0');

  if (!$sms_opted_out) {
    return;
  }

  // Look up the customer's phone from Customer.io
  $cio = new CustomerIO();
  $customer = $cio->searchCustomerById($cid);

  if (!$customer || empty($customer['attributes']['phone'])) {
    return;
  }

  $phone = $customer['attributes']['phone'];

  // Get all future webinar contact list IDs
  $cl_ids = o19_get_future_webinar_cl_ids();

  if (empty($cl_ids)) {
    return;
  }

  o19_remove_phone_from_lists($phone, $cl_ids, $username, $token);
}, 10, 3);

/**
 * Get whatsapp_group_id values from all published webinars with a future date
 */
function o19_get_future_webinar_cl_ids(): array {
  global $wpdb;

  $now = (new DateTime('now', new DateTimeZone('Asia/Jerusalem')))->format('Y-m-d H:i:s');

  $results = $wpdb->get_col($wpdb->prepare("
    SELECT pm_group.meta_value
    FROM {$wpdb->posts} p
    JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = 'webinar_date'
    JOIN {$wpdb->postmeta} pm_group ON p.ID = pm_group.post_id AND pm_group.meta_key = 'whatsapp_group_id'
    WHERE p.post_type = 'webinar'
      AND p.post_status = 'publish'
      AND pm_group.meta_value != ''
      AND pm_date.meta_value >= %s
  ", $now));

  return array_filter($results);
}

/**
 * Remove a phone number from multiple 019 contact lists
 */
function o19_remove_phone_from_lists(string $phone, array $cl_ids, string $username, string $token): void {
  $cls = array_map(function ($cl_id) use ($phone) {
    return [
      'cl_id' => $cl_id,
      'destinations' => [
        'destination' => [['phone' => $phone]]
      ]
    ];
  }, $cl_ids);

  $body = json_encode([
    'removeCL' => [
      'user' => ['username' => $username],
      'cl' => $cls
    ]
  ]);

  $response = wp_remote_post('https://019sms.co.il/api', [
    'body' => $body,
    'headers' => [
      'Authorization' => 'Bearer ' . $token,
      'Content-Type' => 'application/json'
    ],
    'timeout' => 15
  ]);

  if (is_wp_error($response)) {
    CustomerIOAdmin::log("019 remove from lists failed: " . $response->get_error_message());
  } else {
    $result = wp_remote_retrieve_body($response);
    CustomerIOAdmin::log("019 remove phone $phone from " . count($cl_ids) . " lists: " . $result);
  }
}
