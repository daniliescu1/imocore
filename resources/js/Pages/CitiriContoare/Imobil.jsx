import React, { useEffect, useMemo, useRef } from 'react';
import { Link, router, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';

function isPausalTip(tipCalcul) {
    return String(tipCalcul || '').trim().toLowerCase() === 'pausal';
}

function tipCitireLabel(tipCalcul) {
    return isPausalTip(tipCalcul) ? 'Pausal' : 'Contor';
}

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
    return spatii.flatMap((spatiu) => (spatiu.liniiContor || []).map((linie) => {
        const base = {
            spatiu_id: spatiu.id,
            configurare_anexa_linie_id: linie.configurare_anexa_linie_id,
            tip_calcul: linie.tip_calcul,
        };

        if (isPausalTip(linie.tip_calcul)) {
            return {
                ...base,
                consum: formatDecimalForInput(linie.consum),
            };
        }

        return {
            ...base,
            index_vechi: formatDecimalForInput(linie.index_vechi),
            index_nou: formatDecimalForInput(linie.index_nou),
        };
    }));
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

function isLineEditable(linie, isNewMode) {
    if (isNewMode) {
        return true;
    }

    return Boolean(linie.editabila);
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

    function updateCitire(spatiuId, linieId, field, value, tipCalcul) {
        const index = citireIndexFor(spatiuId, linieId);

        if (index === -1) {
            const entry = {
                spatiu_id: spatiuId,
                configurare_anexa_linie_id: linieId,
                tip_calcul: tipCalcul,
                [field]: value,
            };

            if (isPausalTip(tipCalcul)) {
                entry.consum = value;
            } else {
                entry.index_vechi = field === 'index_vechi' ? value : '';
                entry.index_nou = field === 'index_nou' ? value : '';
            }

            setData('citiri', [...data.citiri, entry]);

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
    const hasEditableLines = randuriCitiri.some(({ linie }) => isLineEditable(linie, isNewMode));
    const hasPendingHistoryLines = !isNewMode && randuriCitiri.some(({ linie }) => linie.editabila);

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
            subtitle={isNewMode
                ? 'Contor: index vechi preluat automat, completezi index nou. Pausal: introduci cantitatea direct.'
                : hasPendingHistoryLines
                    ? 'Istoric citiri: liniile fără citire salvată pot fi completate. Cele deja salvate sunt doar vizualizare.'
                    : 'Istoric citiri: lunile vechi sunt blocate pentru editare.'}
            showGlobalSearch={false}
            topbarActions={topbarActions}
        >
            <section className="readonly-info-card compact-info-card">
                <p>
                    Contoare și servicii pausal de citit derivate din anexele alocate spațiilor din <strong>{imobil.nume} ({imobil.localitate})</strong>.
                    {' '}
                    <Link className="secondary-button button-link annex-clear-building-button" href="/citiri-contoare">Înapoi la imobile</Link>
                </p>
            </section>

            <form className="form-card" onSubmit={submit}>
                {randuriCitiri.length === 0 ? (
                    <div className="readonly-info-card">
                        <h2>Nu există contoare de citit</h2>
                        <p>Niciun spațiu din acest imobil nu are anexă cu linii de tip Contor sau Pausal. Alocă anexa pe spații și adaugă servicii cu tip calcul Contor sau Pausal.</p>
                    </div>
                ) : (
                    <div className="meter-reading-groups">
                        <div className="responsive-table">
                            <table className="citiri-contoare-table">
                                <thead>
                                    <tr>
                                        <th>Spațiu</th>
                                        <th>Nume chiriaș</th>
                                        <th>Serviciu</th>
                                        <th>Tip</th>
                                        <th>UM</th>
                                        <th>Index vechi</th>
                                        <th>Index nou</th>
                                        <th>Cantitate</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {randuriCitiri.map(({ spatiu, linie }) => {
                                        const pausal = isPausalTip(linie.tip_calcul);
                                        const editable = isLineEditable(linie, isNewMode);
                                        const citire = data.citiri[citireIndexFor(spatiu.id, linie.configurare_anexa_linie_id)] || (
                                            pausal
                                                ? { consum: formatDecimalForInput(linie.consum) }
                                                : {
                                                    index_vechi: formatDecimalForInput(linie.index_vechi),
                                                    index_nou: formatDecimalForInput(linie.index_nou),
                                                }
                                        );
                                        const indexVechiAfisat = citire.index_vechi ?? formatDecimalForInput(linie.index_vechi);

                                        return (
                                            <tr key={`${spatiu.id}-${linie.configurare_anexa_linie_id}`}>
                                                <td><strong>{spatiu.identificator}</strong></td>
                                                <td title={spatiu.chirias || undefined}>{spatiu.chirias || '—'}</td>
                                                <td title={linie.denumire}>{linie.denumire}</td>
                                                <td>{tipCitireLabel(linie.tip_calcul)}</td>
                                                <td>{linie.um || '—'}</td>
                                                {pausal ? (
                                                    <>
                                                        <td>—</td>
                                                        <td>—</td>
                                                        <td>
                                                            <input
                                                                className="table-input"
                                                                type="text"
                                                                inputMode="decimal"
                                                                value={citire.consum ?? ''}
                                                                readOnly={!editable}
                                                                tabIndex={!editable ? -1 : undefined}
                                                                aria-readonly={!editable ? 'true' : undefined}
                                                                aria-label="Cantitate pausal"
                                                                onChange={(event) => updateCitire(
                                                                    spatiu.id,
                                                                    linie.configurare_anexa_linie_id,
                                                                    'consum',
                                                                    event.target.value,
                                                                    linie.tip_calcul,
                                                                )}
                                                            />
                                                        </td>
                                                    </>
                                                ) : (
                                                    <>
                                                        <td>
                                                            <input
                                                                className="table-input"
                                                                type="text"
                                                                inputMode="decimal"
                                                                value={indexVechiAfisat}
                                                                readOnly={!editable}
                                                                tabIndex={!editable ? -1 : undefined}
                                                                aria-readonly={!editable ? 'true' : undefined}
                                                                onChange={(event) => updateCitire(
                                                                    spatiu.id,
                                                                    linie.configurare_anexa_linie_id,
                                                                    'index_vechi',
                                                                    event.target.value,
                                                                    linie.tip_calcul,
                                                                )}
                                                            />
                                                        </td>
                                                        <td>
                                                            <input
                                                                className="table-input"
                                                                type="text"
                                                                inputMode="decimal"
                                                                value={citire.index_nou ?? ''}
                                                                readOnly={!editable}
                                                                tabIndex={!editable ? -1 : undefined}
                                                                aria-readonly={!editable ? 'true' : undefined}
                                                                onChange={(event) => updateCitire(
                                                                    spatiu.id,
                                                                    linie.configurare_anexa_linie_id,
                                                                    'index_nou',
                                                                    event.target.value,
                                                                    linie.tip_calcul,
                                                                )}
                                                            />
                                                        </td>
                                                        <td>{calculatedConsum(citire.index_vechi ?? indexVechiAfisat, citire.index_nou) || '—'}</td>
                                                    </>
                                                )}
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

                {hasEditableLines && randuriCitiri.length > 0 ? (
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
