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

function PersoaneDeclarateRow({ spatiu, savingId, onSave }) {
    const [value, setValue] = useState(spatiu.persoane_declarate ?? '');
    const isSaving = savingId === spatiu.id;

    useEffect(() => {
        setValue(spatiu.persoane_declarate ?? '');
    }, [spatiu.persoane_declarate]);

    function submitValue() {
        const normalized = String(value).trim();

        if (normalized === String(spatiu.persoane_declarate ?? '').trim()) {
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
            <td>
                <strong>{spatiu.persoane_calculate_automat ?? 0}</strong>
            </td>
            <td className="indexare-chirii-input-cell">
                <input
                    className="indexare-chirii-input"
                    type="number"
                    min="0"
                    step="1"
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
            <td>{spatiu.suprafata_contractuala_mp ? `${spatiu.suprafata_contractuala_mp} mp` : '—'}</td>
            <td>{statusLabel(spatiu.status)}</td>
        </tr>
    );
}

export default function Index({ spatii = [], localitati = [], filters = {}, rezumat = {} }) {
    const [savingId, setSavingId] = useState(null);
    const spatiiInchiriate = rezumat.spatii_inchiriate ?? 0;
    const spatiiDeclarate = rezumat.spatii_cu_persoane_declarate ?? 0;

    function updateFilters(overrides = {}) {
        router.get('/persoane-declarate', {
            localitate: filters.localitate || '',
            search: filters.search || '',
            declarate: filters.declarate || '',
            ...overrides,
        }, { preserveState: true, preserveScroll: true });
    }

    function savePersoaneDeclarate(spatiuId, persoaneDeclarate) {
        setSavingId(spatiuId);

        router.patch(`/persoane-declarate/${spatiuId}`, {
            persoane_declarate: persoaneDeclarate,
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
                <label className="inline-topbar-field spaces-topbar-field">
                    <span>Pers declarate</span>
                    <select
                        className="filter-input topbar-filter"
                        value={filters.declarate || ''}
                        onChange={(event) => updateFilters({ declarate: event.target.value })}
                    >
                        <option value="">Toate</option>
                        <option value="declarate">Declarate</option>
                        <option value="ne_declarate">Ne declarate</option>
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

    const declarateLabel = `${spatiiDeclarate} ${spatiiDeclarate === 1 ? 'spațiu cu persoane declarate' : 'spații cu persoane declarate'}`;
    const subtitle = `Actualizează persoanele declarate de chiriaș — ${declarateLabel}`;

    const topbarTitle = (
        <div className="topbar-page-title">
            <h1>Persoane declarate ({spatiiInchiriate} închiriate)</h1>
            <p>{subtitle}</p>
        </div>
    );

    return (
        <AppLayout
            title={`Persoane declarate (${spatiiInchiriate} închiriate)`}
            subtitle={subtitle}
            showGlobalSearch={false}
            topbarActions={topbarActions}
            topbarTitle={topbarTitle}
        >
            <section className="table-card module-table-card indexare-chirii-table-card">
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
                        </colgroup>
                        <thead>
                            <tr>
                                <th>Imobil</th>
                                <th className="spatiu-identificator-header">Identificat</th>
                                <th>Etaj</th>
                                <th>Pers. cal automat</th>
                                <th>Pers declarate</th>
                                <th>Locatar</th>
                                <th>Suprafață</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            {spatii.map((spatiu) => (
                                <PersoaneDeclarateRow
                                    key={spatiu.id}
                                    spatiu={spatiu}
                                    savingId={savingId}
                                    onSave={savePersoaneDeclarate}
                                />
                            ))}
                            {spatii.length === 0 ? (
                                <tr>
                                    <td colSpan="8">Nu există spații închiriate pentru filtrul selectat.</td>
                                </tr>
                            ) : null}
                        </tbody>
                    </table>
                </div>
            </section>
        </AppLayout>
    );
}
