export function formatDecimal(value, emptyFallback = '') {
    if (value === null || value === undefined || value === '') {
        return emptyFallback;
    }

    const normalized = String(value).trim().replace(',', '.');
    if (!/^-?\d+(\.\d+)?$/.test(normalized)) {
        return String(value);
    }

    return normalized.replace(/(\.\d*?)0+$/, '$1').replace(/\.$/, '');
}
