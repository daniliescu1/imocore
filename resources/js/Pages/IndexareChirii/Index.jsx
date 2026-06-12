import React, { useEffect, useState } from 'react';
import { Link, router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';

const STATUS_LABELS = {
    liber: 'Liber',
    rezervat: 'Rezervat',
    inchiriat: 'Închiriat',
    comun: 'Spațiu comun',
    administrativ: 'Administrativ',
};

function statusLabel(status) {
    return STATUS_LABELS[status] || status;
}

function IndexareRow({ spatiu, savingId, onSave }) {
    const [value, setValue] = useState(spatiu.indexare_2026 ?? '');
    const isSaving = savingId === spatiu.id;

    useEffect(() => {
        setValue(spatiu.indexare_2026 ?? '');
    }, [spatiu.indexare_2026]);

    function submitValue() {
        const normalized = String(value).trim();

        if (normalized === String(spatiu.indexare_2026 ?? '').trim()) {
            return;
        }

        onSave(spatiu.id, normalized === '' ? '' : normalized);
    }

    function handleKeyDown(event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            event.currentTarget.blur();
        }
    }

    return (
        <tr>
            <td>{spatiu.imobil}</td>
            <td className="spatiu-identificator-cell" title={spatiu.identificator}>
                <Link className="table-name-link" href={`/spatii/${spatiu.id}/editare`} title={spatiu.identificator}>{spatiu.identificator}</Link>
            </td>
            <td>{spatiu.etaj}</td>
            <td>{spatiu.suprafata_contractuala_mp ? `${spatiu.suprafata_contractuala_mp} mp` : '—'}</td>
            <td>{statusLabel(spatiu.status)}</td>
            <td>{spatiu.pret_lunar ? `${spatiu.pret_lunar} ${spatiu.moneda_label || spatiu.moneda}` : '—'}</td>
            <td>
                {spatiu.chirie_lunara_curenta ? (
                    <div className="stacked-cell">
                        <strong>{spatiu.chirie_lunara_curenta} {spatiu.moneda_label || spatiu.moneda}</strong>
                        {spatiu.sursa_chirie_curenta ? <small>{spatiu.sursa_chirie_curenta}</small> : null}
                    </div>
                ) : '—'}
            </td>
            <td className="indexare-chirii-input-cell">
                <input
                    className="indexare-chirii-input"
                    type="number"
                    min="0"
                    step="0.01"
                    value={value}
                    onChange={(event) => setValue(event.target.value)}
                    onBlur={submitValue}
                    onKeyDown={handleKeyDown}
                    disabled={isSaving}
                    placeholder="—"
                />
                {isSaving ? <small className="indexare-chirii-saving">Se salvează...</small> : null}
            </td>
            <td>{spatiu.chirias}</td>
        </tr>
    );
}

export default function Index({ spatii = [], localitati = [], filters = {} }) {
    const [savingId, setSavingId] = useState(null);

    function updateFilters(overrides = {}) {
        router.get('/indexare-chirii', {
            localitate: filters.localitate || '',
            search: filters.search || '',
            ...overrides,
        }, { preserveState: true, preserveScroll: true });
    }

    function saveIndexare(spatiuId, indexare2026) {
        setSavingId(spatiuId);

        router.patch(`/indexare-chirii/${spatiuId}`, {
            indexare_2026: indexare2026,
        }, {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => setSavingId(null),
        });
    }

    const topbarActions = (
        <div className="spaces-topbar-toolbar">
            <div className="spaces-topbar-filters">
                <label className="inline-topbar-field spaces-topbar-field">
                    <span>Localitate</span>
                    <select
                        className="filter-input topbar-filter"
                        value={filters.localitate || ''}
                        onChange={(event) => updateFilters({ localitate: event.target.value })}
                    >
                        <option value="">Toate</option>
                        {localitati.map((localitate) => (
                            <option value={localitate} key={localitate}>{localitate}</option>
                        ))}
                    </select>
                </label>
                <input
                    className="filter-input topbar-search"
                    type="search"
                    value={filters.search || ''}
                    placeholder="Caută imobil, spațiu, locatar..."
                    onChange={(event) => updateFilters({ search: event.target.value })}
                />
            </div>
        </div>
    );

    return (
        <AppLayout
            title={`Indexare chirii (${spatii.length})`}
            subtitle="Introdu indexarea 2026 pentru toate spațiile"
            showGlobalSearch={false}
            topbarActions={topbarActions}
        >
            <section className="table-card module-table-card">
                <div className="responsive-table">
                    <table className="indexare-chirii-table">
                        <colgroup>
                            <col />
                            <col className="indexare-identificator-col" />
                            <col />
                            <col />
                            <col />
                            <col />
                            <col />
                            <col />
                            <col />
                        </colgroup>
                        <thead>
                            <tr>
                                <th>Imobil</th>
                                <th className="spatiu-identificator-header">Identificat</th>
                                <th>Etaj</th>
                                <th>Suprafață</th>
                                <th>Status</th>
                                <th>Chirie</th>
                                <th>
                                    <span className="stacked-heading">
                                        <span>Chirie curentă</span>
                                    </span>
                                </th>
                                <th>Indexare 2026</th>
                                <th>Locatar</th>
                            </tr>
                        </thead>
                        <tbody>
                            {spatii.map((spatiu) => (
                                <IndexareRow
                                    key={spatiu.id}
                                    spatiu={spatiu}
                                    savingId={savingId}
                                    onSave={saveIndexare}
                                />
                            ))}
                            {spatii.length === 0 ? (
                                <tr>
                                    <td colSpan="9">Nu există spații pentru filtrul selectat.</td>
                                </tr>
                            ) : null}
                        </tbody>
                    </table>
                </div>
            </section>
        </AppLayout>
    );
}
