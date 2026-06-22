import React from 'react';
import { Link, router } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import ConfigurareAnexaTabs from '../../Components/ConfigurareAnexaTabs';

export default function Index({ anexe = [], imobile = [], selectedImobilId = null, cursImplicit = 5, cursSursa = '' }) {
    function selectImobil(imobilId) {
        router.get('/configurare-anexa', imobilId ? { imobil_id: imobilId } : {}, { preserveScroll: true });
    }

    function deleteAnexa(event, anexa) {
        event.stopPropagation();

        if (anexa.spatii_count > 0) {
            window.alert(`Anexa «${anexa.denumire}» e folosită de ${anexa.spatii_count} spații. Schimbă anexa pe spații înainte de ștergere.`);
            return;
        }

        if (!window.confirm(`Ștergi anexa «${anexa.denumire}»?`)) {
            return;
        }

        const deleteUrl = selectedImobilId
            ? `/configurare-anexa/${anexa.id}?imobil_id=${selectedImobilId}`
            : `/configurare-anexa/${anexa.id}`;

        router.delete(deleteUrl, { preserveScroll: true });
    }

    const topbarActions = (
        <>
            <select className="filter-input topbar-filter" value={selectedImobilId || ''} onChange={(event) => selectImobil(event.target.value)}>
                <option value="">Imobil: Toate</option>
                {imobile.map((item) => <option value={item.id} key={item.id}>{item.label}</option>)}
            </select>
            <Link className="primary-button button-link topbar-primary-button" href={`/configurare-anexa/adauga${selectedImobilId ? `?imobil_id=${selectedImobilId}` : ''}`}>+ Adaugă anexă</Link>
        </>
    );

    return (
        <AppLayout title={`Configurare anexă (${anexe.length})`} subtitle="Administrează anexele configurate și alocarea lor pe imobile" showGlobalSearch={false} topbarActions={topbarActions}>
            <section className="readonly-info-card annex-generation-status">
                <ConfigurareAnexaTabs cursImplicit={cursImplicit} cursSursa={cursSursa} />
            </section>

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
                                    <td colSpan="6">Nu există anexe configurate. Adaugă prima anexă.</td>
                                </tr>
                            ) : null}
                        </tbody>
                    </table>
                </div>
            </section>
        </AppLayout>
    );
}
