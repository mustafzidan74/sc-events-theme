/*
 * WhatsApp codes on the auth pages: sign in with a code (login), confirm the number when creating
 * an account (register), and reset a password (forgot-password).
 * Server: inc/auth/sc-otp-handlers.php. Texts come from window.scOtpText (printed by the page).
 *
 * The register form is also bound by public-scripts.js on document; this script handles it on the
 * form itself and stops the event there, so only one request goes out.
 */
(function () {
    'use strict';

    var T = window.scOtpText || {};
    function t(key, fallback) { return T[key] || fallback; }

    function $(sel, root) { return (root || document).querySelector(sel); }
    function $all(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

    function post(action, data) {
        var body = new FormData();
        body.append('action', action);
        body.append('nonce', window.scPublic ? scPublic.nonce : '');
        Object.keys(data || {}).forEach(function (k) { body.append(k, data[k] == null ? '' : data[k]); });
        return fetch(scPublic.ajaxurl, { method: 'POST', body: body, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .catch(function () { return { success: false, data: { message: t('network', 'Could not reach the site. Check your connection and try again.') } }; });
    }

    function say(box, text, ok) {
        if (!box) { return; }
        box.textContent = text || '';
        box.hidden = !text;
        box.classList.toggle('is-ok', !!ok);
        box.classList.toggle('is-error', !ok);
    }

    function busy(btn, on) {
        if (!btn) { return; }
        if (on) { btn.dataset.label = btn.textContent; btn.disabled = true; btn.setAttribute('aria-busy', 'true'); }
        else { btn.disabled = false; btn.removeAttribute('aria-busy'); }
    }

    // Digits only, up to 6; submit the form when the sixth digit is typed or pasted.
    function codeInput(input) {
        input.addEventListener('input', function () {
            input.value = input.value.replace(/\D/g, '').slice(0, 6);
            if (input.value.length === 6 && input.form && !input.form.dataset.autoSent) {
                input.form.dataset.autoSent = '1';
                input.form.requestSubmit ? input.form.requestSubmit() : input.form.dispatchEvent(new Event('submit', { cancelable: true }));
            }
            if (input.value.length < 6 && input.form) { delete input.form.dataset.autoSent; }
        });
    }

    // "Send a new code" stays disabled for the cooldown, with the seconds counting down.
    function resendTimer(btn, seconds) {
        if (!btn) { return; }
        clearInterval(btn._timer);
        var left = Math.max(0, parseInt(seconds, 10) || 0);
        var label = t('resend', 'Send a new code');
        function tick() {
            btn.disabled = left > 0;
            btn.textContent = left > 0 ? t('resend_in', 'New code in %s s').replace('%s', left) : label;
            if (left <= 0) { clearInterval(btn._timer); }
            left--;
        }
        tick();
        btn._timer = setInterval(tick, 1000);
    }

    function go(url) { window.location.href = url || scPublic.homeUrl; }

    function renderChoices(box, list, onPick) {
        box.innerHTML = '';
        list.forEach(function (acc) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'w-otp-account';
            var n = document.createElement('strong');
            n.textContent = acc.name;
            var e = document.createElement('span');
            e.textContent = acc.email;
            b.appendChild(n);
            b.appendChild(e);
            b.addEventListener('click', function () { onPick(acc.id, b); });
            box.appendChild(b);
        });
        box.hidden = false;
    }

    /* ------------------------------------------------------------------ login */

    var switcher = $('[data-login-modes]');
    if (switcher) {
        var pwForm = $('#login-form');
        var codeBox = $('#otp-login');
        var setMode = function (mode) {
            $all('[data-login-mode]', switcher).forEach(function (b) { b.setAttribute('aria-selected', b.dataset.loginMode === mode ? 'true' : 'false'); });
            pwForm.hidden = mode !== 'password';
            codeBox.hidden = mode !== 'code';
            try { localStorage.setItem('scLoginMode', mode); } catch (e) {}
            var first = mode === 'code' ? $('#otp-login-phone') : $('#email');
            if (first && document.activeElement && document.activeElement.closest('[data-login-modes]')) { first.focus(); }
        };
        $all('[data-login-mode]', switcher).forEach(function (b) { b.addEventListener('click', function () { setMode(b.dataset.loginMode); }); });
        var saved = null;
        try { saved = localStorage.getItem('scLoginMode'); } catch (e) {}
        if (saved === 'code' || /[?&]with=whatsapp/.test(location.search)) { setMode('code'); }

        var sendForm = $('#otp-login-send');
        var verifyForm = $('#otp-login-verify');
        var msg = $('#otp-login-msg');
        var redirect = ($('#login-form [name=redirect]') || {}).value || '';
        var phoneData = function () { return { phone_code: $('#otp-login-code').value, phone: $('#otp-login-phone').value }; };

        var send = function (btn) {
            say(msg, '');
            busy(btn, true);
            return post('sc_otp_login_send', phoneData()).then(function (r) {
                busy(btn, false);
                if (!r.success) {
                    say(msg, r.data.message, false);
                    if (r.data.resend_in) { resendTimer($('#otp-login-resend'), r.data.resend_in); }
                    return false;
                }
                sendForm.hidden = true;
                verifyForm.hidden = false;
                $('#otp-login-sent').textContent = r.data.message;
                $('#otp-login-to').textContent = r.data.phone;
                resendTimer($('#otp-login-resend'), r.data.resend_in);
                $('#otp-login-input').value = '';
                $('#otp-login-input').focus();
                return true;
            });
        };

        sendForm.addEventListener('submit', function (e) { e.preventDefault(); send(sendForm.querySelector('[type=submit]')); });
        $('#otp-login-resend').addEventListener('click', function () { send(this); });
        $('#otp-login-change').addEventListener('click', function () {
            verifyForm.hidden = true;
            sendForm.hidden = false;
            $('#otp-login-choose').hidden = true;
            say(msg, '');
            $('#otp-login-phone').focus();
        });
        codeInput($('#otp-login-input'));

        verifyForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var btn = verifyForm.querySelector('[type=submit]');
            say(msg, '');
            busy(btn, true);
            var data = phoneData();
            data.code = $('#otp-login-input').value;
            data.remember = $('#otp-login-remember').checked ? 1 : 0;
            data.redirect = redirect;
            post('sc_otp_login_verify', data).then(function (r) {
                busy(btn, false);
                delete verifyForm.dataset.autoSent;
                if (!r.success) { say(msg, r.data.message, false); $('#otp-login-input').select(); return; }
                if (r.data.choose) {
                    say(msg, r.data.message, true);
                    renderChoices($('#otp-login-choose'), r.data.choose, function (id, b) {
                        busy(b, true);
                        post('sc_otp_login_choose', { proof: r.data.proof, user_id: id, redirect: redirect }).then(function (c) {
                            if (!c.success) { busy(b, false); say(msg, c.data.message, false); return; }
                            go(c.data.redirect);
                        });
                    });
                    return;
                }
                say(msg, r.data.message, true);
                go(r.data.redirect);
            });
        });
    }

    /* --------------------------------------------------------------- register */

    var regForm = $('#register-form');
    var regVerify = $('#register-verify');
    if (regForm && regVerify) {
        var regMsg = $('#register-msg');
        var token = '';

        regForm.addEventListener('submit', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var f = regForm.elements;
            say(regMsg, '');
            if (f.password.value !== f.confirm_password.value) { say(regMsg, t('mismatch', 'The passwords don’t match.'), false); return; }
            if (!f.terms.checked) { say(regMsg, t('terms', 'Please agree to the Terms and Privacy policy to continue.'), false); return; }
            var btn = regForm.querySelector('[type=submit]');
            busy(btn, true);
            post('sc_public_register', {
                name: f['name'].value, email: f.email.value, phone_code: f.phone_code.value, phone: f.phone.value, password: f.password.value
            }).then(function (r) {
                busy(btn, false);
                if (!r.success) {
                    say(regMsg, r.data.message, false);
                    if (r.data.field && f[r.data.field]) { f[r.data.field].focus(); }
                    return;
                }
                if (!r.data.verify) { go(r.data.redirect); return; }
                token = r.data.token;
                regForm.hidden = true;
                regVerify.hidden = false;
                $('#register-to').textContent = r.data.phone;
                resendTimer($('#register-resend'), r.data.resend_in);
                $('#register-code').focus();
            });
        }, true);

        codeInput($('#register-code'));
        $('#register-verify-form').addEventListener('submit', function (e) {
            e.preventDefault();
            var form = this, btn = form.querySelector('[type=submit]');
            say(regMsg, '');
            busy(btn, true);
            post('sc_register_verify', { token: token, code: $('#register-code').value }).then(function (r) {
                busy(btn, false);
                delete form.dataset.autoSent;
                if (!r.success) {
                    say(regMsg, r.data.message, false);
                    if (r.data.restart) { regVerify.hidden = true; regForm.hidden = false; }
                    else { $('#register-code').select(); }
                    return;
                }
                say(regMsg, r.data.message, true);
                go(r.data.redirect);
            });
        });
        $('#register-resend').addEventListener('click', function () {
            var btn = this;
            say(regMsg, '');
            busy(btn, true);
            post('sc_register_resend', { token: token }).then(function (r) {
                busy(btn, false);
                if (!r.success) {
                    say(regMsg, r.data.message, false);
                    if (r.data.resend_in) { resendTimer(btn, r.data.resend_in); }
                    if (r.data.restart) { regVerify.hidden = true; regForm.hidden = false; }
                    return;
                }
                say(regMsg, r.data.message, true);
                resendTimer(btn, r.data.resend_in);
            });
        });
        $('#register-back').addEventListener('click', function () {
            regVerify.hidden = true;
            regForm.hidden = false;
            say(regMsg, '');
            regForm.elements.phone.focus();
        });
    }

    /* ---------------------------------------------------------- reset password */

    var reset = $('#reset-flow');
    if (reset) {
        var rMsg = $('#reset-msg');
        var by = 'phone';
        var proof = '';
        var chosen = 0;
        var steps = { ask: $('#reset-ask'), code: $('#reset-code-form'), choose: $('#reset-choose-step'), pass: $('#reset-pass-form') };
        var show = function (name) {
            Object.keys(steps).forEach(function (k) { steps[k].hidden = k !== name; });
            $all('[data-reset-step]').forEach(function (s) {
                var order = ['ask', 'code', 'pass'];
                var i = order.indexOf(s.dataset.resetStep), cur = order.indexOf(name === 'choose' ? 'pass' : name);
                s.classList.toggle('active', i === cur);
                s.classList.toggle('done', i < cur);
            });
        };
        var target = function () {
            return by === 'email'
                ? { by: 'email', email: $('#reset-email').value }
                : { by: 'phone', phone_code: $('#reset-phone-code').value, phone: $('#reset-phone').value };
        };
        $all('[data-reset-by]').forEach(function (b) {
            b.addEventListener('click', function () {
                by = b.dataset.resetBy;
                $all('[data-reset-by]').forEach(function (x) { x.setAttribute('aria-selected', x === b ? 'true' : 'false'); });
                $('#reset-by-phone').hidden = by !== 'phone';
                $('#reset-by-email').hidden = by !== 'email';
                $('#reset-email').required = by === 'email';
                $('#reset-phone').required = by === 'phone';
                say(rMsg, '');
            });
        });

        var sendReset = function (btn) {
            say(rMsg, '');
            busy(btn, true);
            return post('sc_reset_send', target()).then(function (r) {
                busy(btn, false);
                if (!r.success) {
                    say(rMsg, r.data.message, false);
                    if (r.data.resend_in) { resendTimer($('#reset-resend'), r.data.resend_in); }
                    return;
                }
                $('#reset-sent').textContent = r.data.message;
                show('code');
                resendTimer($('#reset-resend'), r.data.resend_in);
                $('#reset-code').value = '';
                $('#reset-code').focus();
            });
        };
        steps.ask.addEventListener('submit', function (e) { e.preventDefault(); sendReset(steps.ask.querySelector('[type=submit]')); });
        $('#reset-resend').addEventListener('click', function () { sendReset(this); });
        $('#reset-change').addEventListener('click', function () { show('ask'); say(rMsg, ''); });
        codeInput($('#reset-code'));

        steps.code.addEventListener('submit', function (e) {
            e.preventDefault();
            var form = this, btn = form.querySelector('[type=submit]');
            say(rMsg, '');
            busy(btn, true);
            var data = target();
            data.code = $('#reset-code').value;
            post('sc_reset_verify', data).then(function (r) {
                busy(btn, false);
                delete form.dataset.autoSent;
                if (!r.success) { say(rMsg, r.data.message, false); $('#reset-code').select(); return; }
                proof = r.data.proof;
                if (r.data.choose && r.data.choose.length) {
                    show('choose');
                    renderChoices($('#reset-choose'), r.data.choose, function (id) { chosen = id; show('pass'); $('#reset-new').focus(); });
                    return;
                }
                show('pass');
                $('#reset-new').focus();
            });
        });

        steps.pass.addEventListener('submit', function (e) {
            e.preventDefault();
            var btn = this.querySelector('[type=submit]');
            say(rMsg, '');
            if ($('#reset-new').value !== $('#reset-confirm').value) { say(rMsg, t('mismatch', 'The passwords don’t match.'), false); return; }
            busy(btn, true);
            post('sc_reset_set_password', { proof: proof, user_id: chosen, password: $('#reset-new').value }).then(function (r) {
                busy(btn, false);
                if (!r.success) {
                    say(rMsg, r.data.message, false);
                    if (r.data.restart) { show('ask'); }
                    return;
                }
                say(rMsg, r.data.message, true);
                setTimeout(function () { go(r.data.redirect); }, 900);
            });
        });
    }
}());
