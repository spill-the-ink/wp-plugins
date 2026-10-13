/**
 * Shared Settings – vanilla JS controller.
 *
 * Powers tab switching, search/filter, conditional dependencies,
 * unsaved-changes detection, AJAX save/reset, and media picker.
 *
 * Expects `window.SharedSettingsConfig` (localized from PHP) and
 * `.wp-polyfill-admin-wrapper` DOM structure rendered by Settings_Page.
 *
 * @since   0.3.0
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
  var settingsTabFilter  = document.getElementById('wp-polyfill-settings-tab-filter');
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
        var depHidden = row.classList.contains('wp-polyfill-dependency-hidden');
        rowMatches.push(textMatch && !depHidden);
      }

      // 2. Section heading rows are context, not matches of their own:
      //    keep a heading visible only if at least one of its member field
      //    rows is visible. Walk backwards so we know the members' state.
      var sectionHasVisibleMember = false;
      for (var j = rows.length - 1; j >= 0; j--) {
        if (rows[j].classList.contains('wp-polyfill-settings-section-row')) {
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
      table.classList.toggle('wp-polyfill-table-hidden', !!searchQuery && !tableHasMatch);

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
        dep.classList.remove('wp-polyfill-dependency-hidden');
        dep.removeAttribute('hidden');
        dep.removeAttribute('aria-hidden');
      } else {
        dep.classList.add('wp-polyfill-dependency-hidden');
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

  // Parse a form field name into bracket segments, e.g.
  //   'noindex_authors'                → ['noindex_authors']
  //   'block_ai_bots[]'                → ['block_ai_bots'] (array)
  //   'post_type_noindex[product]'     → ['post_type_noindex', 'product']
  //   'wp_manifest_settings[noindex_authors]' → ['noindex_authors'] (optionName stripped)
  function parseFieldName(key) {
    var isArray = key.slice(-2) === '[]';
    var base = key.replace(/\[\]$/, '');

    // Strip the option-name prefix so the nested payload matches what
    // sanitize() expects (same shape as a native options.php POST).
    if (config.optionName && base.indexOf(config.optionName + '[') === 0) {
      base = base.slice(config.optionName.length + 1, -1);
    }

    var segments = base.split(/[\[\]]+/).filter(function (s) { return s !== ''; });
    return { path: segments, isArray: isArray };
  }

  // Assign value into a nested object along a path.
  function assignNested(result, path, value, isArray) {
    var curr = result;
    for (var i = 0; i < path.length - 1; i++) {
      var seg = path[i];
      if (curr[seg] == null || typeof curr[seg] !== 'object' || Array.isArray(curr[seg])) {
        curr[seg] = {};
      }
      curr = curr[seg];
    }
    var last = path[path.length - 1];
    if (isArray) {
      if (!Array.isArray(curr[last])) curr[last] = [];
      curr[last].push(value);
    } else {
      curr[last] = value;
    }
  }

  function serializeForm() {
    var data = new FormData(form);
    var result = {};
    data.forEach(function (value, key) {
      var parsed = parseFieldName(key);
      assignNested(result, parsed.path, value, parsed.isArray);
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

    // Send serialized form data as JSON string (nested, optionName stripped)
    payload.append('settings', JSON.stringify(serializeForm()));

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

  // Reusable (repeater rows are added after page load).
  function bindMediaPickers(scope) {
    var mediaButtons = scope.querySelectorAll('.wp-polyfill-pick-media');
    for (var i = 0; i < mediaButtons.length; i++) {
      var button = mediaButtons[i];
      if (button.getAttribute('data-bound') === '1') continue;
      button.setAttribute('data-bound', '1');

      button.addEventListener('click', function (e) {
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
  }

  bindMediaPickers(form);

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

  // ── 7. REPEATER ROWS + CONTEXT COMBOBOX ──────────

  // Fire the form-level 'input'/'change' listeners (unsaved detection,
  // dependency sync) after a repeater/combobox mutation.
  function notifyFormChanged() {
    var inputEvent  = new Event('input', { bubbles: true });
    var changeEvent = new Event('change', { bubbles: true });
    form.dispatchEvent(inputEvent);
    form.dispatchEvent(changeEvent);
  }

  // Rewrite a row's __INDEX__ tokens in names/ids to a concrete index.
  function renumberRow(row, index) {
    row.setAttribute('data-index', index);
    var token = '__INDEX__';
    var els = row.querySelectorAll('[name], [id], [data-name], [data-target]');
    for (var i = 0; i < els.length; i++) {
      var el = els[i];
      ['name', 'id', 'data-name', 'data-target'].forEach(function (attr) {
        var value = el.getAttribute(attr);
        if (value && value.indexOf(token) !== -1) {
          el.setAttribute(attr, value.split(token).join(String(index)));
        }
      });
    }
  }

  function renumberAll(repeater) {
    var rowsContainer = repeater.querySelector('[data-repeater-rows]');
    if (!rowsContainer) return;
    var rows = rowsContainer.querySelectorAll('[data-repeater-row]');
    for (var i = 0; i < rows.length; i++) {
      renumberRow(rows[i], i);
    }
  }

  function initRepeaterRow(row) {
    bindMediaPickers(row);

    var comboboxes = row.querySelectorAll('[data-context-combobox]');
    for (var i = 0; i < comboboxes.length; i++) {
      initContextCombobox(comboboxes[i]);
    }

    var removes = row.querySelectorAll('[data-repeater-remove]');
    for (var j = 0; j < removes.length; j++) {
      if (removes[j].getAttribute('data-bound') === '1') continue;
      removes[j].setAttribute('data-bound', '1');
      removes[j].addEventListener('click', function () {
        var rowEl = this.closest('[data-repeater-row]');
        var rowsContainer = rowEl.closest('[data-repeater-rows]');
        var repeater = rowsContainer.closest('[data-repeater]');
        rowEl.parentNode.removeChild(rowEl);
        renumberAll(repeater);
        notifyFormChanged();
      });
    }
  }

  function initRepeaters() {
    var repeaters = form.querySelectorAll('[data-repeater]');
    for (var i = 0; i < repeaters.length; i++) {
      var repeater = repeaters[i];
      var rowsContainer = repeater.querySelector('[data-repeater-rows]');
      var template = repeater.querySelector('template[data-repeater-template]');
      var addButton = repeater.querySelector('[data-repeater-add]');

      // Bind the rows the server already rendered.
      if (rowsContainer) {
        var rows = rowsContainer.querySelectorAll('[data-repeater-row]');
        for (var r = 0; r < rows.length; r++) {
          initRepeaterRow(rows[r]);
        }
      }

      if (!template || !addButton || addButton.getAttribute('data-bound') === '1') continue;
      addButton.setAttribute('data-bound', '1');

      addButton.addEventListener('click', function () {
        var existing = rowsContainer.querySelectorAll('[data-repeater-row]');
        var index = existing.length;
        var fragment = template.content.cloneNode(true);
        var newRow = fragment.querySelector('[data-repeater-row]');
        if (!newRow) return;
        renumberRow(newRow, index);
        initRepeaterRow(newRow);
        rowsContainer.appendChild(fragment);
        notifyFormChanged();
      });
    }
  }

  // Multi-select context combobox (chips + typed filter + grouped list).
  function initContextCombobox(combobox) {
    if (combobox.getAttribute('data-bound') === '1') return;
    combobox.setAttribute('data-bound', '1');

    var options = config.contextOptions || [];
    var valuesBox = combobox.querySelector('.wp-polyfill-combobox__values');
    var chipList = combobox.querySelector('.wp-polyfill-combobox__chip-list');
    var search = combobox.querySelector('.wp-polyfill-combobox__search');
    var menu = combobox.querySelector('.wp-polyfill-combobox__menu');

    if (!valuesBox || !chipList || !search || !menu) return;

    function selectedValues() {
      var out = [];
      var checks = valuesBox.querySelectorAll('input[type="checkbox"]');
      for (var i = 0; i < checks.length; i++) {
        out.push(checks[i].value);
      }
      return out;
    }

    function optionByValue(value) {
      for (var i = 0; i < options.length; i++) {
        if (options[i].value === value) return options[i];
      }
      return null;
    }

    function toggleValue(value, selected) {
      var checks = valuesBox.querySelectorAll('input[type="checkbox"]');
      var existing = null;
      for (var i = 0; i < checks.length; i++) {
        if (checks[i].value === value) { existing = checks[i]; break; }
      }

      if (selected && !existing) {
        existing = document.createElement('input');
        existing.type = 'checkbox';
        existing.value = value;
        existing.name = valuesBox.getAttribute('data-name') || '';
        existing.checked = true;
        valuesBox.appendChild(existing);
      } else if (existing) {
        if (selected) {
          existing.checked = true;
        } else {
          existing.parentNode.removeChild(existing);
        }
      }

      renderChips();
      notifyFormChanged();
    }

    function renderChips() {
      chipList.innerHTML = '';
      var values = selectedValues();

      for (var i = 0; i < values.length; i++) {
        var opt = optionByValue(values[i]);
        var labelText = opt ? opt.label : values[i];

        (function (value, label) {
          var li = document.createElement('li');
          li.className = 'wp-polyfill-combobox__chip';

          var labelEl = document.createElement('span');
          labelEl.className = 'wp-polyfill-combobox__chip-label';
          labelEl.textContent = label;

          var remove = document.createElement('button');
          remove.type = 'button';
          remove.className = 'wp-polyfill-combobox__chip-remove';
          remove.setAttribute('aria-label', 'Remove ' + label);
          remove.innerHTML = '&times;';
          remove.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            toggleValue(value, false);
            search.focus();
          });

          li.appendChild(labelEl);
          li.appendChild(remove);
          chipList.appendChild(li);
        })(values[i], labelText);
      }
    }

    function renderMenu() {
      menu.innerHTML = '';
      var query = search.value.trim().toLowerCase();
      var selected = selectedValues();
      var groups = [];
      var currentGroup = null;

      for (var i = 0; i < options.length; i++) {
        var opt = options[i];
        if (selected.indexOf(opt.value) !== -1) continue;
        if (query && String(opt.label).toLowerCase().indexOf(query) === -1) continue;

        if (opt.group !== currentGroup) {
          currentGroup = opt.group || '';
          groups.push({ name: currentGroup, items: [] });
        }
        groups[groups.length - 1].items.push(opt);
      }

      for (var g = 0; g < groups.length; g++) {
        var groupEl = document.createElement('div');
        groupEl.className = 'wp-polyfill-combobox__group';

        if (groups[g].name) {
          var groupLabel = document.createElement('span');
          groupLabel.className = 'wp-polyfill-combobox__group-label';
          groupLabel.textContent = groups[g].name;
          groupEl.appendChild(groupLabel);
        }

        for (var j = 0; j < groups[g].items.length; j++) {
          (function (opt) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'wp-polyfill-combobox__option';
            btn.textContent = opt.label;
            btn.addEventListener('click', function (e) {
              e.preventDefault();
              toggleValue(opt.value, true);
              search.value = '';
              renderMenu();
              search.focus();
            });
            groupEl.appendChild(btn);
          })(groups[g].items[j]);
        }

        menu.appendChild(groupEl);
      }

      if (!groups.length) {
        var empty = document.createElement('p');
        empty.className = 'wp-polyfill-combobox__empty';
        empty.textContent = 'No matching contexts.';
        menu.appendChild(empty);
      }

      menu.hidden = false;
    }

    search.addEventListener('click', function (e) {
      e.stopPropagation();
      renderMenu();
    });

    search.addEventListener('focus', renderMenu);
    search.addEventListener('input', renderMenu);

    search.addEventListener('keydown', function (e) {
      if (e.key === 'Backspace' && !search.value) {
        var values = selectedValues();
        if (values.length) toggleValue(values[values.length - 1], false);
      } else if (e.key === 'Escape') {
        menu.hidden = true;
      }
    });

    renderChips();
  }

  // Close any combobox menu when clicking elsewhere.
  document.addEventListener('click', function (e) {
    var menus = form.querySelectorAll('.wp-polyfill-combobox__menu');
    for (var i = 0; i < menus.length; i++) {
      var combobox = menus[i].closest('[data-context-combobox]');
      if (!combobox || !combobox.contains(e.target)) {
        menus[i].hidden = true;
      }
    }
  });

  initRepeaters();

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
