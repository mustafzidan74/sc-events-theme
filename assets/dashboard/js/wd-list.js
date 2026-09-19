/**
 * WDList — the dashboard list pattern: server-paged table with view tabs,
 * filters kept in the URL, selection with a bulk bar, row menus, a pager and
 * empty / loading / error states. Plain DOM; no jQuery.
 *
 *   const list = WDList.create({
 *     root: document.getElementById('attendees-list'),
 *     action: 'sc_get_attendees_paginated',
 *     filters: ['search', 'event_id', ...],      // names of [data-w-filter] controls
 *     tabs: [{ key, label, params, countKey }],   // optional
 *     columns: [{ key, label, sort, className, label_sm, render(row) }],
 *     rowMenu: row => [{ label, icon, href | onSelect, danger, disabled }],
 *     bulkActions: [{ key, label, danger, run(ids, rows, list) }],
 *     extraParams: () => ({}), chips: state => [{ label, value, clear }],
 *     fixedFilters: ['scope'],                 // filters that define the page, not narrow it
 *   });
 *
 * Renderers return HTML strings and must escape data with WDList.esc().
 *
 * @package sc_events
 */
(function (window, document) {
  'use strict';

  var ESC = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
  function esc(value) {
    return value === null || value === undefined ? '' : String(value).replace(/[&<>"']/g, function (c) { return ESC[c]; });
  }
  function num(n) {
    return Number(n || 0).toLocaleString('en-US');
  }
  function icon(path, size) {
    size = size || 16;
    return '<svg class="w-icon" width="' + size + '" height="' + size + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="' + path + '"/></svg>';
  }
  var ICONS = {
    dots: 'M12 12h.01M19 12h.01M5 12h.01',
    close: 'M18 6 6 18M6 6l12 12',
    search: 'M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14zM20 20l-3.5-3.5',
    alert: 'M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z',
    inbox: 'M22 12h-6l-2 3h-4l-2-3H2M5.5 5h13l3.5 7v6a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-6z'
  };

  function create(opts) {
    var root = opts.root;
    var T = Object.assign({
      of: 'of', perPage: 'per page', goTo: 'Go to', previous: 'Previous', next: 'Next',
      selected: 'selected', clearSelection: 'Clear selection', clearAll: 'Clear all',
      loadingError: 'The list could not be loaded.', retry: 'Try again',
      emptyTitle: 'Nothing here yet', emptyFiltered: 'Nothing matches these filters',
      emptyFilteredText: 'Try removing a filter or searching for something else.',
      selectAllOnPage: 'Select all on this page', selectRow: 'Select row', actions: 'Actions'
    }, opts.i18n || {});

    var els = {
      tabs: root.querySelector('[data-w-tabs]'),
      chips: root.querySelector('[data-w-chips]'),
      bulk: root.querySelector('[data-w-bulk]'),
      card: root.querySelector('[data-w-card]'),
      progress: root.querySelector('[data-w-progress]'),
      table: root.querySelector('[data-w-table]'),
      head: root.querySelector('[data-w-table] thead'),
      body: root.querySelector('[data-w-table] tbody'),
      scroll: root.querySelector('[data-w-scroll]'),
      state: root.querySelector('[data-w-state]'),
      pager: root.querySelector('[data-w-pager]'),
      count: document.querySelector(opts.countSelector || '[data-w-total]')
    };

    var perPageOptions = opts.perPageOptions || [25, 50, 100];
    var state = {
      page: 1,
      perPage: opts.perPage || perPageOptions[0],
      tab: opts.tabs && opts.tabs.length ? opts.tabs[0].key : null,
      orderby: (opts.defaultSort || {}).orderby || '',
      order: (opts.defaultSort || {}).order || 'desc',
      filters: {}
    };
    var rows = [];
    var selected = new Map();     // id -> row, kept across pages until the filters change
    var controller = null;
    var loadedOnce = false;
    var lastData = null;

    /* ------------------------------------------------------------ filters */

    function filterControls() {
      return Array.prototype.slice.call(root.querySelectorAll('[data-w-filter]'));
    }
    function readControls() {
      var f = {};
      filterControls().forEach(function (el) {
        var v = (el.value || '').trim();
        if (v !== '') { f[el.getAttribute('data-w-filter')] = v; }
        if (el.tagName === 'SELECT') { el.classList.toggle('is-set', v !== ''); }
      });
      return f;
    }
    function writeControls() {
      filterControls().forEach(function (el) {
        var key = el.getAttribute('data-w-filter');
        el.value = state.filters[key] !== undefined ? state.filters[key] : '';
        if (el.tagName === 'SELECT') { el.classList.toggle('is-set', !!state.filters[key]); }
      });
    }
    function activeFilterCount() {
      return Object.keys(state.filters).filter(function (key) { return (opts.fixedFilters || []).indexOf(key) === -1; }).length;
    }

    /* ---------------------------------------------------------------- URL */

    var URL_KEYS = { page: 'page', perPage: 'per_page', tab: 'view', orderby: 'orderby', order: 'order' };

    function readUrl() {
      var q = new URLSearchParams(window.location.search);
      (opts.filters || []).forEach(function (key) {
        if (q.has(key) && q.get(key) !== '') { state.filters[key] = q.get(key); }
      });
      if (q.has('page')) { state.page = Math.max(1, parseInt(q.get('page'), 10) || 1); }
      if (q.has('per_page') && perPageOptions.indexOf(parseInt(q.get('per_page'), 10)) > -1) { state.perPage = parseInt(q.get('per_page'), 10); }
      if (q.has('view') && (opts.tabs || []).some(function (t) { return t.key === q.get('view'); })) { state.tab = q.get('view'); }
      if (q.has('orderby')) { state.orderby = q.get('orderby'); }
      if (q.has('order')) { state.order = q.get('order') === 'asc' ? 'asc' : 'desc'; }
    }
    function writeUrl() {
      var q = new URLSearchParams(window.location.search);
      (opts.filters || []).forEach(function (key) { q.delete(key); });
      Object.keys(URL_KEYS).forEach(function (k) { q.delete(URL_KEYS[k]); });
      Object.keys(state.filters).forEach(function (key) { q.set(key, state.filters[key]); });
      if (state.page > 1) { q.set('page', state.page); }
      if (state.perPage !== (opts.perPage || perPageOptions[0])) { q.set('per_page', state.perPage); }
      if (opts.tabs && state.tab !== opts.tabs[0].key) { q.set('view', state.tab); }
      if (state.orderby && (state.orderby !== (opts.defaultSort || {}).orderby || state.order !== (opts.defaultSort || {}).order)) {
        q.set('orderby', state.orderby);
        q.set('order', state.order);
      }
      var qs = q.toString();
      window.history.replaceState(null, '', window.location.pathname + (qs ? '?' + qs : '') + window.location.hash);
    }

    /* ------------------------------------------------------------ request */

    function params() {
      var p = Object.assign({}, state.filters);
      var tab = (opts.tabs || []).filter(function (t) { return t.key === state.tab; })[0];
      if (tab && tab.params) { Object.assign(p, tab.params); }
      if (state.orderby) { p.orderby = state.orderby; p.order = state.order; }
      if (opts.extraParams) { Object.assign(p, opts.extraParams() || {}); }
      return p;
    }

    function load() {
      if (controller) { controller.abort(); }
      controller = typeof AbortController !== 'undefined' ? new AbortController() : null;

      var body = new FormData();
      body.append('action', opts.action);
      body.append('nonce', window.scDashboard ? window.scDashboard.nonce : '');
      body.append('page', state.page);
      body.append('per_page', state.perPage);
      if (opts.tabs) { body.append('with_counts', '1'); }
      var p = params();
      Object.keys(p).forEach(function (k) { body.append(k, p[k]); });

      writeUrl();
      setBusy(true);

      return fetch(window.scDashboard.ajaxurl, { method: 'POST', body: body, credentials: 'same-origin', signal: controller ? controller.signal : undefined })
        .then(function (r) {
          if (!r.ok) { throw new Error('HTTP ' + r.status); }
          return r.json();
        })
        .then(function (json) {
          if (!json || !json.success) {
            throw new Error(json && json.data && json.data.message ? json.data.message : 'Request failed');
          }
          var data = json.data;
          // Past the last page (e.g. rows were deleted): step back once.
          if (data.total > 0 && !(data[opts.rowsKey || 'attendees'] || []).length && state.page > 1) {
            state.page = Math.max(1, Math.ceil(data.total / state.perPage));
            return load();
          }
          lastData = data;
          rows = data[opts.rowsKey || 'attendees'] || [];
          loadedOnce = true;
          render(data);
          if (opts.onData) { opts.onData(data, api); }
          setBusy(false);
        })
        .catch(function (err) {
          if (err && err.name === 'AbortError') { return; }
          setBusy(false);
          showError(err && err.message);
        });
    }

    function setBusy(busy) {
      if (els.progress) { els.progress.hidden = !busy; }
      if (els.card) { els.card.setAttribute('aria-busy', busy ? 'true' : 'false'); }
      if (busy && !loadedOnce) {
        renderSkeleton();
      } else if (els.table) {
        els.table.classList.toggle('is-loading', busy);
      }
    }

    /* ------------------------------------------------------------- render */

    function renderHead() {
      var html = '<tr>';
      if (opts.bulkActions && opts.bulkActions.length) {
        html += '<th class="w-table__check" scope="col"><input type="checkbox" class="w-check" data-w-check-all aria-label="' + esc(T.selectAllOnPage) + '"></th>';
      }
      opts.columns.forEach(function (col) {
        var cls = col.className ? ' class="' + esc(col.className) + '"' : '';
        if (col.sort) {
          var active = state.orderby === col.sort;
          var arrow = active ? (state.order === 'asc' ? '▲' : '▼') : '▼';
          html += '<th scope="col"' + cls + '><button type="button" class="w-sort" data-w-sort="' + esc(col.sort) + '"' +
            (active ? ' aria-sort="' + (state.order === 'asc' ? 'ascending' : 'descending') + '"' : '') + '>' +
            esc(col.label) + '<span class="w-sort__arrow" aria-hidden="true">' + arrow + '</span></button></th>';
        } else {
          html += '<th scope="col"' + cls + '>' + esc(col.label) + '</th>';
        }
      });
      if (opts.rowMenu) { html += '<th class="w-table__menu" scope="col"><span class="sr-only">' + esc(T.actions) + '</span></th>'; }
      els.head.innerHTML = html + '</tr>';
    }

    function renderSkeleton() {
      if (els.state) { els.state.hidden = true; }
      if (els.scroll) { els.scroll.hidden = false; }
      renderHead();
      var cols = opts.columns.length;
      var html = '';
      for (var i = 0; i < Math.min(state.perPage, 10); i++) {
        html += '<tr aria-hidden="true">';
        if (opts.bulkActions && opts.bulkActions.length) { html += '<td class="w-table__check"><span class="w-skel" style="width:18px;height:18px"></span></td>'; }
        for (var c = 0; c < cols; c++) {
          html += c === 0
            ? '<td class="w-table__primary"><div class="w-person"><span class="w-skel w-skel--circle"></span><span style="flex:1"><span class="w-skel" style="width:' + (50 + (i * 7) % 35) + '%"></span><span class="w-skel" style="width:' + (35 + (i * 11) % 30) + '%;height:8px;margin-top:6px"></span></span></div></td>'
            : '<td><span class="w-skel" style="width:' + (40 + ((i + c) * 13) % 45) + '%"></span></td>';
        }
        if (opts.rowMenu) { html += '<td class="w-table__menu"></td>'; }
        html += '</tr>';
      }
      els.body.innerHTML = html;
      if (els.pager) { els.pager.hidden = true; }
    }

    function render(data) {
      if (els.count) { els.count.textContent = num(data.counts ? data.counts.all : data.total); }
      renderTabs(data.counts);
      renderChips();
      renderHead();

      if (!rows.length) {
        els.body.innerHTML = '';
        if (els.scroll) { els.scroll.hidden = true; }
        if (els.pager) { els.pager.hidden = true; }
        showEmpty();
        syncSelectionUi();
        return;
      }
      if (els.state) { els.state.hidden = true; }
      if (els.scroll) { els.scroll.hidden = false; }

      var hasBulk = opts.bulkActions && opts.bulkActions.length;
      els.table.classList.toggle('w-table--nocheck', !hasBulk);
      els.body.innerHTML = rows.map(function (row) {
        var id = row[opts.rowKey || 'id'];
        var html = '<tr data-id="' + esc(id) + '"' + (selected.has(String(id)) ? ' class="is-selected"' : '') + '>';
        if (hasBulk) {
          html += '<td class="w-table__check"><input type="checkbox" class="w-check" data-w-check value="' + esc(id) + '"' +
            (selected.has(String(id)) ? ' checked' : '') + ' aria-label="' + esc(T.selectRow) + '"></td>';
        }
        opts.columns.forEach(function (col, i) {
          var cls = [i === 0 ? 'w-table__primary' : '', col.className || '', col.hideSm ? 'w-table__hide-sm' : ''].filter(Boolean).join(' ');
          html += '<td' + (cls ? ' class="' + cls + '"' : '') + (i > 0 ? ' data-label="' + esc(col.labelSm || col.label) + '"' : '') + '>' + col.render(row) + '</td>';
        });
        if (opts.rowMenu) {
          html += '<td class="w-table__menu"><button type="button" class="w-rowmenu-btn" data-w-rowmenu="' + esc(id) + '" aria-haspopup="menu" aria-expanded="false" aria-label="' + esc(T.actions) + '">' + icon(ICONS.dots, 18) + '</button></td>';
        }
        return html + '</tr>';
      }).join('');

      renderPager(data);
      syncSelectionUi();
    }

    function renderTabs(counts) {
      if (!els.tabs || !opts.tabs) { return; }
      els.tabs.innerHTML = opts.tabs.map(function (t) {
        var on = t.key === state.tab;
        var n = counts && t.countKey && counts[t.countKey] !== undefined ? '<span class="w-tab__count">' + num(counts[t.countKey]) + '</span>' : '';
        return '<button type="button" role="tab" class="w-tab" data-w-tab="' + esc(t.key) + '" aria-selected="' + on + '" tabindex="' + (on ? 0 : -1) + '">' + esc(t.label) + n + '</button>';
      }).join('');
    }

    function renderChips() {
      if (!els.chips) { return; }
      var chips = opts.chips ? opts.chips(state) : [];
      if (!chips.length) {
        els.chips.hidden = true;
        els.chips.innerHTML = '';
        return;
      }
      els.chips.hidden = false;
      els.chips.innerHTML = chips.map(function (c, i) {
        return '<span class="w-chip"><span class="w-chip__label">' + esc(c.label) + ':</span> <span class="w-chip__value">' + esc(c.value) + '</span>' +
          '<button type="button" class="w-chip__x" data-w-chip="' + i + '" aria-label="Remove ' + esc(c.label) + '">' + icon(ICONS.close, 12) + '</button></span>';
      }).join('') + (chips.length > 1 ? '<button type="button" class="w-chips__clear" data-w-chips-clear>' + esc(T.clearAll) + '</button>' : '');
      els.chips._chips = chips;
    }

    function renderPager(data) {
      if (!els.pager) { return; }
      var total = data.total;
      var pages = Math.max(1, Math.ceil(total / state.perPage));
      var from = (state.page - 1) * state.perPage + 1;
      var to = Math.min(state.page * state.perPage, total);

      var list = [];
      var add = function (p) { if (list.indexOf(p) === -1 && p >= 1 && p <= pages) { list.push(p); } };
      add(1); add(state.page - 1); add(state.page); add(state.page + 1); add(pages);
      list.sort(function (a, b) { return a - b; });

      var pageBtns = '';
      list.forEach(function (p, i) {
        if (i > 0 && p - list[i - 1] > 1) { pageBtns += '<span class="w-pager__gap" aria-hidden="true">…</span>'; }
        pageBtns += '<button type="button" class="w-pager__btn" data-w-page="' + p + '"' + (p === state.page ? ' aria-current="page"' : '') + '>' + num(p) + '</button>';
      });

      els.pager.hidden = false;
      els.pager.innerHTML =
        '<div class="w-pager__info"><span class="w-pager__range">' + num(from) + '–' + num(to) + ' ' + esc(T.of) + ' ' + num(total) + '</span>' +
        '<label class="sr-only" for="w-perpage">' + esc(T.perPage) + '</label><select id="w-perpage" class="form-control" data-w-perpage>' +
        perPageOptions.map(function (n) { return '<option value="' + n + '"' + (n === state.perPage ? ' selected' : '') + '>' + n + ' ' + esc(T.perPage) + '</option>'; }).join('') +
        '</select></div>' +
        '<nav class="w-pager__pages" aria-label="Pagination">' +
        '<button type="button" class="w-pager__btn w-pager__btn--edge" data-w-page="' + (state.page - 1) + '"' + (state.page <= 1 ? ' disabled' : '') + ' aria-label="' + esc(T.previous) + '">‹</button>' +
        pageBtns +
        '<button type="button" class="w-pager__btn w-pager__btn--edge" data-w-page="' + (state.page + 1) + '"' + (state.page >= pages ? ' disabled' : '') + ' aria-label="' + esc(T.next) + '">›</button>' +
        (pages > 5 ? '<span class="w-pager__goto"><label for="w-goto">' + esc(T.goTo) + '</label><input id="w-goto" type="number" min="1" max="' + pages + '" class="form-control" data-w-goto></span>' : '') +
        '</nav>';
    }

    function showEmpty() {
      if (!els.state) { return; }
      var filtered = activeFilterCount() > 0 || (opts.extraFilterCount && opts.extraFilterCount() > 0) || (opts.tabs && state.tab !== opts.tabs[0].key);
      els.state.className = 'w-state';
      els.state.hidden = false;
      els.state.innerHTML =
        '<span class="w-state__icon">' + icon(filtered ? ICONS.search : ICONS.inbox, 26) + '</span>' +
        '<p class="w-state__title">' + esc(filtered ? T.emptyFiltered : T.emptyTitle) + '</p>' +
        (filtered ? '<p class="w-state__text">' + esc(T.emptyFilteredText) + '</p>' : (opts.emptyText ? '<p class="w-state__text">' + esc(opts.emptyText) + '</p>' : '')) +
        '<div class="w-state__actions">' +
        (filtered ? '<button type="button" class="btn btn-secondary" data-w-reset>' + esc(T.clearAll) + '</button>' : '') +
        (opts.emptyAction || '') + '</div>';
    }

    function showError(message) {
      if (!els.state) { return; }
      els.body.innerHTML = '';
      if (els.scroll) { els.scroll.hidden = true; }
      if (els.pager) { els.pager.hidden = true; }
      els.state.className = 'w-state w-state--error';
      els.state.hidden = false;
      els.state.innerHTML =
        '<span class="w-state__icon">' + icon(ICONS.alert, 26) + '</span>' +
        '<p class="w-state__title">' + esc(T.loadingError) + '</p>' +
        (message ? '<p class="w-state__text">' + esc(message) + '</p>' : '') +
        '<div class="w-state__actions"><button type="button" class="btn btn-primary" data-w-retry>' + esc(T.retry) + '</button></div>';
    }

    /* ---------------------------------------------------------- selection */

    function syncSelectionUi() {
      var all = root.querySelector('[data-w-check-all]');
      var onPage = rows.filter(function (r) { return selected.has(String(r[opts.rowKey || 'id'])); }).length;
      if (all) {
        all.checked = rows.length > 0 && onPage === rows.length;
        all.indeterminate = onPage > 0 && onPage < rows.length;
      }
      if (!els.bulk) { return; }
      els.bulk.hidden = selected.size === 0;
      if (selected.size) {
        els.bulk.innerHTML =
          '<span class="w-bulkbar__count">' + num(selected.size) + ' ' + esc(T.selected) + '</span>' +
          '<span class="w-bulkbar__actions">' +
          opts.bulkActions.map(function (a) {
            return '<button type="button" class="w-bulkbar__btn' + (a.danger ? ' w-bulkbar__btn--danger' : '') + '" data-w-bulk-action="' + esc(a.key) + '">' + (a.icon ? icon(a.icon, 15) : '') + esc(a.label) + '</button>';
          }).join('') +
          '<button type="button" class="w-bulkbar__btn w-bulkbar__btn--icon" data-w-clear-selection aria-label="' + esc(T.clearSelection) + '">' + icon(ICONS.close, 16) + '</button>' +
          '</span>';
      }
    }

    function toggleRow(id, on) {
      id = String(id);
      var row = rows.filter(function (r) { return String(r[opts.rowKey || 'id']) === id; })[0];
      if (on && row) { selected.set(id, row); } else { selected.delete(id); }
      var tr = els.body.querySelector('tr[data-id="' + CSS.escape(id) + '"]');
      if (tr) { tr.classList.toggle('is-selected', on); }
    }

    /* ----------------------------------------------------------- row menu */

    var popover = document.createElement('div');
    popover.className = 'w-popover';
    popover.setAttribute('role', 'menu');
    popover.hidden = true;
    document.body.appendChild(popover);
    var popoverTrigger = null;
    var popoverItems = [];

    function closeMenu(focusTrigger) {
      if (popover.hidden) { return; }
      popover.hidden = true;
      if (popoverTrigger) {
        popoverTrigger.setAttribute('aria-expanded', 'false');
        if (focusTrigger) { popoverTrigger.focus(); }
      }
      popoverTrigger = null;
    }

    function openMenu(trigger) {
      var id = trigger.getAttribute('data-w-rowmenu');
      var row = rows.filter(function (r) { return String(r[opts.rowKey || 'id']) === id; })[0];
      if (!row) { return; }
      popoverItems = opts.rowMenu(row).filter(Boolean);
      popover.innerHTML = popoverItems.map(function (item, i) {
        if (item.separator) { return '<div class="w-menu__sep" role="separator"></div>'; }
        var cls = 'w-menu__item' + (item.danger ? ' w-menu__item--danger' : '');
        var attrs = ' role="menuitem" data-w-item="' + i + '"' + (item.disabled ? ' aria-disabled="true" tabindex="-1"' : '');
        var inner = (item.icon ? icon(item.icon, 16) : '') + '<span>' + esc(item.label) + '</span>';
        return item.href && !item.disabled
          ? '<a class="' + cls + '" href="' + esc(item.href) + '"' + (item.target ? ' target="' + esc(item.target) + '" rel="noopener"' : '') + attrs + '>' + inner + '</a>'
          : '<button type="button" class="' + cls + '"' + attrs + '>' + inner + '</button>';
      }).join('');

      popover.hidden = false;
      popoverTrigger = trigger;
      placeMenu();

      trigger.setAttribute('aria-expanded', 'true');
      var first = popover.querySelector('.w-menu__item:not([aria-disabled="true"])');
      if (first) { first.focus(); }
    }

    /** Pin the menu under (or above) its trigger; false when the trigger has scrolled out of view. */
    function placeMenu() {
      if (!popoverTrigger) { return false; }
      var r = popoverTrigger.getBoundingClientRect();
      if (r.bottom < 0 || r.top > window.innerHeight) { return false; }
      var w = popover.offsetWidth, h = popover.offsetHeight;
      var rtl = document.documentElement.dir === 'rtl';
      var left = rtl ? r.left : r.right - w;
      var top = r.bottom + 4;
      if (top + h > window.innerHeight - 8) { top = Math.max(8, r.top - h - 4); }
      popover.style.left = Math.max(8, Math.min(left, window.innerWidth - w - 8)) + 'px';
      popover.style.top = top + 'px';
      return true;
    }

    popover.addEventListener('click', function (e) {
      var el = e.target.closest('[data-w-item]');
      if (!el) { return; }
      var item = popoverItems[parseInt(el.getAttribute('data-w-item'), 10)];
      if (!item || item.disabled) { return; }
      if (!item.href) { e.preventDefault(); }
      closeMenu(false);
      if (item.onSelect) { item.onSelect(); }
    });

    popover.addEventListener('keydown', function (e) {
      var items = Array.prototype.slice.call(popover.querySelectorAll('.w-menu__item:not([aria-disabled="true"])'));
      var i = items.indexOf(document.activeElement);
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault();
        items[(i + (e.key === 'ArrowDown' ? 1 : items.length - 1)) % items.length].focus();
      } else if (e.key === 'Tab') {
        closeMenu(false);
      }
    });

    document.addEventListener('click', function (e) {
      if (!popover.hidden && !popover.contains(e.target) && e.target.closest('[data-w-rowmenu]') !== popoverTrigger) { closeMenu(false); }
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !popover.hidden) { closeMenu(true); }
      if (e.key === '/' && !e.target.closest('input, textarea, select, [contenteditable]') && !document.querySelector('.modal.show')) {
        var search = root.querySelector('[data-w-filter="search"]');
        if (search) { e.preventDefault(); search.focus(); }
      }
    });
    // Follow the trigger while scrolling; close once it leaves the viewport.
    window.addEventListener('scroll', function () { if (!popover.hidden && !placeMenu()) { closeMenu(false); } }, true);
    window.addEventListener('resize', function () { closeMenu(false); });

    /* ------------------------------------------------------------- events */

    var debounce = null;
    function onFilterChange(immediate) {
      clearTimeout(debounce);
      var run = function () {
        var next = readControls();
        if (JSON.stringify(next) === JSON.stringify(state.filters)) { return; }
        state.filters = next;
        state.page = 1;
        selected.clear();
        if (opts.onFiltersChange) { opts.onFiltersChange(state.filters, api); }
        load();
      };
      if (immediate) { run(); } else { debounce = setTimeout(run, 350); }
    }

    root.addEventListener('input', function (e) {
      if (e.target.matches('input[data-w-filter]')) { onFilterChange(false); }
    });
    root.addEventListener('change', function (e) {
      if (e.target.matches('select[data-w-filter]')) { onFilterChange(true); }
      else if (e.target.matches('[data-w-perpage]')) {
        state.perPage = parseInt(e.target.value, 10);
        state.page = 1;
        load();
      } else if (e.target.matches('[data-w-check-all]')) {
        rows.forEach(function (r) { toggleRow(r[opts.rowKey || 'id'], e.target.checked); });
        syncSelectionUi();
        root.querySelectorAll('[data-w-check]').forEach(function (cb) { cb.checked = e.target.checked; });
      } else if (e.target.matches('[data-w-check]')) {
        toggleRow(e.target.value, e.target.checked);
        syncSelectionUi();
      }
    });
    root.addEventListener('keydown', function (e) {
      if (e.target.matches('input[data-w-filter]') && e.key === 'Enter') { e.preventDefault(); onFilterChange(true); }
      if (e.target.matches('[data-w-goto]') && e.key === 'Enter') {
        var p = parseInt(e.target.value, 10);
        var pages = Math.max(1, Math.ceil((lastData ? lastData.total : 0) / state.perPage));
        if (p >= 1 && p <= pages) { state.page = p; load(); scrollTop(); }
      }
      if (e.target.matches('[data-w-tab]') && (e.key === 'ArrowRight' || e.key === 'ArrowLeft')) {
        var tabs = Array.prototype.slice.call(els.tabs.querySelectorAll('[data-w-tab]'));
        var next = tabs[(tabs.indexOf(e.target) + (e.key === 'ArrowRight' ? 1 : tabs.length - 1)) % tabs.length];
        next.focus();
        next.click();
      }
    });
    root.addEventListener('click', function (e) {
      var t;
      if ((t = e.target.closest('[data-w-tab]'))) {
        if (t.getAttribute('data-w-tab') !== state.tab) {
          state.tab = t.getAttribute('data-w-tab');
          state.page = 1;
          selected.clear();
          load();
        }
      } else if ((t = e.target.closest('[data-w-sort]'))) {
        var key = t.getAttribute('data-w-sort');
        // A new column starts A→Z for text and newest-first for dates (*_at); a second click flips it.
        var first = /_at$/.test(key) ? 'desc' : 'asc';
        state.order = state.orderby === key ? (state.order === 'asc' ? 'desc' : 'asc') : first;
        state.orderby = key;
        state.page = 1;
        load();
      } else if ((t = e.target.closest('[data-w-page]'))) {
        if (t.disabled) { return; }
        state.page = parseInt(t.getAttribute('data-w-page'), 10);
        load();
        scrollTop();
      } else if ((t = e.target.closest('[data-w-rowmenu]'))) {
        if (popoverTrigger === t) { closeMenu(true); } else { closeMenu(false); openMenu(t); }
      } else if ((t = e.target.closest('[data-w-bulk-action]'))) {
        var action = opts.bulkActions.filter(function (a) { return a.key === t.getAttribute('data-w-bulk-action'); })[0];
        if (action) {
          var ids = Array.from(selected.keys());
          action.run(ids, Array.from(selected.values()), api);
        }
      } else if (e.target.closest('[data-w-clear-selection]')) {
        selected.clear();
        root.querySelectorAll('[data-w-check]').forEach(function (cb) { cb.checked = false; });
        root.querySelectorAll('tr.is-selected').forEach(function (tr) { tr.classList.remove('is-selected'); });
        syncSelectionUi();
      } else if ((t = e.target.closest('[data-w-chip]'))) {
        var chip = els.chips._chips[parseInt(t.getAttribute('data-w-chip'), 10)];
        if (chip) { chip.clear(api); }
      } else if (e.target.closest('[data-w-chips-clear], [data-w-reset]')) {
        api.reset();
      } else if (e.target.closest('[data-w-retry]')) {
        load();
      }
    });

    function scrollTop() {
      var top = root.getBoundingClientRect().top + window.scrollY - 72;
      if (window.scrollY > top) { window.scrollTo({ top: top, behavior: 'smooth' }); }
    }

    /* ---------------------------------------------------------------- api */

    var api = {
      reload: function (keepSelection) {
        if (!keepSelection) { selected.clear(); }
        return load();
      },
      setFilter: function (key, value) {
        if (value === '' || value === null || value === undefined) { delete state.filters[key]; } else { state.filters[key] = String(value); }
        writeControls();
        state.page = 1;
        selected.clear();
        if (opts.onFiltersChange) { opts.onFiltersChange(state.filters, api); }
        return load();
      },
      setFilters: function (values) {
        Object.keys(values).forEach(function (key) {
          var v = values[key];
          if (v === '' || v === null || v === undefined) { delete state.filters[key]; } else { state.filters[key] = String(v); }
        });
        writeControls();
        state.page = 1;
        selected.clear();
        if (opts.onFiltersChange) { opts.onFiltersChange(state.filters, api); }
        return load();
      },
      applyControls: function () {
        state.filters = readControls();
        state.page = 1;
        selected.clear();
        if (opts.onFiltersChange) { opts.onFiltersChange(state.filters, api); }
        return load();
      },
      reset: function () {
        // Fixed filters (e.g. the event a page is about) survive Clear all.
        state.filters = (opts.fixedFilters || []).reduce(function (keep, key) {
          if (state.filters[key] !== undefined) { keep[key] = state.filters[key]; }
          return keep;
        }, {});
        state.page = 1;
        if (opts.tabs) { state.tab = opts.tabs[0].key; }
        selected.clear();
        writeControls();
        if (opts.onReset) { opts.onReset(api); }
        if (opts.onFiltersChange) { opts.onFiltersChange(state.filters, api); }
        return load();
      },
      clearSelection: function () {
        selected.clear();
        syncSelectionUi();
      },
      params: params,
      state: function () { return JSON.parse(JSON.stringify(state)); },
      data: function () { return lastData; },
      rows: function () { return rows.slice(); }
    };

    readUrl();
    writeControls();
    if (opts.onFiltersChange) { opts.onFiltersChange(state.filters, api); }
    load();
    return api;
  }

  window.WDList = { create: create, esc: esc, num: num, icon: icon };
})(window, document);
