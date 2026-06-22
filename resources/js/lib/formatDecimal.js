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

export function formatAnnexDecimal(value, emptyFallback = '—') {
    if (value === null || value === undefined || value === '') {
        return emptyFallback;
    }

    const normalized = String(value).trim().replace(',', '.');
    const num = Number(normalized);

    if (Number.isNaN(num)) {
        return String(value);
    }

    const rounded = Math.round(num * 100) / 100;

    return String(rounded).replace(/(\.\d*?)0+$/, '$1').replace(/\.$/, '');
}
