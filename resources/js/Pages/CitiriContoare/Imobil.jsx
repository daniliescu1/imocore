import React, { useEffect, useMemo, useRef } from 'react';
import { router, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';

function isPausalTip(tipCalcul) {
    return String(tipCalcul || '').trim().toLowerCase() === 'pausal';
}

function pausalCitireManualCuIndex(denumire) {
    const normalized = String(denumire || '').toLowerCase();

    return normalized.includes('consum apa') || normalized.includes('canalizare');
}

function folosesteIndexLaCitire(tipCalcul, denumire, linieSauCitire = null) {
    if (isPausalTip(tipCalcul)) {
        if (pausalCitireManualCuIndex(denumire)) {
            return true;
        }

        const indexVechi = Number(String(linieSauCitire?.index_vechi ?? '').replace(',', '.'));
        const indexNou = Number(String(linieSauCitire?.index_nou ?? '').replace(',', '.'));

        return (Number.isFinite(indexNou) && indexNou > 0)
            || (Number.isFinite(indexVechi) && indexVechi > 0);
    }

    return true;
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

        if (isPausalTip(linie.tip_calcul) && !folosesteIndexLaCitire(linie.tip_calcul, linie.denumire, linie)) {
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
        if ((isPausalTip(linie.tip_calcul) || linie.is_pausal) && !folosesteIndexLaCitire(linie.tip_calcul, linie.denumire, linie)) {
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

function spatiuHaystack(spatiu) {
    return [
        spatiu.identificator,
        spatiu.chirias,
        spatiu.locator,
        spatiu.anexa,
    ]
        .filter(Boolean)
        .join(' ')
        .toLowerCase();
}

function spatiuMatchesSearch(spatiu, search) {
    const query = String(search || '').trim().toLowerCase();

    if (!query) {
        return true;
    }

    return spatiuHaystack(spatiu).includes(query);
}

function getMatchingSpatiuIds(spatii, search) {
    const query = String(search || '').trim().toLowerCase();

    if (!query) {
        return null;
    }

    return new Set(
        spatii
            .filter((spatiu) => spatiuMatchesSearch(spatiu, search))
            .map((spatiu) => Number(spatiu.id)),
    );
}

function matchesCitiriSearch(spatiu, linie, search) {
    const query = String(search || '').trim().toLowerCase();

    if (!query) {
        return true;
    }

    if (spatiuMatchesSearch(spatiu, search)) {
        return true;
    }

    return String(linie.denumire || '').toLowerCase().includes(query);
}

function matchesContorConfigurabilSearch(linie, search, matchingSpatiuIds) {
    const query = String(search || '').trim().toLowerCase();

    if (!query) {
        return true;
    }

    const linieHaystack = [linie.anexa, linie.denumire]
        .filter(Boolean)
        .join(' ')
        .toLowerCase();

    if (linieHaystack.includes(query)) {
        return true;
    }

    if (matchingSpatiuIds && matchingSpatiuIds.size > 0) {
        return (linie.alocari_spatiu_ids || []).some((spatiuId) => matchingSpatiuIds.has(Number(spatiuId)));
    }

    return false;
}

export default function Imobil({
    embedded = false,
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
    searchSpatiu = '',
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
    const [search, setSearch] = React.useState(searchSpatiu || '');

    useEffect(() => {
        setSearch(searchSpatiu || '');
    }, [searchSpatiu]);

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
            search: searchSpatiu || undefined,
        }, { preserveScroll: true });
    }

    function updateCitire(spatiuId, linieId, field, value, tipCalcul, denumire = '') {
        const index = citireIndexFor(spatiuId, linieId);

        if (index === -1) {
            const entry = {
                spatiu_id: spatiuId,
                configurare_anexa_linie_id: linieId,
                tip_calcul: tipCalcul,
                [field]: value,
            };

            if (isPausalTip(tipCalcul) && !folosesteIndexLaCitire(tipCalcul, denumire) && field === 'consum') {
                entry.consum = value;
            } else if (field === 'consum') {
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

    const randuriCitiri = useMemo(() => spatii.flatMap((spatiu) => (spatiu.liniiContor || []).map((linie) => ({
        spatiu,
        linie,
    }))), [spatii]);
    const matchingSpatiuIds = useMemo(
        () => getMatchingSpatiuIds(spatii, search),
        [spatii, search],
    );
    const filteredRanduriCitiri = useMemo(
        () => randuriCitiri.filter(({ spatiu, linie }) => matchesCitiriSearch(spatiu, linie, search)),
        [randuriCitiri, search],
    );
    const randuriConfigurabile = useMemo(
        () => (contoareConfigurabile || []).map((linie) => ({ linie })),
        [contoareConfigurabile],
    );
    const filteredRanduriConfigurabile = useMemo(
        () => randuriConfigurabile.filter(({ linie }) => matchesContorConfigurabilSearch(linie, search, matchingSpatiuIds)),
        [randuriConfigurabile, search, matchingSpatiuIds],
    );
    const totalRanduri = randuriCitiri.length + randuriConfigurabile.length;
    const isNewMode = mode === 'new';
    const hasEditableLines = !lunaInchisa && (
        randuriCitiri.some(({ linie }) => isLineEditable(linie))
        || randuriConfigurabile.some(({ linie }) => isLineEditable(linie))
    );
    const canCloseMonth = !lunaInchisa;

    const topbarActions = (
        <>
            {!embedded && (randuriCitiri.length > 0 || randuriConfigurabile.length > 0) ? (
                <input
                    className="filter-input topbar-search"
                    type="search"
                    value={search}
                    placeholder="Caută spațiu..."
                    onChange={(event) => setSearch(event.target.value)}
                />
            ) : null}
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

    const panelControls = (
        <div className="citiri-imobil-panel-controls">
            {topbarActions}
        </div>
    );

    const formContent = (
        <form className={`form-card${embedded ? ' citiri-imobil-embedded-form' : ''}`} onSubmit={submit}>
                {totalRanduri === 0 ? (
                    <div className="readonly-info-card">
                        <h2>Nu există contoare de citit</h2>
                        <p>Niciun spațiu din acest imobil nu are anexă cu linii de tip Contor, iar nu există contoare configurabile sau pausale de configurat. Alocă anexa pe spații și adaugă servicii cu tip calcul Contor, Pausal sau Contor configurabil.</p>
                    </div>
                ) : (
                    <div className="meter-reading-groups">
                        {filteredRanduriConfigurabile.length > 0 ? (
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
                                            {filteredRanduriConfigurabile.map(({ linie }) => {
                                                const editable = isLineEditable(linie);
                                                const pausal = isPausalTip(linie.tip_calcul) || linie.is_pausal;
                                                const cuIndex = folosesteIndexLaCitire(linie.tip_calcul, linie.denumire, linie);
                                                const citire = data.citiri[citireIndexFor(null, linie.configurare_anexa_linie_id)] || (
                                                    pausal && !cuIndex
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
                                                        {pausal && !cuIndex ? (
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
                                                                            linie.denumire,
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
                                                                            linie.denumire,
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
                                                                            linie.denumire,
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

                        {randuriCitiri.length > 0 && filteredRanduriCitiri.length > 0 ? (
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
                                    {filteredRanduriCitiri.map(({ spatiu, linie }) => {
                                        const pausal = isPausalTip(linie.tip_calcul);
                                        const cuIndex = folosesteIndexLaCitire(linie.tip_calcul, linie.denumire, linie);
                                        const editable = isLineEditable(linie);
                                        const citire = data.citiri[citireIndexFor(spatiu.id, linie.configurare_anexa_linie_id)] || (
                                            pausal && !cuIndex
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
                                                {pausal && !cuIndex ? (
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
                                                                    linie.denumire,
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
                                                                    linie.denumire,
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
                                                                    linie.denumire,
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

                        {search.trim() && filteredRanduriConfigurabile.length === 0 && filteredRanduriCitiri.length === 0 ? (
                            <div className="readonly-info-card">
                                <h2>Niciun rezultat</h2>
                                <p>Nu am găsit spații, contoare configurabile sau servicii care să corespundă căutării „{search.trim()}”. Încearcă după identificator spațiu, chiriaș, locator, anexă sau serviciu.</p>
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
    );

    if (embedded) {
        const imobilHref = `/citiri-contoare/imobil/${imobil.id}?mode=new${searchSpatiu ? `&search=${encodeURIComponent(searchSpatiu)}` : ''}`;

        return (
            <section className="table-card module-table-card citiri-imobil-embedded">
                <div className="citiri-imobil-embedded-header">
                    <div>
                        <h2>{imobil.nume} ({imobil.localitate})</h2>
                        <p>
                            {lunaInchisa
                                ? `Citirile pentru ${formatLunaLabel(luna)} sunt închise.`
                                : isNewMode
                                    ? 'Completezi citirile filtrate pentru acest imobil.'
                                    : 'Poți modifica citirile salvate până închizi luna.'}
                        </p>
                    </div>
                    <div className="citiri-imobil-embedded-actions">
                        {panelControls}
                        <a className="secondary-button button-link" href={imobilHref}>Deschide pagina imobil</a>
                    </div>
                </div>
                {formContent}
            </section>
        );
    }

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
            {formContent}
        </AppLayout>
    );
}
