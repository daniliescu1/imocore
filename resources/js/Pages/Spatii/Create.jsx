import React from 'react';
import { Link, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';

const etajOptions = ['-1', 'Parter', '1', '2', '3', '4', '5', 'Acoperiș'];

const defaultVisibleSpaceFields = [
    'suprafata_contractuala_mp',
    'corp',
    'etaj',
    'persoane_standard',
    'pret_lunar',
    'indexare_2025',
    'indexare_2026',
    'pret_mp_ultima_indexare',
    'regim_incalzire',
    'procent_incalzire_override',
    'locator_id',
    'configurare_anexa_id',
    'chirias',
    'observatii',
];

function defaultConfigurareAnexaId(configurari) {
    if (!configurari.length) {
        return '';
    }

    const implicit = configurari.find((configurare) => configurare.implicit);

    return implicit?.id || (configurari.length === 1 ? configurari[0].id : '');
}

function numericValue(value) {
    if (value === null || value === undefined) {
        return null;
    }

    const normalized = normalizeDecimalInput(value);
    if (normalized === null || normalized === '') {
        return null;
    }

    const number = Number(normalized);

    return Number.isFinite(number) ? number : null;
}

function normalizeDecimalInput(value) {
    if (value === null || value === undefined) {
        return null;
    }

    let normalized = String(value).trim().replace(/\s/g, '');

    if (normalized === '') {
        return null;
    }

    const hasComma = normalized.includes(',');
    const hasDot = normalized.includes('.');

    if (hasComma && hasDot) {
        normalized = normalized.replace(/,/g, '');
    } else if (hasComma) {
        normalized = normalized.replace(',', '.');
    }

    return normalized;
}

function decimalInputProps(field, value, setData) {
    return {
        type: 'text',
        inputMode: 'decimal',
        value,
        onChange: (event) => setData(field, event.target.value),
        onBlur: (event) => {
            const normalized = normalizeDecimalInput(event.target.value);
            setData(field, normalized ?? '');
        },
    };
}

function defaultRegimIncalzire(status, regimExistent = null) {
    if (regimExistent) {
        return regimExistent;
    }

    if (status === 'liber' || status === 'inchiriat') {
        return 'integral';
    }

    return 'neincalzit';
}

function applyStatusChange(status, data) {
    if (status === 'administrativ') {
        return {
            ...data,
            status,
            regim_incalzire: 'neincalzit',
            procent_incalzire_override: '',
            locator_id: '',
            configurare_anexa_id: '',
            chirias: '',
            indexare_2025: '',
            indexare_2026: '',
        };
    }

    if (status === 'liber' || status === 'inchiriat') {
        return {
            ...data,
            status,
            regim_incalzire: 'integral',
            procent_incalzire_override: '',
        };
    }

    return { ...data, status };
}

export default function Create({ imobile, locatori, configurariAnexe = {}, campuriSpatiuVizibile = {}, spatiu = null, initialImobilId = null }) {
    const isEditing = Boolean(spatiu);
    const initialStatus = spatiu?.status || 'liber';
    const { data, setData, post, put, processing, errors, transform } = useForm({
        imobil_id: spatiu?.imobil_id || initialImobilId || '',
        identificator: spatiu?.identificator || '',
        suprafata_contractuala_mp: spatiu?.suprafata_contractuala_mp || '',
        corp: spatiu?.corp || '',
        etaj: spatiu?.etaj || 'Parter',
        regim_incalzire: defaultRegimIncalzire(initialStatus, spatiu?.regim_incalzire || null),
        procent_incalzire_override: spatiu?.procent_incalzire_override || '',
        retim_direct: Boolean(spatiu?.retim_direct),
        status: spatiu?.status || 'liber',
        pret_lunar: spatiu?.pret_lunar || '',
        indexare_2025: spatiu?.indexare_2025 || '',
        indexare_2026: spatiu?.indexare_2026 || '',
        moneda: spatiu?.moneda || 'EUR',
        locator_id: spatiu?.locator_id || '',
        configurare_anexa_id: spatiu?.configurare_anexa_id || '',
        chirias: spatiu?.chirias || '',
        observatii: spatiu?.observatii || '',
    });

    const locatoriDisponibili = locatori || [];
    const configurariPentruImobil = data.imobil_id ? (configurariAnexe[data.imobil_id] || []) : [];
    const campuriVizibile = data.imobil_id ? (campuriSpatiuVizibile[data.imobil_id] || defaultVisibleSpaceFields) : defaultVisibleSpaceFields;
    const showField = (field) => campuriVizibile.includes(field);
    const esteAdministrativ = data.status === 'administrativ';
    const ultimaIndexare = numericValue(data.indexare_2026) ?? numericValue(data.indexare_2025);
    const suprafataPentruIndexare = numericValue(data.suprafata_contractuala_mp);
    const pretMpUltimaIndexare = ultimaIndexare !== null && suprafataPentruIndexare
        ? (ultimaIndexare / suprafataPentruIndexare).toFixed(2)
        : '';

    transform((formData) => ({
        ...formData,
        suprafata_contractuala_mp: normalizeDecimalInput(formData.suprafata_contractuala_mp) ?? '',
        pret_lunar: normalizeDecimalInput(formData.pret_lunar) ?? '',
        indexare_2025: normalizeDecimalInput(formData.indexare_2025) ?? '',
        indexare_2026: normalizeDecimalInput(formData.indexare_2026) ?? '',
        procent_incalzire_override: normalizeDecimalInput(formData.procent_incalzire_override) ?? '',
    }));

    function submit(event) {
        event.preventDefault();

        if (isEditing) {
            put(`/spatii/${spatiu.id}`);
            return;
        }

        post('/spatii');
    }

    const backHref = data.imobil_id ? `/spatii?imobil_id=${data.imobil_id}` : '/spatii';
    const topbarActions = <Link className="secondary-button button-link" href={backHref}>Înapoi la spații</Link>;

    return (
        <AppLayout
            title={isEditing ? `Editare ${spatiu.identificator}` : 'Adaugă spațiu'}
            subtitle="Completează datele spațiului aferent unui imobil."
            showGlobalSearch={false}
            topbarActions={topbarActions}
        >
            <form className="form-card" onSubmit={submit}>
                <div className="form-grid">
                    <label className="form-field">
                        <span>Imobil *</span>
                        <select value={data.imobil_id} onChange={(event) => {
                            const imobilId = event.target.value;
                            const configurariPentruImobilNou = imobilId ? (configurariAnexe[imobilId] || []) : [];

                            setData({
                                ...data,
                                imobil_id: imobilId,
                                locator_id: '',
                                configurare_anexa_id: defaultConfigurareAnexaId(configurariPentruImobilNou),
                            });
                        }}>
                            <option value="">Alege imobilul</option>
                            {imobile.map((imobil) => <option value={imobil.id} key={imobil.id}>{imobil.label}</option>)}
                        </select>
                        {errors.imobil_id ? <small>{errors.imobil_id}</small> : null}
                    </label>

                    <label className="form-field">
                        <span>Identificat la locator cu numărul *</span>
                        <input type="text" value={data.identificator} onChange={(event) => setData('identificator', event.target.value)} />
                        {errors.identificator ? <small>{errors.identificator}</small> : null}
                    </label>

                    {showField('suprafata_contractuala_mp') ? (
                        <label className="form-field">
                            <span>Suprafață mp</span>
                            <input {...decimalInputProps('suprafata_contractuala_mp', data.suprafata_contractuala_mp, setData)} />
                            {errors.suprafata_contractuala_mp ? <small>{errors.suprafata_contractuala_mp}</small> : null}
                        </label>
                    ) : null}

                    {showField('pret_lunar') ? (
                        <label className="form-field">
                            <span>Chirie lunară EUR</span>
                            <input {...decimalInputProps('pret_lunar', data.pret_lunar, setData)} />
                            {errors.pret_lunar ? <small>{errors.pret_lunar}</small> : null}
                        </label>
                    ) : null}

                    {showField('corp') ? (
                        <label className="form-field">
                            <span>Corp</span>
                            <input type="text" value={data.corp} onChange={(event) => setData('corp', event.target.value)} />
                            {errors.corp ? <small>{errors.corp}</small> : null}
                        </label>
                    ) : null}

                    {showField('etaj') ? (
                        <label className="form-field">
                            <span>Etaj</span>
                            <select value={data.etaj} onChange={(event) => setData('etaj', event.target.value)}>
                                {etajOptions.map((etaj) => <option value={etaj} key={etaj}>{etaj}</option>)}
                                {data.etaj && !etajOptions.includes(data.etaj) ? <option value={data.etaj}>{data.etaj}</option> : null}
                            </select>
                            {errors.etaj ? <small>{errors.etaj}</small> : null}
                        </label>
                    ) : null}

                    <label className="form-field">
                        <span>Status *</span>
                        <select value={data.status} onChange={(event) => setData(applyStatusChange(event.target.value, data))}>
                            <option value="liber">Liber</option>
                            <option value="rezervat">Rezervat</option>
                            <option value="inchiriat">Închiriat</option>
                            <option value="comun">Spațiu comun</option>
                            <option value="administrativ">Administrativ</option>
                        </select>
                        {errors.status ? <small>{errors.status}</small> : null}
                    </label>

                    {showField('persoane_standard') && !esteAdministrativ ? (
                        <label className="form-field">
                            <span>Persoane standard calculate</span>
                            <input type="text" value={Math.floor((numericValue(data.suprafata_contractuala_mp) ?? 0) / 10)} readOnly />
                        </label>
                    ) : null}

                    {showField('regim_incalzire') && !esteAdministrativ ? (
                        <label className="form-field">
                            <span>Regim încălzire</span>
                            <select value={data.regim_incalzire} onChange={(event) => {
                                const regim = event.target.value;
                                setData({
                                    ...data,
                                    regim_incalzire: regim,
                                    procent_incalzire_override: regim === 'partial' ? data.procent_incalzire_override : '',
                                });
                            }}>
                                <option value="integral">Încălzit integral</option>
                                <option value="partial">Țevi încălzire / încălzire parțială</option>
                                <option value="neincalzit">Neîncălzit</option>
                                <option value="manual">Excepție</option>
                            </select>
                            {errors.regim_incalzire ? <small>{errors.regim_incalzire}</small> : null}
                        </label>
                    ) : null}

                    {showField('procent_incalzire_override') && !esteAdministrativ && data.regim_incalzire === 'partial' ? (
                        <label className="form-field">
                            <span>Procent încălzire manual</span>
                            <input type="number" min="0" max="100" step="0.01" value={data.procent_incalzire_override} onChange={(event) => setData('procent_incalzire_override', event.target.value)} />
                            {errors.procent_incalzire_override ? <small>{errors.procent_incalzire_override}</small> : null}
                        </label>
                    ) : null}

                    {showField('locator_id') && !esteAdministrativ ? (
                        <label className="form-field">
                            <span>Locator</span>
                            <select value={data.locator_id} onChange={(event) => setData('locator_id', event.target.value)} disabled={!data.imobil_id}>
                                <option value="">Alege locator existent</option>
                                {locatoriDisponibili.map((locator) => <option value={locator.id} key={locator.id}>{locator.nume}</option>)}
                            </select>
                            {errors.locator_id ? <small>{errors.locator_id}</small> : null}
                        </label>
                    ) : null}

                    {showField('configurare_anexa_id') && !esteAdministrativ ? (
                        <label className="form-field">
                            <span>Configurare anexă</span>
                            <select value={data.configurare_anexa_id} onChange={(event) => setData('configurare_anexa_id', event.target.value)} disabled={!data.imobil_id || configurariPentruImobil.length === 0}>
                                <option value="">{configurariPentruImobil.length ? 'Alege anexa pentru acest spațiu' : 'Nu există anexă configurată pe acest imobil'}</option>
                                {configurariPentruImobil.map((configurare) => <option value={configurare.id} key={configurare.id}>{configurare.denumire}</option>)}
                            </select>
                            {errors.configurare_anexa_id ? <small>{errors.configurare_anexa_id}</small> : null}
                        </label>
                    ) : null}

                    {showField('chirias') && !esteAdministrativ ? (
                        <label className="form-field">
                            <span>Chiriaș</span>
                            <input type="text" value={data.chirias} onChange={(event) => setData('chirias', event.target.value)} />
                            {errors.chirias ? <small>{errors.chirias}</small> : null}
                        </label>
                    ) : null}

                </div>

                {showField('observatii') ? (
                    <label className="form-field form-field-full">
                        <span>Observații</span>
                        <textarea value={data.observatii} onChange={(event) => setData('observatii', event.target.value)} rows="4" />
                        {errors.observatii ? <small>{errors.observatii}</small> : null}
                    </label>
                ) : null}

                <div className="form-footer-actions">
                    <span />
                    <div className="form-actions">
                        <Link className="secondary-button button-link" href={backHref}>Anulează</Link>
                        <button className="primary-button" type="submit" disabled={processing}>{processing ? 'Se salvează...' : (isEditing ? 'Salvează modificările' : 'Salvează spațiu')}</button>
                    </div>
                </div>

                {!esteAdministrativ && (showField('indexare_2025') || showField('indexare_2026') || (showField('pret_mp_ultima_indexare') && ultimaIndexare !== null && pretMpUltimaIndexare)) ? (
                    <section className="form-indexare-section">
                        <div className="form-grid">
                            {showField('indexare_2025') ? (
                                <label className="form-field">
                                    <span>Indexare 2025</span>
                                    <input {...decimalInputProps('indexare_2025', data.indexare_2025, setData)} />
                                    {errors.indexare_2025 ? <small>{errors.indexare_2025}</small> : null}
                                </label>
                            ) : null}

                            {showField('indexare_2026') ? (
                                <label className="form-field">
                                    <span>Indexare 2026</span>
                                    <input {...decimalInputProps('indexare_2026', data.indexare_2026, setData)} />
                                    {errors.indexare_2026 ? <small>{errors.indexare_2026}</small> : null}
                                </label>
                            ) : null}

                            {showField('pret_mp_ultima_indexare') && ultimaIndexare !== null && pretMpUltimaIndexare ? (
                                <label className="form-field">
                                    <span>Preț / mp ultima indexare</span>
                                    <input type="text" value={pretMpUltimaIndexare} readOnly tabIndex={-1} aria-readonly="true" />
                                </label>
                            ) : null}
                        </div>
                    </section>
                ) : null}
            </form>
        </AppLayout>
    );
}
