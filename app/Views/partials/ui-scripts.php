<?php

declare(strict_types=1);
?>
<script>
(function () {
  var menus = document.querySelectorAll('.guest-mobile-menu, .mobile-app-menu');

  function closeMenus(except) {
    menus.forEach(function (menu) {
      if (menu !== except) {
        menu.open = false;
      }
    });
  }

  menus.forEach(function (menu) {
    menu.addEventListener('toggle', function () {
      if (menu.open) {
        closeMenus(menu);
      }
    });
  });

  document.addEventListener('click', function (event) {
    menus.forEach(function (menu) {
      if (menu.open && !menu.contains(event.target)) {
        menu.open = false;
      }
    });
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      closeMenus(null);
    }
  });

  menus.forEach(function (menu) {
    menu.addEventListener('click', function (event) {
      if (event.target.closest('a, button')) {
        menu.open = false;
      }
    });
  });

  document.addEventListener('click', function (event) {
    var button = event.target.closest('[data-copy]');
    if (!button) {
      return;
    }
    var value = button.getAttribute('data-copy') || '';
    var original = button.getAttribute('data-label') || button.textContent;
    var done = function () {
      button.textContent = 'Copied';
      window.setTimeout(function () { button.textContent = original; }, 1600);
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(value).then(done).catch(function () {});
      return;
    }
    var field = document.createElement('textarea');
    field.value = value;
    document.body.appendChild(field);
    field.select();
    try { document.execCommand('copy'); done(); } catch (error) {}
    document.body.removeChild(field);
  });

  document.querySelectorAll('[data-filter-scope]').forEach(function (scope) {
    var input = scope.querySelector('[data-filter-input]');
    var selects = scope.querySelectorAll('[data-filter-key]');
    var rows = scope.querySelectorAll('[data-filter-row]');
    var empty = scope.querySelector('[data-filter-empty]');

    function applyFilters() {
      var query = input ? input.value.trim().toLowerCase() : '';
      var visible = 0;
      rows.forEach(function (row) {
        var textMatches = query === '' || row.textContent.toLowerCase().indexOf(query) !== -1;
        var facetsMatch = true;
        selects.forEach(function (select) {
          var value = select.value;
          var key = select.getAttribute('data-filter-key') || '';
          if (value !== '' && row.getAttribute('data-' + key) !== value) {
            facetsMatch = false;
          }
        });
        row.hidden = !(textMatches && facetsMatch);
        if (!row.hidden) visible++;
      });
      if (empty) empty.hidden = visible !== 0;
    }

    if (input) input.addEventListener('input', applyFilters);
    selects.forEach(function (select) { select.addEventListener('change', applyFilters); });
  });

  var translatorControls = document.querySelectorAll('[data-browser-translator]');
  if (translatorControls.length) {
    var translatedNodes = [];
    var storedLanguage = null;
    try { storedLanguage = window.localStorage ? localStorage.getItem('wayfairs-language') : null; } catch (error) {}

    function translatorStatus(message) {
      translatorControls.forEach(function (control) {
        var status = control.querySelector('[data-translate-status]');
        if (status) status.textContent = message;
      });
    }

    function restoreOriginal() {
      translatedNodes.forEach(function (entry) { entry.node.nodeValue = entry.original; });
      translatedNodes = [];
      document.documentElement.lang = 'en';
    }

    function translatableNodes() {
      var nodes = [];
      var walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT, {
        acceptNode: function (node) {
          var text = node.nodeValue.trim();
          var parent = node.parentElement;
          if (!text || !parent || /^[\d\s.,:+−$€£¥₿Ξ%/()\-]+$/.test(text)) return NodeFilter.FILTER_REJECT;
          if (parent.closest('script,style,noscript,textarea,input,select,option,svg,code,.mono,.record-primary,.profile-hero,.user-chip,.transaction-amount,.cell-amount,[translate="no"]')) return NodeFilter.FILTER_REJECT;
          return NodeFilter.FILTER_ACCEPT;
        }
      });
      while (walker.nextNode() && nodes.length < 400) nodes.push(walker.currentNode);
      return nodes;
    }

    async function translatePage(targetLanguage, userInitiated) {
      restoreOriginal();
      translatorControls.forEach(function (control) {
        var select = control.querySelector('[data-translate-language]');
        if (select) select.value = targetLanguage;
      });
      try { if (window.localStorage) localStorage.setItem('wayfairs-language', targetLanguage); } catch (error) {}
      if (targetLanguage === 'en') {
        translatorStatus('English restored');
        return;
      }
      if (!('Translator' in window) || typeof Translator.create !== 'function') {
        translatorStatus('Use your browser menu → Translate');
        return;
      }
      try {
        var availability = typeof Translator.availability === 'function'
          ? await Translator.availability({ sourceLanguage: 'en', targetLanguage: targetLanguage })
          : 'available';
        if (availability === 'unavailable') {
          translatorStatus('This language is unavailable in your browser');
          return;
        }
        if (!userInitiated && availability !== 'available') {
          translatorStatus('Select Translate to download the browser language model');
          return;
        }
        translatorStatus(availability === 'available' ? 'Translating…' : 'Preparing language model…');
        var translator = await Translator.create({
          sourceLanguage: 'en',
          targetLanguage: targetLanguage,
          monitor: function (monitor) {
            monitor.addEventListener('downloadprogress', function (event) {
              translatorStatus('Downloading language model ' + Math.round(event.loaded * 100) + '%');
            });
          }
        });
        var nodes = translatableNodes();
        for (var index = 0; index < nodes.length; index++) {
          var node = nodes[index];
          var original = node.nodeValue;
          var leading = original.match(/^\s*/)[0];
          var trailing = original.match(/\s*$/)[0];
          var translated = await translator.translate(original.trim());
          translatedNodes.push({ node: node, original: original });
          node.nodeValue = leading + translated + trailing;
        }
        if (typeof translator.destroy === 'function') translator.destroy();
        document.documentElement.lang = targetLanguage;
        translatorStatus('Translated on this device');
      } catch (error) {
        restoreOriginal();
        translatorStatus('Browser translation could not complete');
      }
    }

    translatorControls.forEach(function (control) {
      var select = control.querySelector('[data-translate-language]');
      var button = control.querySelector('[data-translate-button]');
      if (select && storedLanguage) select.value = storedLanguage;
      if (button && select) button.addEventListener('click', function () { translatePage(select.value, true); });
    });
    if (storedLanguage && storedLanguage !== 'en') translatePage(storedLanguage, false);
  }
})();
</script>
