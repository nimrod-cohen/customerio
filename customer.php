<?php
class CustomerIO {
  private $enabled = false;
  private $trackApiKey = "";
  private $siteId = "";
  private $apiKey = "";
  private $betaApiKey = "";
  private $defaultCountryCode = "1";
  private $region = "us";

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

  function addRegion($url) {
    if ($this->region != "us") {
      $url = preg_replace("/^([^\.]*)(.*$)/", "$1-" . $this->region . "$2", $url);
    }

    return $url;
  }

  static function saveSettings($enabled, $trackApiKey, $siteId, $apiKey, $betaApiKey, $defaultCountryCode, $region) {
    $data = [
      "enabled" => $enabled,
      "siteid" => $siteId,
      "trackapikey" => $trackApiKey,
      "apikey" => $apiKey,
      "betaapikey" => $betaApiKey,
      "defaultcountrycode" => $defaultCountryCode,
      "region" => $region
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
      $existing = $this->sendAPIRequest("/customers?email=" . $email, [], 'GET');

      $existing = json_decode($existing, true);

      return $existing && !empty($existing["results"]) && count($existing["results"]) > 0;

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

  private function sendTrackRequest($endpoint, $data, $method = 'PUT') {
    $url = $this->addRegion(self::CUSTOMERIO_TRACK_API_URL) . $endpoint;

    $auth = base64_encode($this->siteId . ":" . $this->trackApiKey);

    return $this->sendRequest($url, $data, $method, [CURLOPT_HTTPHEADER => [
      'Authorization: Basic ' . $auth,
      'content-type: application/json'
    ]]);
  }

  private function sendAPIRequest($endpoint, $data, $method = 'PUT') {
    $url = $this->addRegion(self::CUSTOMERIO_API_URL) . $endpoint;
    return $this->sendRequest($url, $data, $method);
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
    if (!$this->enabled || wp_get_environment_type() !== 'production') {
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

      $result = curl_exec($conn);
      $code = curl_getinfo($conn, CURLINFO_RESPONSE_CODE);

      curl_close($conn);

      if ($code >= 300 || !$result || preg_match("/DOCTYPE html/i", $result)) {
        throw new Exception("Failed to call customer.io");
      }

    } catch (Exception $ex) {
      //TODO: log error
      $err = $ex->getMessage();

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
      //TODO: log error
      $err = $ex->getMessage();

      return false;
    }
    return $result;
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

    return $this->sendTrackRequest("/customers/" . $email, $data);
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

    if (!empty($name)) {
      $data["name"] = $name;
    }

    if (!empty($data["phone"])) {
      $data["phone"] = $this->preparePhone($data["phone"]);
    }

    return $this->sendTrackRequest("/customers/" . $email, $data);
  }

  function sendEvent($event, $email, $data) {
    $data = [
      "name" => $event,
      "data" => $data
    ];

    return $this->sendTrackRequest("/customers/" . $email . "/events", $data, 'POST');
  }
}
?>