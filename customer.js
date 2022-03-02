JSUtils.domReady(() => {
  document.querySelector('#submit_customerio_settings')?.addEventListener('click', async e => {
    e.preventDefault();
    try {
      const form = document.querySelector('.form-table');
      const data = {
        enabled: form.querySelector('[name=enabled]').checked,
        region: form.querySelector('[name=region]').value,
        trackApiKey: form.querySelector('[name=track_api_key]').value,
        siteId: form.querySelector('[name=site_id]').value,
        apiKey: form.querySelector('[name=api_key]').value,
        betaApiKey: form.querySelector('[name=beta_api_key]').value
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

  document.querySelector('#test_customer_exists')?.addEventListener('click', async e => {
    let email = document.querySelector('#customer_email').value;
    let result = await JSUtils.fetch(window.customerIOData.ajax_url, {
      action: 'test_customer_email',
      email: email
    });

    notifications.show(result.message, result.error ? 'error' : 'success');
  });

  document.querySelector('#test_track_auth')?.addEventListener('click', async e => {
    let result = await JSUtils.fetch(window.customerIOData.ajax_url, {
      action: 'test_track_auth'
    });

    notifications.show(result.message, result.error ? 'error' : 'success');
  });

  document.querySelector('#test_broadcast')?.addEventListener('click', async e => {
    let email = document.querySelector('#customer_email').value;
    let broadcast_id = document.querySelector('#broadcast_id').value;

    if (email.length === 0 || broadcast_id.length === 0) {
      notifications.show('Please fill email and broadcast id', 'error');
      return;
    }

    let result = await JSUtils.fetch(window.customerIOData.ajax_url, {
      action: 'test_broadcast',
      email,
      broadcast_id
    });

    notifications.show(result.message, result.error ? 'error' : 'success');
  });
});
