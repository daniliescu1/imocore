import React from 'react';
import { Deferred, router, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { useDebouncedSearch } from '../../lib/useDebouncedSearch';

function formatMoney(value) {
    if (value === null || value === undefined || value === '') return '—';
    return `${String(Number(value)).replace(/(\.\d*?)0+$/, '$1').replace(/\.$/, '')} lei`;
}

const luni = [
    ['01', 'Ianuarie'],
    ['02', 'Februarie'],
    ['03', 'Martie'],
    ['04', 'Aprilie'],
    ['05', 'Mai'],
    ['06', 'Iunie'],
    ['07', 'Iulie'],
    ['08', 'August'],
    ['09', 'Septembrie'],
    ['10', 'Octombrie'],
    ['11', 'Noiembrie'],
    ['12', 'Decembrie'],
];

function buildFilters(filters, overrides = {}) {
    return {
        search: filters.search || '',
        localitate: filters.localitate || '',
        ...overrides,
    };
}

function RezumatImobileTable({ rezumatImobile = [] }) {
    return (
        <section className="table-card module-table-card facturare-table-card">
            <div className="responsive-table">
                <table>
                    <thead>
                        <tr>
                            <th>Imobil</th>
                            <th>Spații închiriate</th>
                            <th>Anexe generate</th>
                            <th>Total generat</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rezumatImobile.map((imobil) => (
                            <tr
                                key={imobil.id}
                                className="clickable-row"
                                data-prefetch-href={`/anexe/imobil/${imobil.id}`}
                                onClick={() => router.visit(`/anexe/imobil/${imobil.id}`)}
                            >
                                <td>{imobil.nume} ({imobil.localitate})</td>
                                <td>{imobil.spatii_inchiriate}</td>
                                <td>{imobil.anexe_generate}</td>
                                <td>{formatMoney(imobil.total_generat)}</td>
                            </tr>
                        ))}
                        {rezumatImobile.length === 0 ? (
                            <tr>
                                <td colSpan="4">Nu există imobile introduse.</td>
                            </tr>
                        ) : null}
                    </tbody>
                </table>
            </div>
        </section>
    );
}

function AnexeSearchTable({ anexe = [], search = '' }) {
    return (
        <section className="table-card module-table-card anexe-list-table-card">
            <div className="responsive-table">
                <table className="anexe-list-table">
                    <thead>
                        <tr>
                            <th>Imobil</th>
                            <th>Contract</th>
                            <th className="anexe-spatiu-header">Spațiu</th>
                            <th>Chiriaș</th>
                            <th>Luna</th>
                            <th>Total</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        {anexe.map((anexa) => {
                            const anexaHref = `/anexe/${anexa.id}`;

                            return (
                                <tr
                                    key={anexa.id}
                                    className="clickable-row"
                                    data-prefetch-href={anexaHref}
                                    onClick={() => router.visit(anexaHref)}
                                >
                                    <td>{anexa.imobil}</td>
                                    <td>{anexa.contract}</td>
                                    <td className="anexe-spatiu-cell">
                                        <span className="anexe-spatiu-cell-text" title={anexa.spatiu}>{anexa.spatiu}</span>
                                    </td>
                                    <td>{anexa.chirias}</td>
                                    <td>{anexa.luna}</td>
                                    <td>{formatMoney(anexa.total)}</td>
                                    <td>{anexa.status}</td>
                                </tr>
                            );
                        })}
                        {anexe.length === 0 ? (
                            <tr>
                                <td colSpan="7">
                                    {search
                                        ? 'Nu există anexe generate care să corespundă căutării.'
                                        : 'Nu există anexe generate.'}
                                </td>
                            </tr>
                        ) : null}
                    </tbody>
                </table>
            </div>
        </section>
    );
}

export default function Index({
    rezumatImobile = [],
    anexe = [],
    localitati = [],
    filters = {},
    lunaImplicita = '',
    contracteEligibile = 0,
}) {
    const isAnexeSearchView = Boolean(filters.search);
    const [anImplicit, lunaImplicit] = String(lunaImplicita || '').split('-');
    const { data, setData, processing } = useForm({
        luna: lunaImplicit || String(new Date().getMonth() + 1).padStart(2, '0'),
        an: anImplicit || String(new Date().getFullYear()),
    });
    const lunaSelectata = luni.find(([value]) => value === data.luna)?.[1] || '—';
    const lunaUtilitatiValue = data.luna === '01' ? '12' : String(Number(data.luna) - 1).padStart(2, '0');
    const anUtilitati = data.luna === '01' ? String(Number(data.an) - 1) : data.an;
    const lunaUtilitati = luni.find(([value]) => value === lunaUtilitatiValue)?.[1] || '—';
    const lunaPentruBackend = `${data.an}-${data.luna}`;

    function updateFilters(overrides = {}) {
        router.get('/anexe', buildFilters(filters, overrides), {
            preserveState: true,
            preserveScroll: true,
        });
    }

    const [searchDraft, handleSearchChange] = useDebouncedSearch(filters.search, (value) => {
        updateFilters({ search: value });
    });

    function generate(event) {
        event.preventDefault();
        router.post('/anexe/generare', {
            luna: lunaPentruBackend,
        }, { preserveScroll: true });
    }

    const topbarActions = (
        <div className="spaces-topbar-toolbar">
            {isAnexeSearchView ? (
                <button
                    type="button"
                    className="secondary-button topbar-back-button"
                    onClick={() => updateFilters({ search: '', localitate: '' })}
                >
                    ← Înapoi
                </button>
            ) : null}
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
                    value={searchDraft}
                    placeholder="Caută anexă, contract, spațiu, chiriaș..."
                    onChange={(event) => handleSearchChange(event.target.value)}
                />
            </div>
            <form className="topbar-actions" onSubmit={generate}>
                <select className="filter-input topbar-filter" value={data.luna} onChange={(event) => setData('luna', event.target.value)}>
                    {luni.map(([value, label]) => <option value={value} key={value}>{value} - {label}</option>)}
                </select>
                <input className="filter-input topbar-filter" type="number" min="2000" max="2100" value={data.an} onChange={(event) => setData('an', event.target.value)} />
                <button className="primary-button topbar-primary-button" type="submit" disabled={processing}>
                    {processing ? 'Se generează...' : 'Generează anexele'}
                </button>
            </form>
        </div>
    );

    return (
        <AppLayout
            title={isAnexeSearchView ? `Rezultate căutare (${anexe.length})` : 'Generare anexe'}
            showGlobalSearch={false}
            topbarActions={topbarActions}
        >
            <section className="facturare-imobil-toolbar">
                <div className="facturare-imobil-toolbar-main">
                    <strong className="facturare-imobil-count">{contracteEligibile} spații eligibile</strong>
                    <p className="facturare-imobil-hint">
                        Anexe pentru contracte active cu configurare selectată.
                        {contracteEligibile === 0 ? ' Adaugă contract activ și anexă pe spațiu.' : ''}
                    </p>
                </div>
                <div className="facturare-imobil-toolbar-meta">
                    <span className="facturare-imobil-curs">
                        Utilități {lunaUtilitati} {anUtilitati} · facturare {lunaSelectata} {data.an}
                    </span>
                </div>
            </section>

            {isAnexeSearchView ? (
                <AnexeSearchTable anexe={anexe} search={filters.search} />
            ) : (
                <Deferred data="rezumatImobile" fallback={<p className="facturare-loading-note">Se încarcă rezumatul pe imobile...</p>}>
                    <RezumatImobileTable rezumatImobile={rezumatImobile} />
                </Deferred>
            )}
        </AppLayout>
    );
}
