(() => {
    const module = ExternalModules.UWMadison.CallLog;

    module.utils = {
        toArray(val) {
            if (!val) return [];
            if (Array.isArray(val)) {
                if (val.length === 1 && Array.isArray(val[0])) return val[0].map(s => String(s).trim()).filter(Boolean);
                return val.map(s => String(s).trim()).filter(Boolean);
            }
            if (typeof val === 'string') return val.split(',').map(s => s.trim()).filter(Boolean);
            return [String(val).trim()];
        },
        getVal(arr, i, fallback = '') {
            if (!arr) return fallback;
            const item = arr[i];
            if (item === undefined || item === null) return fallback;
            if (Array.isArray(item)) return item[0] !== undefined ? String(item[0]) : fallback;
            return String(item);
        },
        getParam(name, url = window.location.href) {
            return window.getParameterByName(name);
        },
        parseDateComponents(str) {
            if (!str) return null;
            if (str instanceof Date && !isNaN(str.getTime())) {
                return { date: str, hasTime: true };
            }
            let s = String(str).trim();
            let m = s.match(/^(\d{4})[-/](\d{1,2})[-/](\d{1,2})(?:[ T](\d{1,2}):(\d{2})(?::(\d{2}))?)?/);
            if (!m) return null;
            let [, y, mo, d, h, mi, sec] = m;
            let hasTime = (h !== undefined);
            let dateObj = new Date(
                parseInt(y, 10),
                parseInt(mo, 10) - 1,
                parseInt(d, 10),
                h !== undefined ? parseInt(h, 10) : 0,
                mi !== undefined ? parseInt(mi, 10) : 0,
                sec !== undefined ? parseInt(sec, 10) : 0
            );
            return { date: dateObj, hasTime: hasTime };
        },
        getDateOnlyFormat(fmt) {
            if (!fmt) return 'm/d/Y';
            let dateOnly = fmt
                .replace(/(?:[\s,@\-_–—]+)?\b[aAgGhHisuv](?:[ :._\-\/aAgGhHisuv\\]*)$/g, '')
                .trim();
            dateOnly = dateOnly.replace(/[\s,@\-_–—:]+$/, '').trim();
            return dateOnly || fmt;
        },
        normalizePhpDateFormat(fmt) {
            if (!fmt || fmt === 'MDY_12') return 'm/d/Y g:i A';
            if (fmt === 'MDY_24') return 'm/d/Y H:i';
            if (fmt === 'YMD_12') return 'Y-m-d g:i A';
            if (fmt === 'YMD_24') return 'Y-m-d H:i';
            if (fmt === 'DMY_12') return 'd/m/Y g:i A';
            if (fmt === 'DMY_24') return 'd/m/Y H:i';
            let s = fmt;
            s = s.replace(/YYYY/g, 'Y');
            s = s.replace(/YY/g, 'y');
            s = s.replace(/MM/g, 'm');
            s = s.replace(/DD/g, 'd');
            return s;
        },
        formatPhpDate(d, format) {
            if (!(d instanceof Date) || isNaN(d.getTime())) return '';
            if (!format) format = 'm/d/Y g:i A';

            const dayNamesShort = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            const dayNamesFull = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            const monthNamesShort = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            const monthNamesFull = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

            function getSuffix(n) {
                let j = n % 10, k = n % 100;
                if (j === 1 && k !== 11) return 'st';
                if (j === 2 && k !== 12) return 'nd';
                if (j === 3 && k !== 13) return 'rd';
                return 'th';
            }

            let out = '';
            let escaped = false;

            for (let i = 0; i < format.length; i++) {
                let ch = format[i];
                if (escaped) {
                    out += ch;
                    escaped = false;
                    continue;
                }
                if (ch === '\\') {
                    escaped = true;
                    continue;
                }

                switch (ch) {
                    // Day
                    case 'd': out += String(d.getDate()).padStart(2, '0'); break;
                    case 'j': out += String(d.getDate()); break;
                    case 'D': out += dayNamesShort[d.getDay()]; break;
                    case 'l': out += dayNamesFull[d.getDay()]; break;
                    case 'N': out += String(d.getDay() === 0 ? 7 : d.getDay()); break;
                    case 'w': out += String(d.getDay()); break;
                    case 'S': out += getSuffix(d.getDate()); break;

                    // Month
                    case 'F': out += monthNamesFull[d.getMonth()]; break;
                    case 'm': out += String(d.getMonth() + 1).padStart(2, '0'); break;
                    case 'M': out += monthNamesShort[d.getMonth()]; break;
                    case 'n': out += String(d.getMonth() + 1); break;
                    case 't': out += String(new Date(d.getFullYear(), d.getMonth() + 1, 0).getDate()); break;

                    // Year
                    case 'Y': out += String(d.getFullYear()); break;
                    case 'y': out += String(d.getFullYear()).slice(-2); break;

                    // Time
                    case 'a': out += d.getHours() >= 12 ? 'pm' : 'am'; break;
                    case 'A': out += d.getHours() >= 12 ? 'PM' : 'AM'; break;
                    case 'g': out += String(d.getHours() % 12 || 12); break;
                    case 'h': out += String(d.getHours() % 12 || 12).padStart(2, '0'); break;
                    case 'G': out += String(d.getHours()); break;
                    case 'H': out += String(d.getHours()).padStart(2, '0'); break;
                    case 'i': out += String(d.getMinutes()).padStart(2, '0'); break;
                    case 's': out += String(d.getSeconds()).padStart(2, '0'); break;

                    default:
                        out += ch;
                        break;
                }
            }
            return out;
        },
        formatDateTime(val, forceTime = false, forceDateOnly = false, customFormat = null) {
            if (val === undefined || val === null || val === '') return '';
            let parsed = this.parseDateComponents(val);
            if (!parsed) return String(val);

            let rawFormat = customFormat || (module && module.dateTimeFormat) || 'm/d/Y g:i A';
            let format = this.normalizePhpDateFormat(rawFormat);

            let useFmt = (forceDateOnly || (!parsed.hasTime && !forceTime))
                ? this.getDateOnlyFormat(format)
                : format;

            return this.formatPhpDate(parsed.date, useFmt);
        }
    };

    const SWAL_DEFAULTS = {
        customClass: {
            container: 'call-log-swal-container',
            popup: 'call-log-swal-popup',
            header: 'call-log-swal-header',
            title: 'call-log-swal-title',
            content: 'call-log-swal-content',
            htmlContainer: 'call-log-swal-content',
            actions: 'call-log-swal-actions',
            confirmButton: 'call-log-swal-btn call-log-swal-confirm',
            cancelButton: 'call-log-swal-btn call-log-swal-cancel',
            denyButton: 'call-log-swal-btn call-log-swal-deny',
            icon: 'call-log-swal-icon'
        },
        buttonsStyling: false,
        showCloseButton: false,
        focusConfirm: false
    };

    function isCallLogContext() {
        if (typeof window === 'undefined') return false;
        if (window.location && window.location.href && window.location.href.includes('prefix=call_log')) return true;
        if (typeof document !== 'undefined') {
            return !!(
                document.getElementById('callLogConfig') ||
                document.querySelector('.call-list-dashboard') ||
                document.querySelector('.callHistoryTable') ||
                document.querySelector('.callHistoryContainer') ||
                document.getElementById('call_log_wrapper-tr') ||
                document.querySelector('.callTable')
            );
        }
        return false;
    }

    function mergeSwalOptions(userOpts = {}) {
        let opts = typeof userOpts === 'string' ? { title: userOpts } : { ...userOpts };
        let customClass = Object.assign({}, SWAL_DEFAULTS.customClass, opts.customClass || {});

        if (opts.isDestructive || opts.confirmButtonColor === '#d33' || opts.confirmButtonColor === '#dc3545' || opts.confirmButtonColor === '#dc2626') {
            customClass.confirmButton = ((customClass.confirmButton || '') + ' call-log-swal-destructive').trim();
        }

        if (opts.showConfirmButton === false && !opts.showCancelButton) {
            customClass.popup = ((customClass.popup || '') + ' call-log-swal-no-buttons').trim();
        }

        let merged = Object.assign({}, SWAL_DEFAULTS, opts, {
            customClass: customClass,
            buttonsStyling: false
        });

        if (opts.customIcon && !opts.icon) {
            let iconHtml = `<div class="call-log-swal-custom-icon ${opts.customIconClass || ''}">${opts.customIcon}</div>`;
            if (opts.html) {
                merged.html = iconHtml + opts.html;
            } else if (opts.text) {
                merged.html = `${iconHtml}<p class="call-log-swal-content">${opts.text}</p>`;
                delete merged.text;
            } else {
                merged.html = iconHtml;
            }
        }

        return merged;
    }

    module.swal = {
        defaults: SWAL_DEFAULTS,
        mergeOptions: mergeSwalOptions,

        fire(options, ...rest) {
            let swalObj = (typeof window !== 'undefined' && window.Swal) ? window.Swal : (typeof Swal !== 'undefined' ? Swal : null);
            if (!swalObj) {
                console.warn("SweetAlert2 is not defined on this page.");
                let msg = (options && (options.title || options.text)) ? `${options.title || ''}\n${options.text || ''}` : 'Alert';
                if (typeof window !== 'undefined' && window.alert) window.alert(msg);
                return Promise.resolve({ isConfirmed: true, isDismissed: false });
            }
            let merged = mergeSwalOptions(options);
            let targetFn = swalObj.originalFire || swalObj.fire;
            return targetFn.call(swalObj, merged, ...rest);
        },

        success(title, text = '', options = {}) {
            return this.fire(Object.assign({ icon: 'success', title: title, text: text }, options));
        },

        error(title, text = '', options = {}) {
            return this.fire(Object.assign({ icon: 'error', title: title, text: text }, options));
        },

        warning(title, text = '', options = {}) {
            return this.fire(Object.assign({ icon: 'warning', title: title, text: text }, options));
        },

        info(title, text = '', options = {}) {
            return this.fire(Object.assign({ icon: 'info', title: title, text: text }, options));
        },

        confirm(title, text = '', options = {}) {
            let isDestructive = options.isDestructive || options.confirmButtonColor === '#d33';
            let defaults = {
                icon: 'warning',
                title: title,
                text: text,
                showCancelButton: true,
                showConfirmButton: true,
                confirmButtonText: isDestructive ? '<i class="fas fa-trash-alt me-1.5"></i> Delete' : 'Confirm',
                cancelButtonText: 'Cancel',
                focusCancel: true
            };
            return this.fire(Object.assign({}, defaults, options));
        }
    };

    function attachSwalDecorator() {
        let swalObj = (typeof window !== 'undefined' && window.Swal) ? window.Swal : (typeof Swal !== 'undefined' ? Swal : null);
        if (swalObj && !swalObj.originalFire) {
            swalObj.originalFire = swalObj.fire;
            swalObj.fire = function (...args) {
                if (isCallLogContext()) {
                    if (args.length === 1 && typeof args[0] === 'object' && args[0] !== null) {
                        return module.swal.fire(args[0]);
                    } else if (args.length > 0) {
                        let [title, html, icon] = args;
                        return module.swal.fire({ title: title, html: html, icon: icon });
                    }
                }
                return swalObj.originalFire.apply(this, args);
            };
        }
    }

    attachSwalDecorator();
    if (typeof document !== 'undefined') {
        document.addEventListener('DOMContentLoaded', attachSwalDecorator);
    }
})();
