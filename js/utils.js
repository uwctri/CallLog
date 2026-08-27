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
        }
    };
})();
