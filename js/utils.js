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
})();
