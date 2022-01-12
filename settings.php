<?php
  $customerio = new CustomerIO();
  $region = $customerio->getRegion();
?>
<h1>CustomerIO settings</h1>
<table class="form-table">
  <tbody>
    <tr>
      <th>Enabled</th>
      <td>
        <input class="wpjsutils switch round" type="checkbox" name="enabled" <?php echo $customerio->isEnabled() ? 'checked' : ''?> >
      </td>
    </tr>
    <tr>
      <th><label for="region">Region</label></th>
      <td>
        <select name="region" id="region">
          <option value="us" <?php echo $region == 'us' ? 'selected' : ''?>>US</option>
          <option value="eu" <?php echo $region == 'eu' ? 'selected' : ''?>>EU</option>
        </select>
      </td>
    </tr>
    <tr>
      <th><label for="api_key">API key</label></th>
      <td> <input name="api_key" id="api_key" type="text" value="<?php echo $customerio->getApiKey() ?>" class="regular-text code"></td>
    </tr>
    <tr>
      <th><label for="site_id">Site ID</label></th>
      <td> <input name="site_id" id="site_id" type="text" value="<?php echo $customerio->getSiteId() ?>" class="regular-text code"></td>
    </tr>
    <tr>
      <th><label for="broadcast_key">Broadcast key</label></th>
      <td> <input name="broadcast_key" id="broadcast_key" type="text" value="<?php echo $customerio->getBroadcastKey() ?>" class="regular-text code"></td>
    </tr>
  </tbody>
</table>
<p class="submit">
  <input type="button" id="submit_customerio_settings" class="button button-primary" value="Save Settings">
</p>
