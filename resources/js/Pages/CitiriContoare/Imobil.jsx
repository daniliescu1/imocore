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

function isContorFixTip(tipCalcul) {
    const normalized = String(tipCalcul || '').toLowerCase().replace(/[\s_*-]/g, '');

    return normalized === 'contorfix';
}

function tipCitireLabel(tipCalcul) {
    if (isPausalTip(tipCalcul)) {
        return 'Pausal';
    }

    if (isContorFixTip(tipCalcul)) {
        return 'Contor fix';
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

function flattenCitiri(spatii, contoareConfigurabile = [], contoareFix = []) {
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

    const citiriFix = (contoareFix || []).map((linie) => ({
        spatiu_id: linie.spatiu_id,
        configurare_anexa_linie_id: linie.configurare_anexa_linie_id,
        tip_calcul: linie.tip_calcul,
        consum: formatDecimalForInput(linie.consum),
    }));

    return [...citiriConfigurabile, ...citiriFix, ...citiriSpatii];
}

function buildFormState({ imobilId, luna, dataCitire, spatii, contoareConfigurabile, contoareFix }) {
    const initialDataCitire = dataCitire || new Date().toISOString().slice(0, 16);

    return {
        imobil_id: imobilId || '',
        luna: luna || initialDataCitire.slice(0, 7),
        data_citire: initialDataCitire,
        citiri: flattenCitiri(spatii, contoareConfigurabile, contoareFix),
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

function normalizeStrictSearch(value) {
    return String(value || '').trim().toLowerCase().replace(/\s+/g, '');
}

function spatiuMatchesSearch(spatiu, search) {
    const query = normalizeStrictSearch(search);

    if (!query) {
        return true;
    }

    return [spatiu.identificator, spatiu.chirias, spatiu.locator]
        .filter(Boolean)
        .some((value) => normalizeStrictSearch(value).includes(query));
}

function getMatchingSpatiuIds(spatii, search) {
    const query = normalizeStrictSearch(search);

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
    return spatiuMatchesSearch(spatiu, search);
}

function matchingSpatiiForSearch(search, searchMatchingSpatii, spatiiIndex) {
    const query = String(search || '').trim();

    if (!query) {
        return [];
    }

    if (searchMatchingSpatii?.length) {
        return searchMatchingSpatii;
    }

    return (spatiiIndex || []).filter((spatiu) => spatiuMatchesSearch(spatiu, search));
}

function matchesContorConfigurabilSearch(linie, search, matchingSpatii) {
    const query = normalizeStrictSearch(search);

    if (!query) {
        return true;
    }

    if (matchingSpatii.length > 0) {
        return matchingSpatii.some((spatiu) => (
            spatiu.configurare_anexa_id != null
            && Number(spatiu.configurare_anexa_id) === Number(linie.configurare_anexa_id)
            && (linie.alocari_spatiu_ids || []).includes(Number(spatiu.id))
        ));
    }

    const linieHaystack = normalizeStrictSearch([linie.anexa, linie.denumire].filter(Boolean).join(' '));

    return linieHaystack.includes(query);
}

function matchesContorFixSearch(linie, search) {
    const query = normalizeStrictSearch(search);

    if (!query) {
        return true;
    }

    return spatiuMatchesSearch({
        identificator: linie.spatiu_identificator,
        chirias: linie.chirias,
    }, search)
        || normalizeStrictSearch(linie.denumire || '').includes(query)
        || normalizeStrictSearch(linie.anexa || '').includes(query);
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
    contoareFix = [],
    searchSpatiu = '',
    searchMatchingSpatii = [],
    spatiiIndex = [],
}) {
    const serverStateKey = useMemo(() => JSON.stringify({
        imobilId: imobil?.id,
        luna,
        dataCitire,
        mode,
        spatii: flattenCitiri(spatii, contoareConfigurabile, contoareFix),
    }), [imobil?.id, luna, dataCitire, mode, spatii, contoareConfigurabile, contoareFix]);

    const lastServerStateKey = useRef(serverStateKey);
    const { data, setData, post, processing, errors } = useForm(buildFormState({
        imobilId: imobil?.id,
        luna,
        dataCitire,
        spatii,
        contoareConfigurabile,
        contoareFix,
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
        setData(buildFormState({ imobilId: imobil?.id, luna, dataCitire, spatii, contoareConfigurabile, contoareFix }));
    }, [serverStateKey, imobil?.id, luna, dataCitire, spatii, contoareConfigurabile, contoareFix, setData]);

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

    function updateCitire(spatiuId, linieId, field, value, tipCalcul) {
        const index = citireIndexFor(spatiuId, linieId);

        if (index === -1) {
            const entry = {
                spatiu_id: spatiuId,
                configurare_anexa_linie_id: linieId,
                tip_calcul: tipCalcul,
                [field]: value,
            };

            if (isPausalTip(tipCalcul) || isContorFixTip(tipCalcul)) {
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

    const randuriCitiri = useMemo(() => spatii.flatMap((spatiu) => (spatiu.liniiContor || []).map((linie) => ({
        spatiu,
        linie,
    }))), [spatii]);
    const matchingSpatii = useMemo(
        () => matchingSpatiiForSearch(search, searchMatchingSpatii, spatiiIndex),
        [search, searchMatchingSpatii, spatiiIndex],
    );
    const searchedSpatiuFaraAnexa = useMemo(() => {
        const query = String(search || '').trim();

        if (!query || matchingSpatii.length === 0) {
            return null;
        }

        return matchingSpatii.find((spatiu) => spatiu.configurare_anexa_id == null) || null;
    }, [matchingSpatii, search]);
    const filteredRanduriCitiri = useMemo(
        () => randuriCitiri.filter(({ spatiu, linie }) => matchesCitiriSearch(spatiu, linie, search)),
        [randuriCitiri, search],
    );
    const randuriConfigurabile = useMemo(
        () => (contoareConfigurabile || []).map((linie) => ({ linie })),
        [contoareConfigurabile],
    );
    const filteredRanduriConfigurabile = useMemo(
        () => randuriConfigurabile.filter(({ linie }) => matchesContorConfigurabilSearch(linie, search, matchingSpatii)),
        [randuriConfigurabile, search, matchingSpatii],
    );
    const randuriFix = useMemo(
        () => (contoareFix || []).map((linie) => ({ linie })),
        [contoareFix],
    );
    const filteredRanduriFix = useMemo(
        () => randuriFix.filter(({ linie }) => matchesContorFixSearch(linie, search)),
        [randuriFix, search],
    );
    const totalRanduri = randuriCitiri.length + randuriConfigurabile.length + randuriFix.length;
    const isNewMode = mode === 'new';
    const hasEditableLines = !lunaInchisa && (
        randuriCitiri.some(({ linie }) => isLineEditable(linie))
        || randuriConfigurabile.some(({ linie }) => isLineEditable(linie))
        || randuriFix.some(({ linie }) => isLineEditable(linie))
    );
    const canCloseMonth = !lunaInchisa;

    const topbarActions = (
        <>
            {!embedded && (randuriCitiri.length > 0 || randuriConfigurabile.length > 0 || randuriFix.length > 0) ? (
                <input
                    className="filter-input topbar-search"
                    type="search"
                    value={search}
                    placeholder="Caută spațiu, chiriaș..."
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
                        <p>Niciun spațiu din acest imobil nu are anexă cu linii de tip Contor, Contor fix, Pausal sau Contor configurabil. Alocă anexa pe spații și adaugă serviciile corespunzătoare.</p>
                    </div>
                ) : (
                    <div className="meter-reading-groups">
                        {filteredRanduriConfigurabile.length > 0 ? (
                            <div className="contor-config-citiri-block">
                                <h2 className="contor-config-citiri-title">Contoare configurabile și pausale (imobil)</h2>
                                <p className="contor-config-citiri-help">Citire unică la nivel de imobil; apa și canalizarea pausal se împart la numărul total de persoane alocate, celelalte contoare configurabile la numărul de spații închiriate.</p>
                                <div className="responsive-table contor-config-citiri-scroll">
                                    <table className="citiri-contoare-table contor-config-citiri-table">
                                        <colgroup>
                                            <col className="contor-config-col-anexa" />
                                            <col className="contor-config-col-numeric" />
                                            <col className="contor-config-col-numeric" />
                                            <col className="contor-config-col-serviciu" />
                                            <col />
                                            <col />
                                            <col />
                                            <col />
                                            <col />
                                        </colgroup>
                                        <thead>
                                            <tr>
                                                <th>Anexă</th>
                                                <th className="citiri-numeric-header" aria-label="Număr spații">spații</th>
                                                <th className="citiri-numeric-header" aria-label="Număr persoane">
                                                    <span className="citiri-numeric-header-text">nr<br />pers</span>
                                                </th>
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
                                                        <td title={linie.anexa}>
                                                            {linie.anexa || '—'}
                                                        </td>
                                                        <td className="citiri-numeric-cell">{linie.alocari_count ?? '—'}</td>
                                                        <td className="citiri-numeric-cell">{linie.alocari_persoane_count ?? '—'}</td>
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

                        {filteredRanduriFix.length > 0 ? (
                            <div className="contor-fix-citiri-block">
                                <h2 className="contor-fix-citiri-title">Contoare fix (spațiu)</h2>
                                <p className="contor-fix-citiri-help">Introdu manual valoarea facturată pentru fiecare spațiu; apare în anexă la coloana Facturat.</p>
                                <div className="responsive-table contor-fix-citiri-scroll">
                                    <table className="citiri-contoare-table contor-fix-citiri-table">
                                        <thead>
                                            <tr>
                                                <th>Spațiu</th>
                                                <th>Chiriaș</th>
                                                <th>Serviciu</th>
                                                <th>UM</th>
                                                <th>Facturat</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {filteredRanduriFix.map(({ linie }) => {
                                                const editable = isLineEditable(linie);
                                                const citire = data.citiri[citireIndexFor(linie.spatiu_id, linie.configurare_anexa_linie_id)] || {
                                                    consum: formatDecimalForInput(linie.consum),
                                                };

                                                return (
                                                    <tr key={`fix-${linie.spatiu_id}-${linie.configurare_anexa_linie_id}`}>
                                                        <td><strong>{linie.spatiu_identificator}</strong></td>
                                                        <td title={linie.chirias || undefined}>{linie.chirias || '—'}</td>
                                                        <td title={linie.denumire}>{linie.denumire}</td>
                                                        <td>{linie.um || '—'}</td>
                                                        <td>
                                                            <input
                                                                className="table-input"
                                                                type="text"
                                                                inputMode="decimal"
                                                                value={citire.consum ?? ''}
                                                                readOnly={!editable}
                                                                tabIndex={!editable ? -1 : undefined}
                                                                aria-readonly={!editable ? 'true' : undefined}
                                                                aria-label="Facturat contor fix"
                                                                onChange={(event) => updateCitire(
                                                                    linie.spatiu_id,
                                                                    linie.configurare_anexa_linie_id,
                                                                    'consum',
                                                                    event.target.value,
                                                                    linie.tip_calcul,
                                                                )}
                                                            />
                                                        </td>
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

                        {searchedSpatiuFaraAnexa ? (
                            <div className="spatiu-context-banner spatiu-context-banner-compact">
                                {`Spațiul ${searchedSpatiuFaraAnexa.identificator} nu are anexă alocată. Alocă anexa din editare spațiu ca să apară contoarele imobil aferente.`}
                            </div>
                        ) : null}

                        {search.trim() && filteredRanduriConfigurabile.length === 0 && filteredRanduriFix.length === 0 && filteredRanduriCitiri.length === 0 ? (
                            <div className="readonly-info-card">
                                <h2>Niciun rezultat</h2>
                                <p>Nu am găsit spații sau contoare configurabile care să corespundă căutării „{search.trim()}”. Încearcă după identificator spațiu sau chiriaș.</p>
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
