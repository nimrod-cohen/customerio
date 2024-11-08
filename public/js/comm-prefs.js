class CommPrefs {
  choose_unsubscribe = e => {
    document.querySelectorAll('.communcation-preferences input[type="checkbox"]').forEach(checkbox => {
      checkbox.checked = false;
    });

    this.save_comm_prefs(e);
  };

  save_comm_prefs = async e => {
    const btns = document.querySelectorAll('.comm-prefs-actions button');

    try {
      e.target.classList.add('loading');
      btns.forEach(btn => {
        btn.classList.add('locked');
      });

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
        this.show_message('העדפותיך נשמרו בהצלחה', 'success');
      } else {
        this.show_message('אירעה שגיאה בשמירת העדפותיך', 'error');
      }
    } finally {
      e.target.classList.remove('loading');
      btns.forEach(btn => {
        btn.classList.remove('locked');
      });
    }
  };

  show_message = (message, type = 'info') => {
    const msg = document.querySelector('.comm-prefs-message');

    msg.innerHTML = message;
    msg.classList.add('show', type);

    setTimeout(() => {
      msg.innerHTML = '';
      msg.classList.remove('show');
      msg.classList.remove('error', 'success', 'info');
    }, 3000);
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
