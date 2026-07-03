(function (window) {
    'use strict';

    var CSRF = document.querySelector('meta[name="csrf-token"]');
    var BASE = document.querySelector('meta[name="app-base"]');

    var CWP = {
        csrfToken: CSRF ? CSRF.content : '',
        base: BASE ? BASE.content : '',

        url: function (path) {
            return this.base + path;
        },

        _request: function (path, options, timeoutMs) {
            var controller = (typeof AbortController !== 'undefined') ? new AbortController() : null;
            var timer = null;
            if (controller) {
                options.signal = controller.signal;
                timer = setTimeout(function () { controller.abort(); }, timeoutMs || 25000);
            }
            return fetch(this.url(path), options).then(function (res) {
                if (timer) clearTimeout(timer);
                return res.text().then(function (text) {
                    var json = null;
                    try { json = text ? JSON.parse(text) : null; } catch (e) { json = null; }
                    if (json === null) {
                        return {
                            httpOk: false,
                            status: res.status,
                            body: { success: false, message: 'پاسخ نامعتبر از سرور (کد ' + res.status + '). ممکن است هاست درخواست را قطع کرده باشد؛ کمی بعد دوباره تلاش کنید.' },
                        };
                    }
                    return { httpOk: res.ok, status: res.status, body: json };
                });
            }).catch(function (err) {
                if (timer) clearTimeout(timer);
                var aborted = err && err.name === 'AbortError';
                return {
                    httpOk: false,
                    status: 0,
                    body: { success: false, message: aborted ? 'زمان درخواست تمام شد. اتصال اینترنت یا هاست را بررسی کنید.' : 'خطای شبکه یا سرور.' },
                };
            });
        },

        apiPost: function (path, data, timeoutMs) {
            return this._request(path, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.csrfToken,
                },
                body: JSON.stringify(data || {}),
            }, timeoutMs);
        },

        apiGet: function (path, timeoutMs) {
            return this._request(path, {
                headers: { 'X-CSRF-Token': this.csrfToken },
            }, timeoutMs);
        },

        toast: function (message, type) {
            type = type || 'info';
            var box = document.createElement('div');
            box.className = 'alert alert-' + type;
            box.style.position = 'fixed';
            box.style.bottom = '20px';
            box.style.left = '50%';
            box.style.transform = 'translateX(-50%)';
            box.style.zIndex = '999';
            box.style.minWidth = '260px';
            box.style.maxWidth = '90vw';
            box.style.textAlign = 'center';
            box.style.boxShadow = '0 8px 24px rgba(0,0,0,.4)';
            box.textContent = message;
            document.body.appendChild(box);
            setTimeout(function () {
                box.style.transition = 'opacity .3s';
                box.style.opacity = '0';
                setTimeout(function () { box.remove(); }, 300);
            }, 3200);
        },

        copyText: function (text) {
            var done = function (ok) {
                CWP.toast(ok ? 'کپی شد' : 'کپی نشد، لطفاً دستی کپی کنید', ok ? 'success' : 'error');
            };
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(function () { done(true); }, function () { done(false); });
                return;
            }
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.focus();
            ta.select();
            var ok = false;
            try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
            document.body.removeChild(ta);
            done(ok);
        },
    };

    CWP.runAction = function (btn, endpoint, payload, opts) {
        opts = opts || {};
        var original = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner"></span>';
        CWP.apiPost(endpoint, payload, opts.timeoutMs || 45000).then(function (res) {
            var body = res.body || {};
            if (body.success) {
                if (body.message) CWP.toast(body.message, 'success');
                if (opts.reload !== false) {
                    setTimeout(function () { window.location.reload(); }, 500);
                } else {
                    btn.disabled = false;
                    btn.innerHTML = original;
                    if (opts.onSuccess) opts.onSuccess(body);
                }
            } else {
                CWP.toast(body.message || 'خطایی رخ داد', 'error');
                btn.disabled = false;
                btn.innerHTML = original;
            }
        }).catch(function () {
            CWP.toast('خطای شبکه یا سرور', 'error');
            btn.disabled = false;
            btn.innerHTML = original;
        });
    };

    window.CWP = CWP;

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-copy]');
        if (btn) {
            CWP.copyText(btn.getAttribute('data-copy'));
        }
    });
})(window);
