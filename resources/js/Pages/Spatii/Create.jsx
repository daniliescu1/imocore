import React, { useRef } from 'react';
import { Link, router, useForm } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import FacadeRentalCalendar from './FacadeRentalCalendar';

const etajOptions = ['-1', 'Parter', '1', '2', '3', '4', '5', 'Acoperiș', 'Fațadă', 'Parcare'];
const etajeFaraPersoane = ['Acoperiș', 'Fațadă', 'Parcare'];

function monedaLabel(moneda) {
    return moneda === 'RON' ? 'Lei' : 'EUR';
}

const defaultVisibleSpaceFields = [
    'suprafata_contractuala_mp',
    'corp',
    'etaj',
    'persoane_standard',
    'pret_lunar',
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

function persoaneStandardCalculate(suprafata) {
    const mp = numericValue(suprafata) ?? 0;

    if (mp <= 0) {
        return 0;
    }

    return Math.ceil(mp / 10);
}

function normalizeRegimIncalzire(regim) {
    return regim === 'manual' ? 'integral' : regim;
}

function defaultRegimIncalzire(status, regimExistent = null) {
    if (regimExistent) {
        return normalizeRegimIncalzire(regimExistent);
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
            indexare_2026: '',
        };
    }

    if (status === 'comun') {
        return {
            ...data,
            status,
            regim_incalzire: 'neincalzit',
            procent_incalzire_override: '',
            locator_id: '',
            configurare_anexa_id: '',
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

function applyEtajChange(etaj, data) {
    if (etajeFaraPersoane.includes(etaj)) {
        return {
            ...data,
            etaj,
            regim_incalzire: 'neincalzit',
            procent_incalzire_override: '',
            configurare_anexa_id: '',
            moneda: etaj === 'Parcare'
                ? (data.etaj === 'Parcare' ? data.moneda : 'RON')
                : 'EUR',
        };
    }

    if (etajeFaraPersoane.includes(data.etaj)) {
        return {
            ...data,
            etaj,
            regim_incalzire: defaultRegimIncalzire(data.status, null),
            moneda: 'EUR',
        };
    }

    return { ...data, etaj, moneda: 'EUR' };
}

export default function Create({ imobile, locatori, configurariAnexe = {}, campuriSpatiuVizibile = {}, spatiu = null, initialImobilId = null, perioadeFatada = [], canDeleteSpatii = false }) {
    const isEditing = Boolean(spatiu);
    const initialStatus = spatiu?.status || 'inchiriat';
    const { data, setData, post, put, processing, errors, transform } = useForm({
        imobil_id: spatiu?.imobil_id || initialImobilId || '',
        identificator: spatiu?.identificator || '',
        suprafata_contractuala_mp: spatiu?.suprafata_contractuala_mp || '',
        corp: spatiu?.corp || '',
        etaj: spatiu?.etaj || 'Parter',
        regim_incalzire: defaultRegimIncalzire(initialStatus, spatiu?.regim_incalzire || null),
        procent_incalzire_override: spatiu?.procent_incalzire_override || '',
        retim_direct: Boolean(spatiu?.retim_direct),
        status: spatiu?.status || 'inchiriat',
        pret_lunar: spatiu?.pret_lunar || '',
        indexare_2026: spatiu?.indexare_2026 || '',
        moneda: spatiu?.moneda || (spatiu?.etaj === 'Parcare' ? 'RON' : 'EUR'),
        locator_id: spatiu?.locator_id || '',
        configurare_anexa_id: spatiu?.configurare_anexa_id || '',
        chirias: spatiu?.chirias || '',
        observatii: spatiu?.observatii || '',
        de_lamurit: Boolean(spatiu?.de_lamurit),
        de_lamurit_detaliu: spatiu?.de_lamurit_detaliu || '',
        marcat_galben: Boolean(spatiu?.marcat_galben),
        marcat_verde: Boolean(spatiu?.marcat_verde),
    });

    const locatoriDisponibili = locatori || [];
    const configurariPentruImobil = data.imobil_id ? (configurariAnexe[data.imobil_id] || []) : [];
    const campuriVizibile = data.imobil_id ? (campuriSpatiuVizibile[data.imobil_id] || defaultVisibleSpaceFields) : defaultVisibleSpaceFields;
    const showField = (field) => campuriVizibile.includes(field);
    const esteAdministrativ = data.status === 'administrativ';
    const esteComun = data.status === 'comun';
    const etajFaraPersoane = etajeFaraPersoane.includes(data.etaj);
    const esteFatada = data.etaj === 'Fațadă';
    const esteParcare = data.etaj === 'Parcare';
    const chirieMonedaLabel = monedaLabel(data.moneda);
    const ultimaIndexare = numericValue(data.indexare_2026);
    const suprafataPentruIndexare = numericValue(data.suprafata_contractuala_mp);
    const pretMpUltimaIndexare = ultimaIndexare !== null && suprafataPentruIndexare
        ? (ultimaIndexare / suprafataPentruIndexare).toFixed(2)
        : '';

    const lamuritDetaliuRef = useRef(null);

    transform((formData) => ({
        ...formData,
        suprafata_contractuala_mp: normalizeDecimalInput(formData.suprafata_contractuala_mp) ?? '',
        pret_lunar: normalizeDecimalInput(formData.pret_lunar) ?? '',
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

    function deleteSpatiu() {
        if (!window.confirm('Are you sure?')) {
            return;
        }

        router.delete(`/spatii/${spatiu.id}`);
    }

    function toggleMarcaj(field) {
        const activating = !data[field];
        const previous = {
            marcat_galben: data.marcat_galben,
            marcat_verde: data.marcat_verde,
            de_lamurit: data.de_lamurit,
            de_lamurit_detaliu: data.de_lamurit_detaliu,
        };

        setData((current) => {
            const next = {
                ...current,
                marcat_galben: false,
                marcat_verde: false,
                de_lamurit: false,
            };

            if (activating) {
                next[field] = true;
            }

            if (field === 'de_lamurit' && !activating) {
                next.de_lamurit_detaliu = '';
            }

            if (field !== 'de_lamurit' && activating) {
                next.de_lamurit_detaliu = '';
            }

            return next;
        });

        if (field === 'de_lamurit' && activating) {
            setTimeout(() => lamuritDetaliuRef.current?.focus(), 0);
        }

        if (!isEditing) {
            return;
        }

        router.patch(`/spatii/${spatiu.id}/marcaj`, { field, value: activating }, {
            preserveScroll: true,
            preserveState: true,
            onError: () => {
                setData((current) => ({
                    ...current,
                    ...previous,
                }));
            },
        });
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
                        <>
                            <label className="form-field">
                                <span>Chirie lunară {esteParcare ? chirieMonedaLabel : 'EUR'}</span>
                                <input {...decimalInputProps('pret_lunar', data.pret_lunar, setData)} />
                                {errors.pret_lunar ? <small>{errors.pret_lunar}</small> : null}
                            </label>
                            {esteParcare ? (
                                <label className="form-field">
                                    <span>Monedă</span>
                                    <select value={data.moneda} onChange={(event) => setData('moneda', event.target.value)}>
                                        <option value="RON">Lei</option>
                                        <option value="EUR">EUR</option>
                                    </select>
                                    {errors.moneda ? <small>{errors.moneda}</small> : null}
                                </label>
                            ) : null}
                        </>
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
                            <select value={data.etaj} onChange={(event) => setData(applyEtajChange(event.target.value, data))}>
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

                    {showField('persoane_standard') && !esteAdministrativ && !esteComun && !etajFaraPersoane ? (
                        <label className="form-field">
                            <span>Persoane standard calculate</span>
                            <input type="text" value={persoaneStandardCalculate(data.suprafata_contractuala_mp)} readOnly />
                        </label>
                    ) : null}

                    {showField('regim_incalzire') && !esteAdministrativ && !esteComun && !etajFaraPersoane ? (
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
                            </select>
                            {errors.regim_incalzire ? <small>{errors.regim_incalzire}</small> : null}
                        </label>
                    ) : null}

                    {showField('procent_incalzire_override') && !esteAdministrativ && !esteComun && !etajFaraPersoane && data.regim_incalzire === 'partial' ? (
                        <label className="form-field">
                            <span>Procent încălzire parțială</span>
                            <div className="form-input-addon">
                                <input type="number" min="0" max="100" step="0.01" value={data.procent_incalzire_override} onChange={(event) => setData('procent_incalzire_override', event.target.value)} />
                                <span className="form-input-addon-suffix">%</span>
                            </div>
                            {errors.procent_incalzire_override ? <small>{errors.procent_incalzire_override}</small> : null}
                        </label>
                    ) : null}

                    {showField('locator_id') && !esteAdministrativ && !esteComun ? (
                        <label className="form-field">
                            <span>Locator</span>
                            <select value={data.locator_id} onChange={(event) => setData('locator_id', event.target.value)} disabled={!data.imobil_id}>
                                <option value="">Alege locator existent</option>
                                {locatoriDisponibili.map((locator) => <option value={locator.id} key={locator.id}>{locator.nume}</option>)}
                            </select>
                            {errors.locator_id ? <small>{errors.locator_id}</small> : null}
                        </label>
                    ) : null}

                    {showField('configurare_anexa_id') && !esteAdministrativ && !esteComun && !etajFaraPersoane ? (
                        <label className="form-field">
                            <span>Configurare anexă</span>
                            <select value={data.configurare_anexa_id} onChange={(event) => setData('configurare_anexa_id', event.target.value)} disabled={!data.imobil_id || configurariPentruImobil.length === 0}>
                                <option value="">{configurariPentruImobil.length ? 'Alege anexa pentru acest spațiu' : 'Nu există anexă configurată pe acest imobil'}</option>
                                {configurariPentruImobil.map((configurare) => <option value={configurare.id} key={configurare.id}>{configurare.denumire}</option>)}
                            </select>
                            {errors.configurare_anexa_id ? <small>{errors.configurare_anexa_id}</small> : null}
                        </label>
                    ) : null}

                    {showField('chirias') && !esteAdministrativ && !esteFatada ? (
                        <label className="form-field">
                            <span>Chiriaș</span>
                            <input type="text" value={data.chirias} onChange={(event) => setData('chirias', event.target.value)} />
                            {errors.chirias ? <small>{errors.chirias}</small> : null}
                        </label>
                    ) : null}

                    {showField('chirias') && !esteAdministrativ && esteFatada && isEditing ? (
                        <label className="form-field">
                            <span>Chiriaș curent</span>
                            <input type="text" value={data.chirias || '—'} readOnly tabIndex={-1} aria-readonly="true" />
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

                <section className="form-indexare-section">
                    <div className="spatiu-marcaj-switches">
                        <label className="spatiu-marcaj-switch">
                            <button
                                type="button"
                                role="switch"
                                aria-checked={data.marcat_galben}
                                className={`spatiu-marcaj-toggle is-yellow${data.marcat_galben ? ' is-on' : ''}`}
                                onClick={() => toggleMarcaj('marcat_galben')}
                            >
                                <span className="spatiu-marcaj-toggle-thumb" />
                            </button>
                            <span className="spatiu-marcaj-switch-label">Galben</span>
                        </label>

                        <label className="spatiu-marcaj-switch">
                            <button
                                type="button"
                                role="switch"
                                aria-checked={data.marcat_verde}
                                className={`spatiu-marcaj-toggle is-green${data.marcat_verde ? ' is-on' : ''}`}
                                onClick={() => toggleMarcaj('marcat_verde')}
                            >
                                <span className="spatiu-marcaj-toggle-thumb" />
                            </button>
                            <span className="spatiu-marcaj-switch-label">Verde</span>
                        </label>

                        <label className="spatiu-marcaj-switch">
                            <button
                                type="button"
                                role="switch"
                                aria-checked={data.de_lamurit}
                                className={`spatiu-marcaj-toggle is-red${data.de_lamurit ? ' is-on' : ''}`}
                                onClick={() => toggleMarcaj('de_lamurit')}
                            >
                                <span className="spatiu-marcaj-toggle-thumb" />
                            </button>
                            <span className="spatiu-marcaj-switch-label">De lămurit</span>
                        </label>
                    </div>
                    {errors.de_lamurit ? <small>{errors.de_lamurit}</small> : null}
                    {errors.marcat_galben ? <small>{errors.marcat_galben}</small> : null}
                    {errors.marcat_verde ? <small>{errors.marcat_verde}</small> : null}

                    {data.de_lamurit ? (
                        <label className="form-field form-field-full de-lamurit-detaliu-field">
                            <span>Ce este de lămurit</span>
                            <textarea
                                ref={lamuritDetaliuRef}
                                value={data.de_lamurit_detaliu}
                                onChange={(event) => setData('de_lamurit_detaliu', event.target.value)}
                                rows="3"
                                placeholder="Ex.: suprafață de confirmat, chirie de verificat..."
                            />
                            {errors.de_lamurit_detaliu ? <small>{errors.de_lamurit_detaliu}</small> : null}
                        </label>
                    ) : null}

                    {!esteAdministrativ && !esteFatada && (showField('indexare_2026') || (showField('pret_mp_ultima_indexare') && ultimaIndexare !== null && pretMpUltimaIndexare)) ? (
                    <div className="form-grid">
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
                    ) : null}

                    {isEditing && canDeleteSpatii ? (
                        <button type="button" className="delete-imobil-button" onClick={deleteSpatiu}>
                            <Trash2 size={16} strokeWidth={2.4} />
                            <span>Șterge spațiu</span>
                        </button>
                    ) : null}
                </section>
            </form>

            {esteFatada && isEditing ? (
                <FacadeRentalCalendar spatiuId={spatiu.id} />
            ) : null}

            {esteFatada && !isEditing ? (
                <p className="fatada-calendar-note">Salvează spațiul pentru a gestiona calendarul anual de închiriere pe fațadă.</p>
            ) : null}
        </AppLayout>
    );
}
