import React, { useMemo, useState } from 'react';
import { useForm, usePage } from '@inertiajs/react';

const MINIM_ZILE = 30;
const LUNI = ['Ian', 'Feb', 'Mar', 'Apr', 'Mai', 'Iun', 'Iul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

function formatIso(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

function parseIso(value) {
    const [year, month, day] = value.split('-').map(Number);

    return new Date(year, month - 1, day);
}

function addDaysToIso(iso, days) {
    const date = parseIso(iso);
    date.setDate(date.getDate() + days);

    return formatIso(date);
}

function daysInYear(year) {
    const days = [];
    const cursor = new Date(year, 0, 1);
    const end = new Date(year, 11, 31);

    while (cursor <= end) {
        days.push(new Date(cursor));
        cursor.setDate(cursor.getDate() + 1);
    }

    return days;
}

function findPerioadaForDay(iso, perioade) {
    return perioade.find((perioada) => iso >= perioada.data_start && iso <= perioada.data_end) || null;
}

function rangeOverlapsPerioade(startIso, endIso, perioade, exceptId = null) {
    return perioade.some((perioada) => {
        if (exceptId && perioada.id === exceptId) {
            return false;
        }

        return startIso <= perioada.data_end && endIso >= perioada.data_start;
    });
}

function zileInPerioada(startIso, endIso) {
    const start = parseIso(startIso);
    const end = parseIso(endIso);

    return Math.floor((end - start) / (1000 * 60 * 60 * 24)) + 1;
}

function calculeazaChirieProportionala(startIso, endIso, chirieLunara) {
    if (!startIso || !endIso || chirieLunara === '' || chirieLunara === null || chirieLunara === undefined) {
        return null;
    }

    const chirie = Number(chirieLunara);

    if (!Number.isFinite(chirie)) {
        return null;
    }

    let total = 0;
    const cursor = parseIso(startIso);
    const end = parseIso(endIso);

    while (cursor <= end) {
        const daysInMonth = new Date(cursor.getFullYear(), cursor.getMonth() + 1, 0).getDate();
        total += chirie / daysInMonth;
        cursor.setDate(cursor.getDate() + 1);
    }

    return Math.round(total * 100) / 100;
}

function formatSuma(value, moneda = 'EUR') {
    if (value === null || value === undefined) {
        return '—';
    }

    return `${value.toFixed(2)} ${moneda}`;
}

function formatPerioada(iso) {
    const [year, month, day] = iso.split('-');

    return `${day}.${month}.${year}`;
}

function formatOccupiedTooltip(perioada) {
    return `${perioada.chirias}: ${formatPerioada(perioada.data_start)} – ${formatPerioada(perioada.data_end)} (${perioada.chirie_lunara} ${perioada.moneda}/lună)`;
}

function perioadeInAn(perioade, an) {
    return perioade
        .filter((perioada) => {
            const startYear = Number(perioada.data_start.slice(0, 4));
            const endYear = Number(perioada.data_end.slice(0, 4));

            return startYear <= an && endYear >= an;
        })
        .sort((left, right) => left.data_start.localeCompare(right.data_start));
}

export default function FacadeRentalCalendar({ spatiuId }) {
    const currentYear = new Date().getFullYear();
    const [an, setAn] = useState(currentYear);
    const [selectStart, setSelectStart] = useState('');
    const [selectEnd, setSelectEnd] = useState('');
    const [editingId, setEditingId] = useState(null);
    const perioade = usePage().props.perioadeFatada ?? [];

    const { data, setData, post, put, processing, errors, reset, transform } = useForm({
        an: currentYear,
        data_start: '',
        data_end: '',
        chirias: '',
        chirie_lunara: '',
    });

    transform((formData) => ({
        ...formData,
        an,
    }));

    const yearDays = useMemo(() => daysInYear(an), [an]);

    const monthSegments = useMemo(() => {
        const segments = [];
        let currentMonth = null;
        let count = 0;

        yearDays.forEach((day) => {
            const month = day.getMonth();

            if (currentMonth === null) {
                currentMonth = month;
            }

            if (month !== currentMonth) {
                segments.push({ label: LUNI[currentMonth], days: count });
                currentMonth = month;
                count = 0;
            }

            count += 1;
        });

        if (count > 0) {
            segments.push({ label: LUNI[currentMonth], days: count });
        }

        return segments;
    }, [yearDays]);

    const perioadeAn = useMemo(() => perioadeInAn(perioade, an), [perioade, an]);

    const totalFormular = useMemo(
        () => calculeazaChirieProportionala(data.data_start, data.data_end, data.chirie_lunara),
        [data.data_start, data.data_end, data.chirie_lunara],
    );

    function clearEditState() {
        setEditingId(null);
        reset('data_start', 'data_end', 'chirias', 'chirie_lunara');
        setSelectStart('');
        setSelectEnd('');
    }

    function loadPeriodForEdit(perioada) {
        const perioadaAn = Number(perioada.data_start.slice(0, 4));

        setEditingId(perioada.id);
        setSelectStart(perioada.data_start);
        setSelectEnd(perioada.data_end);
        setData({
            an: perioadaAn,
            data_start: perioada.data_start,
            data_end: perioada.data_end,
            chirias: perioada.chirias,
            chirie_lunara: perioada.chirie_lunara,
        });

        if (perioadaAn !== an) {
            setAn(perioadaAn);
        }
    }

    function applyStartDate(startIso) {
        const endIso = addDaysToIso(startIso, 29);

        setSelectStart(startIso);
        setSelectEnd(endIso);
        setData({
            ...data,
            data_start: startIso,
            data_end: endIso,
        });
    }

    function handleYearChange(nextYear) {
        setAn(nextYear);
        setData('an', nextYear);
        clearEditState();
    }

    function handleDayClick(iso, perioada) {
        if (perioada && perioada.id !== editingId) {
            loadPeriodForEdit(perioada);
            return;
        }

        const perioadeForOverlap = perioade.filter((item) => item.id !== editingId);

        if (!selectStart || (selectStart && selectEnd)) {
            const startIso = iso;
            const endIso = addDaysToIso(startIso, 29);

            if (rangeOverlapsPerioade(startIso, endIso, perioadeForOverlap)) {
                return;
            }

            setSelectStart(startIso);
            setSelectEnd(endIso);
            setData({
                ...data,
                data_start: startIso,
                data_end: endIso,
            });
            return;
        }

        let startIso = selectStart;
        let endIso = iso;

        if (parseIso(endIso) < parseIso(startIso)) {
            [startIso, endIso] = [endIso, startIso];
        }

        if (rangeOverlapsPerioade(startIso, endIso, perioadeForOverlap)) {
            return;
        }

        setSelectStart(startIso);
        setSelectEnd(endIso);
        setData({
            ...data,
            data_start: startIso,
            data_end: endIso,
        });
    }

    function dayClass(iso, occupied, perioada, isSelected) {
        const classes = ['fatada-calendar-day'];

        if (occupied) {
            classes.push('is-occupied');
        } else {
            classes.push('is-free');
        }

        if (isSelected) {
            classes.push('is-selected');
        }

        if (perioada && editingId === perioada.id) {
            classes.push('is-editing-period');
        }

        if (perioada) {
            classes.push('has-tooltip');
        }

        return classes.join(' ');
    }

    function submit(event) {
        event.preventDefault();
        event.stopPropagation();

        const options = {
            preserveScroll: true,
            preserveState: false,
            onSuccess: () => {
                clearEditState();
            },
        };

        if (editingId) {
            put(`/spatii/${spatiuId}/perioade-fatada/${editingId}`, options);
            return;
        }

        post(`/spatii/${spatiuId}/perioade-fatada`, options);
    }

    const zileSelectate = data.data_start && data.data_end ? zileInPerioada(data.data_start, data.data_end) : 0;
    const validationErrors = Object.entries(errors);

    return (
        <section className="fatada-calendar-section form-field-full">
            <div className="fatada-calendar-header">
                <div>
                    <h3>Calendar închiriere fațadă</h3>
                    <p className="fatada-calendar-hint">Perioada minimă de închiriere: 30 de zile. Chiria este lunară.</p>
                </div>
                <label className="inline-topbar-field">
                    <span>An</span>
                    <select value={an} onChange={(event) => handleYearChange(Number(event.target.value))}>
                        {[currentYear - 1, currentYear, currentYear + 1].map((year) => (
                            <option value={year} key={year}>{year}</option>
                        ))}
                    </select>
                </label>
            </div>

            <div className="fatada-calendar-legend">
                <span className="fatada-calendar-legend-item"><i className="is-free" /> Liber</span>
                <span className="fatada-calendar-legend-item"><i className="is-occupied" /> Ocupat</span>
                <span className="fatada-calendar-legend-item"><i className="is-selected" /> Selectat</span>
            </div>

            <div className="fatada-calendar-track">
                <div className="fatada-calendar-months">
                    {monthSegments.map((segment) => (
                        <span key={`${segment.label}-${segment.days}`} className="fatada-calendar-month" style={{ flex: `${segment.days} 1 0` }}>
                            {segment.label}
                        </span>
                    ))}
                </div>

                <div className="fatada-calendar-bar">
                    {yearDays.map((day) => {
                        const iso = formatIso(day);
                        const perioada = findPerioadaForDay(iso, perioade);
                        const occupied = Boolean(perioada);
                        const isSelected = Boolean(
                            selectStart && selectEnd && iso >= selectStart && iso <= selectEnd
                            || selectStart && !selectEnd && iso === selectStart,
                        );
                        const tooltip = occupied ? formatOccupiedTooltip(perioada) : null;

                        return (
                            <button
                                key={iso}
                                type="button"
                                className={dayClass(iso, occupied, perioada, isSelected)}
                                title={occupied ? undefined : iso}
                                data-tooltip={tooltip ?? undefined}
                                aria-label={tooltip ?? iso}
                                onClick={() => handleDayClick(iso, perioada)}
                            />
                        );
                    })}
                </div>
            </div>

            <div className="fatada-calendar-periods-wrap">
                <h4 className="fatada-calendar-periods-title">Perioade blocate</h4>
                {perioadeAn.length ? (
                    <ul className="fatada-calendar-periods">
                        {perioadeAn.map((perioada) => {
                            const total = calculeazaChirieProportionala(
                                perioada.data_start,
                                perioada.data_end,
                                Number(perioada.chirie_lunara),
                            );

                            return (
                                <li key={perioada.id}>
                                    <button
                                        type="button"
                                        className={`fatada-calendar-period-row${editingId === perioada.id ? ' is-editing' : ''}`}
                                        onClick={() => loadPeriodForEdit(perioada)}
                                    >
                                        <span><strong>{formatPerioada(perioada.data_start)} – {formatPerioada(perioada.data_end)}</strong></span>
                                        <span>{perioada.chirias}</span>
                                        <span>{perioada.chirie_lunara} {perioada.moneda}/lună</span>
                                        <span>{formatSuma(total, perioada.moneda)}</span>
                                    </button>
                                </li>
                            );
                        })}
                    </ul>
                ) : (
                    <p className="fatada-calendar-periods-empty">Nu există perioade blocate pentru {an}.</p>
                )}
            </div>

            <div className="fatada-calendar-form">
                {editingId ? (
                    <p className="fatada-calendar-editing-banner">Editezi o perioadă existentă. Modificările se salvează separat de formularul spațiului.</p>
                ) : null}

                {validationErrors.length ? (
                    <div className="fatada-calendar-errors" role="alert">
                        {validationErrors.map(([field, message]) => (
                            <p key={field}><small>{message}</small></p>
                        ))}
                    </div>
                ) : null}

                <div className="form-grid">
                    <label className="form-field">
                        <span>De la</span>
                        <input type="date" value={data.data_start} min={`${an}-01-01`} max={`${an}-12-31`} onChange={(event) => {
                            applyStartDate(event.target.value);
                        }} />
                        {errors.data_start ? <small>{errors.data_start}</small> : null}
                    </label>

                    <label className="form-field">
                        <span>Până la</span>
                        <input type="date" value={data.data_end} min={data.data_start || `${an}-01-01`} max={`${an}-12-31`} onChange={(event) => {
                            setSelectEnd(event.target.value);
                            setData('data_end', event.target.value);
                        }} />
                        {errors.data_end ? <small>{errors.data_end}</small> : null}
                    </label>

                    <label className="form-field">
                        <span>Chiriaș</span>
                        <input type="text" value={data.chirias} onChange={(event) => setData('chirias', event.target.value)} />
                        {errors.chirias ? <small>{errors.chirias}</small> : null}
                    </label>

                    <label className="form-field">
                        <span>Chirie lunară EUR</span>
                        <input type="number" min="0" step="0.01" value={data.chirie_lunara} onChange={(event) => setData('chirie_lunara', event.target.value)} />
                        {errors.chirie_lunara ? <small>{errors.chirie_lunara}</small> : null}
                    </label>
                </div>

                {totalFormular !== null && data.data_start && data.data_end ? (
                    <p className="fatada-calendar-total">
                        Total perioadă: <strong>{formatSuma(totalFormular)}</strong>
                        {data.chirie_lunara ? ` (proporțional, ${zileSelectate} zile)` : null}
                    </p>
                ) : null}

                {zileSelectate > 0 && zileSelectate < MINIM_ZILE ? (
                    <p className="fatada-calendar-warning">Perioada selectată are {zileSelectate} zile. Minimul este de 30 de zile.</p>
                ) : null}

                {(!data.data_start || !data.data_end) && !processing ? (
                    <p className="fatada-calendar-hint">Selectează o perioadă de cel puțin 30 de zile pe calendar sau din câmpurile de date.</p>
                ) : null}

                <div className="form-actions">
                    {editingId ? (
                        <button
                            className="secondary-button"
                            type="button"
                            onClick={clearEditState}
                            disabled={processing}
                        >
                            Anulează editarea
                        </button>
                    ) : null}
                    <button
                        className="primary-button"
                        type="button"
                        onClick={submit}
                        disabled={processing || zileSelectate < MINIM_ZILE}
                    >
                        {processing
                            ? (editingId ? 'Se salvează...' : 'Se închiriază...')
                            : (editingId ? 'Salvează modificările' : 'Închiriază perioada')}
                    </button>
                </div>
            </div>
        </section>
    );
}
