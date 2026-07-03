(function (window) {
    'use strict';

    var CSRF = document.querySelector('meta[name="csrf-token"]');
    var BASE = document.querySelector('meta[name="app-base"]');

    var ICONS = {
        pause: '<svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><path fill="currentColor" d="M13 19V5h6v14zm-8 0V5h6v14zm10-2h2V7h-2zm-8 0h2V7H7zM7 7v10zm8 0v10z"/></svg>',
        play_arrow: '<svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><path fill="currentColor" d="M8 19V5l11 7zm2-3.65L15.25 12L10 8.65z"/></svg>',
        download: '<svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><path fill="currentColor" d="m12 16l-5-5l1.4-1.45l2.6 2.6V4h2v8.15l2.6-2.6L17 11zm-6 4q-.825 0-1.412-.587T4 18v-3h2v3h12v-3h2v3q0 .825-.587 1.413T18 20z"/></svg>',
        content_copy: '<svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><path fill="currentColor" d="M9 18q-.825 0-1.412-.587T7 16V4q0-.825.588-1.412T9 2h9q.825 0 1.413.588T20 4v12q0 .825-.587 1.413T18 18zm0-2h9V4H9zm-4 6q-.825 0-1.412-.587T3 20V6h2v14h11v2zm4-6V4z"/></svg>',
        edit: '<svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><path fill="currentColor" d="M5 19h1.425L16.2 9.225L14.775 7.8L5 17.575zm-2 2v-4.25L16.2 3.575q.3-.275.663-.425t.762-.15t.775.15t.65.45L20.425 5q.3.275.438.65T21 6.4q0 .4-.137.763t-.438.662L7.25 21zM19 6.4L17.6 5zm-3.525 2.125l-.7-.725L16.2 9.225z"/></svg>',
        delete: '<svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><path fill="currentColor" d="M7 21q-.825 0-1.412-.587T5 19V6H4V4h5V3h6v1h5v2h-1v13q0 .825-.587 1.413T17 21zM17 6H7v13h10zM9 17h2V8H9zm4 0h2V8h-2zM7 6v13z"/></svg>',
        person_off: '<svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><path fill="currentColor" d="M19.775 22.625L17.15 20H4v-2.8q0-.85.438-1.562T5.6 14.55q1.125-.575 2.288-.925t2.362-.525L1.375 4.225L2.8 2.8l18.4 18.4zM6 18h9.15l-3-3H12q-1.4 0-2.775.338T6.5 16.35q-.225.125-.363.35T6 17.2zm12.4-3.45q.725.35 1.15 1.063T20 17.15l-3.35-3.35q.45.175.888.35t.862.4m-4.2-3.2l-1.475-1.475q.575-.225.925-.737T14 8q0-.825-.587-1.412T12 6q-.625 0-1.137.35t-.738.925L8.65 5.8q.575-.85 1.45-1.325T12 4q1.65 0 2.825 1.175T16 8q0 1.025-.475 1.9T14.2 11.35m.95 6.65H6zm-3.725-9.425"/></svg>'
    };

    var CWP = {
        csrfToken: CSRF ? CSRF.content : '',
        base: BASE ? BASE.content : '',

        icon: function (name, extraClass) {
            var svg = ICONS[name] || '';
            if (!svg) return '';
            var cls = 'cw-icon' + (extraClass ? ' ' + extraClass : '');
            return svg.replace('<svg ', '<svg class="' + cls + '" aria-hidden="true" ');
        },

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
