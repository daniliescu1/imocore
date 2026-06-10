import React, { useEffect, useMemo, useRef } from 'react';
import { router, useForm } from '@inertiajs/react';
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

function buildFormState({ selectedImobilId, luna, dataCitire, spatii }) {
    const initialDataCitire = dataCitire || new Date().toISOString().slice(0, 16);

    return {
        imobil_id: selectedImobilId || '',
        luna: luna || initialDataCitire.slice(0, 7),
        data_citire: initialDataCitire,
        citiri: flattenCitiri(spatii),
    };
}

export default function Index({ imobile = [], selectedImobilId = null, luna = '', dataCitire = '', mode = 'history', readOnly = true, luniCitite = [], luniSelectabile = [], imobil = null, spatii = [] }) {
    const serverStateKey = useMemo(() => JSON.stringify({
        selectedImobilId,
        luna,
        dataCitire,
        mode,
        spatii: flattenCitiri(spatii),
    }), [selectedImobilId, luna, dataCitire, mode, spatii]);

    const lastServerStateKey = useRef(serverStateKey);
    const { data, setData, post, processing, errors } = useForm(buildFormState({ selectedImobilId, luna, dataCitire, spatii }));

    useEffect(() => {
        if (lastServerStateKey.current === serverStateKey) {
            return;
        }

        lastServerStateKey.current = serverStateKey;
        setData(buildFormState({ selectedImobilId, luna, dataCitire, spatii }));
    }, [serverStateKey, selectedImobilId, luna, dataCitire, spatii, setData]);

    function reload(next) {
        router.get('/citiri-contoare', {
            imobil_id: next.imobil_id ?? data.imobil_id,
            mode: next.mode ?? mode,
            luna: next.luna ?? data.luna,
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
    const spatiiFaraCitiri = spatii.filter((spatiu) => !spatiu.configurare_anexa_id || (spatiu.liniiContor || []).length === 0);
    const isNewMode = mode === 'new';

    const topbarActions = (
        <>
            <select className="filter-input topbar-filter" value={data.imobil_id || ''} onChange={(event) => {
                const imobilId = event.target.value;
                setData('imobil_id', imobilId);
                reload({ imobil_id: imobilId, mode: 'history' });
            }}>
                <option value="">Alege imobilul</option>
                {imobile.map((item) => <option value={item.id} key={item.id}>{item.label}</option>)}
            </select>
            <select className="filter-input topbar-filter" value={data.luna} onChange={(event) => {
                setData('luna', event.target.value);
                reload({ luna: event.target.value, mode: 'history' });
            }} disabled={!data.imobil_id}>
                {!data.imobil_id ? <option value="">Alege imobilul</option> : null}
                {data.imobil_id && luniSelectabile.length === 0 ? <option value="">Nu există luni</option> : null}
                {luniSelectabile.map((item) => <option value={item.luna} key={item.luna}>{item.label}</option>)}
            </select>
            <button className={isNewMode ? 'secondary-button' : 'primary-button'} type="button" onClick={() => reload({ mode: isNewMode ? 'history' : 'new' })} disabled={!data.imobil_id}>
                {isNewMode ? 'Vezi istoric' : 'Citire lună nouă'}
            </button>
        </>
    );

    return (
        <AppLayout title="Citiri contoare" subtitle={isNewMode ? 'Luna aleasă nu are citiri: index vechi este preluat automat din ultima citire, completezi doar index nou.' : 'Istoric citiri: lunile vechi sunt blocate pentru editare.'} showGlobalSearch={false} topbarActions={topbarActions}>
            <form className="form-card" onSubmit={submit}>
                {!imobil ? (
                    <div className="readonly-info-card">
                        <h2>Alege imobilul</h2>
                        <p>Selectează un imobil pentru a încărca serviciile de tip contor din anexa configurată.</p>
                    </div>
                ) : spatii.length === 0 ? (
                    <div className="readonly-info-card">
                        <h2>Nu există spații</h2>
                        <p>Imobilul {imobil.nume} nu are spații introduse.</p>
                    </div>
                ) : randuriCitiri.length === 0 ? (
                    <div className="readonly-info-card">
                        <h2>Nu există contoare de citit</h2>
                        <p>Verifică dacă spațiile au anexă selectată și dacă anexa are linii cu tip calcul Contor.</p>
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
                                        const poateEditaIndexVechi = isNewMode && Number(String(indexVechiAfisat).replace(',', '.')) === 0;

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
                                                        readOnly={!poateEditaIndexVechi}
                                                        tabIndex={poateEditaIndexVechi ? undefined : -1}
                                                        aria-readonly={!poateEditaIndexVechi ? 'true' : undefined}
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

                        {spatiiFaraCitiri.length ? (
                            <div className="readonly-info-card compact-info-card">
                                Spații fără contoare afișate: {spatiiFaraCitiri.map((spatiu) => spatiu.identificator).join(', ')}. Verifică anexa selectată pe spațiu și liniile cu tip calcul Contor.
                            </div>
                        ) : null}
                    </div>
                )}

                {errors.imobil_id ? <small>{errors.imobil_id}</small> : null}
                {errors.data_citire ? <small>{errors.data_citire}</small> : null}

                {isNewMode ? (
                    <div className="form-footer-actions">
                        <span />
                        <div className="form-actions">
                            <button className="primary-button" type="submit" disabled={processing || !imobil || data.citiri.length === 0}>
                                {processing ? 'Se salvează...' : 'Salvează citirea lunii'}
                            </button>
                        </div>
                    </div>
                ) : null}
            </form>
        </AppLayout>
    );
}
