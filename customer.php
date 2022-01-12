<?php 
class CustomerIO {
  private $enabled = false;
  private $apiKey = "";
  private $siteId = "";
  private $broadcastKey = "";
  private $region = "us";
  
  const CUSTOMERIO_TRACK_API_URL = "https://track.customer.io/api/v1";
  const CUSTOMERIO_CAMPAIGN_API_URL = "https://api.customer.io/v1";
  const CUSTOMERIO_AUTH_URL = "https://track.customer.io/auth";
  const CUSTOMERIO_SETTINGS = "customerio_settings";

  function __construct()
  {
    $data = get_option(self::CUSTOMERIO_SETTINGS,false);
		if(!$data) return;

    $data = json_decode($data, true);
    if(!$data) return;

    $this->enabled = $data["enabled"];
    $this->apiKey = $data["apikey"];
    $this->siteId = $data["siteid"];
    $this->region = isset($data["region"]) ? $data["region"] : 'us';
    $this->broadcastKey = empty($data["broadcastkey"]) ? "" : $data["broadcastkey"];
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

  function getRegion() {
    return $this->region;
  }

  function addRegion($url) {
    if($this->region != "us") 
      $url = preg_replace("/^([^\.]*)(.*$)/","$1-".$this->region."$2",$url);
    return $url;
  }

  static function saveSettings($enabled, $apiKey, $siteId, $broadcastKey, $region) {
    $data = ["enabled" => $enabled, "siteid" => $siteId, "apikey" => $apiKey, "broadcastkey" => $broadcastKey, "region" => $region];
    update_option(self::CUSTOMERIO_SETTINGS,json_encode($data,JSON_UNESCAPED_SLASHES));
  }

  function testAuth()  {
    $auth = base64_encode($this->siteId.":".$this->apiKey);
    return file_get_contents($this->addRegion(self::CUSTOMERIO_AUTH_URL),false,stream_context_create([
      'http' => [
        'method' => 'GET',
        'header' => 'Authorization: Basic '.$auth,
      ]
    ]));
  }

  private function sendTrackRequest($endpoint, $data, $method = 'PUT') {
    $url = $this->addRegion(self::CUSTOMERIO_TRACK_API_URL).$endpoint;
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

  //this function receives a segment and/or a list of email recepients, and sends a transaction broadcast to them.
  //recipients, if provided, should be an array of emails
  //the andOr operator determines if we should send only to emails in segment or to all emails AND all segment customers (unification vs slicing)
  function sendBroadcast($broadcastId, $params, $recipients = [], $segment = null, $andOr = "and") {
    if(!$this->enabled) return false; 

    if(wp_get_environment_type() != "production" && isset($params["subject"])) {
      $params["subject"] = "[DEVELOPMENT] ".$params["subject"];
    }

    $data = ["data" => $params];

    $recips = [$andOr => []];

    if($segment != null) {
      $recips[$andOr][] = [
        "segment" => [
          "id" => $segment
        ]
      ];
    } 
    if(count($recipients) > 0) {
      $arr = ["or" => []];
      foreach($recipients as $recipient) {
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

    $url = $this->addRegion(self::CUSTOMERIO_CAMPAIGN_API_URL)."/campaigns/".$broadcastId."/triggers";

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