import React from 'react';
import { Link, router } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';

export default function Index({ imobile, localitati, filters }) {
    function updateFilters(nextFilters) {
        router.get('/imobile', {
            localitate: filters.localitate || '',
            search: filters.search || '',
            ...nextFilters,
        }, { preserveState: true, preserveScroll: true });
    }

    function openImobil(imobil) {
        router.visit(`/imobile/${imobil.id}/editare`);
    }

    function deleteImobil(event, imobil) {
        event.stopPropagation();

        if (!window.confirm(`Ștergi imobilul „${imobil.nume}”?`)) {
            return;
        }

        router.delete(`/imobile/${imobil.id}`, { preserveScroll: true });
    }

    const topbarActions = (
        <>
            <select className="filter-input topbar-filter" value={filters.localitate || ''} onChange={(event) => updateFilters({ localitate: event.target.value })}>
                <option value="">Localitate: Toate</option>
                {localitati.map((localitate) => <option value={localitate} key={localitate}>{localitate}</option>)}
            </select>
            <input
                className="filter-input topbar-search"
                type="search"
                value={filters.search || ''}
                placeholder="Caută în imobile..."
                onChange={(event) => updateFilters({ search: event.target.value })}
            />
            <Link className="primary-button button-link topbar-primary-button" href="/imobile/adauga">+ Imobil nou</Link>
        </>
    );

    return (
        <AppLayout
            title={`Imobile (${imobile.length})`}
            subtitle="Administrare nume imobil și adresă"
            showGlobalSearch={false}
            topbarActions={topbarActions}
        >
            <section className="table-card module-table-card">
                <div className="responsive-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Nume imobil</th>
                                <th>Adresă</th>
                                <th>Nr. CF</th>
                                <th>Spații</th>
                                <th>Libere</th>
                                <th>Închiriate</th>
                                <th>Spații comune</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            {imobile.map((imobil) => (
                                <tr key={imobil.id} className="clickable-row" onClick={() => openImobil(imobil)}>
                                    <td>
                                        <div className="name-with-action">
                                            <Link className="table-name-link" href={`/imobile/${imobil.id}/editare`}>{imobil.nume}</Link>
                                        </div>
                                    </td>
                                    <td>{imobil.adresa}</td>
                                    <td>{imobil.numere_cf}</td>
                                    <td>{imobil.spatii_total}</td>
                                    <td>{imobil.spatii_libere}</td>
                                    <td>{imobil.spatii_inchiriate}</td>
                                    <td>{imobil.spatii_comune}</td>
                                    <td className="table-action-cell">
                                        <button type="button" className="delete-inline-button" onClick={(event) => deleteImobil(event, imobil)} aria-label={`Șterge ${imobil.nume}`} title="Șterge imobil">
                                            <Trash2 size={14} strokeWidth={2.4} />
                                        </button>
                                    </td>
                                </tr>
                            ))}
                            {imobile.length === 0 ? (
                                <tr>
                                    <td colSpan="8">Nu există imobile pentru filtrul selectat.</td>
                                </tr>
                            ) : null}
                        </tbody>
                    </table>
                </div>
            </section>
        </AppLayout>
    );
}