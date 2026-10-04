<?php
class CustomerIO {
  private $enabled = false;
  private $trackApiKey = "";
  private $siteId = "";
  private $apiKey = "";
  private $betaApiKey = "";
  private $defaultCountryCode = "1";
  private $region = "us";
  private $verificationMessageId = "";

  const CUSTOMERIO_TRACK_API_URL = "https://track.customer.io/api/v1";
  const CUSTOMERIO_API_URL = "https://api.customer.io/v1";
  const CUSTOMERIO_BETA_API_URL = "https://beta-api.customer.io/v1/api"; /* beta api capabilities require different authentication method */
  const CUSTOMERIO_AUTH_URL = "https://track.customer.io/auth";
  const CUSTOMERIO_SETTINGS = "customerio_settings";

  function __construct() {
    $data = get_option(self::CUSTOMERIO_SETTINGS, false);
    if (!$data) {
      return;
    }

    $data = json_decode($data, true);
    if (!$data) {
      return;
    }

    $this->enabled = $data["enabled"];
    $this->trackApiKey = $data["trackapikey"];
    $this->siteId = $data["siteid"];
    $this->region = !empty($data["region"]) ? $data["region"] : 'us';
    $this->betaApiKey = !empty($data["betaapikey"]) ? $data["betaapikey"] : '';
    $this->defaultCountryCode = !empty($data["defaultcountrycode"]) ?
    $data["defaultcountrycode"] : '1';
    $this->apiKey = !empty($data["apikey"]) ? $data["apikey"] : '';
    $this->verificationMessageId = !empty($data["verificationmessageid"]) ? $data["verificationmessageid"] : '';
  }

  function isEnabled() {
    return $this->enabled;
  }

  function getTrackApiKey() {
    return $this->trackApiKey;
  }

  function getApiKey() {
    return $this->apiKey;
  }

  function getSiteId() {
    return $this->siteId;
  }

  function getRegion() {
    return $this->region;
  }

  function getBetaApiKey() {
    return $this->betaApiKey;
  }

  function getDefaultCountryCode() {
    return $this->defaultCountryCode;
  }

  function getVerificationMessageId() {
    return $this->verificationMessageId;
  }

  function addRegion($url) {
    if ($this->region != "us") {
      $url = preg_replace("/^([^\.]*)(.*$)/", "$1-" . $this->region . "$2", $url);
    }

    return $url;
  }

  static function saveSettings($enabled, $trackApiKey, $siteId, $apiKey, $betaApiKey, $defaultCountryCode, $region, $verificationMessageId = '') {
    $data = [
      "enabled" => $enabled,
      "siteid" => $siteId,
      "trackapikey" => $trackApiKey,
      "apikey" => $apiKey,
      "betaapikey" => $betaApiKey,
      "defaultcountrycode" => $defaultCountryCode,
      "region" => $region,
      "verificationmessageid" => $verificationMessageId
    ];
    update_option(self::CUSTOMERIO_SETTINGS, json_encode($data, JSON_UNESCAPED_SLASHES));
  }

  function testAuth() {
    $auth = base64_encode($this->siteId . ":" . $this->trackApiKey);
    return file_get_contents($this->addRegion(self::CUSTOMERIO_AUTH_URL), false, stream_context_create([
      'http' => [
        'method' => 'GET',
        'header' => 'Authorization: Basic ' . $auth
      ]
    ]));
  }

  function customerExists($email) {
    try {
      $existing = $this->sendAPIRequest("/customers?email=" . urlencode($email), [], 'GET');

      $existing = json_decode($existing, true);

      return $existing && !empty($existing["results"]) && count($existing["results"]) > 0;

    } catch (Exception $ex) {
      return false;
    }
  }

  //create segment, and add customers to it from array of emails
  function createSegment($name, $emails) {
    try {
      $result = $this->sendAPIRequest("/segments", ["segment" => ["name" => $name, "type" => "manual"]], 'POST');

      $result = json_decode($result, true);

      if (!isset($result["segment"]["id"])) {
        return false;
      }

      $segmentId = $result["segment"]["id"];

      $chunks = array_chunk($emails, 1000);

      foreach ($chunks as $chunk) {
        $result = $this->sendTrackRequest("/segments/" . $segmentId . "/add_customers?id_type=email", ["ids" => $chunk], 'POST');
        if (!$result) {
          return false;
        }
      }

      return $segmentId;

    } catch (Exception $ex) {
      return false;
    }
  }

  function getBySegment($segment) {
    try {
      $result = $this->sendAPIRequest("/customers?limit=1000", ["filter" => ["segment" => ["id" => $segment]]], 'POST');

      $result = json_decode($result, true);

      if (!$result) {
        return null;
      }

      $existing = $result["identifiers"];

      while ($result["next"]) {
        $result = $this->sendAPIRequest("/customers?start=" . $result["next"] . "&limit=200", ["filter" => ["segment" => ["id" => $segment]]], 'POST');
        $result = json_decode($result, true);
        $existing = array_merge($existing, $result["identifiers"]);
      }

      return array_map(function ($val) {return $val["email"];}, $existing);

    } catch (Exception $ex) {
      return false;
    }
  }

  //list all segments. dynamic segments come back with their `conditions` tree.
  function getSegments($timeout = 0) {
    $result = $this->sendAPIRequest("/segments", [], 'GET', $timeout ? [CURLOPT_TIMEOUT => $timeout] : []);
    if (!$result) {
      return null;
    }

    $result = json_decode($result, true);

    return isset($result["segments"]) ? $result["segments"] : null;
  }

  function getSegment($segmentId, $timeout = 0) {
    $result = $this->sendAPIRequest("/segments/" . intval($segmentId), [], 'GET', $timeout ? [CURLOPT_TIMEOUT => $timeout] : []);
    if (!$result) {
      return null;
    }

    $result = json_decode($result, true);

    return isset($result["segment"]) ? $result["segment"] : null;
  }

  //segments are addressed by id in the API, but humans know them by name.
  function getSegmentByName($name, $timeout = 0) {
    $segments = $this->getSegments($timeout);
    if (empty($segments)) {
      return null;
    }

    foreach ($segments as $segment) {
      if (strcasecmp(trim($segment["name"]), trim($name)) === 0) {
        return $segment;
      }
    }

    return null;
  }

  //how many profiles the segment currently holds. null when it cannot be read.
  function getSegmentCount($segmentId, $timeout = 0) {
    $result = $this->sendAPIRequest("/segments/" . intval($segmentId) . "/customer_count", [], 'GET', $timeout ? [CURLOPT_TIMEOUT => $timeout] : []);
    if (!$result) {
      return null;
    }

    $result = json_decode($result, true);

    return isset($result["count"]) ? intval($result["count"]) : null;
  }

  private function sendTrackRequest($endpoint, $data, $method = 'PUT') {
    $url = $this->addRegion(self::CUSTOMERIO_TRACK_API_URL) . $endpoint;

    $auth = base64_encode($this->siteId . ":" . $this->trackApiKey);

    return $this->sendRequest($url, $data, $method, [CURLOPT_HTTPHEADER => [
      'Authorization: Basic ' . $auth,
      'content-type: application/json'
    ]]);
  }

  private function sendAPIRequest($endpoint, $data, $method = 'PUT', $options = []) {
    $url = $this->addRegion(self::CUSTOMERIO_API_URL) . $endpoint;
    return $this->sendRequest($url, $data, $method, $options);
  }

  private function sendBetaRequest($endpoint, $data, $method = 'PUT') {
    if (!$this->enabled) {
      return false;
    }

    try {
      $url = $this->addRegion(self::CUSTOMERIO_BETA_API_URL) . $endpoint;

      $auth = $this->betaApiKey;
      $result = $this->sendRequest($url, $data, $method, [CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $auth,
        'content-type: application/json'
      ]]);
    } catch (Exception $ex) {
      //log error
      $err = $ex->getMessage();

      return false;
    }
    return $result;
  }

  private function sendRequest($url, $data, $method = 'PUT', $options = []) {
    if (!$this->enabled) {
      return false;
    }

    try {
      $data = apply_filters('customerio/request_data', $data);

      $content = json_encode($data);

      $headers = null;
      if (isset($options[CURLOPT_HTTPHEADER])) {
        $headers = $options[CURLOPT_HTTPHEADER];
      } else {
        $auth = $this->apiKey;
        $headers = [
          'Authorization: Bearer ' . $auth,
          'content-type: application/json'
        ];
      }

      $conn = curl_init($url);
      if ($method != 'GET') {
        curl_setopt($conn, CURLOPT_POSTFIELDS, $content);
      }

      curl_setopt($conn, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($conn, CURLOPT_CUSTOMREQUEST, $method);
      curl_setopt($conn, CURLOPT_HTTPHEADER, $headers);

      // Callers on a page-render path pass a timeout so a slow Customer.io
      // cannot hold up the response. Without it curl waits indefinitely.
      if (!empty($options[CURLOPT_TIMEOUT])) {
        curl_setopt($conn, CURLOPT_TIMEOUT, $options[CURLOPT_TIMEOUT]);
        curl_setopt($conn, CURLOPT_CONNECTTIMEOUT, min(5, $options[CURLOPT_TIMEOUT]));
      }

      $result = curl_exec($conn);
      $code = curl_getinfo($conn, CURLINFO_RESPONSE_CODE);

      curl_close($conn);

      if ($code >= 300 || ($method != 'DELETE' && !$result) || preg_match("/DOCTYPE html/i", $result)) {
        throw new Exception("HTTP $code | body=" . substr((string) $result, 0, 500));
      }

    } catch (Exception $ex) {
      $err = $ex->getMessage();
      $payloadPreview = substr((string) $content, 0, 500);
      // Emit a generic action so any consumer plugin (per site) can route this to its
      // own logger. customerio stays site-agnostic — no class references to specific
      // projects.
      do_action('customerio/log', 'error', "$method $url failed: $err", [
        'method' => $method, 'url' => $url, 'error' => $err, 'payload' => $payloadPreview,
      ]);

      return false;
    }
    return $result;
  }

  //this function receives a segment and/or a list of email recepients, and sends a transaction broadcast to them.
  //recipients, if provided, should be an array of emails
  //the andOr operator determines if we should send only to emails in segment or to all emails AND all segment customers (unification vs slicing)
  function sendBroadcast($broadcastId, $params, $recipients = [], $segment = null, $andOr = "and") {
    if (wp_get_environment_type() != "production" && isset($params["subject"])) {
      $params["subject"] = "[DEVELOPMENT] " . $params["subject"];
    }

    $data = ["data" => $params];

    $recips = [$andOr => []];

    if ($segment != null) {
      $recips[$andOr][] = [
        "segment" => [
          "id" => $segment
        ]
      ];
    }
    if (count($recipients) > 0) {
      $arr = ["or" => []];
      foreach ($recipients as $recipient) {
        $arr["or"][] = [
          "attribute" => [
            "field" => "email",
            "operator" => "eq",
            "value" => $recipient
          ]
        ];
      }
      $recips[$andOr][] = $arr;
    }

    $data["recipients"] = $recips;

    $url = $this->addRegion(self::CUSTOMERIO_API_URL) . "/campaigns/" . $broadcastId . "/triggers";

    $result = false;
    try {
      $result = $this->sendRequest($url, $data, "POST");
    } catch (Exception $ex) {
      $err = $ex->getMessage();
      do_action('customerio/log', 'error', "broadcast $broadcastId failed: $err", [
        'broadcast_id' => $broadcastId, 'error' => $err,
      ]);
      return false;
    }
    if ($result === false) {
      // sendRequest swallowed an HTTP error and returned false — surface the dropped broadcast.
      do_action('customerio/log', 'warning', "broadcast $broadcastId not delivered (sendRequest returned false)", [
        'broadcast_id' => $broadcastId, 'recipients' => $recips,
      ]);
    }
    return $result;
  }

  function deleteCustomer($email) {
    if (!$email || !is_email($email)) {
      return false;
    }

    return $this->sendTrackRequest("/customers/" . urlencode($email), [], 'DELETE');
  }

  function unsubscribeCustomer($email) {
    if (!$email || !is_email($email)) {
      return false;
    }

    $data = ["email" => $email, "unsubscribed" => true];

    return $this->sendTrackRequest("/customers/" . $email, $data);
  }

  function updateCustomer($email, $name, $data = []) {
    if (!$email || !is_email($email)) {
      return false;
    }

    if (wp_get_environment_type() != "production") {
      return true;
    }

    $data = array_merge(["email" => $email], $data);

    if (!empty($data["phone"])) {
      $data["phone"] = $this->preparePhone($data["phone"]);
    }

    if (!empty($name)) {
      $data["name"] = $name;
    }

    return $this->sendTrackRequest("/customers/" . urlencode($email), $data);
  }

  function searchCustomerById($cio_id) {
    try {
      $result = $this->sendAPIRequest("/customers/" . $cio_id . "/attributes", [], 'GET');

      $result = json_decode($result, true);

      if (empty($result)) {
        return false;
      }

      return $result["customer"];

    } catch (Exception $ex) {
      return false;
    }
  }

  function updateCustomerById($cio_id, $data = []) {
    if (empty($cio_id)) {
      return false;
    }

    if (!empty($data["phone"])) {
      $data["phone"] = $this->preparePhone($data["phone"]);
    }

    return $this->sendTrackRequest("/customers/cio_" . $cio_id, $data);
  }

  function preparePhone($phone) {
    if (preg_match('/^\+/', $phone)) {
      return '+' . preg_replace('/[^\d]/', '', $phone);
    }

    $phone = preg_replace('/[^\d]/', '', $phone);

    if (preg_match('/^00/', $phone)) {
      return preg_replace('/^00/', '+', $phone);
    }

    $defaultCountryCode = $this->defaultCountryCode;

    if (preg_match('/^0/', $phone)) {
      return '+' . $defaultCountryCode . preg_replace('/^0/', '', $phone);
    }

    return '+' . $defaultCountryCode . $phone;
  }

  function createCustomer($email, $name, $data = []) {
    if (!$email || !is_email($email)) {
      return false;
    }

    $data = array_merge(["email" => $email, "unsubscribed" => false], $data);

    if (!$this->customerExists($email)) {
      $data["created_at"] = time();
    }

    if (!empty($name)) {
      $data["name"] = $name;
    }

    if (!empty($data["phone"])) {
      $data["phone"] = $this->preparePhone($data["phone"]);
    }

    return $this->sendTrackRequest("/customers/" . urlencode($email), $data);
  }

  /** Customer.io rejects a send whose attachments exceed 2MB once encoded. */
  const MAX_ATTACHMENTS_BYTES = 2097152;

  /**
   * Send a transactional email.
   *
   * $options carries anything beyond the template and its merge data:
   *
   *   attachments  ['invoice.pdf' => '/path/to/file.pdf']            a readable path
   *                ['invoice.pdf' => ['content' => $rawBytes]]       bytes you already have
   *                ['invoice.pdf' => ['base64' => $encoded]]         pre-encoded
   *   body         HTML body, overriding the template's
   *   body_plain   text alternative
   *   subject, from, reply_to, bcc, preheader, headers, language
   *   tracked, send_to_unsubscribed, disable_message_retention, queue_draft
   *   identifiers  defaults to ['email' => $to]
   *
   * A transactional message id is still the normal path: it carries the
   * layout, the sending domain and the reporting. Passing none is allowed, but
   * then Customer.io has nothing to render, so subject, from and body are
   * required and the call fails early rather than at the API.
   */
  function sendTransactionalEmail($to, $transactionalMessageId, $messageData = [], $options = []) {
    if (!$this->enabled) {
      return false;
    }

    $adHoc = empty($transactionalMessageId);

    if ($adHoc) {
      foreach (['subject', 'from', 'body'] as $required) {
        if (empty($options[$required])) {
          do_action('customerio/log', 'error',
            "sendTransactionalEmail: without a transactional_message_id, '$required' is required", []);
          return false;
        }
      }
    }

    $data = [
      'to' => $to,
      'identifiers' => $options['identifiers'] ?? ['email' => $to],
      'message_data' => $messageData
    ];

    if (!$adHoc) {
      $data['transactional_message_id'] = is_numeric($transactionalMessageId)
        ? (int) $transactionalMessageId
        : $transactionalMessageId;
    }

    // Straight passthrough: these are Customer.io's own field names, so the
    // caller reads their documentation rather than ours.
    foreach (['body', 'body_plain', 'body_amp', 'subject', 'from', 'reply_to', 'bcc',
      'preheader', 'headers', 'language', 'tracked', 'send_to_unsubscribed',
      'disable_message_retention', 'queue_draft'] as $field) {
      if (isset($options[$field]) && $options[$field] !== '') {
        $data[$field] = $options[$field];
      }
    }

    if (!empty($options['attachments'])) {
      $attachments = $this->encodeAttachments($options['attachments']);
      if ($attachments === false) {
        return false; // already logged, with the reason
      }
      $data['attachments'] = $attachments;
    }

    $url = $this->addRegion(self::CUSTOMERIO_API_URL) . '/send/email';

    return $this->sendRequest($url, $data, 'POST');
  }

  /**
   * Send an HTML email without a Customer.io template.
   *
   * The convenience wrapper for "just send this HTML, with these files".
   * Everything still goes through the transactional endpoint, so it is
   * delivered, tracked and reported like any other transactional message.
   */
  function sendHtmlEmail($to, $subject, $html, $options = []) {
    return $this->sendTransactionalEmail($to, null, $options['message_data'] ?? [], array_merge($options, [
      'subject' => $subject,
      'body' => $html,
      'from' => $options['from'] ?? get_option('customerio_default_from', get_option('admin_email'))
    ]));
  }

  /**
   * filename => base64, which is the only shape the API accepts.
   *
   * Encoding inflates a file by about a third, and the 2MB ceiling applies to
   * the encoded size, so the check happens after encoding — measuring the file
   * on disk would let a 1.6MB PDF through and fail at the API.
   */
  private function encodeAttachments($attachments) {
    $encoded = [];
    $total = 0;

    foreach ((array) $attachments as $filename => $source) {
      $base64 = null;

      if (is_array($source)) {
        if (isset($source['base64'])) {
          $base64 = $source['base64'];
        } elseif (isset($source['content'])) {
          $base64 = base64_encode($source['content']);
        }
      } elseif (is_string($source) && is_readable($source) && is_file($source)) {
        $contents = file_get_contents($source);
        $base64 = $contents === false ? null : base64_encode($contents);
      }

      if ($base64 === null) {
        do_action('customerio/log', 'error',
          "sendTransactionalEmail: attachment '$filename' could not be read", ['source' => is_string($source) ? $source : gettype($source)]);
        return false;
      }

      $total += strlen($base64);
      $encoded[$filename] = $base64;
    }

    if ($total > self::MAX_ATTACHMENTS_BYTES) {
      do_action('customerio/log', 'error', sprintf(
        'sendTransactionalEmail: attachments total %s encoded, over the %s Customer.io accepts',
        size_format($total), size_format(self::MAX_ATTACHMENTS_BYTES)
      ), ['bytes' => $total]);
      return false;
    }

    return $encoded;
  }

  function sendEvent($event, $email, $data) {
    $data = [
      "name" => $event,
      "data" => $data
    ];

    return $this->sendTrackRequest("/customers/" . urlencode($email) . "/events", $data, 'POST');
  }
}
?>