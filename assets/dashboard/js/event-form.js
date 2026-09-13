/**
 * Event form — pickers, tickets, registration questions, FAQ, page sections and
 * social links for partials/event-form.php, on top of WDForm.
 *
 * Every repeater keeps its list in memory and mirrors it into a hidden JSON
 * input on each change, so the form's dirty tracking and the save request both
 * see it without extra wiring. Data comes from window.scEventForm.
 *
 * @package sc_events
 */
jQuery(function ($) {
  'use strict';

  var cfg = window.scEventForm;
  var formEl = document.getElementById('event-form');
  if (!cfg || !formEl) { return; }
  var L = cfg.i18n;

  function esc(v) {
    return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c]; });
  }
  function fmt(template, n) { return String(template).replace('%d', n); }
  function changed(input) { input.dispatchEvent(new Event('change', { bubbles: true })); }
  function setJson(id, value) {
    var input = document.getElementById(id);
    var next = JSON.stringify(value);
    if (input.value !== next) { input.value = next; changed(input); }
  }
  function iconBtn(action, label, glyph, extra) {
    return '<button type="button" class="w-icon-btn' + (extra || '') + '" data-act="' + action + '" aria-label="' + esc(label) + '" title="' + esc(label) + '"><i class="fa ' + glyph + '" aria-hidden="true"></i></button>';
  }
  function moveItem(list, from, to) {
    if (to < 0 || to >= list.length) { return false; }
    list.splice(to, 0, list.splice(from, 1)[0]);
    return true;
  }
  var isUrl = function (v) { return /^https?:\/\/[^\s.]+\.\S+/i.test(String(v || '').trim()); };

  /** wp.media frame: single image → cb({id, url}); multiple → cb([{id, url}]). */
  function pickMedia(multiple, title, cb) {
    if (!window.wp || !window.wp.media) { return; }
    var frame = window.wp.media({ title: title, library: { type: 'image' }, multiple: multiple });
    frame.on('select', function () {
      var picked = frame.state().get('selection').toJSON().map(function (a) {
        return { id: a.id, url: a.sizes && a.sizes.thumbnail ? a.sizes.thumbnail.url : a.url };
      });
      cb(multiple ? picked : picked[0]);
    });
    frame.open();
  }

  /* ================================================================ pickers */

  var pickerCount = 0;
  function Picker(root) {
    var key = root.getAttribute('data-picker');
    var data = (cfg.pickers || {})[key] || { options: [], selected: [] };
    var name = root.getAttribute('data-name');
    var byId = {};
    data.options.forEach(function (o) { byId[o.id] = o; });
    var selected = data.selected.filter(function (id) { return byId[id]; });
    var listId = 'picker-list-' + (++pickerCount);

    root.innerHTML =
      '<ol class="w-picker__chosen"></ol>' +
      '<div class="w-picker__add">' +
      '<input type="search" class="form-control" role="combobox" aria-expanded="false" aria-autocomplete="list" aria-controls="' + listId + '" placeholder="' + esc(L.search) + '" autocomplete="off">' +
      '<ul class="w-picker__menu" id="' + listId + '" role="listbox" hidden></ul>' +
      '</div>';
    var chosen = root.querySelector('.w-picker__chosen');
    var input = root.querySelector('input');
    var menu = root.querySelector('.w-picker__menu');
    var active = -1;
    var matches = [];

    function renderChosen() {
      chosen.innerHTML = selected.length ? selected.map(function (id, i) {
        var o = byId[id];
        return '<li class="w-picker__chip">' +
          '<input type="hidden" name="' + esc(name) + '" value="' + id + '">' +
          '<span class="w-picker__order w-ltr">' + (i + 1) + '</span>' +
          '<span class="w-picker__text"><strong>' + esc(o.label) + '</strong>' + (o.sub ? '<span>' + esc(o.sub) + '</span>' : '') + (o.inactive ? '<span class="w-tag">' + esc(L.inactive) + '</span>' : '') + '</span>' +
          iconBtn('up', L.moveUp, 'fa-arrow-up', i === 0 ? ' is-off' : '') +
          iconBtn('down', L.moveDown, 'fa-arrow-down', i === selected.length - 1 ? ' is-off' : '') +
          iconBtn('remove', L.remove, 'fa-times') +
          '</li>';
      }).join('') : '<li class="w-picker__none">' + esc(L.noneSelected) + '</li>';
    }

    function renderMenu() {
      var q = input.value.trim().toLowerCase();
      matches = data.options.filter(function (o) {
        return selected.indexOf(o.id) === -1 && (!q || (o.label + ' ' + o.sub).toLowerCase().indexOf(q) !== -1);
      }).slice(0, 50);
      active = matches.length ? 0 : -1;
      menu.innerHTML = matches.length ? matches.map(function (o, i) {
        return '<li role="option" id="' + listId + '-' + i + '" data-id="' + o.id + '" aria-selected="' + (i === active) + '"><strong>' + esc(o.label) + '</strong>' + (o.sub ? '<span>' + esc(o.sub) + '</span>' : '') + '</li>';
      }).join('') : '<li class="w-picker__empty">' + esc(L.noMatches) + '</li>';
      open(true);
    }
    function open(on) {
      menu.hidden = !on;
      input.setAttribute('aria-expanded', String(on));
      if (!on) { input.removeAttribute('aria-activedescendant'); }
    }
    function highlight(i) {
      active = i;
      Array.prototype.forEach.call(menu.querySelectorAll('[role="option"]'), function (li, n) { li.setAttribute('aria-selected', String(n === i)); });
      if (i >= 0) {
        input.setAttribute('aria-activedescendant', listId + '-' + i);
        var el = document.getElementById(listId + '-' + i);
        if (el) { el.scrollIntoView({ block: 'nearest' }); }
      }
    }
    function add(id) {
      selected.push(id);
      renderChosen();
      input.value = '';
      open(false);
      changed(chosen.lastElementChild.querySelector('input'));
    }

    input.addEventListener('focus', renderMenu);
    input.addEventListener('input', renderMenu);
    input.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowDown') { e.preventDefault(); if (menu.hidden) { renderMenu(); } else if (matches.length) { highlight((active + 1) % matches.length); } }
      else if (e.key === 'ArrowUp') { e.preventDefault(); if (matches.length) { highlight((active - 1 + matches.length) % matches.length); } }
      else if (e.key === 'Enter') { e.preventDefault(); if (!menu.hidden && active >= 0 && matches[active]) { add(matches[active].id); } }
      else if (e.key === 'Escape') { open(false); }
    });
    input.addEventListener('blur', function () { setTimeout(function () { open(false); }, 150); });
    menu.addEventListener('mousedown', function (e) {
      var li = e.target.closest('[data-id]');
      if (li) { e.preventDefault(); add(+li.getAttribute('data-id')); }
    });
    chosen.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-act]');
      if (!btn) { return; }
      var i = Array.prototype.indexOf.call(chosen.children, btn.closest('li'));
      var act = btn.getAttribute('data-act');
      if (act === 'remove') { selected.splice(i, 1); }
      else if (!moveItem(selected, i, act === 'up' ? i - 1 : i + 1)) { return; }
      renderChosen();
      changed(formEl.querySelector('[name="event_id"]'));
      var again = chosen.children[act === 'remove' ? Math.min(i, selected.length - 1) : (act === 'up' ? i - 1 : i + 1)];
      var focusBtn = again && again.querySelector('[data-act="' + act + '"]:not(.is-off)');
      if (focusBtn) { focusBtn.focus(); } else { input.focus(); }
    });

    renderChosen();
  }
  Array.prototype.forEach.call(formEl.querySelectorAll('[data-picker]'), function (el) { new Picker(el); });

  /* ================================================================ tickets */

  var tickets = (cfg.tickets || []).map(function (t) { return Object.assign({}, t); });
  var money = function (n) { return Number(n).toLocaleString('en-US', { maximumFractionDigits: 2 }) + ' ' + cfg.currency; };

  function syncTickets() {
    setJson('tickets-data', tickets.map(function (t, i) {
      return {
        id: t.id || null,
        name: t.name,
        description: t.description || '',
        price: t.coupon_only ? 0 : Number(t.price) || 0,
        quantity: t.quantity > 0 ? t.quantity : -1,
        min_per_order: t.min_per_order || 1,
        max_per_order: t.max_per_order || 10,
        sale_start: t.sale_start ? t.sale_start.replace('T', ' ') : '',
        sale_end: t.sale_end ? t.sale_end.replace('T', ' ') : '',
        is_active: !!t.is_active,
        use_coupons: t.coupon_only ? 1 : 0,
        ticket_type: t.ticket_type || 'general',
        sort_order: i
      };
    }));
  }

  function renderTickets() {
    var body = document.getElementById('tickets-body');
    if (!tickets.length) {
      body.innerHTML = '<tr><td colspan="5" class="w-subtable__empty">' + esc(L.noTickets) + '</td></tr>';
    } else {
      body.innerHTML = tickets.map(function (t, i) {
        var price = t.coupon_only ? '<span class="w-tag w-tag--gold">' + esc(L.couponOnly) + '</span>' : (Number(t.price) > 0 ? esc(money(t.price)) : '<span class="text-success">' + esc(L.free) + '</span>');
        var tags = (t.ticket_type === 'competitor' ? ' <span class="w-tag">' + esc(L.competitor) + '</span>' : '') + (!t.id ? ' <span class="w-tag w-tag--primary">' + esc(L.newTicket) + '</span>' : '');
        return '<tr data-index="' + i + '">' +
          '<td><strong>' + esc(t.name) + '</strong>' + tags + (t.description ? '<span class="w-sub">' + esc(t.description) + '</span>' : '') + '</td>' +
          '<td class="w-ltr">' + price + '</td>' +
          '<td class="w-ltr">' + (t.registered || 0) + ' / ' + (t.quantity > 0 ? t.quantity : esc(L.unlimited)) + '</td>' +
          '<td>' + (t.is_active ? '<span class="w-tag w-tag--teal">' + esc(L.onSale) + '</span>' : '<span class="w-tag">' + esc(L.offSale) + '</span>') + '</td>' +
          '<td class="w-subtable__actions">' +
          iconBtn('up', L.moveUp, 'fa-arrow-up', i === 0 ? ' is-off' : '') +
          iconBtn('down', L.moveDown, 'fa-arrow-down', i === tickets.length - 1 ? ' is-off' : '') +
          '<button type="button" class="btn btn-sm btn-secondary" data-act="edit">' + esc(L.edit) + '</button>' +
          iconBtn('remove', L.remove, 'fa-trash', ' w-icon-btn--danger') +
          '</td></tr>';
      }).join('');
    }
    syncTickets();
  }

  var $ticketModal = $('#ticketModal');
  var ticketForm = document.getElementById('ticket-form');
  var editingTicket = -1;

  function ticketError(input, message) {
    var wrap = input.closest('.w-field');
    wrap.classList.add('has-error');
    var p = document.createElement('p');
    p.className = 'w-field__error';
    p.textContent = message;
    wrap.appendChild(p);
  }
  function syncCouponOnly() {
    var on = ticketForm.coupon_only.checked;
    ticketForm.querySelector('[data-ticket-price]').hidden = on;
    if (on) { ticketForm.price.value = 0; }
  }
  ticketForm.coupon_only.addEventListener('change', syncCouponOnly);

  function openTicket(index) {
    var t = index >= 0 ? tickets[index] : null;
    editingTicket = index;
    ticketForm.reset();
    ticketForm.querySelectorAll('.w-field__error').forEach(function (p) { p.remove(); });
    ticketForm.querySelectorAll('.has-error').forEach(function (w) { w.classList.remove('has-error'); });
    document.getElementById('ticket-modal-title').textContent = t ? L.editTicket : L.addTicket;
    if (t) {
      ticketForm.name.value = t.name;
      ticketForm.description.value = t.description || '';
      ticketForm.price.value = t.price;
      ticketForm.quantity.value = t.quantity > 0 ? t.quantity : '';
      ticketForm.min_per_order.value = t.min_per_order || 1;
      ticketForm.max_per_order.value = t.max_per_order || 10;
      ticketForm.sale_start.value = t.sale_start || '';
      ticketForm.sale_end.value = t.sale_end || '';
      ticketForm.is_active.checked = !!t.is_active;
      ticketForm.coupon_only.checked = !!t.coupon_only;
      ticketForm.querySelector('[name="ticket_type"][value="' + (t.ticket_type === 'competitor' ? 'competitor' : 'general') + '"]').checked = true;
    }
    syncCouponOnly();
    $ticketModal.modal('show');
  }
  $ticketModal.on('shown.bs.modal', function () { ticketForm.name.focus(); });

  document.getElementById('add-ticket-btn').addEventListener('click', function () { openTicket(-1); });

  ticketForm.addEventListener('submit', function (e) {
    e.preventDefault();
    ticketForm.querySelectorAll('.w-field__error').forEach(function (p) { p.remove(); });
    ticketForm.querySelectorAll('.has-error').forEach(function (w) { w.classList.remove('has-error'); });
    var min = parseInt(ticketForm.min_per_order.value, 10) || 1;
    var max = parseInt(ticketForm.max_per_order.value, 10) || 10;
    var bad = false;
    if (!ticketForm.name.value.trim()) { ticketError(ticketForm.name, L.errTicketName); bad = true; }
    if (max < min) { ticketError(ticketForm.max_per_order, L.errTicketMax); bad = true; }
    if (ticketForm.sale_start.value && ticketForm.sale_end.value && ticketForm.sale_end.value < ticketForm.sale_start.value) { ticketError(ticketForm.sale_end, L.errSaleEnd); bad = true; }
    if (bad) { ticketForm.querySelector('.has-error input').focus(); return; }

    var base = editingTicket >= 0 ? tickets[editingTicket] : { id: null, registered: 0 };
    var next = Object.assign({}, base, {
      name: ticketForm.name.value.trim(),
      description: ticketForm.description.value.trim(),
      price: ticketForm.coupon_only.checked ? 0 : Math.max(0, parseFloat(ticketForm.price.value) || 0),
      quantity: parseInt(ticketForm.quantity.value, 10) > 0 ? parseInt(ticketForm.quantity.value, 10) : -1,
      min_per_order: min,
      max_per_order: max,
      sale_start: ticketForm.sale_start.value,
      sale_end: ticketForm.sale_end.value,
      is_active: ticketForm.is_active.checked,
      coupon_only: ticketForm.coupon_only.checked,
      ticket_type: ticketForm.querySelector('[name="ticket_type"]:checked').value
    });
    if (editingTicket >= 0) { tickets[editingTicket] = next; } else { tickets.push(next); }
    renderTickets();
    $ticketModal.modal('hide');
  });

  document.getElementById('tickets-body').addEventListener('click', function (e) {
    var btn = e.target.closest('[data-act]');
    if (!btn || btn.classList.contains('is-off')) { return; }
    var i = +btn.closest('tr').getAttribute('data-index');
    var act = btn.getAttribute('data-act');
    if (act === 'edit') { openTicket(i); return; }
    if (act === 'remove') {
      var t = tickets[i];
      var ask = t.registered ? fmt(L.removeHeld, t.registered) : L.removeTicket;
      window.showDeleteConfirm(ask).then(function (r) {
        if (r.isConfirmed) { tickets.splice(i, 1); renderTickets(); }
      });
      return;
    }
    if (moveItem(tickets, i, act === 'up' ? i - 1 : i + 1)) { renderTickets(); }
  });

  renderTickets();

  /* ===================================================== generic repeater */

  /**
   * A list of cards with move/remove, re-drawn on structural changes and read
   * back from the DOM on input (so typing never loses focus).
   */
  function Repeater(opts) {
    var list = document.getElementById(opts.list);
    var items = opts.items;

    function render() {
      list.innerHTML = items.length ? items.map(function (item, i) {
        return '<div class="w-repeat__item" data-index="' + i + '">' +
          '<div class="w-repeat__head"><span class="w-repeat__num w-ltr">' + (i + 1) + '</span>' +
          '<span class="w-repeat__title">' + esc(opts.title(item, i)) + '</span>' +
          iconBtn('up', L.moveUp, 'fa-arrow-up', i === 0 ? ' is-off' : '') +
          iconBtn('down', L.moveDown, 'fa-arrow-down', i === items.length - 1 ? ' is-off' : '') +
          iconBtn('remove', L.remove, 'fa-trash', ' w-icon-btn--danger') +
          '</div><div class="w-repeat__body">' + opts.body(item, i) + '</div></div>';
      }).join('') : '<p class="w-repeat__empty">' + esc(opts.empty) + '</p>';
      if (opts.afterRender) { opts.afterRender(list); }
      sync();
    }
    function sync() { setJson(opts.hidden, opts.serialize(items)); }
    function readItem(el) {
      var i = +el.getAttribute('data-index');
      el.querySelectorAll('[data-key]').forEach(function (input) {
        var key = input.getAttribute('data-key');
        if (input.closest('[data-card]')) { return; }
        items[i][key] = input.type === 'checkbox' ? input.checked : input.value;
      });
      if (opts.readExtra) { opts.readExtra(items[i], el); }
      var title = el.querySelector('.w-repeat__title');
      if (title) { title.textContent = opts.title(items[i], i); }
    }

    list.addEventListener('input', function (e) {
      var el = e.target.closest('.w-repeat__item');
      if (el) { readItem(el); sync(); }
    });
    list.addEventListener('change', function (e) {
      var el = e.target.closest('.w-repeat__item');
      if (!el || e.target.type === 'hidden') { return; }
      readItem(el);
      if (opts.rerenderOn && opts.rerenderOn.indexOf(e.target.getAttribute('data-key')) !== -1) { render(); } else { sync(); }
    });
    list.addEventListener('click', function (e) {
      var btn = e.target.closest('.w-repeat__head [data-act]');
      if (!btn || btn.classList.contains('is-off')) { return; }
      var i = +btn.closest('.w-repeat__item').getAttribute('data-index');
      var act = btn.getAttribute('data-act');
      if (act === 'remove') { items.splice(i, 1); }
      else if (!moveItem(items, i, act === 'up' ? i - 1 : i + 1)) { return; }
      render();
      var target = list.children[act === 'remove' ? Math.min(i, items.length - 1) : (act === 'up' ? i - 1 : i + 1)];
      var focusBtn = target && target.querySelector('.w-repeat__head [data-act="' + act + '"]:not(.is-off)');
      if (focusBtn) { focusBtn.focus(); }
    });

    document.getElementById(opts.addButton).addEventListener('click', function () {
      items.push(opts.blank());
      render();
      var last = list.lastElementChild;
      var first = last && last.querySelector('input:not([type="hidden"]), textarea, select');
      if (first) { first.focus(); }
    });

    render();
    return { items: items, render: render, sync: sync };
  }

  var field = function (id, label, control, extraClass) {
    return '<div class="w-field' + (extraClass ? ' ' + extraClass : '') + '"><label for="' + id + '">' + esc(label) + '</label>' + control + '</div>';
  };

  /* ============================================== registration questions */

  var questions = Repeater({
    list: 'questions-list',
    hidden: 'extra-fields-data',
    addButton: 'add-question-btn',
    empty: L.noQuestions,
    items: (cfg.questions || []).map(function (q) {
      return { label: q.label || '', type: q.type || q.field_type || 'text', required: !!q.required && q.required !== 'false', options: Array.isArray(q.options) ? q.options.join('\n') : (q.options || ''), default: q.default || '' };
    }),
    blank: function () { return { label: '', type: 'text', required: false, options: '', default: '' }; },
    title: function (q) { return q.label || L.question; },
    rerenderOn: ['type'],
    body: function (q, i) {
      var types = Object.keys(L.types).map(function (k) { return '<option value="' + k + '"' + (q.type === k ? ' selected' : '') + '>' + esc(L.types[k]) + '</option>'; }).join('');
      if (!L.types[q.type]) { types += '<option value="' + esc(q.type) + '" selected>' + esc(q.type) + '</option>'; }
      return '<div class="w-fields w-fields--wide">' +
        field('q-label-' + i, L.question, '<input type="text" class="form-control" id="q-label-' + i + '" data-key="label" value="' + esc(q.label) + '">') +
        field('q-type-' + i, L.type, '<select class="form-control" id="q-type-' + i + '" data-key="type">' + types + '</select>') +
        '</div>' +
        (q.type === 'select' ? field('q-options-' + i, L.options, '<textarea class="form-control" id="q-options-' + i + '" data-key="options" rows="3">' + esc(q.options) + '</textarea>') : '') +
        '<label class="w-switch"><input type="checkbox" data-key="required"' + (q.required ? ' checked' : '') + '><span class="w-switch__track" aria-hidden="true"></span><span class="w-switch__text"><strong>' + esc(L.required) + '</strong></span></label>';
    },
    serialize: function (items) {
      return items.filter(function (q) { return q.label.trim() || (q.type === 'select' && q.options.trim()); }).map(function (q) {
        return { label: q.label.trim(), type: q.type, required: !!q.required, options: q.type === 'select' ? q.options : '', default: q.default || '' };
      });
    }
  });

  /* ==================================================================== FAQ */

  var faq = Repeater({
    list: 'faq-list',
    hidden: 'faq-data',
    addButton: 'add-faq-btn',
    empty: L.noFaq,
    items: (cfg.faq || []).map(function (f) {
      return { question: f.question || f.q || f.sc_faq_title || '', answer: f.answer || f.a || f.sc_faq_content || '' };
    }),
    blank: function () { return { question: '', answer: '' }; },
    title: function (f) { return f.question || L.faqQuestion; },
    body: function (f, i) {
      return field('faq-q-' + i, L.faqQuestion, '<input type="text" class="form-control" id="faq-q-' + i + '" data-key="question" value="' + esc(f.question) + '">') +
        field('faq-a-' + i, L.faqAnswer, '<textarea class="form-control" id="faq-a-' + i + '" data-key="answer" rows="3">' + esc(f.answer) + '</textarea>');
    },
    serialize: function (items) {
      return items.filter(function (f) { return f.question.trim() || f.answer.trim(); }).map(function (f) { return { question: f.question.trim(), answer: f.answer }; });
    }
  });

  /* ========================================================== page sections */

  var sectionTypes = L.sectionTypes;
  var sections = Repeater({
    list: 'sections-list',
    hidden: 'sections-data',
    addButton: 'add-section-btn',
    empty: L.noSections,
    items: (cfg.sections || []).map(function (s) {
      return {
        id: s.id || '',
        type: s.type || 'about',
        heading: s.heading || s.title || '',
        content: s.content || '',
        button_text: s.button_text || s.btn_text || '',
        button_url: s.button_url || s.btn_url || '',
        main_image: s.main_image || '',
        images: (s.images || []).map(function (id) { return { id: id, url: (s.image_urls || {})[String(id)] || '' }; }),
        cards: (s.cards || []).map(function (c) { return { icon: c.icon || '', title: c.title || '', description: c.description || c.content || '', image: c.image || 0, image_url: c.image_url || '' }; })
      };
    }),
    blank: function () { return { id: '', type: 'about', heading: '', content: '', button_text: '', button_url: '', main_image: '', images: [], cards: [] }; },
    title: function (s) { return (s.heading || sectionTypes[s.type] || s.type); },
    rerenderOn: ['type'],
    body: function (s, i) {
      var types = Object.keys(sectionTypes).map(function (k) { return '<option value="' + k + '"' + (s.type === k ? ' selected' : '') + '>' + esc(sectionTypes[k]) + '</option>'; }).join('');
      if (!sectionTypes[s.type]) { types += '<option value="' + esc(s.type) + '" selected>' + esc(s.type) + '</option>'; }
      var html = '<div class="w-fields w-fields--wide">' +
        field('s-heading-' + i, L.heading, '<input type="text" class="form-control" id="s-heading-' + i + '" data-key="heading" value="' + esc(s.heading) + '">') +
        field('s-type-' + i, L.layout, '<select class="form-control" id="s-type-' + i + '" data-key="type">' + types + '</select>') +
        '</div>';
      if (s.type === 'about' || s.content) {
        html += field('s-content-' + i, L.text, '<textarea class="form-control" id="s-content-' + i + '" data-key="content" rows="4">' + esc(s.content) + '</textarea>');
      }
      if (s.type !== 'card' || s.images.length) {
        html += '<div class="w-field"><span class="w-field__label">' + esc(L.photos) + '</span><div class="w-thumbs">' +
          s.images.map(function (img, n) {
            return '<span class="w-thumbs__item" data-photo="' + n + '">' + (img.url ? '<img src="' + esc(img.url) + '" alt="">' : '<i class="fa fa-image" aria-hidden="true"></i>') +
              '<button type="button" class="w-thumbs__x" data-photo-remove="' + n + '" aria-label="' + esc(L.remove) + '">&times;</button></span>';
          }).join('') +
          '<button type="button" class="w-thumbs__add" data-photos-add><i class="fa fa-plus" aria-hidden="true"></i><span>' + esc(L.addPhotos) + '</span></button>' +
          '</div></div>';
      }
      if (s.type === 'card' || s.cards.length) {
        html += '<div class="w-field"><span class="w-field__label">' + esc(L.cards) + '</span><div class="w-cards">' +
          s.cards.map(function (c, n) {
            return '<div class="w-cards__item" data-card="' + n + '">' +
              '<button type="button" class="w-cards__img" data-card-image="' + n + '" aria-label="' + esc(L.chooseImage) + '">' + (c.image_url ? '<img src="' + esc(c.image_url) + '" alt="">' : '<i class="fa fa-image" aria-hidden="true"></i>') + '</button>' +
              '<div class="w-cards__fields">' +
              '<input type="text" class="form-control" data-card-key="title" placeholder="' + esc(L.cardTitle) + '" aria-label="' + esc(L.cardTitle) + '" value="' + esc(c.title) + '">' +
              '<textarea class="form-control" data-card-key="description" rows="2" placeholder="' + esc(L.cardText) + '" aria-label="' + esc(L.cardText) + '">' + esc(c.description) + '</textarea>' +
              '</div>' +
              '<button type="button" class="w-icon-btn w-icon-btn--danger" data-card-remove="' + n + '" aria-label="' + esc(L.remove) + '"><i class="fa fa-trash" aria-hidden="true"></i></button>' +
              '</div>';
          }).join('') +
          '<button type="button" class="btn btn-sm btn-secondary" data-card-add><i class="fa fa-plus" aria-hidden="true"></i> ' + esc(L.addCard) + '</button>' +
          '</div></div>';
      }
      if (s.type === 'about' || s.button_text || s.button_url) {
        html += '<div class="w-fields w-fields--2">' +
          field('s-btn-' + i, L.buttonText, '<input type="text" class="form-control" id="s-btn-' + i + '" data-key="button_text" value="' + esc(s.button_text) + '">') +
          field('s-url-' + i, L.buttonUrl, '<input type="url" class="form-control w-ltr" id="s-url-' + i + '" data-key="button_url" value="' + esc(s.button_url) + '" placeholder="https://">') +
          '</div>';
      }
      return html;
    },
    readExtra: function (s, el) {
      el.querySelectorAll('[data-card]').forEach(function (cardEl) {
        var c = s.cards[+cardEl.getAttribute('data-card')];
        if (!c) { return; }
        cardEl.querySelectorAll('[data-card-key]').forEach(function (input) { c[input.getAttribute('data-card-key')] = input.value; });
      });
    },
    serialize: function (items) {
      return items.map(function (s) {
        return {
          id: s.id || undefined,
          type: s.type,
          heading: s.heading.trim(),
          content: s.content,
          button_text: s.button_text.trim(),
          button_url: s.button_url.trim(),
          main_image: s.main_image || '',
          images: s.images.map(function (img) { return img.id; }),
          cards: s.cards.filter(function (c) { return c.title.trim() || c.description.trim() || c.image; }).map(function (c) {
            return { icon: c.icon, title: c.title.trim(), description: c.description, image: c.image || 0 };
          })
        };
      }).filter(function (s) { return s.heading || s.content.trim() || s.images.length || s.cards.length; });
    }
  });

  document.getElementById('sections-list').addEventListener('click', function (e) {
    var itemEl = e.target.closest('.w-repeat__item');
    if (!itemEl) { return; }
    var s = sections.items[+itemEl.getAttribute('data-index')];
    var t;
    if (e.target.closest('[data-photos-add]')) {
      pickMedia(true, L.addPhotos, function (picked) {
        picked.forEach(function (p) { if (!s.images.some(function (x) { return String(x.id) === String(p.id); })) { s.images.push(p); } });
        sections.render();
      });
    } else if ((t = e.target.closest('[data-photo-remove]'))) {
      s.images.splice(+t.getAttribute('data-photo-remove'), 1);
      sections.render();
    } else if (e.target.closest('[data-card-add]')) {
      s.cards.push({ icon: '', title: '', description: '', image: 0, image_url: '' });
      sections.render();
      var cards = document.querySelectorAll('.w-repeat__item[data-index="' + itemEl.getAttribute('data-index') + '"] [data-card] [data-card-key="title"]');
      if (cards.length) { cards[cards.length - 1].focus(); }
    } else if ((t = e.target.closest('[data-card-remove]'))) {
      s.cards.splice(+t.getAttribute('data-card-remove'), 1);
      sections.render();
    } else if ((t = e.target.closest('[data-card-image]'))) {
      var card = s.cards[+t.getAttribute('data-card-image')];
      pickMedia(false, L.chooseImage, function (p) { card.image = p.id; card.image_url = p.url; sections.render(); });
    }
  });

  /* ============================================================ social links */

  var networks = L.networks;
  var links = Repeater({
    list: 'links-list',
    hidden: 'social-links-data',
    addButton: 'add-link-btn',
    empty: L.noLinks,
    items: (cfg.links || []).map(function (l) { return { icon: l.icon || 'fa-globe', url: l.url || '' }; }),
    blank: function () { return { icon: 'fa-facebook', url: '' }; },
    title: function (l) { return networks[l.icon] || l.icon || L.link; },
    body: function (l, i) {
      var opts = Object.keys(networks).map(function (k) { return '<option value="' + k + '"' + (l.icon === k ? ' selected' : '') + '>' + esc(networks[k]) + '</option>'; }).join('');
      if (!networks[l.icon]) { opts += '<option value="' + esc(l.icon) + '" selected>' + esc(L.otherIcon + ' (' + l.icon + ')') + '</option>'; }
      return '<div class="w-fields w-fields--link">' +
        field('l-icon-' + i, L.network, '<select class="form-control" id="l-icon-' + i + '" data-key="icon">' + opts + '</select>') +
        field('l-url-' + i, L.link, '<input type="url" class="form-control w-ltr" id="l-url-' + i + '" data-key="url" value="' + esc(l.url) + '" placeholder="https://">') +
        '</div>';
    },
    serialize: function (items) {
      return items.filter(function (l) { return l.url.trim(); }).map(function (l) { return { icon: l.icon, url: l.url.trim() }; });
    }
  });

  /* ================================================================== form */

  var titleInput = document.getElementById('f-title');
  var slugInput = document.getElementById('f-slug');
  var slugTouched = !!slugInput.value;
  var slugify = function (v) {
    return String(v).toLowerCase().normalize('NFKD').replace(/[̀-ͯ]/g, '').replace(/[^\p{L}\p{N}]+/gu, '-').replace(/^-+|-+$/g, '').slice(0, 190);
  };
  titleInput.addEventListener('input', function () {
    document.querySelector('[data-w-title]').textContent = titleInput.value.trim() || L.untitled;
    if (!cfg.isEdit && !slugTouched) { slugInput.value = slugify(titleInput.value); }
  });
  slugInput.addEventListener('input', function () { slugTouched = slugInput.value !== ''; });
  slugInput.addEventListener('change', function () { slugInput.value = slugify(slugInput.value); });

  var form = WDForm.create({
    form: formEl,
    i18n: { saving: L.saving, fixErrors: L.fixErrors, errorsTitle: L.errorsTitle, errorTitleOne: L.errorTitleOne, failed: L.failed, leave: L.leave, savedJustNow: L.savedAt },
    validate: function (v) {
      var e = [];
      if (!String(v.event_title || '').trim()) { e.push({ field: 'event_title', message: L.errTitle }); }
      if (!v.start_date) { e.push({ field: 'start_date', message: L.errStart }); }
      if (v.end_date && v.start_date && v.end_date < v.start_date) { e.push({ field: 'end_date', message: L.errEndDate }); }
      var sameDay = !v.end_date || v.end_date === v.start_date;
      if (sameDay && v.start_time && v.end_time && v.end_time <= v.start_time) { e.push({ field: 'end_time', message: L.errEndTime }); }
      if (v.location_type === 'online' || v.location_type === 'hybrid') {
        if (!String(v.meeting_link || '').trim()) { e.push({ field: 'meeting_link', message: L.errMeeting }); }
        else if (!isUrl(v.meeting_link)) { e.push({ field: 'meeting_link', message: L.errUrl }); }
      }
      questions.items.forEach(function (q, i) {
        if (!q.label.trim() && (q.options.trim() || q.required)) { e.push({ field: 'q-label-' + i, message: fmt(L.errQuestion, i + 1) }); }
        if (q.label.trim() && q.type === 'select' && !q.options.trim()) { e.push({ field: 'q-options-' + i, message: fmt(L.errOptions, i + 1) }); }
      });
      faq.items.forEach(function (f, i) {
        if (!f.question.trim() && f.answer.trim()) { e.push({ field: 'faq-q-' + i, message: fmt(L.errFaq, i + 1) }); }
      });
      links.items.forEach(function (l, i) {
        if (l.url.trim() && !/^(https?:\/\/|mailto:|tel:)\S+/i.test(l.url.trim())) { e.push({ field: 'l-url-' + i, message: fmt(L.errLink, i + 1) }); }
      });
      return e;
    },
    submit: function (fd) {
      fd.append('action', 'sc_create_or_update_event');
      fd.append('nonce', window.scDashboard.nonce);
      return fetch(window.scDashboard.ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); });
    },
    onSuccess: function (data, api) {
      if (!cfg.isEdit && data.redirect) {
        api.markClean();
        window.location.href = data.redirect;
        return;
      }
      // New tickets got ids and the programme file may have changed: reload so the page shows what was stored.
      var reloadNeeded = tickets.some(function (t) { return !t.id; }) || tickets.length !== (cfg.tickets || []).length ||
        (formEl.schedules_file && formEl.schedules_file.value) || (formEl.remove_schedules_file && formEl.remove_schedules_file.checked);
      if (data.slug) {
        slugInput.value = data.slug;
        var preview = document.querySelector('[data-w-preview]');
        if (preview) { preview.href = cfg.eventBase + data.slug + '/'; }
      }
      api.markClean();
      if (reloadNeeded) {
        try { sessionStorage.setItem('scEventSaved', '1'); } catch (x) { /* storage blocked */ }
        window.location.reload();
        return;
      }
      if (window.toastr) { window.toastr.success(data.message || L.saved); }
    }
  });

  try {
    if (sessionStorage.getItem('scEventSaved')) {
      sessionStorage.removeItem('scEventSaved');
      if (window.toastr) { window.toastr.success(L.saved); }
    }
  } catch (x) { /* storage blocked */ }
  if (/[?&]created=1/.test(location.search)) {
    if (window.toastr) { window.toastr.success(L.created); }
    history.replaceState(null, '', location.pathname + location.search.replace(/[?&]created=1/, '').replace(/^&/, '?'));
  }

  var deleteBtn = document.getElementById('delete-event-btn');
  if (deleteBtn) {
    deleteBtn.addEventListener('click', function () {
      window.showDeleteConfirm(L.deleteEvent).then(function (r) {
        if (!r.isConfirmed) { return; }
        var fd = new FormData();
        fd.append('action', 'sc_delete_event');
        fd.append('nonce', window.scDashboard.nonce);
        fd.append('event_id', cfg.eventId);
        fetch(window.scDashboard.ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (res) { return res.json(); }).then(function (res) {
          if (res.success) {
            form.markClean();
            window.location.href = cfg.dashboardUrl + 'events';
          } else if (window.toastr) {
            window.toastr.error(res.data && res.data.message ? res.data.message : L.failed);
          }
        });
      });
    });
  }
});
