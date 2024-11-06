<?php
/**
 * User: nimrodcohen
 * Date: 04/11/2024
 * Time: 16:02
 */

class CommPrefs {
  private static $instance;
  private $CIO_ID = null;

  public static function get_instance() {
    if (!isset(self::$instance)) {
      self::$instance = new CommPrefs();
    }

    return self::$instance;
  }

  private function __construct() {
    add_action("template_redirect", [$this, "register_assets"]);
    add_action("wp_ajax_save_communication_preferences", [$this, "save_comm_prefs"]);
    add_action("wp_ajax_nopriv_save_communication_preferences", [$this, "save_comm_prefs"]);
  }

  public function save_comm_prefs(): void {
    try {
      $cio = new CustomerIO();
      $preferences = stripslashes($_POST['preferences']);

      $prefsArray = json_decode($preferences, true);

      //check if all preferences are with value 0
      $full_unsubscribe = array_reduce($prefsArray, function ($carry, $item) {
        return $carry && $item == "0";
      }, true);

      $data = ["comm_prefs" => $preferences];

      if ($full_unsubscribe) {
        $data["unsubscribed"] = true;
      } else {
        $data["unsubscribed"] = false;
      }

      $cio->updateCustomerById($_POST["cid"], $data);

      wp_send_json_success();
    } catch (Exception $e) {
      wp_send_json_error($e->getMessage());
    }
  }

  public function register_assets() {
    wp_register_style("cio-comm-prefs-styles", plugins_url("customerio/public/css/comm-prefs.css"));
    wp_enqueue_style("cio-comm-prefs-styles");
    //load comm-prefs.js
    wp_register_script("cio-comm-prefs-js", plugins_url("customerio/public/js/comm-prefs.js"), ["wpjsutils"]);
    wp_enqueue_script("cio-comm-prefs-js");

    wp_localize_script("cio-comm-prefs-js", "__commPrefs", [
      "ajax_url" => admin_url("admin-ajax.php")
    ]);

  }

  public function show_comm_prefs($atts) {
    $this->CIO_ID = sanitize_text_field($_GET['cid']) ?? null;

    if ($this->CIO_ID == null) {
      return "";
    }

    $cio = new CustomerIO();

    $customer = $cio->searchCustomerById($this->CIO_ID);

    if (!$customer) {
      return "";
    }

    wp_enqueue_script('cio-comm-prefs-js');
    wp_enqueue_style('cio-comm-prefs-styles');

    $preferences = $customer['attributes']['comm_prefs'] ?? null;

    if (!empty($preferences)) {
      $preferences = json_decode($preferences, true);
    }

    $pref_types = [[
      "title" => "הזמנות להרצאות",
      "desc" => "קבלת הזמנות להרצאות של ינון אריאלי על השוק ושיטות השקעה - שיווקי",
      "setting" => "webinar-invites"
    ], [
      "title" => "הודעות בסמס - הזמנות להרצאות - שיווקי",
      "desc" => "תזכורות על הרצאות של ינון אריאלי בסמס",
      "setting" => "webinar-invites-sms"
    ], [
      "title" => "״התיק המנצח״ - עדכונים שוטפים",
      "desc" => "מיועד למנויי התיק המנצח - עדכוני מוצר, סקירות והתראות קניה ומכירה",
      "setting" => "portfolio-updates"
    ], [
      "title" => "הודעות מערכת הקורסים לתלמידים",
      "desc" => "הודעות מערכת הקורסים שלנו, מיועד לתלמידים בלבד",
      "setting" => "courses-updates"
    ], [
      "title" => "הודעות על פוסטים חדשים ב bursa4u",
      "desc" => "עדכונים על פוסטים חדשים בבלוג שלנו",
      "setting" => "blog-posts"
    ]
    ];

    $result = "<div class='communcation-preferences'>
      <h2 class='comm-prefs-title'>קבלת עדכונים והתראות - ניהול העדפות</h2>
      <span class='comm-prefs-subtitle'>בחרו את סוג העדכונים שאתם מעוניינים לקבל מאיתנו</span>
      <input type='hidden' id='user-cid' value='" . $this->CIO_ID . "'>
      <ul class='communication-types'>";
    foreach ($pref_types as $pref_type) {
      $id = "comm-prefs-" . $pref_type['setting'];
      $result .= "
        <li>
          <label class='comm-pref-title' for='$id'>
            <input type='checkbox' id='$id' name='$id' value='" . $pref_type["setting"] . "' " .
        (!isset($preferences[$pref_type["setting"]]) || $preferences[$pref_type["setting"]] == "1" ? "checked" : "") . ">" .
        $pref_type['title'] .
        "</label><br/>
          <span class='comm-pref-desc'>" . $pref_type['desc'] . "</span>
        </li>";
    }
    $result .= "</ul>
          <button class='save-comm-prefs button primary'>שמור העדפות</button>
          <button class='full-unsubscribe button secondary'>הסרה מכל הרשימות</button>
      </div>";

    return $result;
  }
}

$commPrefs = CommPrefs::get_instance();
