function scmAdminInit() {
  const editor = document.getElementById('schema_json');
  const preview = document.getElementById('scm-json-preview');
  const status = document.getElementById('scm-json-status');
  const btn = document.getElementById('scm-validate-json');
  const targetType = document.getElementById('target_type');
  const targetValueRow = document.querySelector('.scm-target-value-row');
  const targetValue = document.getElementById('target_value');
  const targetHelp = document.getElementById('scm-target-help');
  const mode = document.getElementById('mode');
  const replacedTypesRow = document.querySelector('.scm-replaced-types-row');
  const modeHelp = document.getElementById('scm-mode-help');
  const summaryAioseo = document.getElementById('scm-summary-aioseo');
  const summaryReplacements = document.getElementById('scm-summary-replacements');
  const summaryOutput = document.getElementById('scm-summary-output');

  const modeDescriptions = {
    aioseo_only: 'Uses only the schema graph from AIOSEO.',
    aioseo_plus_custom: 'Keeps the AIOSEO graph and adds your custom nodes.',
    custom_override_selected: 'Replaces selected types and rewires key references when possible.',
    custom_only: 'Disables AIOSEO on this target and outputs only your custom graph.'
  };

  function normalizeForPreview(parsed) {
    let graph = [];
    if (Array.isArray(parsed)) {
      graph = parsed;
    } else if (parsed && typeof parsed === 'object' && Array.isArray(parsed['@graph'])) {
      graph = parsed['@graph'];
    } else if (parsed && typeof parsed === 'object') {
      graph = [parsed];
    }
    return {
      '@context': 'https://schema.org',
      '@graph': graph
    };
  }

  function refreshPreview() {
    if (!editor || !preview) return;
    try {
      const parsed = JSON.parse(editor.value);
      preview.textContent = JSON.stringify(normalizeForPreview(parsed), null, 2);
    } catch (err) {
      preview.textContent = editor.value;
    }
  }

  function validateJson() {
    if (!editor || !status) return;
    try {
      const parsed = JSON.parse(editor.value);
      status.textContent = 'Valid JSON';
      status.style.color = '#008a20';
      preview.textContent = JSON.stringify(normalizeForPreview(parsed), null, 2);
    } catch (err) {
      status.textContent = err.message;
      status.style.color = '#b32d2e';
    }
  }

  var targetHelpTexts = {
    exact_slug:        'Use the exact slug/path without the domain, for example: dar-de-baja-un-coche or info/talleres/alicante',
    exact_url:         'Use a full URL or a relative path, for example: https://example.com/page/ or /info/talleres/alicante/',
    post_type:         'Use the post type slug, for example: post, page, talleres',
    post_type_archive: 'Use the post type slug for the archive, for example: post or talleres',
    category:          'Use the category slug, for example: noticias',
    tag:               'Use the tag slug, for example: seo',
    taxonomy_term:     'Use taxonomy:term-slug, for example: category:noticias or genero:accion',
    author:            'Use the author slug, for example: javier'
  };

  function updateTargetUi() {
    if (!targetType || !targetValueRow) return;
    const value = targetType.value;

    if (value === 'home' || value === 'front_page') {
      if (targetValue) targetValue.value = '';
      if (targetValue) targetValue.setAttribute('disabled', 'disabled');
      if (targetHelp) targetHelp.textContent = 'This target does not require a value.';
      return;
    }

    if (targetValue) targetValue.removeAttribute('disabled');
    if (targetHelp) targetHelp.textContent = targetHelpTexts[value] || '';
  }

  function updateModeUi() {
    if (!mode) return;
    const currentMode = mode.value;
    if (replacedTypesRow) {
      replacedTypesRow.style.display = currentMode === 'custom_override_selected' ? '' : 'none';
    }
    if (modeHelp) {
      modeHelp.textContent = modeDescriptions[currentMode] || '';
    }
    if (summaryAioseo) {
      summaryAioseo.textContent = currentMode === 'custom_only' ? 'Disabled' : (currentMode === 'aioseo_only' ? 'Active only' : 'Active');
    }
    if (summaryOutput) {
      summaryOutput.textContent = currentMode === 'custom_only' ? 'Only custom output' : (currentMode === 'aioseo_only' ? 'Only AIOSEO output' : (currentMode === 'custom_override_selected' ? 'AIOSEO filtered + custom' : 'AIOSEO + custom merge'));
    }
    if (summaryReplacements) {
      const checked = Array.from(document.querySelectorAll('input[name="replaced_types[]"]:checked')).map((el) => el.value);
      summaryReplacements.textContent = checked.length ? checked.join(', ') : '—';
    }
  }

  if (editor) {
    refreshPreview();
    editor.addEventListener('input', refreshPreview);
  }
  if (btn) {
    btn.addEventListener('click', validateJson);
  }
  if (targetType) {
    updateTargetUi();
    targetType.addEventListener('change', updateTargetUi);
  }
  if (mode) {
    updateModeUi();
    mode.addEventListener('change', updateModeUi);
  }
  document.querySelectorAll('input[name="replaced_types[]"]').forEach((checkbox) => checkbox.addEventListener('change', updateModeUi));

  // ── Simulated preview ────────────────────────────────────────────────────
  var simBtn    = document.getElementById('scm-sim-preview-btn');
  var simSelect = document.getElementById('scm-sim-post-select');
  var simOutput = document.getElementById('scm-sim-preview-output');
  var simStatus = document.getElementById('scm-sim-preview-status');
  var simSearch = document.getElementById('scm-sim-post-search');
  var simMatch  = document.getElementById('scm-sim-match-status');

  // ── Preselect ─────────────────────────────────────────────────────────
  if (simSelect) {
    var preselect = simSelect.getAttribute('data-preselect');
    if (preselect && preselect !== '0') {
      simSelect.value = preselect;
    }
  }

  // ── Match status ──────────────────────────────────────────────────────
  var archiveTypes = ['post_type_archive', 'category', 'tag', 'taxonomy_term', 'author', 'home', 'front_page'];

  function updateMatchStatus() {
    if (!simSelect || !simMatch) return;
    var opt = simSelect.options[simSelect.selectedIndex];
    if (!opt || !opt.value) {
      simMatch.textContent = '';
      simMatch.className = 'scm-sim-match-status';
      return;
    }
    var targetType = simSelect.getAttribute('data-rule-target-type') || '';
    var isArchive  = archiveTypes.indexOf(targetType) !== -1;
    var matches    = opt.getAttribute('data-matches');

    if (isArchive) {
      simMatch.textContent = '\u26a0 Archive rule \u2014 preview uses a singular post context.';
      simMatch.className = 'scm-sim-match-status scm-match-archive';
    } else if (matches === '1') {
      simMatch.textContent = '\u2714 This post matches the rule.';
      simMatch.className = 'scm-sim-match-status scm-match-yes';
    } else {
      simMatch.textContent = '\u26a0 This post does NOT match the rule.';
      simMatch.className = 'scm-sim-match-status scm-match-no';
    }
  }

  if (simSelect) {
    updateMatchStatus();
    simSelect.addEventListener('change', updateMatchStatus);
  }

  // ── Search / filter ───────────────────────────────────────────────────
  // Snapshot all real options (non-empty value) so we can rebuild the list.
  var simAllOptions = [];
  if (simSearch && simSelect) {
    Array.from(simSelect.options).forEach(function (opt) {
      if (opt.value) {
        simAllOptions.push({
          el:   opt,
          text: [
            (opt.getAttribute('data-title') || ''),
            (opt.getAttribute('data-slug')  || ''),
            opt.value
          ].join(' ').toLowerCase()
        });
      }
    });

    var searchTimer = null;
    simSearch.addEventListener('input', function () {
      clearTimeout(searchTimer);
      searchTimer = setTimeout(function () {
        var query = simSearch.value.toLowerCase().trim();

        // Remove all options except placeholder ("— Select a post —").
        while (simSelect.options.length > 1) {
          simSelect.remove(1);
        }

        // Re-append matching options.
        simAllOptions.forEach(function (item) {
          if (!query || item.text.indexOf(query) !== -1) {
            simSelect.appendChild(item.el);
          }
        });

        // Reset selection to placeholder if current value disappeared.
        if (simSelect.value && !simSelect.querySelector('option[value="' + simSelect.value + '"]')) {
          simSelect.value = '';
        }
        updateMatchStatus();
      }, 180);
    });
  }

  // ── Generate preview ──────────────────────────────────────────────────
  if (simBtn && simSelect && simOutput) {
    simBtn.addEventListener('click', function () {
      var postId = simSelect.value;
      var ruleId = simBtn.getAttribute('data-rule-id');

      if (!postId) {
        if (simStatus) simStatus.textContent = 'Select a post first.';
        return;
      }

      var selectedOpt = simSelect.options[simSelect.selectedIndex];
      var postMatches = selectedOpt ? selectedOpt.getAttribute('data-matches') === '1' : false;
      var targetType  = simSelect.getAttribute('data-rule-target-type') || '';
      var isArchive   = archiveTypes.indexOf(targetType) !== -1;

      simBtn.disabled = true;
      simOutput.hidden = true;
      if (simStatus) simStatus.textContent = 'Generating\u2026';

      var formData = new FormData();
      formData.append('action',  'scm_generate_preview');
      formData.append('nonce',   (typeof scmData !== 'undefined' ? scmData.nonce : ''));
      formData.append('rule_id', ruleId);
      formData.append('post_id', postId);

      fetch(typeof scmData !== 'undefined' ? scmData.ajaxurl : ajaxurl, {
        method: 'POST',
        body: formData
      })
        .then(function (r) {
          return r.text().then(function (text) {
            try {
              return JSON.parse(text);
            } catch (e) {
              throw new Error('Invalid server response');
            }
          });
        })
        .then(function (data) {
          simBtn.disabled = false;
          if (data.success) {
            simOutput.textContent = data.data.json;
            simOutput.hidden = false;
            if (simStatus) {
              if (data.data.is_empty) {
                if (!isArchive && !postMatches) {
                  simStatus.textContent = 'This post does not match the current rule.';
                } else {
                  simStatus.textContent = 'Schema produced no valid nodes. Check required properties.';
                }
              } else {
                simStatus.textContent = '';
              }
            }
          } else {
            simOutput.hidden = true;
            var msg = (data.data && data.data.message) ? data.data.message : 'Unexpected error occurred.';
            if (simStatus) simStatus.textContent = 'Error: ' + msg;
          }
        })
        .catch(function (err) {
          simBtn.disabled = false;
          simOutput.hidden = true;
          if (simStatus) simStatus.textContent = 'Error: ' + err.message;
        });
    });
  }
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', scmAdminInit);
} else {
  scmAdminInit();
}

// ── Final Graph Preview ────────────────────────────────────────────────────
(function scmPreviewInit() {
  var panel = document.getElementById('scm-final-preview');
  if (!panel) return;

  // Collapsible sections
  panel.querySelectorAll('.scm-collapsible-trigger').forEach(function (trigger) {
    trigger.addEventListener('click', function () {
      var expanded = this.getAttribute('aria-expanded') === 'true';
      this.setAttribute('aria-expanded', expanded ? 'false' : 'true');
      var body = this.nextElementSibling;
      if (body) body.classList.toggle('open', !expanded);
    });
  });

  // Copy JSON button
  var copyBtn = document.getElementById('scm-copy-json');
  var jsonViewer = document.getElementById('scm-json-viewer');
  if (copyBtn && jsonViewer && navigator.clipboard) {
    var labelCopy = copyBtn.textContent.trim();
    var labelCopied = copyBtn.getAttribute('data-scm-copied') || labelCopy;
    copyBtn.addEventListener('click', function () {
      navigator.clipboard.writeText(jsonViewer.textContent).then(function () {
        copyBtn.textContent = labelCopied;
        setTimeout(function () {
          copyBtn.textContent = labelCopy;
        }, 1500);
      });
    });
  }

  // Expand / Collapse JSON viewer
  var expandBtn = document.getElementById('scm-expand-json');
  if (expandBtn && jsonViewer) {
    var labelExpand = expandBtn.textContent.trim();
    var labelCollapse = expandBtn.getAttribute('data-scm-collapse') || labelExpand;
    expandBtn.addEventListener('click', function () {
      var isExpanded = jsonViewer.classList.toggle('expanded');
      expandBtn.textContent = isExpanded ? labelCollapse : labelExpand;
    });
  }
})();
