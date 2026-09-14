/**
 * WDForm — behaviour for dashboard create/edit forms. Plain DOM; no jQuery.
 *
 *   const form = WDForm.create({
 *     form: document.getElementById('workshop-form'),
 *     validate: values => [{ field: 'title', message: '…' }],   // client rules
 *     submit: formData => fetch(…).then(r => r.json()),          // resolves to WP JSON
 *     onSuccess: (data, api) => {},
 *     i18n: { … }
 *   });
 *
 * Markup hooks:
 *   [data-w-save]                 save buttons (head + phone bar)
 *   [data-w-dirty]                "Unsaved changes" badge
 *   [data-w-saved]                "Saved …" text
 *   [data-w-errors]               error summary container
 *   [data-w-secnav] a[href="#id"] section nav; sections are .w-section[id]
 *   [data-w-show-if="name:a,b"]   shown only while field `name` has value a or b
 *   [data-w-count="max"]          on a textarea/input: live "n / max" counter
 *   [data-w-media]                image picker: hidden input + preview + choose/remove
 *
 * Server errors: a JSON error whose data.errors is {field: message} maps onto fields.
 *
 * @package sc_events
 */
(function (window, document) {
  'use strict';

  var instances = [];

  function create(opts) {
    var form = opts.form;
    var T = Object.assign({
      saving: 'Saving…',
      save: 'Save changes',
      fixErrors: 'Fix %d to save',
      errorsTitle: '%d fields need attention before saving.',
      errorTitleOne: 'One field needs attention before saving.',
      savedJustNow: 'Saved just now',
      failed: 'Could not save. Please try again.',
      leave: 'You have unsaved changes.'
    }, opts.i18n || {});

    // Several forms can share a page (e.g. settings): each one owns the save buttons, badges and
    // error box inside it. A page with a single form may keep them outside it, as before.
    instances.push(form);
    var own = function (sel) { return Array.prototype.slice.call(form.querySelectorAll(sel)); };
    var loose = function (sel) {
      return Array.prototype.slice.call(document.querySelectorAll(sel)).filter(function (el) {
        return !el.closest('form') || el.closest('form') === form;
      });
    };
    var saveButtons = own('[data-w-save]').length ? own('[data-w-save]') : loose('[data-w-save]');
    var dirtyBadge = form.querySelector('[data-w-dirty]') || loose('[data-w-dirty]')[0] || null;
    var savedText = form.querySelector('[data-w-saved]') || loose('[data-w-saved]')[0] || null;
    var summary = form.querySelector('[data-w-errors]') || loose('[data-w-errors]')[0] || null;
    var saving = false;
    var errors = [];

    /* ------------------------------------------------------------ values */

    function syncEditors() {
      if (window.tinymce && window.tinymce.editors) {
        window.tinymce.editors.forEach(function (ed) { try { ed.save(); } catch (e) {} });
      }
    }

    function snapshot() {
      syncEditors();
      var fd = new FormData(form);
      var parts = [];
      fd.forEach(function (v, k) { if (typeof v === 'string') { parts.push(k + '=' + v); } });
      return parts.join('&');
    }

    function values() {
      syncEditors();
      var out = {};
      new FormData(form).forEach(function (v, k) {
        if (k.slice(-2) === '[]') { (out[k] = out[k] || []).push(v); } else { out[k] = v; }
      });
      Array.prototype.slice.call(form.querySelectorAll('input[type="checkbox"][name]')).forEach(function (cb) {
        if (!(cb.name in out) && cb.name.slice(-2) !== '[]') { out[cb.name] = ''; }
      });
      return out;
    }

    var clean = snapshot();

    /* ------------------------------------------------------------- dirty */

    function isDirty() { return snapshot() !== clean; }

    function refreshDirty() {
      var dirty = isDirty();
      if (dirtyBadge) { dirtyBadge.hidden = !dirty; }
      return dirty;
    }

    var dirtyTimer = null;
    function queueDirty() {
      clearTimeout(dirtyTimer);
      dirtyTimer = setTimeout(refreshDirty, 150);
    }

    form.addEventListener('input', function (e) { queueDirty(); clearFieldError(e.target); });
    form.addEventListener('change', function (e) { queueDirty(); clearFieldError(e.target); applyShowIf(); });

    // TinyMCE lives in an iframe; hook its change events once editors exist.
    function hookEditors() {
      if (!window.tinymce) { return; }
      window.tinymce.editors.forEach(function (ed) {
        if (ed._wdHooked) { return; }
        ed._wdHooked = true;
        ed.on('change keyup undo redo SetContent', queueDirty);
        // An editor created before this form but not yet initialised rewrites its
        // markup on init (style="a" → style="a;"); re-take the baseline then.
        if (!ed.initialized) {
          ed.on('init', function () { clean = snapshot(); refreshDirty(); });
        }
      });
    }
    if (window.tinymce) {
      hookEditors();
      window.tinymce.on('AddEditor', function (e) {
        e.editor.on('init', function () {
          // The editor rewrites markup on init; take the baseline after that.
          clean = snapshot();
          hookEditors();
          refreshDirty();
        });
      });
    }

    window.addEventListener('beforeunload', function (e) {
      if (!saving && isDirty()) {
        e.preventDefault();
        e.returnValue = T.leave;
        return T.leave;
      }
    });

    /* ------------------------------------------------------------ errors */

    function fieldWrap(name) {
      var input = form.querySelector('[name="' + CSS.escape(name) + '"]') || document.getElementById(name);
      return input ? (input.closest('.w-field') || input.parentElement) : null;
    }

    function clearFieldError(input) {
      var wrap = input && input.closest ? input.closest('.w-field') : null;
      if (!wrap || !wrap.classList.contains('has-error')) { return; }
      wrap.classList.remove('has-error');
      var msg = wrap.querySelector('.w-field__error');
      if (msg) { msg.remove(); }
      errors = errors.filter(function (er) { var w = fieldWrap(er.field); return w !== wrap; });
      renderSummary();
    }

    function clearErrors() {
      form.querySelectorAll('.w-field.has-error').forEach(function (w) {
        w.classList.remove('has-error');
        var msg = w.querySelector('.w-field__error');
        if (msg) { msg.remove(); }
      });
      errors = [];
      renderSummary();
    }

    function showErrors(list) {
      clearErrors();
      errors = list.slice();
      list.forEach(function (er) {
        var wrap = fieldWrap(er.field);
        if (!wrap) { return; }
        wrap.classList.add('has-error');
        var p = document.createElement('p');
        p.className = 'w-field__error';
        p.id = 'err-' + er.field.replace(/[^\w-]/g, '_');
        p.textContent = er.message;
        wrap.appendChild(p);
        var input = wrap.querySelector('input, select, textarea');
        if (input) {
          input.setAttribute('aria-invalid', 'true');
          input.setAttribute('aria-describedby', p.id);
        }
      });
      renderSummary();
      if (list.length) {
        var first = fieldWrap(list[0].field);
        if (summary && !summary.hidden) {
          summary.scrollIntoView({ behavior: 'smooth', block: 'center' });
          summary.focus({ preventScroll: true });
        } else if (first) {
          first.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
      }
    }

    function renderSummary() {
      var n = errors.length;
      saveButtons.forEach(function (btn) {
        if (saving) { return; }
        btn.innerHTML = n ? esc(T.fixErrors.replace('%d', n)) : btn.getAttribute('data-label') || esc(T.save);
      });

      // Section nav warning dots.
      document.querySelectorAll('[data-w-secnav] a[href^="#"]').forEach(function (a) {
        var section = document.getElementById(a.getAttribute('href').slice(1));
        var dot = a.querySelector('.w-secnav__warn');
        if (!dot || !section) { return; }
        dot.hidden = !section.querySelector('.w-field.has-error');
      });

      if (!summary) { return; }
      if (!n) { summary.hidden = true; summary.innerHTML = ''; return; }
      summary.hidden = false;
      summary.setAttribute('tabindex', '-1');
      summary.setAttribute('role', 'alert');
      summary.innerHTML =
        '<span class="w-errors__count">' + n + '</span><div><p class="w-errors__title">' +
        esc(n === 1 ? T.errorTitleOne : T.errorsTitle.replace('%d', n)) + '</p><ul class="w-errors__list">' +
        errors.map(function (er) { return '<li><a href="#" data-w-goto-field="' + esc(er.field) + '">' + esc(er.message) + '</a></li>'; }).join('') +
        '</ul></div>';
    }

    document.addEventListener('click', function (e) {
      var link = e.target.closest('[data-w-goto-field]');
      if (!link) { return; }
      e.preventDefault();
      var wrap = fieldWrap(link.getAttribute('data-w-goto-field'));
      if (!wrap) { return; }
      wrap.scrollIntoView({ behavior: 'smooth', block: 'center' });
      var input = wrap.querySelector('input:not([type="hidden"]), select, textarea');
      if (input) { setTimeout(function () { input.focus({ preventScroll: true }); }, 350); }
    });

    /* -------------------------------------------------------------- save */

    function setSaving(on) {
      saving = on;
      saveButtons.forEach(function (btn) {
        if (!btn.hasAttribute('data-label')) { btn.setAttribute('data-label', btn.innerHTML); }
        btn.disabled = on;
        btn.innerHTML = on ? '<span class="w-spinner" aria-hidden="true"></span> ' + esc(T.saving) : btn.getAttribute('data-label');
      });
      form.setAttribute('aria-busy', on ? 'true' : 'false');
    }

    function save() {
      if (saving) { return Promise.resolve(); }
      var list = opts.validate ? (opts.validate(values(), api) || []) : [];
      if (list.length) { showErrors(list); return Promise.resolve(); }
      clearErrors();
      setSaving(true);
      syncEditors();
      return Promise.resolve(opts.submit(new FormData(form), api))
        .then(function (res) {
          setSaving(false);
          if (res && res.success) {
            clean = snapshot();
            refreshDirty();
            if (savedText) { savedText.textContent = T.savedJustNow; }
            if (opts.onSuccess) { opts.onSuccess(res.data || {}, api); }
            return res;
          }
          if (res && res.cancelled) { return res; } // submit() stopped at a confirm step
          var data = (res && res.data) || {};
          if (data.errors && typeof data.errors === 'object') {
            showErrors(Object.keys(data.errors).map(function (k) { return { field: k, message: data.errors[k] }; }));
          }
          notifyError(data.message || T.failed);
          return res;
        })
        .catch(function () {
          setSaving(false);
          notifyError(T.failed);
        });
    }

    function notifyError(message) {
      if (window.toastr) { window.toastr.error(message); } else if (window.showError) { window.showError(message); } else { window.alert(message); }
    }

    form.addEventListener('submit', function (e) { e.preventDefault(); save(); });
    saveButtons.forEach(function (btn) {
      btn.setAttribute('data-label', btn.innerHTML);
      if (btn.form !== form) { btn.addEventListener('click', function (e) { e.preventDefault(); save(); }); }
    });
    document.addEventListener('keydown', function (e) {
      if ((e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'S') && !document.querySelector('.modal.show')) {
        // With more than one form on the page, Ctrl+S saves only the one being edited.
        if (instances.length > 1 && !form.contains(document.activeElement)) { return; }
        e.preventDefault();
        save();
      }
    });

    /* ----------------------------------------------------------- show-if */

    function fieldValue(name) {
      var els = form.querySelectorAll('[name="' + CSS.escape(name) + '"]');
      for (var i = 0; i < els.length; i++) {
        var el = els[i];
        // Skip the hidden 0 that sits before a checkbox of the same name.
        if (el.type === 'hidden' && els.length > 1) { continue; }
        if (el.type === 'radio' || el.type === 'checkbox') { if (el.checked) { return el.value; } }
        else { return el.value; }
      }
      return els.length > 1 && els[0].type === 'hidden' ? els[0].value : '';
    }

    function applyShowIf() {
      form.querySelectorAll('[data-w-show-if]').forEach(function (el) {
        var rule = el.getAttribute('data-w-show-if').split(':');
        var allowed = (rule[1] || '').split(',');
        el.hidden = allowed.indexOf(fieldValue(rule[0])) === -1;
      });
    }
    applyShowIf();

    /* ----------------------------------------------------------- counters */

    form.querySelectorAll('[data-w-count]').forEach(function (input) {
      var max = parseInt(input.getAttribute('data-w-count'), 10);
      var wrap = input.closest('.w-field');
      var out = wrap ? wrap.querySelector('.w-field__count') : null;
      if (!out) { return; }
      var update = function () {
        var n = input.value.length;
        out.textContent = n + ' / ' + max;
        out.classList.toggle('is-over', n > max);
      };
      input.addEventListener('input', update);
      update();
    });

    /* -------------------------------------------------------------- media */

    form.querySelectorAll('[data-w-media]').forEach(function (box) {
      var input = box.querySelector('input[type="hidden"]');
      var img = box.querySelector('img');
      var empty = box.querySelector('.w-media__empty');
      var choose = box.querySelectorAll('[data-w-media-choose]');
      var remove = box.querySelector('[data-w-media-remove]');
      var frame = null;

      function set(id, url) {
        input.value = id || '';
        if (img) { img.src = url || ''; img.hidden = !url; }
        if (empty) { empty.hidden = !!url; }
        if (remove) { remove.hidden = !url; }
        input.dispatchEvent(new Event('change', { bubbles: true }));
      }
      function open(e) {
        e.preventDefault();
        if (!window.wp || !window.wp.media) { return; }
        if (!frame) {
          frame = window.wp.media({ title: box.getAttribute('data-w-media') || 'Choose image', library: { type: 'image' }, multiple: false });
          frame.on('select', function () {
            var att = frame.state().get('selection').first().toJSON();
            var url = att.sizes && att.sizes.large ? att.sizes.large.url : att.url;
            set(att.id, url);
          });
        }
        frame.open();
      }
      Array.prototype.forEach.call(choose, function (b) { b.addEventListener('click', open); });
      if (empty) {
        empty.addEventListener('click', open);
        empty.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { open(e); } });
      }
      if (remove) { remove.addEventListener('click', function (e) { e.preventDefault(); set('', ''); }); }
    });

    /* ------------------------------------------------------------ secnav */

    var navLinks = Array.prototype.slice.call(document.querySelectorAll('[data-w-secnav] a[href^="#"]'));
    if (navLinks.length && 'IntersectionObserver' in window) {
      var visible = {};
      var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (en) { visible[en.target.id] = en.isIntersecting ? en.intersectionRatio : 0; });
        var best = null, bestTop = Infinity;
        navLinks.forEach(function (a) {
          var sec = document.getElementById(a.getAttribute('href').slice(1));
          if (sec && visible[sec.id]) {
            var top = Math.abs(sec.getBoundingClientRect().top);
            if (top < bestTop) { bestTop = top; best = a; }
          }
        });
        if (best) { navLinks.forEach(function (a) { a.setAttribute('aria-current', String(a === best)); }); }
      }, { rootMargin: '-80px 0px -45% 0px', threshold: [0, 0.1, 0.5] });
      navLinks.forEach(function (a) {
        var sec = document.getElementById(a.getAttribute('href').slice(1));
        if (sec) { io.observe(sec); }
      });
    }

    function esc(v) {
      return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c]; });
    }

    var api = {
      save: save,
      values: values,
      isDirty: isDirty,
      markClean: function () { clean = snapshot(); refreshDirty(); },
      showErrors: showErrors,
      clearErrors: clearErrors,
      applyShowIf: applyShowIf
    };
    return api;
  }

  window.WDForm = { create: create };
})(window, document);
