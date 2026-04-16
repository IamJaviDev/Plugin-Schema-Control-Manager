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
    aioseo_only: 'Usa únicamente el grafo de schema de AIOSEO.',
    aioseo_plus_custom: 'Mantiene el grafo de AIOSEO y añade tus nodos personalizados.',
    custom_override_selected: 'Reemplaza los tipos seleccionados y reconecta referencias clave cuando es posible.',
    custom_only: 'Desactiva AIOSEO en este objetivo y genera únicamente tu grafo personalizado.'
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
      status.textContent = 'JSON válido';
      status.style.color = '#008a20';
      preview.textContent = JSON.stringify(normalizeForPreview(parsed), null, 2);
    } catch (err) {
      status.textContent = err.message;
      status.style.color = '#b32d2e';
    }
  }

  var targetHelpTexts = {
    exact_slug:        'Usa el slug o ruta exacta sin el dominio, por ejemplo: dar-de-baja-un-coche o info/talleres/alicante',
    exact_url:         'Usa una URL completa o una ruta relativa, por ejemplo: https://example.com/pagina/ o /info/talleres/alicante/',
    post_type:         'Usa el slug del tipo de entrada, por ejemplo: post, page, talleres',
    post_type_archive: 'Usa el slug del tipo de entrada para el archivo, por ejemplo: post o talleres',
    category:          'Usa el slug de la categoría, por ejemplo: noticias',
    tag:               'Usa el slug de la etiqueta, por ejemplo: seo',
    taxonomy_term:     'Usa taxonomía:slug-del-término, por ejemplo: category:noticias o genero:accion',
    author:            'Usa el slug del autor, por ejemplo: javier'
  };

  function updateTargetUi() {
    if (!targetType || !targetValueRow) return;
    const value = targetType.value;

    if (value === 'home' || value === 'front_page') {
      if (targetValue) targetValue.value = '';
      if (targetValue) targetValue.setAttribute('disabled', 'disabled');
      if (targetHelp) targetHelp.textContent = 'Este objetivo no requiere un valor.';
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
      summaryAioseo.textContent = currentMode === 'custom_only' ? 'Desactivado' : (currentMode === 'aioseo_only' ? 'Solo activo' : 'Activo');
    }
    if (summaryOutput) {
      summaryOutput.textContent = currentMode === 'custom_only' ? 'Solo salida personalizada' : (currentMode === 'aioseo_only' ? 'Solo salida AIOSEO' : (currentMode === 'custom_override_selected' ? 'AIOSEO filtrado + personalizado' : 'Combinación AIOSEO + personalizado'));
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
      simMatch.textContent = '\u26a0 Regla de archivo \u2014 la vista previa usa un contexto de entrada singular.';
      simMatch.className = 'scm-sim-match-status scm-match-archive';
    } else if (matches === '1') {
      simMatch.textContent = '\u2714 Esta entrada coincide con la regla.';
      simMatch.className = 'scm-sim-match-status scm-match-yes';
    } else {
      simMatch.textContent = '\u26a0 Esta entrada NO coincide con la regla.';
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

        // Remove all options except placeholder ("— Selecciona una entrada —").
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
        if (simStatus) simStatus.textContent = 'Selecciona una entrada primero.';
        return;
      }

      var selectedOpt = simSelect.options[simSelect.selectedIndex];
      var postMatches = selectedOpt ? selectedOpt.getAttribute('data-matches') === '1' : false;
      var targetType  = simSelect.getAttribute('data-rule-target-type') || '';
      var isArchive   = archiveTypes.indexOf(targetType) !== -1;

      simBtn.disabled = true;
      simOutput.hidden = true;
      if (simStatus) simStatus.textContent = 'Generando\u2026';

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
              throw new Error('Respuesta del servidor inválida');
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
                  simStatus.textContent = 'Esta entrada no coincide con la regla actual.';
                } else {
                  simStatus.textContent = 'El schema no produjo nodos válidos. Verifica las propiedades requeridas.';
                }
              } else {
                simStatus.textContent = '';
              }
            }
          } else {
            simOutput.hidden = true;
            var msg = (data.data && data.data.message) ? data.data.message : 'Ocurrió un error inesperado.';
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
