import React from 'react';
import { Link, router } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import ConfigurareAnexaTabs from '../../Components/ConfigurareAnexaTabs';
import { useDebouncedSearch } from '../../lib/useDebouncedSearch';

function buildFilters(selectedImobilId, filters, overrides = {}) {
    return {
        imobil_id: selectedImobilId || '',
        search: filters.search || '',
        ...overrides,
    };
}

export default function Index({
    anexe = [],
    imobile = [],
    selectedImobilId = null,
    spatiiCautate = [],
    filters = {},
    cursImplicit = 5,
    cursSursa = '',
}) {
    const isSearchView = Boolean(String(filters.search || '').trim());
    const spatiiFaraAnexa = isSearchView
        ? spatiiCautate.filter((spatiu) => !spatiu.configurare_anexa_id)
        : [];

    function updateFilters(overrides = {}) {
        router.get('/configurare-anexa', buildFilters(selectedImobilId, filters, overrides), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }

    function selectImobil(imobilId) {
        updateFilters({ imobil_id: imobilId || '' });
    }

    const [searchDraft, handleSearchChange] = useDebouncedSearch(filters.search, (value) => {
        updateFilters({ search: value });
    });

    function deleteAnexa(event, anexa) {
        event.stopPropagation();

        if (anexa.spatii_count > 0) {
            window.alert(`Anexa «${anexa.denumire}» e folosită de ${anexa.spatii_count} spații. Schimbă anexa pe spații înainte de ștergere.`);
            return;
        }

        if (!window.confirm(`Ștergi anexa «${anexa.denumire}»?`)) {
            return;
        }

        const params = new URLSearchParams();
        if (selectedImobilId) {
            params.set('imobil_id', String(selectedImobilId));
        }
        if (filters.search) {
            params.set('search', filters.search);
        }

        const query = params.toString();
        const deleteUrl = query
            ? `/configurare-anexa/${anexa.id}?${query}`
            : `/configurare-anexa/${anexa.id}`;

        router.delete(deleteUrl, { preserveScroll: true });
    }

    const topbarActions = (
        <div className="spaces-topbar-toolbar">
            {isSearchView ? (
                <button
                    type="button"
                    className="secondary-button topbar-back-button"
                    onClick={() => updateFilters({ search: '' })}
                >
                    ← Înapoi
                </button>
            ) : null}
            <div className="spaces-topbar-filters">
                <select
                    className="filter-input topbar-filter"
                    value={selectedImobilId || ''}
                    onChange={(event) => selectImobil(event.target.value)}
                >
                    <option value="">Imobil: Toate</option>
                    {imobile.map((item) => <option value={item.id} key={item.id}>{item.label}</option>)}
                </select>
                <input
                    className="filter-input topbar-search"
                    type="search"
                    value={searchDraft}
                    placeholder="Caută spațiu, chiriaș, locator..."
                    onChange={(event) => handleSearchChange(event.target.value)}
                />
            </div>
            <Link className="primary-button button-link topbar-primary-button" href={`/configurare-anexa/adauga${selectedImobilId ? `?imobil_id=${selectedImobilId}` : ''}`}>+ Adaugă anexă</Link>
        </div>
    );

    const emptyMessage = (() => {
        if (!isSearchView) {
            return 'Nu există anexe configurate. Adaugă prima anexă.';
        }

        if (spatiiCautate.length === 0) {
            return 'Nu există spații care să corespundă căutării.';
        }

        if (spatiiFaraAnexa.length === spatiiCautate.length) {
            return 'Spațiile găsite nu au anexă alocată.';
        }

        return 'Nu există anexe configurate pentru spațiile găsite.';
    })();

    return (
        <AppLayout
            title={isSearchView ? `Rezultate căutare (${anexe.length})` : `Configurare anexă (${anexe.length})`}
            subtitle="Administrează anexele configurate și alocarea lor pe imobile"
            showGlobalSearch={false}
            topbarActions={topbarActions}
        >
            <section className="readonly-info-card annex-generation-status">
                <ConfigurareAnexaTabs cursImplicit={cursImplicit} cursSursa={cursSursa} />
            </section>

            {isSearchView && spatiiCautate.length > 0 ? (
                <div className="spatiu-context-banner spatiu-context-banner-compact">
                    {spatiiCautate.length === 1
                        ? `Spațiu găsit: ${spatiiCautate[0].identificator}${spatiiCautate[0].chirias ? ` · ${spatiiCautate[0].chirias}` : ''}${spatiiCautate[0].anexa ? ` · anexă: ${spatiiCautate[0].anexa}` : ' · fără anexă alocată'}.`
                        : `${spatiiCautate.length} spații găsite. Afișez anexele folosite de aceste spații.`}
                </div>
            ) : null}

            <section className="table-card module-table-card">
                <div className="responsive-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Denumire anexă</th>
                                <th>Imobil</th>
                                <th>Implicită</th>
                                <th>Activă</th>
                                <th>Servicii</th>
                                <th>Spații închiriate</th>
                                <th aria-label="Acțiuni" />
                            </tr>
                        </thead>
                        <tbody>
                            {anexe.map((anexa) => {
                                const anexaHref = `/configurare-anexa/${anexa.id}/editare`;
                                const deleteDisabled = anexa.spatii_count > 0;
                                const deleteTitle = deleteDisabled
                                    ? `Folosită de ${anexa.spatii_count} spații`
                                    : 'Șterge anexa';

                                return (
                                <tr key={anexa.id} className="clickable-row" data-prefetch-href={anexaHref} onClick={() => router.visit(anexaHref)}>
                                    <td><span className="table-name-link">{anexa.denumire}</span></td>
                                    <td>{anexa.imobil}</td>
                                    <td>{anexa.implicit ? 'Da' : 'Nu'}</td>
                                    <td>{anexa.activ ? 'Da' : 'Nu'}</td>
                                    <td>{anexa.linii_count}</td>
                                    <td onClick={(event) => event.stopPropagation()}>
                                        {anexa.spatii_inchiriate_count > 0 ? (
                                            <Link className="table-name-link" href={`/contracte?configurare_anexa_id=${anexa.id}`}>
                                                {anexa.spatii_inchiriate_count}
                                            </Link>
                                        ) : (
                                            '0'
                                        )}
                                    </td>
                                    <td className="table-action-cell">
                                        <button
                                            type="button"
                                            className="delete-inline-button"
                                            onClick={(event) => deleteAnexa(event, anexa)}
                                            aria-label={`Șterge ${anexa.denumire}`}
                                            title={deleteTitle}
                                            disabled={deleteDisabled}
                                        >
                                            <Trash2 size={14} strokeWidth={2.4} />
                                        </button>
                                    </td>
                                </tr>
                                );
                            })}
                            {anexe.length === 0 ? (
                                <tr>
                                    <td colSpan="7">{emptyMessage}</td>
                                </tr>
                            ) : null}
                        </tbody>
                    </table>
                </div>
            </section>
        </AppLayout>
    );
}
