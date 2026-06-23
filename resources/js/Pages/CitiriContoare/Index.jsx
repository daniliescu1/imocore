import React from 'react';
import { router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { useDebouncedSearch } from '../../lib/useDebouncedSearch';
import CitiriImobilPanel from './Imobil';

function formatLunaLabel(luna) {
    if (!luna) return '—';
    const [an, lunaNumar] = String(luna).split('-');

    return `${lunaNumar}.${an}`;
}

function buildFilters(filters, overrides = {}) {
    return {
        search: filters.search || '',
        localitate: filters.localitate || '',
        ...overrides,
    };
}

export default function Index({
    imobile = [],
    citiriGrupuri = [],
    localitati = [],
    filters = {},
}) {
    const isCitiriSearchView = Boolean(filters.search_citiri);

    function updateFilters(overrides = {}) {
        router.get('/citiri-contoare', buildFilters(filters, overrides), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }

    const [searchDraft, handleSearchChange] = useDebouncedSearch(filters.search, (value) => {
        updateFilters({ search: value });
    });

    function openImobil(imobil) {
        router.visit(`/citiri-contoare/imobil/${imobil.id}?mode=new`);
    }

    const topbarActions = (
        <div className="spaces-topbar-toolbar">
            {isCitiriSearchView ? (
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
                    placeholder="Caută spațiu, chiriaș, locator..."
                    onChange={(event) => handleSearchChange(event.target.value)}
                />
            </div>
        </div>
    );

    return (
        <AppLayout
            title={isCitiriSearchView ? `Rezultate căutare (${citiriGrupuri.length})` : 'Citiri contoare'}
            subtitle="Alege imobilul pentru a introduce indexurile contoarelor din anexele alocate spațiilor."
            showGlobalSearch={false}
            topbarActions={topbarActions}
        >
            {isCitiriSearchView ? (
                citiriGrupuri.length > 0 ? (
                    <div className="page-compact-list citiri-search-results">
                        {citiriGrupuri.map((grup) => (
                            <CitiriImobilPanel key={grup.imobil.id} embedded {...grup} />
                        ))}
                    </div>
                ) : (
                    <section className="table-card module-table-card">
                        <div className="empty-state-card">
                            Nu există contoare de citit pentru spațiile găsite. Verifică dacă spațiile au anexă alocată cu linii de tip Contor.
                        </div>
                    </section>
                )
            ) : (
                <section className="table-card module-table-card">
                    <div className="responsive-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Imobil</th>
                                    <th>Contoare de citit</th>
                                    <th>Ultima lună citită</th>
                                </tr>
                            </thead>
                            <tbody>
                                {imobile.map((imobil) => {
                                    const rowHref = `/citiri-contoare/imobil/${imobil.id}?mode=new`;

                                    return (
                                        <tr
                                            key={imobil.id}
                                            className="clickable-row"
                                            data-prefetch-href={rowHref}
                                            onClick={() => openImobil(imobil)}
                                        >
                                            <td><strong>{imobil.nume}</strong> ({imobil.localitate})</td>
                                            <td>{imobil.contoare_count || '—'}</td>
                                            <td>{formatLunaLabel(imobil.ultima_luna_citita)}</td>
                                        </tr>
                                    );
                                })}
                                {imobile.length === 0 ? (
                                    <tr>
                                        <td colSpan="3">
                                            {filters.search
                                                ? 'Nu există imobile care să corespundă căutării.'
                                                : 'Nu există imobile introduse.'}
                                        </td>
                                    </tr>
                                ) : null}
                            </tbody>
                        </table>
                    </div>
                </section>
            )}
        </AppLayout>
    );
}
