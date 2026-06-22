import React, { useEffect, useMemo, useRef } from 'react';
import { router, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';

function isPausalTip(tipCalcul) {
    return String(tipCalcul || '').trim().toLowerCase() === 'pausal';
}

function isContorConfigurabilTip(tipCalcul) {
    const normalized = String(tipCalcul || '').toLowerCase().replace(/[\s_*-]/g, '');

    return normalized.includes('contor') && normalized.includes('configurabil');
}

function tipCitireLabel(tipCalcul) {
    if (isPausalTip(tipCalcul)) {
        return 'Pausal';
    }

    if (isContorConfigurabilTip(tipCalcul)) {
        return 'Configurabil';
    }

    return 'Contor';
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

function flattenCitiri(spatii, contoareConfigurabile = []) {
    const citiriSpatii = spatii.flatMap((spatiu) => (spatiu.liniiContor || []).map((linie) => {
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

    const citiriConfigurabile = (contoareConfigurabile || []).map((linie) => {
        if (isPausalTip(linie.tip_calcul) || linie.is_pausal) {
            return {
                spatiu_id: null,
                configurare_anexa_linie_id: linie.configurare_anexa_linie_id,
                tip_calcul: linie.tip_calcul,
                consum: formatDecimalForInput(linie.consum),
            };
        }

        return {
            spatiu_id: null,
            configurare_anexa_linie_id: linie.configurare_anexa_linie_id,
            tip_calcul: linie.tip_calcul,
            index_vechi: formatDecimalForInput(linie.index_vechi),
            index_nou: formatDecimalForInput(linie.index_nou),
        };
    });

    return [...citiriConfigurabile, ...citiriSpatii];
}

function buildFormState({ imobilId, luna, dataCitire, spatii, contoareConfigurabile }) {
    const initialDataCitire = dataCitire || new Date().toISOString().slice(0, 16);

    return {
        imobil_id: imobilId || '',
        luna: luna || initialDataCitire.slice(0, 7),
        data_citire: initialDataCitire,
        citiri: flattenCitiri(spatii, contoareConfigurabile),
    };
}

function isLineEditable(linie) {
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
    lunaInchisa = false,
    luniSelectabile = [],
    luniCitite = [],
    spatii = [],
    contoareConfigurabile = [],
}) {
    const serverStateKey = useMemo(() => JSON.stringify({
        imobilId: imobil?.id,
        luna,
        dataCitire,
        mode,
        spatii: flattenCitiri(spatii, contoareConfigurabile),
    }), [imobil?.id, luna, dataCitire, mode, spatii, contoareConfigurabile]);

    const lastServerStateKey = useRef(serverStateKey);
    const { data, setData, post, processing, errors } = useForm(buildFormState({
        imobilId: imobil?.id,
        luna,
        dataCitire,
        spatii,
        contoareConfigurabile,
    }));
    const [inchidereProcessing, setInchidereProcessing] = React.useState(false);

    useEffect(() => {
        if (lastServerStateKey.current === serverStateKey) {
            return;
        }

        lastServerStateKey.current = serverStateKey;
        setData(buildFormState({ imobilId: imobil?.id, luna, dataCitire, spatii, contoareConfigurabile }));
    }, [serverStateKey, imobil?.id, luna, dataCitire, spatii, contoareConfigurabile, setData]);

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
            } else if (spatiuId === null || spatiuId === undefined || spatiuId === '') {
                entry.index_vechi = field === 'index_vechi' ? value : '';
                entry.index_nou = field === 'index_nou' ? value : '';
                if (field === 'consum') {
                    entry.consum = value;
                }
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
            (citire.spatiu_id === null || citire.spatiu_id === undefined || citire.spatiu_id === ''
                ? spatiuId === null || spatiuId === undefined || spatiuId === ''
                : Number(citire.spatiu_id) === Number(spatiuId))
            && Number(citire.configurare_anexa_linie_id) === Number(linieId)
        ));
    }

    function submit(event) {
        event.preventDefault();
        post('/citiri-contoare', { preserveScroll: true });
    }

    function inchideCitirile(event) {
        event.preventDefault();

        if (!window.confirm(`Salvezi și închizi citirile pentru ${formatLunaLabel(data.luna)}? După închidere nu mai pot fi modificate.`)) {
            return;
        }

        setInchidereProcessing(true);
        router.post('/citiri-contoare/inchide', {
            imobil_id: imobil.id,
            luna: data.luna,
            data_citire: data.data_citire,
            citiri: data.citiri,
        }, {
            preserveScroll: true,
            onFinish: () => setInchidereProcessing(false),
        });
    }

    const randuriCitiri = spatii.flatMap((spatiu) => (spatiu.liniiContor || []).map((linie) => ({
        spatiu,
        linie,
    })));
    const randuriConfigurabile = (contoareConfigurabile || []).map((linie) => ({ linie }));
    const totalRanduri = randuriCitiri.length + randuriConfigurabile.length;
    const isNewMode = mode === 'new';
    const hasEditableLines = !lunaInchisa && (
        randuriCitiri.some(({ linie }) => isLineEditable(linie))
        || randuriConfigurabile.some(({ linie }) => isLineEditable(linie))
    );
    const canCloseMonth = !lunaInchisa;

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
            subtitle={lunaInchisa
                ? `Citirile pentru ${formatLunaLabel(luna)} sunt închise și nu mai pot fi modificate.`
                : isNewMode
                    ? 'Completezi citirile, salvezi sau salvezi și închizi luna când ai terminat.'
                    : 'Poți modifica citirile salvate până apeși „Salvează și închide”.'}
            showGlobalSearch={false}
            topbarActions={topbarActions}
        >
            <form className="form-card" onSubmit={submit}>
                {totalRanduri === 0 ? (
                    <div className="readonly-info-card">
                        <h2>Nu există contoare de citit</h2>
                        <p>Niciun spațiu din acest imobil nu are anexă cu linii de tip Contor, iar nu există contoare configurabile sau pausale de configurat. Alocă anexa pe spații și adaugă servicii cu tip calcul Contor, Pausal sau Contor configurabil.</p>
                    </div>
                ) : (
                    <div className="meter-reading-groups">
                        {randuriConfigurabile.length > 0 ? (
                            <div className="contor-config-citiri-block">
                                <h2 className="contor-config-citiri-title">Contoare configurabile și pausale (imobil)</h2>
                                <p className="contor-config-citiri-help">Citire unică la nivel de imobil; cantitatea se repartizează pe spațiile alocate din Configurare contoare.</p>
                                <div className="responsive-table">
                                    <table className="citiri-contoare-table contor-config-citiri-table">
                                        <thead>
                                            <tr>
                                                <th>Anexă</th>
                                                <th>Serviciu</th>
                                                <th>Tip</th>
                                                <th>UM</th>
                                                <th>Index vechi</th>
                                                <th>Index nou</th>
                                                <th>Cantitate</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {randuriConfigurabile.map(({ linie }) => {
                                                const editable = isLineEditable(linie);
                                                const pausal = isPausalTip(linie.tip_calcul) || linie.is_pausal;
                                                const citire = data.citiri[citireIndexFor(null, linie.configurare_anexa_linie_id)] || (
                                                    pausal
                                                        ? { consum: formatDecimalForInput(linie.consum) }
                                                        : {
                                                            index_vechi: formatDecimalForInput(linie.index_vechi),
                                                            index_nou: formatDecimalForInput(linie.index_nou),
                                                        }
                                                );
                                                const indexVechiAfisat = citire.index_vechi ?? formatDecimalForInput(linie.index_vechi);

                                                return (
                                                    <tr key={`config-${linie.configurare_anexa_linie_id}`}>
                                                        <td title={linie.anexa}>{linie.anexa || '—'}</td>
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
                                                                        aria-label="Cantitate pausal imobil"
                                                                        onChange={(event) => updateCitire(
                                                                            null,
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
                                                                            null,
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
                                                                            null,
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
                        ) : null}

                        {randuriCitiri.length > 0 ? (
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
                                        const editable = isLineEditable(linie);
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
                        ) : null}
                    </div>
                )}

                {errors.imobil_id ? <small>{errors.imobil_id}</small> : null}
                {errors.data_citire ? <small>{errors.data_citire}</small> : null}

                {!lunaInchisa && totalRanduri > 0 ? (
                    <div className="form-footer-actions">
                        <label className="form-field">
                            <span>Data citire</span>
                            <input type="datetime-local" value={data.data_citire} onChange={(event) => setData('data_citire', event.target.value)} />
                        </label>
                        <div className="form-actions">
                            {hasEditableLines ? (
                                <button className="primary-button" type="submit" disabled={processing || data.citiri.length === 0}>
                                    {processing ? 'Se salvează...' : `Salvează citirea ${formatLunaLabel(data.luna)}`}
                                </button>
                            ) : null}
                            {canCloseMonth ? (
                                <button
                                    className="secondary-button"
                                    type="button"
                                    disabled={inchidereProcessing || processing}
                                    onClick={inchideCitirile}
                                >
                                    {inchidereProcessing ? 'Se salvează și închide...' : `Salvează și închide ${formatLunaLabel(data.luna)}`}
                                </button>
                            ) : null}
                        </div>
                    </div>
                ) : null}
            </form>
        </AppLayout>
    );
}
