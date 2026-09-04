/**
 * Shared Settings – vanilla JS controller.
 *
 * Powers tab switching, search/filter, conditional dependencies,
 * unsaved-changes detection, AJAX save/reset, and media picker.
 *
 * Expects `window.SharedSettingsConfig` (localized from PHP) and
 * `.wp-polyfill-admin-wrapper` DOM structure rendered by Settings_Page.
 *
 * @since 0.2.0
 */
(function () {
  'use strict';

  // ── Locals ──────────────────────────────────────
  var config = window.SharedSettingsConfig || {};
  var form   = document.querySelector('.wp-polyfill-admin-wrapper form.wp-polyfill-admin-wrapper');

  if (!form) {
    form = document.querySelector('form.wp-polyfill-admin-wrapper');
  }

  if (!form) {
    return;
  }

  var formId             = form.id || 'shared-settings';
  var settingsTabs       = document.querySelectorAll('#wp-polyfill-settings-tabs-wrapper a');
  var settingsFormTables = form.querySelectorAll('table.wp-polyfill-settings-table');
  var settingsTabFilter  = document.getElementById('ss-settings-tab-filter');
  var settingsSearchClear = form.querySelector('.wp-polyfill-settings-search-clear');
  var settingsSearchNoResults = document.querySelector('.wp-polyfill-settings-search-no-results');
  var submitWrapper      = form.querySelector('.wp-polyfill-submit-wrapper');
  var spinner            = document.querySelector('.spinner.saving');
  var resetButton        = form.querySelector('.wp-polyfill-reset-button');
  var initialFormData    = '';

  // Store serialized form data on load
  try {
    initialFormData = new URLSearchParams(new FormData(form)).toString();
  } catch (e) {
    initialFormData = '';
  }

  // ── 1. TAB SWITCHING ─────────────────────────────

  function showTab(tabId) {
    for (var i = 0; i < settingsFormTables.length; i++) {
      var table = settingsFormTables[i];
      if (table.id === tabId) {
        table.classList.add('active');
      } else {
        table.classList.remove('active');
      }
    }
    applySettingsSearch();
  }

  function clearSettingsSearch() {
    if (settingsTabFilter) {
      settingsTabFilter.value = '';
    }
    form.classList.remove('searching-all-settings');
    if (settingsSearchClear) {
      settingsSearchClear.hidden = true;
    }
    if (settingsSearchNoResults) {
      settingsSearchNoResults.hidden = true;
    }
  }

  for (var i = 0; i < settingsTabs.length; i++) {
    settingsTabs[i].addEventListener('click', function (e) {
      e.preventDefault();
      var tab = e.currentTarget;
      var tabId = tab.getAttribute('data-tab-id');
      if (!tabId) return;

      location.hash = tabId;
      window.scrollTo({ top: 0 });

      for (var j = 0; j < settingsTabs.length; j++) {
        settingsTabs[j].classList.remove('nav-tab-active');
        settingsTabs[j].removeAttribute('aria-current');
      }

      tab.classList.add('nav-tab-active');
      tab.setAttribute('aria-current', 'true');

      clearSettingsSearch();
      showTab(tabId);
    });
  }

  // Activate tab from URL hash on load
  var activeHash = location.hash.replace('#', '');
  if (activeHash) {
    var hashTab = document.querySelector('[data-tab-id="' + activeHash + '"]');
    if (hashTab) hashTab.click();
  }

  // ── 2. SEARCH / FILTER ───────────────────────────

  function getSearchText(row) {
    return row.textContent.replace(/\s+/g, ' ').trim().toLowerCase();
  }

  function applySettingsSearch() {
    var searchQuery = settingsTabFilter ? settingsTabFilter.value.trim().toLowerCase() : '';
    var hasAnyVisibleRow = false;

    form.classList.toggle('searching-all-settings', !!searchQuery);

    if (settingsSearchClear) {
      settingsSearchClear.hidden = !searchQuery;
    }

    for (var i = 0; i < settingsFormTables.length; i++) {
      var table = settingsFormTables[i];
      var rows = table.querySelectorAll('tbody > tr');
      var rowMatches = [];

      // 1. Compute raw visibility per row: search-text match AND not
      //    internally hidden by a conditional dependency.
      for (var j = 0; j < rows.length; j++) {
        var row = rows[j];
        var textMatch = true;
        if (searchQuery) {
          textMatch = getSearchText(row).indexOf(searchQuery) !== -1;
        }
        var depHidden = row.classList.contains('ss-dependency-hidden');
        rowMatches.push(textMatch && !depHidden);
      }

      // 2. Section heading rows are context, not matches of their own:
      //    keep a heading visible only if at least one of its member field
      //    rows is visible. Walk backwards so we know the members' state.
      var sectionHasVisibleMember = false;
      for (var j = rows.length - 1; j >= 0; j--) {
        if (rows[j].classList.contains('ss-settings-section-row')) {
          rowMatches[j] = rowMatches[j] && sectionHasVisibleMember;
          sectionHasVisibleMember = false;
        } else if (rowMatches[j]) {
          sectionHasVisibleMember = true;
        }
      }

      // 3. Apply row visibility and decide the table's overall match state.
      var tableHasMatch = false;
      for (var j = 0; j < rows.length; j++) {
        rows[j].hidden = !rowMatches[j];
        if (rowMatches[j]) tableHasMatch = true;
      }

      table.classList.toggle('has-search-match', tableHasMatch);
      // Hide the whole table when searching left it with no visible rows,
      // even if it is the currently active tab.
      table.classList.toggle('ss-table-hidden', !!searchQuery && !tableHasMatch);

      if (tableHasMatch) hasAnyVisibleRow = true;
    }

    if (settingsSearchNoResults) {
      settingsSearchNoResults.hidden = !searchQuery || hasAnyVisibleRow;
    }
  }

  if (settingsTabFilter) {
    settingsTabFilter.addEventListener('input', applySettingsSearch);
  }

  if (settingsSearchClear) {
    settingsSearchClear.addEventListener('click', function () {
      if (settingsTabFilter) {
        settingsTabFilter.value = '';
        settingsTabFilter.focus();
      }
      clearSettingsSearch();
      applySettingsSearch();
    });
  }

  // Ctrl/Cmd+K to focus search
  document.addEventListener('keydown', function (e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
      e.preventDefault();
      if (settingsTabFilter) settingsTabFilter.focus();
    }
  });

  // ── 3. DEPENDENCY VISIBILITY ─────────────────────

  function resolveDependencyTarget(selector) {
    if (!selector) return null;
    // Strip leading # if present
    var id = selector.replace(/^#/, '');
    return document.getElementById(id) || form.querySelector('[name="' + formId + '[' + id + ']"]') || form.querySelector('[name="' + id + '"]');
  }

  function dependencyMatches(dependency, target) {
    if (!target) return false;

    var expectedValue = dependency.getAttribute('data-settings-show-value');
    var matches = false;

    if (expectedValue !== null) {
      if (expectedValue === 'not-empty') {
        matches = !!target.value;
      } else {
        matches = target.value === expectedValue;
      }
    } else if (target.type === 'checkbox' || target.type === 'radio') {
      matches = target.checked;
    } else if (target.tagName === 'SELECT') {
      matches = !!target.value;
    } else {
      matches = !!target.value;
    }

    if (dependency.hasAttribute('data-settings-show-if-not')) {
      matches = !matches;
    }

    return matches;
  }

  function syncSettingsVisibilityDependencies() {
    var showIfDeps = form.querySelectorAll('[data-settings-show-if]');
    var showIfNotDeps = form.querySelectorAll('[data-settings-show-if-not]');

    var allDeps = [];
    for (var i = 0; i < showIfDeps.length; i++) allDeps.push(showIfDeps[i]);
    for (var i = 0; i < showIfNotDeps.length; i++) allDeps.push(showIfNotDeps[i]);

    for (var i = 0; i < allDeps.length; i++) {
      var dep = allDeps[i];
      var selector = dep.getAttribute('data-settings-show-if') || dep.getAttribute('data-settings-show-if-not');
      var target = resolveDependencyTarget(selector);
      var matches = dependencyMatches(dep, target);

      if (matches) {
        dep.classList.remove('ss-dependency-hidden');
        dep.removeAttribute('hidden');
        dep.removeAttribute('aria-hidden');
      } else {
        dep.classList.add('ss-dependency-hidden');
        dep.setAttribute('hidden', '');
        dep.setAttribute('aria-hidden', 'true');
      }

      // Disable form controls when hidden
      if (dep.hasAttribute('data-settings-disable-when-hidden')) {
        var controls = dep.querySelectorAll('input, select, textarea, button');
        for (var j = 0; j < controls.length; j++) {
          controls[j].disabled = !matches;
        }
      }
    }
  }

  // Listen for changes on all form controls
  form.addEventListener('input', syncSettingsVisibilityDependencies);
  form.addEventListener('change', syncSettingsVisibilityDependencies);

  // Initial sync
  syncSettingsVisibilityDependencies();

  // ── 4. UNSAVED CHANGES DETECTION ─────────────────

  function hasUnsavedChanges() {
    try {
      return new URLSearchParams(new FormData(form)).toString() !== initialFormData;
    } catch (e) {
      return false;
    }
  }

  function updateUnsavedIndicator() {
    var unsaved = hasUnsavedChanges();
    if (submitWrapper) {
      submitWrapper.classList.toggle('has-unsaved-settings', unsaved);
      submitWrapper.classList.remove('has-saved-settings');
    }
    var msg = form.querySelector('.wp-polyfill-settings-unsaved-message');
    if (msg) {
      msg.hidden = !unsaved;
    }
  }

  form.addEventListener('input', updateUnsavedIndicator);
  form.addEventListener('change', updateUnsavedIndicator);

  // ── 5. AJAX SAVE / RESET ─────────────────────────

  function serializeForm() {
    var data = new FormData(form);
    var result = {};
    data.forEach(function (value, key) {
      // Handle array values like key[]
      if (key.endsWith('[]')) {
        var arrayKey = key.slice(0, -2);
        if (!result[arrayKey]) result[arrayKey] = [];
        result[arrayKey].push(value);
      } else {
        result[key] = value;
      }
    });
    return result;
  }

  function setSaveStatus(status) {
    if (!submitWrapper) return;
    submitWrapper.classList.remove('has-unsaved-settings', 'has-saved-settings');

    if (status === 'saving') {
      if (spinner) {
        spinner.classList.add('is-active');
        spinner.style.display = 'inline-block';
      }
      submitWrapper.querySelectorAll('button').forEach(function (b) { b.disabled = true; });
    } else if (status === 'saved') {
      submitWrapper.classList.add('has-saved-settings');
      var msg = form.querySelector('.wp-polyfill-settings-unsaved-message');
      if (msg) msg.hidden = true;
      // Refresh initial data
      try {
        initialFormData = new URLSearchParams(new FormData(form)).toString();
      } catch (e) { /* ignore */ }
    } else if (status === 'error') {
      submitWrapper.classList.add('has-unsaved-settings');
    }

    if (status !== 'saving') {
      if (spinner) {
        spinner.classList.remove('is-active');
        spinner.style.display = 'none';
      }
      submitWrapper.querySelectorAll('button').forEach(function (b) { b.disabled = false; });
    }
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    saveSettings();
  });

  if (resetButton) {
    resetButton.addEventListener('click', function () {
      if (!confirm('Are you sure you want to reset all settings to their defaults?')) return;
      resetSettings();
    });
  }

  function saveSettings() {
    if (!config.ajaxUrl) {
      form.submit();
      return;
    }

    setSaveStatus('saving');

    var payload = new URLSearchParams();
    payload.append('action', config.saveAction || 'shared_settings_save');
    payload.append('nonce', config.nonce || '');

    // Send raw form data as JSON string
    var formData = {};
    var data = new FormData(form);
    data.forEach(function (value, key) {
      if (key.endsWith('[]')) {
        var arrayKey = key.slice(0, -2);
        if (!formData[arrayKey]) formData[arrayKey] = [];
        formData[arrayKey].push(value);
      } else {
        formData[key] = value;
      }
    });
    payload.append('settings', JSON.stringify(formData));

    fetch(config.ajaxUrl, {
      method: 'POST',
      body: payload,
      credentials: 'same-origin',
    })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data.success) {
          setSaveStatus('saved');
        } else {
          setSaveStatus('error');
          alert(data.data?.message || 'Save failed.');
        }
      })
      .catch(function () {
        setSaveStatus('error');
        alert('Network error while saving settings.');
      });
  }

  function resetSettings() {
    if (!config.ajaxUrl) return;

    setSaveStatus('saving');

    var payload = new URLSearchParams();
    payload.append('action', config.resetAction || 'shared_settings_reset');
    payload.append('nonce', config.nonce || '');

    fetch(config.ajaxUrl, {
      method: 'POST',
      body: payload,
      credentials: 'same-origin',
    })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data.success) {
          // Reload to reflect defaults
          location.reload();
        } else {
          setSaveStatus('error');
          alert(data.data?.message || 'Reset failed.');
        }
      })
      .catch(function () {
        setSaveStatus('error');
        alert('Network error while resetting settings.');
      });
  }

  // ── 6. MEDIA PICKER ──────────────────────────────

  var mediaButtons = form.querySelectorAll('.wp-polyfill-pick-media');
  for (var i = 0; i < mediaButtons.length; i++) {
    mediaButtons[i].addEventListener('click', function (e) {
      e.preventDefault();
      var targetId = e.currentTarget.getAttribute('data-target');
      var targetInput = document.getElementById(targetId);
      if (!targetInput) return;

      if (typeof wp !== 'undefined' && wp.media) {
        var frame = wp.media({
          title: 'Select Media',
          button: { text: 'Use this' },
          multiple: false,
        });

        frame.on('select', function () {
          var attachment = frame.state().get('selection').first().toJSON();
          targetInput.value = attachment.id;
          updateMediaPreview(targetInput, attachment);
        });

        frame.open();
      } else {
        // Fallback: prompt for attachment ID
        var id = prompt('Enter attachment ID:');
        if (id) {
          targetInput.value = id;
          updateMediaPreview(targetInput, { id: id });
        }
      }
    });
  }

  function updateMediaPreview(input, attachment) {
    var container = input.parentElement.querySelector('.wp-polyfill-media-preview');
    if (!container) return;

    if (attachment.sizes && attachment.sizes.thumbnail) {
      container.innerHTML = '<img src="' + attachment.sizes.thumbnail.url + '" style="max-width:200px;height:auto;display:block;margin:6px 0;">';
    } else if (attachment.url) {
      container.innerHTML = '<a href="' + attachment.url + '" target="_blank" rel="noopener">Attachment #' + attachment.id + '</a>';
    } else {
      container.innerHTML = '<span class="description">Attachment #' + attachment.id + '</span>';
    }
  }

  // ── 7. URL HASH DEEP LINK ────────────────────────

  var requestedSection = new URLSearchParams(location.search).get('settings-section');
  if (requestedSection) {
    var normalized = requestedSection.replace(/[^a-z0-9_-]/gi, '');
    var section = document.getElementById(formId + '-section-' + normalized);
    if (section) {
      window.requestAnimationFrame(function () {
        section.scrollIntoView({ block: 'start' });
      });
    }
  }

})();
