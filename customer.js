JSUtils.domReady(() => {
  const submitSettings = document.querySelector('#submit_customerio_settings');
  if (!submitSettings) return;

  submitSettings.addEventListener('click', async (e) => {
    e.preventDefault();
    try {
      const form = document.querySelector('.form-table');
      const data = {
        enabled: form.querySelector('[name=enabled]').checked,
        region: form.querySelector('[name=region]').value,
        apiKey: form.querySelector('[name=api_key]').value,
        siteId: form.querySelector('[name=site_id]').value,
        broadcastKey: form.querySelector('[name=broadcast_key]').value,
        betaApiAppKey: form.querySelector('[name=beta_api_app_key]').value
      };

      await JSUtils.fetch(window.customerIOData.ajax_url, {
        action: 'save_customerio_settings',
        ...data
      });

      window.notifications.show('CustomerIO settings saved successfully', 'success');
    } catch (ex) {
      window.notifications.show(ex.message, 'error');
    }
  });
});
