import React, { useEffect, useMemo, useRef } from 'react';
import { Link, router, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';

function calculatedConsum(indexVechi, indexNou) {
    const vechi = Number(String(indexVechi || '').replace(',', '.'));
    const nou = Number(String(indexNou || '').replace(',', '.'));

    if (!Number.isFinite(vechi) || !Number.isFinite(nou)) {
        return '';
    }

    return String(Number(Math.max(0, nou - vechi).toFixed(3)));
}

function formatDecimalForInput(value) {
    if (value === null || value === undefined || value === '') return '';
    const normalized = String(value).trim().replace(',', '.');
    if (!/^-?\d+(\.\d+)?$/.test(normalized)) return value;

    return normalized.replace(/(\.\d*?)0+$/, '$1').replace(/\.$/, '');
}

function flattenCitiri(spatii) {
    return spatii.flatMap((spatiu) => (spatiu.liniiContor || []).map((linie) => ({
        spatiu_id: spatiu.id,
        configurare_anexa_linie_id: linie.configurare_anexa_linie_id,
        index_vechi: formatDecimalForInput(linie.index_vechi),
        index_nou: formatDecimalForInput(linie.index_nou),
    })));
}

function buildFormState({ imobilId, luna, dataCitire, spatii }) {
    const initialDataCitire = dataCitire || new Date().toISOString().slice(0, 16);

    return {
        imobil_id: imobilId || '',
        luna: luna || initialDataCitire.slice(0, 7),
        data_citire: initialDataCitire,
        citiri: flattenCitiri(spatii),
    };
}

function formatLunaLabel(luna) {
    if (!luna) return '—';
    const [an, lunaNumar] = String(luna).split('-');

    return `${lunaNumar}.${an}`;
}

export default function Imobil({
    imobil,
    luna = '',
    dataCitire = '',
    mode = 'history',
    readOnly = true,
    luniSelectabile = [],
    luniCitite = [],
    spatii = [],
}) {
    const serverStateKey = useMemo(() => JSON.stringify({
        imobilId: imobil?.id,
        luna,
        dataCitire,
        mode,
        spatii: flattenCitiri(spatii),
    }), [imobil?.id, luna, dataCitire, mode, spatii]);

    const lastServerStateKey = useRef(serverStateKey);
    const { data, setData, post, processing, errors } = useForm(buildFormState({
        imobilId: imobil?.id,
        luna,
        dataCitire,
        spatii,
    }));

    useEffect(() => {
        if (lastServerStateKey.current === serverStateKey) {
            return;
        }

        lastServerStateKey.current = serverStateKey;
        setData(buildFormState({ imobilId: imobil?.id, luna, dataCitire, spatii }));
    }, [serverStateKey, imobil?.id, luna, dataCitire, spatii, setData]);

    const luniCititeValues = useMemo(
        () => new Set(luniCitite.map((item) => item.luna)),
        [luniCitite],
    );

    function reload(next) {
        const lunaSelectata = next.luna ?? data.luna;
        const modeForLuna = next.mode ?? (luniCititeValues.has(lunaSelectata) ? mode : 'new');

        router.get(`/citiri-contoare/imobil/${imobil.id}`, {
            mode: modeForLuna,
            luna: lunaSelectata,
            data_citire: next.data_citire ?? data.data_citire,
        }, { preserveScroll: true });
    }

    function updateCitire(spatiuId, linieId, field, value) {
        const index = citireIndexFor(spatiuId, linieId);

        if (index === -1) {
            setData('citiri', [
                ...data.citiri,
                {
                    spatiu_id: spatiuId,
                    configurare_anexa_linie_id: linieId,
                    index_vechi: '',
                    index_nou: '',
                    [field]: value,
                },
            ]);

            return;
        }

        setData('citiri', data.citiri.map((citire, currentIndex) => (
            currentIndex === index ? { ...citire, [field]: value } : citire
        )));
    }

    function citireIndexFor(spatiuId, linieId) {
        return data.citiri.findIndex((citire) => (
            Number(citire.spatiu_id) === Number(spatiuId)
            && Number(citire.configurare_anexa_linie_id) === Number(linieId)
        ));
    }

    function submit(event) {
        event.preventDefault();
        post('/citiri-contoare', { preserveScroll: true });
    }

    const randuriCitiri = spatii.flatMap((spatiu) => (spatiu.liniiContor || []).map((linie) => ({
        spatiu,
        linie,
    })));
    const isNewMode = mode === 'new';

    const topbarActions = (
        <>
            <select className="filter-input topbar-filter" value={data.luna} onChange={(event) => {
                const lunaSelectata = event.target.value;
                setData('luna', lunaSelectata);
                reload({
                    luna: lunaSelectata,
                    mode: luniCititeValues.has(lunaSelectata) ? 'history' : 'new',
                });
            }}>
                {luniSelectabile.map((item) => <option value={item.luna} key={item.luna}>{item.label}</option>)}
            </select>
            <button className={isNewMode ? 'secondary-button' : 'primary-button'} type="button" onClick={() => reload({ mode: isNewMode ? 'history' : 'new' })}>
                {isNewMode ? 'Vezi istoric' : 'Citire lună nouă'}
            </button>
        </>
    );

    return (
        <AppLayout
            title={`Citiri contoare ${imobil.nume}`}
            subtitle={isNewMode ? 'Index vechi preluat automat din ultima citire; completezi index nou.' : 'Istoric citiri: lunile vechi sunt blocate pentru editare.'}
            showGlobalSearch={false}
            topbarActions={topbarActions}
        >
            <section className="readonly-info-card compact-info-card">
                <p>
                    Contoare de citit derivate din anexele alocate spațiilor din <strong>{imobil.nume} ({imobil.localitate})</strong>.
                    {' '}
                    <Link className="secondary-button button-link annex-clear-building-button" href="/citiri-contoare">Înapoi la imobile</Link>
                </p>
            </section>

            <form className="form-card" onSubmit={submit}>
                {randuriCitiri.length === 0 ? (
                    <div className="readonly-info-card">
                        <h2>Nu există contoare de citit</h2>
                        <p>Niciun spațiu din acest imobil nu are anexă cu linii de tip Contor. Alocă anexa pe spații și adaugă servicii cu tip calcul Contor.</p>
                    </div>
                ) : (
                    <div className="meter-reading-groups">
                        <div className="responsive-table">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Spațiu</th>
                                        <th>Nume chiriaș</th>
                                        <th>Anexă</th>
                                        <th>Contor / serviciu</th>
                                        <th>UM</th>
                                        <th>Index vechi</th>
                                        <th>Index nou</th>
                                        <th>Consum</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {randuriCitiri.map(({ spatiu, linie }) => {
                                        const citire = data.citiri[citireIndexFor(spatiu.id, linie.configurare_anexa_linie_id)] || {
                                            index_vechi: formatDecimalForInput(linie.index_vechi),
                                            index_nou: formatDecimalForInput(linie.index_nou),
                                        };
                                        const indexVechiAfisat = citire.index_vechi ?? formatDecimalForInput(linie.index_vechi);

                                        return (
                                            <tr key={`${spatiu.id}-${linie.configurare_anexa_linie_id}`}>
                                                <td><strong>{spatiu.identificator}</strong></td>
                                                <td>{spatiu.chirias || '—'}</td>
                                                <td>{spatiu.anexa || '—'}</td>
                                                <td>{linie.denumire}</td>
                                                <td>{linie.um || '—'}</td>
                                                <td>
                                                    <input
                                                        className="table-input"
                                                        type="text"
                                                        inputMode="decimal"
                                                        value={indexVechiAfisat}
                                                        readOnly={!isNewMode}
                                                        tabIndex={!isNewMode ? -1 : undefined}
                                                        aria-readonly={!isNewMode ? 'true' : undefined}
                                                        onChange={(event) => updateCitire(spatiu.id, linie.configurare_anexa_linie_id, 'index_vechi', event.target.value)}
                                                    />
                                                </td>
                                                <td>
                                                    <input
                                                        className="table-input"
                                                        type="text"
                                                        inputMode="decimal"
                                                        value={citire.index_nou ?? ''}
                                                        readOnly={!isNewMode}
                                                        tabIndex={!isNewMode ? -1 : undefined}
                                                        aria-readonly={!isNewMode ? 'true' : undefined}
                                                        onChange={(event) => updateCitire(spatiu.id, linie.configurare_anexa_linie_id, 'index_nou', event.target.value)}
                                                    />
                                                </td>
                                                <td>{calculatedConsum(citire.index_vechi ?? indexVechiAfisat, citire.index_nou) || '—'}</td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                {errors.imobil_id ? <small>{errors.imobil_id}</small> : null}
                {errors.data_citire ? <small>{errors.data_citire}</small> : null}

                {isNewMode && randuriCitiri.length > 0 ? (
                    <div className="form-footer-actions">
                        <label className="form-field">
                            <span>Data citire</span>
                            <input type="datetime-local" value={data.data_citire} onChange={(event) => setData('data_citire', event.target.value)} />
                        </label>
                        <div className="form-actions">
                            <button className="primary-button" type="submit" disabled={processing || data.citiri.length === 0}>
                                {processing ? 'Se salvează...' : `Salvează citirea ${formatLunaLabel(data.luna)}`}
                            </button>
                        </div>
                    </div>
                ) : null}
            </form>
        </AppLayout>
    );
}
