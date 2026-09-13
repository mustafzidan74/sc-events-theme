/**
 * Dashboard shell behaviour: theme switch, sidebar groups, mobile drawer and
 * the top-bar menus. Plain DOM — the dashboard loads several jQuery copies, so
 * nothing here depends on any of them.
 *
 * Keeps the old globals pages may call: scSetTheme(theme), scToggleTheme().
 *
 * @package sc_events
 */
(function () {
  'use strict';

  var THEME_KEY = 'sc_dashboard_theme';
  var NAV_KEY = 'wd_nav_open';
  var root = document.documentElement;
  var body = document.body;

  function store(key, value) {
    try { localStorage.setItem(key, value); } catch (e) {}
  }
  function read(key) {
    try { return localStorage.getItem(key); } catch (e) { return null; }
  }

  /* ---------------------------------------------------------------- theme */

  function systemDark() {
    return !!(window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
  }

  function applyTheme(pref) {
    if (pref !== 'light' && pref !== 'dark') {
      pref = 'system';
    }
    if (pref === 'system') {
      root.removeAttribute('data-theme');
      body.removeAttribute('data-theme');
      // The legacy bundle re-applies whatever it finds under "theme".
      try { localStorage.removeItem('theme'); } catch (e) {}
    } else {
      root.setAttribute('data-theme', pref);
      body.setAttribute('data-theme', pref);
      store('theme', pref);
    }
    store(THEME_KEY, pref);
    document.cookie = THEME_KEY + '=' + pref + ';path=/;max-age=31536000;SameSite=Lax';

    var buttons = document.querySelectorAll('[data-w-theme]');
    for (var i = 0; i < buttons.length; i++) {
      buttons[i].setAttribute('aria-pressed', String(buttons[i].getAttribute('data-w-theme') === pref));
    }
    syncCharts(pref === 'dark' || (pref === 'system' && systemDark()));
  }

  function syncCharts(isDark) {
    if (typeof window.ApexCharts === 'undefined') {
      return;
    }
    var fore = isDark ? '#A9AFC0' : '#6B665E';
    var grid = isDark ? '#262C40' : '#E7E4DE';
    window.Apex = window.Apex || {};
    window.Apex.chart = { foreColor: fore };
    window.Apex.grid = { borderColor: grid };
    window.Apex.tooltip = { theme: isDark ? 'dark' : 'light' };
    try {
      (window.Apex._chartInstances || []).forEach(function (inst) {
        if (inst && inst.chart) {
          inst.chart.updateOptions({ chart: { foreColor: fore }, grid: { borderColor: grid }, tooltip: { theme: isDark ? 'dark' : 'light' } }, false, false);
        }
      });
    } catch (e) {}
  }

  window.scSetTheme = applyTheme;
  window.scToggleTheme = function () {
    var dark = root.getAttribute('data-theme') === 'dark' || (!root.hasAttribute('data-theme') && systemDark());
    applyTheme(dark ? 'light' : 'dark');
  };

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-w-theme]');
    if (btn) {
      applyTheme(btn.getAttribute('data-w-theme'));
    }
  });

  if (window.matchMedia) {
    var mq = window.matchMedia('(prefers-color-scheme: dark)');
    var onChange = function () {
      if ((read(THEME_KEY) || 'system') === 'system') {
        syncCharts(mq.matches);
      }
    };
    if (mq.addEventListener) { mq.addEventListener('change', onChange); } else if (mq.addListener) { mq.addListener(onChange); }
  }

  // Light stays the default, as before the redesign; "system" is opt-in.
  applyTheme(read(THEME_KEY) || (document.cookie.match(/sc_dashboard_theme=(light|dark|system)/) || [])[1] || 'light');

  /* -------------------------------------------------------- sidebar groups */

  var openGroups;
  try { openGroups = JSON.parse(read(NAV_KEY) || '{}') || {}; } catch (e) { openGroups = {}; }

  var toggles = document.querySelectorAll('.w-side__toggle');
  for (var t = 0; t < toggles.length; t++) {
    (function (toggle) {
      var key = toggle.getAttribute('data-group');
      var items = document.getElementById(toggle.getAttribute('aria-controls'));
      if (!items) {
        return;
      }
      // The group holding the current page always starts open.
      var open = toggle.classList.contains('has-active') || openGroups[key] === true;
      toggle.setAttribute('aria-expanded', String(open));
      items.hidden = !open;

      toggle.addEventListener('click', function () {
        var next = toggle.getAttribute('aria-expanded') !== 'true';
        toggle.setAttribute('aria-expanded', String(next));
        items.hidden = !next;
        openGroups[key] = next;
        store(NAV_KEY, JSON.stringify(openGroups));
      });
    })(toggles[t]);
  }

  var active = document.querySelector('.w-side__link.is-active');
  if (active && active.scrollIntoView) {
    var nav = document.querySelector('.w-side__nav');
    if (nav && active.offsetTop > nav.clientHeight - 80) {
      nav.scrollTop = active.offsetTop - nav.clientHeight / 2;
    }
  }

  /* ---------------------------------------------------------------- drawer */

  var scrim = document.getElementById('w-scrim');
  var menuBtn = document.getElementById('w-drawer-open');

  function setDrawer(open) {
    body.classList.toggle('w-drawer-open', open);
    if (scrim) {
      scrim.hidden = !open;
    }
    if (menuBtn) {
      menuBtn.setAttribute('aria-expanded', String(open));
    }
    if (open) {
      var first = document.querySelector('.w-side__close');
      if (first) { first.focus(); }
    } else if (menuBtn && document.activeElement && document.activeElement.closest('.w-side')) {
      menuBtn.focus();
    }
  }

  if (menuBtn) {
    menuBtn.addEventListener('click', function () { setDrawer(true); });
  }
  document.addEventListener('click', function (e) {
    if (e.target.closest('.w-side__close') || e.target === scrim) {
      setDrawer(false);
    } else if (body.classList.contains('w-drawer-open') && e.target.closest('.w-side a[href]')) {
      setDrawer(false);
    }
  });
  window.addEventListener('resize', function () {
    if (window.innerWidth >= 992 && body.classList.contains('w-drawer-open')) {
      setDrawer(false);
    }
  });

  /* ----------------------------------------------------------------- menus */

  function closeMenus(except) {
    var open = document.querySelectorAll('[data-w-menu][aria-expanded="true"]');
    for (var i = 0; i < open.length; i++) {
      if (open[i] !== except) {
        open[i].setAttribute('aria-expanded', 'false');
        var panel = document.getElementById(open[i].getAttribute('aria-controls'));
        if (panel) { panel.hidden = true; }
      }
    }
  }

  document.addEventListener('click', function (e) {
    var trigger = e.target.closest('[data-w-menu]');
    if (trigger) {
      var panel = document.getElementById(trigger.getAttribute('aria-controls'));
      var next = trigger.getAttribute('aria-expanded') !== 'true';
      closeMenus(trigger);
      trigger.setAttribute('aria-expanded', String(next));
      if (panel) {
        panel.hidden = !next;
        if (next) {
          var item = panel.querySelector('.w-menu__item');
          if (item) { item.focus(); }
        }
      }
      return;
    }
    if (!e.target.closest('.w-menu__panel')) {
      closeMenus(null);
    }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      var openTrigger = document.querySelector('[data-w-menu][aria-expanded="true"]');
      if (openTrigger) {
        closeMenus(null);
        openTrigger.focus();
      } else if (body.classList.contains('w-drawer-open')) {
        setDrawer(false);
      }
      return;
    }
    if ((e.key === 'ArrowDown' || e.key === 'ArrowUp') && e.target.closest('.w-menu__panel')) {
      var items = Array.prototype.slice.call(e.target.closest('.w-menu__panel').querySelectorAll('.w-menu__item'));
      var idx = items.indexOf(document.activeElement);
      if (idx > -1) {
        e.preventDefault();
        items[(idx + (e.key === 'ArrowDown' ? 1 : items.length - 1)) % items.length].focus();
      }
    }
  });
})();
