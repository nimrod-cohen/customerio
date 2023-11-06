<?php

$customerio = new CustomerIO();
$region = $customerio->getRegion();
$eventTracking = new EventTracking();

?>
<h1>CustomerIO settings</h1>
<small>Version <?php echo CustomerIOAdmin::get_version(); ?></small>
<nav id="customerio-admin-navs" class="nav-tab-wrapper">
  <a href="#" tabId="customerio" class="nav-tab nav-tab-active">Customer IO</a>
  <a href="#" tabId="event-tracking" class="nav-tab">Event Tracking</a>
</nav>
<div id="customerio-admin-tabs">
  <div class="tab-content customerio">
    <table class="form-table">
      <tbody>
        <tr>
          <th>Enabled</th>
          <td>
            <input class="wpjsutils switch round" type="checkbox" name="enabled" <?php echo $customerio->isEnabled() ? 'checked' : '' ?> >
          </td>
        </tr>
        <tr>
          <th><label for="region">Region</label></th>
          <td>
            <select name="region" id="region">
              <option value="us" <?php echo $region == 'us' ? 'selected' : '' ?>>US</option>
              <option value="eu" <?php echo $region == 'eu' ? 'selected' : '' ?>>EU</option>
            </select>
          </td>
        </tr>
        <tr>
          <th><label for="track_api_key">Track API key</label></th>
          <td>
              <input name="track_api_key" id="track_api_key" type="text" value="<?php echo $customerio->getTrackApiKey() ?>" class="regular-text code">&nbsp;
              <input id="test_track_auth" type="button" class="button" value="Test Track Auth">
          </td>
        </tr>
        <tr>
          <th><label for="site_id">Site ID</label></th>
          <td> <input name="site_id" id="site_id" type="text" value="<?php echo $customerio->getSiteId() ?>" class="regular-text code"></td>
        </tr>
        <tr>
          <th><label for="api_key">Main API key</label></th>
          <td> <input name="api_key" id="api_key" type="text" value="<?php echo $customerio->getApiKey() ?>" class="regular-text code"></td>
        </tr>
        <tr>
          <th><label for="beta_api_key">Beta API key</label></th>
          <td>
            <input name="beta_api_key" id="beta_api_key" type="text" value="<?php echo $customerio->getBetaApiKey() ?>" class="regular-text code">
            <label>this is only needed if you're using beta API features</label>
          </td>
        </tr>
        <tr>
          <th><label for="default_country_code">Default Country Code</label></th>
          <td>
            <input name="default_country_code" id="default_country_code" type="text" value="<?php echo $customerio->getDefaultCountryCode() ?>" class="regular-text code">
            <label>Which country code to add to phone numbers, if not provided</label>
          </td>
        </tr>
      </tbody>
    </table>
    <h2>Test integration</h2>
    <table class="form-table">
      <tbody>
        <tr>
          <th><label for="customer_email">Customer email exists</label></th>
          <td><input id="customer_email" type="text" class="regular-text code">&nbsp;<input id="test_customer_exists" type="button" class="button" value="Test" /></td>
        </tr>
        <tr>
          <th><label>Send test broadcast</label></th>
          <td><input id="broadcast_id" type="text" class="regular-text code">&nbsp;<input id="test_broadcast" type="button" class="button" value="Send test" /></td>
        </tr>
      </tbody>
    </table>
    <p class="submit">
      <input type="button" id="submit_customerio_settings" class="button button-primary" value="Save Settings">
    </p>
  </div>
  <div class="tab-content event-tracking">
    <table class="form-table">
      <tbody>
        <tr>
          <th>Auto Tracking Enabled</th>
          <td>
            <input class="wpjsutils switch round" type="checkbox" id="enable_track_events" name="enable_track_events" <?php echo $eventTracking->autotrack_enabled() ? "checked" : ""; ?> >
          </td>
        </tr>
        <tr>
          <th><label for="api_token">Main API key</label></th>
          <td> <input name="api_token" id="api_token" type="text" value="<?php echo $eventTracking->token() ?>" class="regular-text code"></td>
        </tr>
        <tr>
          <th><label for="company_id">Company Id</label></th>
          <td> <input name="company_id" id="company_id" type="text" value="<?php echo $eventTracking->company_id() ?>" class="regular-text code"></td>
        </tr>
      </tbody>
    </table>
    <p class="submit">
      <input type="button" id="submit_event_tracking_settings" class="button button-primary" value="Save Settings">
    </p>
  </div>
</div>