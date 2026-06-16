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
    return line.tip_calcul === 'mp_coeficient';
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

    return {
        ...line,
        tip_linie: 'serviciu',
        tip_calcul: line.tip_calcul || 'manual',
        index_vechi: formatDecimalForInput(line.index_vechi),
        index_nou: formatDecimalForInput(line.index_nou),
        facturat: formatDecimalForInput(line.facturat),
        pret_unitar: formatDecimalForInput(line.pret_unitar),
        valoare: formatDecimalForInput(line.valoare),
        coeficient: formatDecimalForInput(line.coeficient),
        tva_21: normalizeTvaValue(line.tva_21),
    };
}

function AnnexColumnHeader({ showActions = false, lineIndex = null, onMoveUp, onMoveDown, onRemove, canMoveUp = false, canMoveDown = false }) {
    return (
        <div className={`annex-line-header${showActions ? ' annex-line-header-row' : ''}`}>
            <span>Nr. crt</span>
            <span>Denumire serviciu</span>
            <span>Index contor vechi</span>
            <span>Index contor nou</span>
            <span>Facturat</span>
            <span>UM</span>
            <span>Preț unitar</span>
            <span>Valoare</span>
            <span>TVA %</span>
            <span>Tip calcul</span>
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
    context = null,
}) {
    const isEditing = Boolean(anexa);
    const { data, setData, post, put, processing, errors, transform } = useForm(buildFormState(anexa, selectedImobilId));

    function normalizeLiniiForSave(linii) {
        return linii.map((linie) => {
            if (linie.tip_linie === 'header') {
                return { tip_linie: 'header', id: linie.id ?? null };
            }

            if (isMpCoeficientLine(linie)) {
                return {
                    ...linie,
                    tip_linie: 'serviciu',
                    tip_calcul: 'mp_coeficient',
                    coeficient: linie.coeficient ?? '',
                    index_vechi: '',
                    index_nou: '',
                    facturat: '',
                    valoare: '',
                    tva_21: normalizeTvaValue(linie.tva_21),
                };
            }

            return {
                ...linie,
                tip_linie: 'serviciu',
                tip_calcul: linie.tip_calcul || 'manual',
                tva_21: normalizeTvaValue(linie.tva_21),
            };
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

            if (isMpCoeficientLine(nextLine)) {
                if (['coeficient', 'pret_unitar'].includes(field)) {
                    nextLine.facturat = '';
                    nextLine.valoare = '';
                }

                return nextLine;
            }

            if (['index_vechi', 'index_nou'].includes(field)) {
                nextLine.facturat = calculatedFacturat(nextLine.index_vechi, nextLine.index_nou);
            }
            if (['index_vechi', 'index_nou', 'facturat', 'pret_unitar'].includes(field)) {
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
                            <div className="annex-line-row annex-line-row-coeficient" key={`line-${lineIndex}`}>
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
                                    <input className="calculated-input" type="text" value="" readOnly tabIndex={-1} placeholder="mp spațiu" aria-label="Suprafață mp spațiu" title="Se completează automat din spațiu la generare" />
                                </label>
                                <label className="form-field annex-line-medium">
                                    <input type="number" step="0.0001" min="0" value={linie.coeficient ?? ''} aria-label="Coeficient mp" onChange={(event) => updateLine(lineIndex, 'coeficient', event.target.value)} />
                                </label>
                                <label className="form-field annex-line-small">
                                    <input className="calculated-input" type="text" value="" readOnly tabIndex={-1} placeholder="auto" aria-label="Facturat" title="mp × coeficient, calculat la generare" />
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
                                    <input className="calculated-input" type="text" value="" readOnly tabIndex={-1} aria-label="Valoare" title="Calculată la generare" />
                                </label>
                                <label className="form-field annex-line-small">
                                    <select value={linie.tva_21 ?? ''} aria-label="TVA %" onChange={(event) => updateLine(lineIndex, 'tva_21', event.target.value)}>
                                        <option value="">Fără TVA</option>
                                        {tvaOptions.map((opt) => <option value={opt.valoare} key={opt.valoare}>{opt.label}</option>)}
                                        {linie.tva_21 && !tvaOptions.some((opt) => tvaValuesMatch(opt.valoare, linie.tva_21)) ? <option value={normalizeTvaValue(linie.tva_21)}>{formatTvaLabel(linie.tva_21)}</option> : null}
                                    </select>
                                </label>
                                <label className="form-field">
                                    <input className="calculated-input" type="text" value="Mp × coeficient" readOnly tabIndex={-1} aria-label="Tip calcul" />
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
                        ) : (
                            <div className="annex-line-row" key={`line-${lineIndex}`}>
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
                                    <input type="text" value={linie.index_vechi || ''} aria-label="Index contor vechi" onChange={(event) => updateLine(lineIndex, 'index_vechi', event.target.value)} />
                                </label>
                                <label className="form-field annex-line-medium">
                                    <input type="text" value={linie.index_nou || ''} aria-label="Index contor nou" onChange={(event) => updateLine(lineIndex, 'index_nou', event.target.value)} />
                                </label>
                                <label className="form-field annex-line-small">
                                    <input type="number" step="0.001" value={linie.facturat ?? ''} aria-label="Facturat" onChange={(event) => updateLine(lineIndex, 'facturat', event.target.value)} />
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
                                    <input className="calculated-input" type="number" step="0.01" value={linie.valoare ?? ''} readOnly tabIndex={-1} aria-readonly="true" aria-label="Valoare" />
                                </label>
                                <label className="form-field annex-line-small">
                                    <select value={linie.tva_21 ?? ''} aria-label="TVA %" onChange={(event) => updateLine(lineIndex, 'tva_21', event.target.value)}>
                                        <option value="">Fără TVA</option>
                                        {tvaOptions.map((opt) => <option value={opt.valoare} key={opt.valoare}>{opt.label}</option>)}
                                        {linie.tva_21 && !tvaOptions.some((opt) => tvaValuesMatch(opt.valoare, linie.tva_21)) ? <option value={normalizeTvaValue(linie.tva_21)}>{formatTvaLabel(linie.tva_21)}</option> : null}
                                    </select>
                                </label>
                                <label className="form-field">
                                    <select value={linie.tip_calcul} aria-label="Tip calcul" onChange={(event) => updateLine(lineIndex, 'tip_calcul', event.target.value)}>
                                        {tipCalculOptions.map((opt) => <option value={opt.valoare} key={opt.valoare}>{opt.label}</option>)}
                                        {linie.tip_calcul && !tipCalculOptions.some((opt) => opt.valoare === linie.tip_calcul) ? <option value={linie.tip_calcul}>{linie.tip_calcul}</option> : null}
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
