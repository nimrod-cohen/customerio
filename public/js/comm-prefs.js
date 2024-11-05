class CommPrefs {
  choose_unsubscribe = () => {
    document.querySelectorAll('.communcation-preferences input[type="checkbox"]').forEach(checkbox => {
      checkbox.checked = false;
    });
  };

  save_comm_prefs = async e => {
    try {
      e.target.classList.add('loading');

      var preferences = {};
      document.querySelectorAll('.communcation-preferences input[type="checkbox"]').forEach(checkbox => {
        preferences[checkbox.value] = checkbox.checked ? '1' : '0';
      });

      var data = {
        action: 'save_communication_preferences',
        cid: document.querySelector('.communcation-preferences input#user-cid').value,
        preferences: JSON.stringify(preferences)
      };

      const response = await JSUtils.fetch(__commPrefs.ajax_url, data);

      if (response.success) {
        window.notifications.show('העדפותיך נשמרו בהצלחה', 'success');
      } else {
        window.notifications.show('אירעה שגיאה בשמירת העדפותיך', 'error');
      }
    } finally {
      e.target.classList.remove('loading');
    }
  };

  load_preferences = () => {
    if (!__commPrefs.preferences) return;

    Object.keys(__commPrefs.preferences).forEach(pref => {
      document.querySelector(`.communcation-preferences input#comm-prefs-${pref}`).checked =
        __commPrefs.preferences[pref] === '1';
    });
  };
}

JSUtils.domReady(() => {
  const commPrefs = new CommPrefs();

  commPrefs.load_preferences();

  document.querySelector('button.full-unsubscribe').addEventListener('click', commPrefs.choose_unsubscribe);

  document.querySelector('button.save-comm-prefs').addEventListener('click', commPrefs.save_comm_prefs);
});
