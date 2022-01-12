<?php 
class CustomerIO {
  private $enabled = false;
  private $apiKey = "";
  private $siteId = "";
  private $broadcastKey = "";
  
  const CUSTOMERIO_TRACK_API_URL = "https://track-eu.customer.io/api/v1";
  const CUSTOMERIO_CAMPAIGN_API_URL = "https://api-eu.customer.io/v1";
  const CUSTOMERIO_AUTH_URL = "https://track-eu.customer.io/auth";
  const CUSTOMERIO_SETTINGS = "billing_customerio_settings";

  const CIO_BROADCAST_POST_UPDATE = 7;
  const CIO_BROADCAST_COMMUNICATE_USER = 8;
  const CIO_PAYING_USERS_SEGMENT = 18;
  const CIO_ADMINS_SEGMENT = 34;

  function __construct()
  {
    $data = get_option(self::CUSTOMERIO_SETTINGS,false);
		if(!$data) return;

    $data = json_decode($data, true);
    if(!$data) return;

    $this->enabled = $data["enabled"];
    $this->apiKey = $data["apikey"];
    $this->siteId = $data["siteid"];
    $this->broadcastKey = empty($data["broadcastKey"]) ? "" : $data["broadcastKey"];
  }

  function isEnabled() {
    return $this->enabled;
  }

  function getApiKey() {
    return $this->apiKey;
  }

  function getBroadcastKey() {
    return $this->broadcastKey;
  }

  function getSiteId() {
    return $this->siteId;
  }

  static function saveSettings($enabled, $apiKey, $siteId, $broadcastKey) {
    $data = ["enabled" => $enabled, "siteid" => $siteId, "apikey" => $apiKey, "broadcastKey" => $broadcastKey];
    update_option(self::CUSTOMERIO_SETTINGS,json_encode($data,JSON_UNESCAPED_SLASHES));
  }

  function testAuth()  {
    $auth = base64_encode($this->siteId.":".$this->apiKey);
    return file_get_contents(self::CUSTOMERIO_AUTH_URL,false,stream_context_create([
      'http' => [
        'method' => 'GET',
        'header' => 'Authorization: Basic '.$auth,
      ]
    ]));
  }

  private function sendTrackRequest($endpoint, $data, $method = 'PUT') {
    $url = self::CUSTOMERIO_TRACK_API_URL.$endpoint;
    return $this->sendRequest($url, $data, $method);
  }

  private function sendRequest($url, $data, $method = 'PUT') {
    if(!$this->enabled) return false; 

    try {
      $auth = base64_encode($this->siteId.":".$this->apiKey);
      $content = json_encode($data);

      $conn = curl_init($url);
      curl_setopt($conn, CURLOPT_POSTFIELDS, $content);
      curl_setopt($conn, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($conn, CURLOPT_CUSTOMREQUEST, $method);
      curl_setopt($conn, CURLOPT_HTTPHEADER, [
        'Authorization: Basic '.$auth,
        'content-type: application/json'
      ]);

      $result = curl_exec($conn);
      curl_close($conn);

      if(!$result || preg_match("/DOCTYPE html/i",$result)) throw new Exception("Failed to call customer.io");

    } catch(Exception $ex) {
      //log error
      $err = $ex->getMessage();

      return false;
    }
    return true;
  }

  //recipients, if provided, should be an array of emails
  function sendBroadcast($broadcastId, $params, $recipients = [], $segment = null) {
    if(!$this->enabled) return false; 

    if(wp_get_environment_type() != "production" && isset($params["subject"])) {
      $params["subject"] = "[DEVELOPMENT] ".$params["subject"];
    }

    $data = ["data" => $params];

    $recips = [];

    if($segment != null) {
      $recips = ["and" => [[
        "segment" => [
          "id" => $segment
        ]
      ]]];
    } else if(count($recipients) > 0) {
      $recips = ["or" => []];
      foreach($recipients as $recipient) {
        $recips["or"][] = [
          "attribute" => [
            "field" => "email",
            "operator" => "eq",
            "value" => $recipient
          ]
        ];
      }
    }
    $data["recipients"] = $recips;

    $url = self::CUSTOMERIO_CAMPAIGN_API_URL."/campaigns/".$broadcastId."/triggers";

    try {
      $content = json_encode($data);

      $conn = curl_init($url);
      curl_setopt($conn, CURLOPT_POSTFIELDS, $content);
      curl_setopt($conn, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($conn, CURLOPT_CUSTOMREQUEST, "POST");
      curl_setopt($conn, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer '.$this->getBroadcastKey(),
        'content-type: application/json'
      ]);

      $result = curl_exec($conn);
      curl_close($conn);

      if(!$result || preg_match("/DOCTYPE html/i",$result)) throw new Exception("Failed to call customer.io");

    } catch(Exception $ex) {
      //log error
      $err = $ex->getMessage();

      return false;
    }
    return true;
  }

  function unsubscribeCustomer($email) {
    if(!$email || !is_email($email)) return false;

    $data = ["email" => $email, "unsubscribed" => true];
    
    return $this->sendTrackRequest("/customers/".$email, $data);
  }

  function updateCustomer($email, $name, $data = []) {
    if(!$email || !is_email($email)) return false;
    if(wp_get_environment_type() != "production")  return true;

    $data = array_merge(["email" => $email], $data);

    if(!empty($name))
      $data["name"]  = $name;

    return $this->sendTrackRequest("/customers/".$email, $data);
  }

  function createCustomer($email, $name, $data = []) {
    if(!$email || !is_email($email)) return false;

    $data = array_merge(["email" => $email, "unsubscribed" => false], $data);

    if(!empty($name))
      $data["name"]  = $name;

    return $this->sendTrackRequest("/customers/".$email, $data);
  }

  function sendEvent($event, $email, $data) {
    $data = [
      "name" => $event,
      "data" => $data
    ];

    return $this->sendTrackRequest("/customers/".$email."/events", $data, 'POST');
  }
}
?>