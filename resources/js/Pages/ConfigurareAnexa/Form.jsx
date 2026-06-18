import React from 'react';
import { Link, useForm } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Trash2 } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';

const emptyAnexaLine = {
    tip_linie: 'serviciu',
    nr_crt: '',
    denumire: '',
    index_vechi: '',
    index_nou: '',
    facturat: '',
    coeficient: '',
    um: '',
    pret_unitar: '',
    valoare: '',
    tva_21: '',
    tip_calcul: 'manual',
    apare_cu_zero: true,
    activ: true,
    observatii: '',
};

const emptyHeaderLine = {
    tip_linie: 'header',
};

const emptyCoeficientLine = {
    tip_linie: 'serviciu',
    nr_crt: '',
    denumire: '',
    index_vechi: '',
    index_nou: '',
    facturat: '',
    coeficient: '0.09',
    um: 'MC',
    pret_unitar: '',
    valoare: '',
    tva_21: '',
    tip_calcul: 'mp_coeficient',
    apare_cu_zero: true,
    activ: true,
    observatii: '',
};

function isMpCoeficientLine(line) {
    return isMpCoeficientValue(line.tip_calcul);
}

function isMpCoeficientValue(value) {
    const normalized = String(value || '')
        .toLowerCase()
        .replace(/[×*_\s-]/g, '');

    return normalized.startsWith('mp') && normalized.includes('coeficient');
}

function templateFaraCantitati(tipCalcul) {
    return tipCalcul === 'contor'
        || ['mp', 'pe_mp'].includes(tipCalcul)
        || tipCalcul === 'persoane'
        || isMpCoeficientValue(tipCalcul);
}

function defaultPluvialaDenumire(denumiri) {
    const match = denumiri.find((opt) => /pluvial/i.test(String(opt.valoare)) || /pluvial/i.test(String(opt.label)));

    return match?.valoare || '';
}

function buildFormState(anexa, selectedImobilId) {
    return {
        imobil_id: anexa?.imobil_id || selectedImobilId || '',
        denumire: anexa?.denumire || '',
        implicit: Boolean(anexa?.implicit),
        activ: anexa?.activ === undefined ? true : Boolean(anexa?.activ),
        observatii: anexa?.observatii || '',
        linii: renumberLines(anexa?.linii?.length ? anexa.linii.map(formatLineForForm) : [{ ...emptyAnexaLine }]),
    };
}

function numericValue(value) {
    if (value === null || value === undefined) return null;
    const normalized = String(value).trim().replace(',', '.');
    if (normalized === '') return null;

    const number = Number(normalized);
    return Number.isFinite(number) ? number : null;
}

function formatDecimalForInput(value) {
    if (value === null || value === undefined || value === '') return '';
    const normalized = String(value).trim().replace(',', '.');
    if (!/^-?\d+(\.\d+)?$/.test(normalized)) return value;

    return normalized.replace(/(\.\d*?)0+$/, '$1').replace(/\.$/, '');
}

function normalizeTvaValue(value) {
    if (value === null || value === undefined || value === '') return '';
    const normalized = String(value).trim().replace(',', '.').replace(/%+$/, '');

    return formatDecimalForInput(normalized);
}

function formatTvaLabel(value) {
    const normalized = normalizeTvaValue(value);

    return normalized === '' ? '' : `${normalized}%`;
}

function tvaValuesMatch(a, b) {
    return normalizeTvaValue(a) === normalizeTvaValue(b);
}

function calculatedFacturat(indexVechi, indexNou) {
    const vechi = numericValue(indexVechi);
    const nou = numericValue(indexNou);

    if (vechi === null || nou === null) return '';

    return String(Number((nou - vechi).toFixed(3)));
}

function calculatedValoare(facturat, pretUnitar) {
    const cantitate = numericValue(facturat);
    const pret = numericValue(pretUnitar);

    if (cantitate === null || pret === null) return '';

    return formatDecimalForInput((cantitate * pret).toFixed(2));
}

function calculatedMpCoeficient(mp, coeficient) {
    const suprafata = numericValue(mp);
    const coef = numericValue(coeficient);

    if (suprafata === null || coef === null) return '';

    return formatDecimalForInput((suprafata * coef).toFixed(3));
}

function coeficientFallback(value) {
    const number = numericValue(value);

    if (number !== null && number > 0 && number <= 1) {
        return formatDecimalForInput(value);
    }

    return '';
}

function renumberLines(lines) {
    let zoneCounter = 0;

    return lines.map((line) => {
        if (line.tip_linie === 'header') {
            zoneCounter = 0;

            return { ...line, nr_crt: '' };
        }

        zoneCounter += 1;

        return { ...line, nr_crt: zoneCounter };
    });
}

function formatLineForForm(line) {
    if (line.tip_linie === 'header') {
        return { tip_linie: 'header', id: line.id ?? null };
    }

    const isCoeficient = isMpCoeficientValue(line.tip_calcul);
    const tipCalcul = isCoeficient ? 'mp_coeficient' : (line.tip_calcul || 'manual');
    const stripQuantities = templateFaraCantitati(tipCalcul);

    return {
        ...line,
        tip_linie: 'serviciu',
        tip_calcul: tipCalcul,
        index_vechi: stripQuantities ? '' : formatDecimalForInput(line.index_vechi),
        index_nou: stripQuantities ? '' : formatDecimalForInput(line.index_nou),
        facturat: stripQuantities ? '' : formatDecimalForInput(line.facturat),
        pret_unitar: formatDecimalForInput(line.pret_unitar),
        valoare: stripQuantities ? '' : formatDecimalForInput(line.valoare),
        coeficient: isCoeficient
            ? (coeficientFallback(line.coeficient) || coeficientFallback(line.index_nou) || '0.09')
            : formatDecimalForInput(line.coeficient),
        tva_21: normalizeTvaValue(line.tva_21),
    };
}

function AnnexColumnHeader({ showActions = false, lineIndex = null, onMoveUp, onMoveDown, onRemove, canMoveUp = false, canMoveDown = false }) {
    return (
        <div className={`annex-line-header${showActions ? ' annex-line-header-row' : ''}`}>
            <span>Tip calcul</span>
            <span>Nr. crt</span>
            <span>Denumire serviciu</span>
            <span>Index contor vechi</span>
            <span>Index contor nou / Coeficient</span>
            <span>Facturat</span>
            <span>UM</span>
            <span>Preț unitar</span>
            <span>Valoare</span>
            <span>TVA %</span>
            <span>Ordine</span>
            {showActions ? (
                <>
                    <div className="annex-order-actions">
                        <button type="button" className="annex-order-button" onClick={() => onMoveUp(lineIndex)} disabled={!canMoveUp} aria-label="Mută headerul mai sus">
                            <ArrowUp size={13} strokeWidth={2.4} />
                        </button>
                        <button type="button" className="annex-order-button" onClick={() => onMoveDown(lineIndex)} disabled={!canMoveDown} aria-label="Mută headerul mai jos">
                            <ArrowDown size={13} strokeWidth={2.4} />
                        </button>
                    </div>
                    <button type="button" className="delete-inline-button annex-delete-button annex-line-delete-button" onClick={() => onRemove(lineIndex)} aria-label="Șterge header zonă">
                        <Trash2 size={14} strokeWidth={2.4} />
                    </button>
                </>
            ) : (
                <span />
            )}
        </div>
    );
}

export default function Form({
    anexa = null,
    imobile = [],
    selectedImobilId = null,
    serviciiStandard = {},
    returnUrl = null,
    spatiuId = null,
    previewSpatiu = null,
    context = null,
}) {
    const isEditing = Boolean(anexa);
    const { data, setData, post, put, processing, errors, transform } = useForm(buildFormState(anexa, selectedImobilId));

    function normalizeLiniiForSave(linii) {
        return linii.map((linie) => {
            if (linie.tip_linie === 'header') {
                return { tip_linie: 'header', id: linie.id ?? null };
            }

            const tipCalcul = isMpCoeficientLine(linie) ? 'mp_coeficient' : (linie.tip_calcul || 'manual');
            const base = {
                ...linie,
                tip_linie: 'serviciu',
                tip_calcul: tipCalcul,
                tva_21: normalizeTvaValue(linie.tva_21),
            };

            if (templateFaraCantitati(tipCalcul)) {
                return {
                    ...base,
                    index_vechi: '',
                    index_nou: '',
                    facturat: '',
                    valoare: '',
                    coeficient: isMpCoeficientLine(linie) ? (linie.coeficient ?? '') : '',
                };
            }

            if (linie.tip_calcul === 'fix') {
                return {
                    ...base,
                    index_vechi: '',
                    index_nou: '',
                    valoare: calculatedValoare(linie.facturat, linie.pret_unitar),
                };
            }

            return base;
        });
    }

    function submit(event) {
        event.preventDefault();

        const missingDenumire = data.linii.some((linie) => (
            linie.tip_linie !== 'header'
            && isMpCoeficientLine(linie)
            && !String(linie.denumire || '').trim()
        ));

        if (missingDenumire) {
            window.alert('Selectează denumirea serviciului pe rândul cu coeficient înainte de salvare.');
            return;
        }

        transform((payload) => ({
            ...payload,
            linii: normalizeLiniiForSave(payload.linii),
            return_url: returnUrl || '',
            spatiu_id: spatiuId || '',
        }));

        if (isEditing) {
            put(`/configurare-anexa/${anexa.id}`);
            return;
        }

        post('/configurare-anexa');
    }

    function updateLine(lineIndex, field, value) {
        setData('linii', renumberLines(data.linii.map((linie, currentLineIndex) => {
            if (currentLineIndex !== lineIndex || linie.tip_linie === 'header') return linie;

            const nextLine = { ...linie, [field]: field === 'nr_crt' ? lineIndex + 1 : value };

            if (field === 'tip_calcul') {
                if (templateFaraCantitati(value)) {
                    nextLine.index_vechi = '';
                    nextLine.index_nou = '';
                    nextLine.facturat = '';
                    nextLine.valoare = '';
                }

                if (value !== 'mp_coeficient') {
                    nextLine.coeficient = '';
                }

                if (value === 'mp_coeficient') {
                    nextLine.coeficient = nextLine.coeficient || '0.09';
                }
            }

            if (isMpCoeficientLine(nextLine)) {
                nextLine.index_vechi = '';
                nextLine.index_nou = '';
                nextLine.facturat = '';
                nextLine.valoare = '';

                return nextLine;
            }

            if (templateFaraCantitati(nextLine.tip_calcul)) {
                nextLine.index_vechi = '';
                nextLine.index_nou = '';
                nextLine.facturat = '';
                nextLine.valoare = '';

                return nextLine;
            }

            if (nextLine.tip_calcul === 'fix') {
                nextLine.index_vechi = '';
                nextLine.index_nou = '';
            }

            if (['index_vechi', 'index_nou'].includes(field)) {
                nextLine.facturat = calculatedFacturat(nextLine.index_vechi, nextLine.index_nou);
            }

            if (field === 'denumire') {
                const pretStandard = (serviciiStandard.pret || []).find((item) => item.valoare === value);
                if (pretStandard?.coeficient) {
                    nextLine.pret_unitar = formatDecimalForInput(pretStandard.coeficient);
                }
            }

            if (['index_vechi', 'index_nou', 'facturat', 'pret_unitar', 'denumire'].includes(field)) {
                nextLine.valoare = calculatedValoare(nextLine.facturat, nextLine.pret_unitar);
            }

            return nextLine;
        })));
    }

    function addLine() {
        setData('linii', renumberLines([...data.linii, { ...emptyAnexaLine }]));
    }

    function addHeader() {
        setData('linii', renumberLines([...data.linii, { ...emptyHeaderLine }]));
    }

    function addCoeficientLine() {
        setData('linii', renumberLines([
            ...data.linii,
            {
                ...emptyCoeficientLine,
                denumire: defaultPluvialaDenumire(serviciiStandard.denumire || []),
            },
        ]));
    }

    function removeLine(lineIndex) {
        const nextLines = data.linii.filter((_, currentLineIndex) => currentLineIndex !== lineIndex);
        setData('linii', renumberLines(nextLines.length ? nextLines : [{ ...emptyAnexaLine }]));
    }

    function moveLine(lineIndex, direction) {
        const targetIndex = lineIndex + direction;
        if (targetIndex < 0 || targetIndex >= data.linii.length) return;

        const nextLines = [...data.linii];
        [nextLines[lineIndex], nextLines[targetIndex]] = [nextLines[targetIndex], nextLines[lineIndex]];
        setData('linii', renumberLines(nextLines));
    }

    function valoareForDisplay(linie) {
        if (templateFaraCantitati(linie.tip_calcul)) {
            return '';
        }

        if (linie.tip_calcul === 'fix') {
            return calculatedValoare(linie.facturat, linie.pret_unitar);
        }

        return linie.valoare ?? '';
    }

    const denumiri = serviciiStandard.denumire || [];
    const unitati = serviciiStandard.um || [];
    const tvaOptions = serviciiStandard.tva || [];
    const tipCalculOptions = serviciiStandard.tip_calcul || [];

    const topbarActions = <Link className="secondary-button button-link" href={returnUrl || '/configurare-anexa'}>Înapoi</Link>;

    return (
        <AppLayout title={isEditing ? `Editare ${anexa.denumire}` : 'Adaugă anexă'} subtitle="Alege imobilul și configurează serviciile anexei" showGlobalSearch={false} topbarActions={topbarActions}>
            {context?.spatii_count > 1 ? (
                <div className="spatiu-context-banner">
                    Această anexă e alocată la {context.spatii_count} spații. Modificările se aplică tuturor spațiilor care o folosesc.
                </div>
            ) : null}
            <div className="readonly-info-card annex-template-info">
                <p>Anexa definește doar serviciile, tipul de calcul, prețul și TVA. Citirile contoare se introduc în <strong>Citiri contoare</strong>, iar mp și numărul de persoane se iau automat din fiecare spațiu la generare.</p>
            </div>
            <form className="cf-card module-table-card" onSubmit={submit}>
                <div className="cf-card-heading">
                    <div>
                        <h2>{isEditing ? 'Editare anexă configurată' : 'Anexă nouă'}</h2>
                        <p>Configurează denumirea, imobilul și serviciile care vor apărea în anexă.</p>
                    </div>
                </div>

                <div className="annex-config-grid">
                    <label className="form-field">
                        <span>Imobil *</span>
                        <select value={data.imobil_id} onChange={(event) => setData('imobil_id', event.target.value)}>
                            <option value="">Alege imobilul</option>
                            {imobile.map((imobil) => <option value={imobil.id} key={imobil.id}>{imobil.label}</option>)}
                        </select>
                        {errors.imobil_id ? <small>{errors.imobil_id}</small> : null}
                    </label>
                    <label className="form-field">
                        <span>Denumire anexă</span>
                        <input type="text" value={data.denumire} onChange={(event) => setData('denumire', event.target.value)} />
                        {errors.denumire ? <small>{errors.denumire}</small> : null}
                    </label>
                    <label className="form-field checkbox-field">
                        <span>Implicită</span>
                        <label className="inline-checkbox">
                            <input type="checkbox" checked={data.implicit} onChange={(event) => setData('implicit', event.target.checked)} />
                            <span>Da</span>
                        </label>
                    </label>
                </div>

                <div className="annex-lines">
                    <AnnexColumnHeader />
                    {data.linii.map((linie, lineIndex) => (
                        linie.tip_linie === 'header' ? (
                            <AnnexColumnHeader
                                key={`header-${lineIndex}`}
                                showActions
                                lineIndex={lineIndex}
                                onMoveUp={() => moveLine(lineIndex, -1)}
                                onMoveDown={() => moveLine(lineIndex, 1)}
                                onRemove={removeLine}
                                canMoveUp={lineIndex > 0}
                                canMoveDown={lineIndex < data.linii.length - 1}
                            />
                        ) : isMpCoeficientLine(linie) ? (
                            (() => {
                                const coeficientValue = coeficientFallback(linie.coeficient) || '0.09';

                                return (
                            <div className="annex-line-row annex-line-row-coeficient" key={`line-${lineIndex}`}>
                                <label className="form-field">
                                    <select value={linie.tip_calcul} aria-label="Tip calcul" onChange={(event) => updateLine(lineIndex, 'tip_calcul', event.target.value)}>
                                        {tipCalculOptions.map((opt) => <option value={opt.valoare} key={opt.valoare}>{opt.label}</option>)}
                                        {linie.tip_calcul && !tipCalculOptions.some((opt) => opt.valoare === linie.tip_calcul) ? <option value={linie.tip_calcul}>{linie.tip_calcul}</option> : null}
                                    </select>
                                </label>
                                <label className="form-field annex-line-small">
                                    <input type="number" min="0" value={linie.nr_crt ?? ''} readOnly tabIndex={-1} aria-label="Nr. crt" />
                                </label>
                                <label className="form-field annex-line-main">
                                    <select value={linie.denumire || ''} aria-label="Denumire serviciu" onChange={(event) => updateLine(lineIndex, 'denumire', event.target.value)}>
                                        <option value="">Alege serviciul</option>
                                        {denumiri.map((opt) => <option value={opt.valoare} key={opt.valoare}>{opt.label}</option>)}
                                        {linie.denumire && !denumiri.some((opt) => opt.valoare === linie.denumire) ? <option value={linie.denumire}>{linie.denumire}</option> : null}
                                    </select>
                                </label>
                                <label className="form-field annex-line-medium">
                                    <span className="annex-empty-cell" aria-hidden="true" />
                                </label>
                                <label className="form-field annex-line-medium">
                                    <input type="number" step="0.0001" min="0" value={coeficientValue} aria-label="Coeficient mp" onChange={(event) => updateLine(lineIndex, 'coeficient', event.target.value)} />
                                </label>
                                <label className="form-field annex-line-small">
                                    <span className="annex-empty-cell" aria-hidden="true" />
                                </label>
                                <label className="form-field annex-line-small">
                                    <select value={linie.um || ''} aria-label="UM" onChange={(event) => updateLine(lineIndex, 'um', event.target.value)}>
                                        <option value="">—</option>
                                        {unitati.map((opt) => <option value={opt.valoare} key={opt.valoare}>{opt.label}</option>)}
                                        {linie.um && !unitati.some((opt) => opt.valoare === linie.um) ? <option value={linie.um}>{linie.um}</option> : null}
                                    </select>
                                </label>
                                <label className="form-field annex-line-small">
                                    <input type="number" step="0.0001" value={linie.pret_unitar ?? ''} aria-label="Preț unitar" onChange={(event) => updateLine(lineIndex, 'pret_unitar', event.target.value)} />
                                </label>
                                <label className="form-field annex-line-small">
                                    <span className="annex-empty-cell" aria-hidden="true" />
                                </label>
                                <label className="form-field annex-line-small">
                                    <select value={linie.tva_21 ?? ''} aria-label="TVA %" onChange={(event) => updateLine(lineIndex, 'tva_21', event.target.value)}>
                                        <option value="">Fără TVA</option>
                                        {tvaOptions.map((opt) => <option value={opt.valoare} key={opt.valoare}>{opt.label}</option>)}
                                        {linie.tva_21 && !tvaOptions.some((opt) => tvaValuesMatch(opt.valoare, linie.tva_21)) ? <option value={normalizeTvaValue(linie.tva_21)}>{formatTvaLabel(linie.tva_21)}</option> : null}
                                    </select>
                                </label>
                                <div className="annex-order-actions">
                                    <button type="button" className="annex-order-button" onClick={() => moveLine(lineIndex, -1)} disabled={lineIndex === 0} aria-label="Mută rândul mai sus">
                                        <ArrowUp size={13} strokeWidth={2.4} />
                                    </button>
                                    <button type="button" className="annex-order-button" onClick={() => moveLine(lineIndex, 1)} disabled={lineIndex === data.linii.length - 1} aria-label="Mută rândul mai jos">
                                        <ArrowDown size={13} strokeWidth={2.4} />
                                    </button>
                                </div>
                                <button type="button" className="delete-inline-button annex-delete-button annex-line-delete-button" onClick={() => removeLine(lineIndex)} aria-label="Șterge linie anexă">
                                    <Trash2 size={14} strokeWidth={2.4} />
                                </button>
                            </div>
                                );
                            })()
                        ) : (
                            (() => {
                                const isFixLine = linie.tip_calcul === 'fix';
                                const hideIndexFields = templateFaraCantitati(linie.tip_calcul) || isFixLine;
                                const hideQuantityFields = templateFaraCantitati(linie.tip_calcul);

                                return (
                            <div className="annex-line-row" key={`line-${lineIndex}`}>
                                <label className="form-field">
                                    <select value={linie.tip_calcul} aria-label="Tip calcul" onChange={(event) => updateLine(lineIndex, 'tip_calcul', event.target.value)}>
                                        {tipCalculOptions.map((opt) => <option value={opt.valoare} key={opt.valoare}>{opt.label}</option>)}
                                        {linie.tip_calcul && !tipCalculOptions.some((opt) => opt.valoare === linie.tip_calcul) ? <option value={linie.tip_calcul}>{linie.tip_calcul}</option> : null}
                                    </select>
                                </label>
                                <label className="form-field annex-line-small">
                                    <input type="number" min="0" value={linie.nr_crt ?? ''} readOnly tabIndex={-1} aria-label="Nr. crt" />
                                </label>
                                <label className="form-field annex-line-main">
                                    <select value={linie.denumire || ''} aria-label="Denumire serviciu" onChange={(event) => updateLine(lineIndex, 'denumire', event.target.value)}>
                                        <option value="">Alege serviciul</option>
                                        {denumiri.map((opt) => <option value={opt.valoare} key={opt.valoare}>{opt.label}</option>)}
                                        {linie.denumire && !denumiri.some((opt) => opt.valoare === linie.denumire) ? <option value={linie.denumire}>{linie.denumire}</option> : null}
                                    </select>
                                </label>
                                <label className="form-field annex-line-medium">
                                    {hideIndexFields ? (
                                        <span className="annex-empty-cell" aria-hidden="true" />
                                    ) : (
                                        <input type="text" value={linie.index_vechi || ''} aria-label="Index contor vechi" onChange={(event) => updateLine(lineIndex, 'index_vechi', event.target.value)} />
                                    )}
                                </label>
                                <label className="form-field annex-line-medium">
                                    {hideIndexFields ? (
                                        <span className="annex-empty-cell" aria-hidden="true" />
                                    ) : (
                                        <input type="text" value={linie.index_nou || ''} aria-label="Index contor nou" onChange={(event) => updateLine(lineIndex, 'index_nou', event.target.value)} />
                                    )}
                                </label>
                                <label className="form-field annex-line-small">
                                    {hideQuantityFields ? (
                                        <span className="annex-empty-cell" aria-hidden="true" />
                                    ) : (
                                        <input
                                            type="number"
                                            step="0.001"
                                            value={linie.facturat ?? ''}
                                            aria-label="Facturat"
                                            onChange={(event) => updateLine(lineIndex, 'facturat', event.target.value)}
                                        />
                                    )}
                                </label>
                                <label className="form-field annex-line-small">
                                    <select value={linie.um || ''} aria-label="UM" onChange={(event) => updateLine(lineIndex, 'um', event.target.value)}>
                                        <option value="">—</option>
                                        {unitati.map((opt) => <option value={opt.valoare} key={opt.valoare}>{opt.label}</option>)}
                                        {linie.um && !unitati.some((opt) => opt.valoare === linie.um) ? <option value={linie.um}>{linie.um}</option> : null}
                                    </select>
                                </label>
                                <label className="form-field annex-line-small">
                                    <input type="number" step="0.0001" value={linie.pret_unitar ?? ''} aria-label="Preț unitar" onChange={(event) => updateLine(lineIndex, 'pret_unitar', event.target.value)} />
                                </label>
                                <label className="form-field annex-line-small">
                                    {hideQuantityFields ? (
                                        <span className="annex-empty-cell" aria-hidden="true" />
                                    ) : (
                                        <input className={isFixLine ? 'calculated-input' : undefined} type="number" step="0.01" value={valoareForDisplay(linie)} readOnly={isFixLine} tabIndex={isFixLine ? -1 : undefined} aria-readonly={isFixLine ? 'true' : undefined} aria-label="Valoare" onChange={(event) => updateLine(lineIndex, 'valoare', event.target.value)} />
                                    )}
                                </label>
                                <label className="form-field annex-line-small">
                                    <select value={linie.tva_21 ?? ''} aria-label="TVA %" onChange={(event) => updateLine(lineIndex, 'tva_21', event.target.value)}>
                                        <option value="">Fără TVA</option>
                                        {tvaOptions.map((opt) => <option value={opt.valoare} key={opt.valoare}>{opt.label}</option>)}
                                        {linie.tva_21 && !tvaOptions.some((opt) => tvaValuesMatch(opt.valoare, linie.tva_21)) ? <option value={normalizeTvaValue(linie.tva_21)}>{formatTvaLabel(linie.tva_21)}</option> : null}
                                    </select>
                                </label>
                                <div className="annex-order-actions">
                                    <button type="button" className="annex-order-button" onClick={() => moveLine(lineIndex, -1)} disabled={lineIndex === 0} aria-label="Mută rândul mai sus">
                                        <ArrowUp size={13} strokeWidth={2.4} />
                                    </button>
                                    <button type="button" className="annex-order-button" onClick={() => moveLine(lineIndex, 1)} disabled={lineIndex === data.linii.length - 1} aria-label="Mută rândul mai jos">
                                        <ArrowDown size={13} strokeWidth={2.4} />
                                    </button>
                                </div>
                                <button type="button" className="delete-inline-button annex-delete-button annex-line-delete-button" onClick={() => removeLine(lineIndex)} aria-label="Șterge linie anexă">
                                    <Trash2 size={14} strokeWidth={2.4} />
                                </button>
                            </div>
                                );
                            })()
                        )
                    ))}
                    <div className="annex-lines-actions">
                        <button type="button" className="secondary-button" onClick={addLine}>+ Adaugă rând</button>
                        <button type="button" className="secondary-button" onClick={addCoeficientLine}>+ Adaugă rând coeficient</button>
                        <button type="button" className="secondary-button" onClick={addHeader}>+ Adaugă header</button>
                    </div>
                </div>

                <div className="annex-save-actions">
                    <button className="primary-button" type="submit" disabled={processing}>{processing ? 'Se salvează...' : 'Salvează anexa'}</button>
                </div>
            </form>
        </AppLayout>
    );
}
