<?php
/**
 * 019 SMS/WhatsApp integration for communication preferences
 *
 * Listens to customerio/comm_prefs_updated and removes the user's phone
 * from all 019 contact lists when they opt out of SMS/WhatsApp.
 *
 * Requires WP options: whatsapp_019_username, whatsapp_019_token
 * Silently skips if not configured.
 */

add_action('customerio/comm_prefs_updated', function ($cid, $prefs, $full_unsubscribe) {
  $username = get_option('whatsapp_019_username', '');
  $token = get_option('whatsapp_019_token', '');

  if (empty($username) || empty($token)) {
    return;
  }

  $sms_opted_out = $full_unsubscribe || (isset($prefs['webinar-invites-sms']) && $prefs['webinar-invites-sms'] === '0');

  if (!$sms_opted_out) {
    return;
  }

  $cio = new CustomerIO();
  $customer = $cio->searchCustomerById($cid);

  if (!$customer || empty($customer['attributes']['phone'])) {
    return;
  }

  $phone = $customer['attributes']['phone'];
  $phone = preg_replace('/[^\d]/', '', $phone);

  $cl_ids = o19_get_all_list_ids($username, $token);

  if (empty($cl_ids)) {
    return;
  }

  o19_remove_phone_from_lists($phone, $cl_ids, $username, $token);
}, 10, 3);

/**
 * Get all 019 contact list IDs
 */
function o19_get_all_list_ids(string $username, string $token): array {
  $body = json_encode([
    'getCL' => [
      'user' => ['username' => $username],
      'cl_id' => 'all'
    ]
  ], JSON_UNESCAPED_SLASHES);

  $response = wp_remote_post('https://019sms.co.il/api', [
    'body' => $body,
    'headers' => [
      'Authorization' => 'Bearer ' . $token,
      'Content-Type' => 'application/json'
    ],
    'timeout' => 15
  ]);

  if (is_wp_error($response)) return [];

  $result = json_decode(wp_remote_retrieve_body($response), true);

  if (empty($result['contact_lists']) || !is_array($result['contact_lists'])) return [];

  return array_values(array_unique(array_column($result['contact_lists'], 'cl_id')));
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
