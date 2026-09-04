/**
 * Shared Settings – vanilla HTML renderer.
 *
 * Produces the exact same `.ss-admin-wrapper` flat-table markup that the PHP
 * `Settings_Page`/`Form_Fields` classes render in WordPress, so that a static
 * labs page mirrors the real admin settings page byte-for-byte (same classes,
 * same structure, same ids/names). The screen reader section heading, field
 * rows, checkbox groups, media field and panel rows all match 1:1.
 *
 * The `shared-settings.js` controller then enhances the rendered DOM (tabs,
 * search, dependencies, media picker, save/reset) exactly as it does in WP.
 *
 * Schema shape:
 *   renderSharedSettingsPage({
 *     pageTitle, formId, optionName,
 *     formAction: string,            // labs-only; WP uses 'options.php'
 *     tabs: [{
 *       id, label,
 *       sections: [{
 *         id, title, description,
 *         fields: [{
 *           id, label, type, description,
 *           placeholder, rows, min, max,
 *           options, optionsLayout, value, html
 *         }]
 *       }]
 *     }]
 *   })
 *
 * @since 0.2.0
 */
(function (global) {
  'use strict';

  function escHtml(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function escAttr(value) {
    return escHtml(value);
  }

  // Field names follow the PHP convention: optionName[fieldId].
  function fieldName(optionName, field) {
    if (field.type === 'checkbox_group') {
      return optionName + '[' + field.id + '][]';
    }
    if (field.type === 'media') {
      return optionName + '[' + field.id + ']';
    }
    return optionName + '[' + field.id + ']';
  }

  function fieldValue(field) {
    return field.value !== undefined ? field.value : (field.default !== undefined ? field.default : '');
  }

  function renderControl(field, optionName, formId) {
    var id = formId + '-' + field.id;

    switch (field.type) {
      case 'textarea': {
        var taRows = field.rows || 4;
        return '<textarea id="' + escAttr(id) + '" name="' + escAttr(fieldName(optionName, field)) + '" rows="' + (+taRows) + '" class="large-text" placeholder="' + escAttr(field.placeholder || '') + '">' + escHtml(fieldValue(field)) + '</textarea>';
      }

      case 'number': {
        var min = field.min !== undefined ? field.min : 0;
        var max = field.max !== undefined ? field.max : 10000;
        return '<input type="number" id="' + escAttr(id) + '" name="' + escAttr(fieldName(optionName, field)) + '" value="' + (+fieldValue(field)) + '" min="' + (+min) + '" max="' + (+max) + '" class="small-text" />';
      }

      case 'checkbox': {
        var checked = fieldValue(field) ? ' checked="checked"' : '';
        return '<div class="ss-setting-wrapper">' +
          '<input type="checkbox" id="' + escAttr(id) + '" name="' + escAttr(fieldName(optionName, field)) + '" value="1"' + checked + ' />' +
          '<label for="' + escAttr(id) + '">' + escHtml(field.label) + '</label>' +
          '</div>';
      }

      case 'checkbox_group': {
        var opts = field.options || {};
        var values = Array.isArray(fieldValue(field)) ? fieldValue(field) : [];
        var layoutClass = (field.optionsLayout === 'horizontal') ? ' ss-checkbox-group--horizontal' : '';
        var out = '<div class="ss-checkbox-group' + layoutClass + '">';
        Object.keys(opts).forEach(function (optValue) {
          var label = opts[optValue];
          var optId = id + '-' + String(optValue).replace(/[^a-z0-9-_]/gi, '');
          var isChecked = values.indexOf(String(optValue).toLowerCase()) !== -1 || values.indexOf(optValue) !== -1;
          out += '<div class="ss-setting-wrapper">' +
            '<input type="checkbox" id="' + escAttr(optId) + '" name="' + escAttr(fieldName(optionName, field)) + '" value="' + escAttr(optValue) + '"' + (isChecked ? ' checked' : '') + ' />' +
            '<label for="' + escAttr(optId) + '">' + escHtml(label) + '</label>' +
            '</div>';
        });
        out += '</div>';
        return out;
      }

      case 'select': {
        var sOpts = field.options || {};
        var sVal = String(fieldValue(field));
        var sOut = '<select id="' + escAttr(id) + '" name="' + escAttr(fieldName(optionName, field)) + '" class="regular-text">';
        Object.keys(sOpts).forEach(function (optValue) {
          sOut += '<option value="' + escAttr(optValue) + '"' + (sVal === String(optValue) ? ' selected' : '') + '>' + escHtml(sOpts[optValue]) + '</option>';
        });
        sOut += '</select>';
        return sOut;
      }

      case 'media': {
        var mediaId = (+fieldValue(field)) || 0;
        return '<input type="hidden" id="' + escAttr(id) + '" name="' + escAttr(fieldName(optionName, field)) + '" value="' + mediaId + '" />' +
          '<div class="ss-media-preview" data-target="' + escAttr(id) + '"></div>' +
          '<button type="button" class="button ss-pick-media" data-target="' + escAttr(id) + '">Select media</button>';
      }

      case 'html': {
        return '<tr class="ss-panel-row"><td colspan="2"><div class="ss-settings-panel">' + (field.html || '') + '</div></td></tr>';
      }

      case 'text':
      default: {
        return '<input type="text" id="' + escAttr(id) + '" name="' + escAttr(fieldName(optionName, field)) + '" value="' + escAttr(fieldValue(field)) + '" class="regular-text" placeholder="' + escAttr(field.placeholder || '') + '" />';
      }
    }
  }

  function renderFieldRow(field, optionName, formId) {
    // html and heading type are full-width panel rows emitted without a th/td pair.
    if (field.type === 'html' || field.type === 'heading') {
      return renderControl(field, optionName, formId);
    }

    var out = '<tr>';
    out += '<th>';
    out += '<label for="' + escAttr(formId + '-' + field.id) + '">' + escHtml(field.label) + '</label>';
    if (field.description) {
      out += '<p class="description">' + escHtml(field.description) + '</p>';
    }
    out += '</th>';
    out += '<td>' + renderControl(field, optionName, formId) + '</td>';
    out += '</tr>';
    return out;
  }

  function renderSection(section, optionName, formId) {
    var out = '';
    if (section.title) {
      out += '<tr class="ss-settings-section-row">';
      out += '<th class="screen-reader-text" colspan="2" aria-label="' + escAttr(section.title) + '"><span>' + escHtml(section.title) + '</span></th></tr>';
    }
    (section.fields || []).forEach(function (field) {
      out += renderFieldRow(field, optionName, formId);
    });
    return out;
  }

  /**
   * Render a full settings page into an HTML string.
   * Returns markup for the `.ss-admin-wrapper` block (header, search, tabs,
   * form, sticky submit bar) — the same structure WordPress renders.
   */
  function renderSharedSettingsPage(def) {
    var formId = def.formId || 'shared-settings';
    var optionName = def.optionName || 'shared_settings';
    var formAction = def.formAction || '#';
    var tabs = def.tabs || [];
    var out = '';

    // Header
    out += '<div class="ss-settings-header"><div class="ss-settings-heading"><div class="ss-settings-title-row">';
    out += '<h1 class="title">' + escHtml(def.pageTitle || 'Settings') + '</h1>';
    out += '</div></div></div>';

    // Search
    out += '<div class="ss-settings-tab-search">';
    out += '<label class="screen-reader-text" for="ss-settings-tab-filter">Search settings</label>';
    out += '<span class="dashicons dashicons-search" aria-hidden="true"></span>';
    out += '<input type="search" id="ss-settings-tab-filter" placeholder="Search settings..." autocomplete="off">';
    out += '<button type="button" class="ss-settings-search-clear" aria-label="Clear search" hidden><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>';
    out += '</div>';
    out += '<p class="ss-settings-search-no-results" aria-live="polite" hidden>No settings found.</p>';

    // Tabs
    out += '<ul id="ss-settings-tabs-wrapper" class="nav-tab-wrapper">';
    tabs.forEach(function (tab, i) {
      var active = i === 0 ? ' nav-tab-active' : '';
      var aria = i === 0 ? ' aria-current="true"' : '';
      out += '<li><a href="#' + escAttr(tab.id) + '" class="nav-tab' + active + '"' + aria + ' data-tab-id="' + escAttr(tab.id) + '">' + escHtml(tab.label) + '</a></li>';
    });
    out += '</ul>';

    // Form + tables
    out += '<form id="' + escAttr(formId) + '" class="ss-admin-wrapper" method="post" action="' + escAttr(formAction) + '" autocomplete="off">';
    tabs.forEach(function (tab, i) {
      var active = i === 0 ? ' active' : '';
      out += '<table id="' + escAttr(tab.id) + '" class="ss-settings-table' + active + '" role="presentation"><tbody>';
      (tab.sections || []).forEach(function (section) {
        out += renderSection(section, optionName, formId);
      });
      out += '</tbody></table>';
    });

    // Sticky submit bar
    out += '<div class="ss-submit-wrapper">';
    out += '<button type="submit" name="save" class="button button-primary button-large">Save Settings</button>';
    out += '<p class="ss-settings-unsaved-message" role="status" aria-live="polite" hidden>Unsaved changes</p>';
    out += '<button type="button" name="reset" class="button button-secondary button-large ss-reset-button">Reset Settings</button>';
    out += '</div>';
    out += '<span class="spinner saving"></span>';
    out += '</form>';

    return out;
  }

  global.renderSharedSettingsPage = renderSharedSettingsPage;
})(typeof window !== 'undefined' ? window : globalThis);
