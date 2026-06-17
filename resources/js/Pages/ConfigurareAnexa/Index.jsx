import React from 'react';
import { Link, router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';

const serviciiStandardTabs = [
    { key: 'denumire', label: 'Denumire serviciu', href: '/configurare-anexa/servicii-standard/denumire' },
    { key: 'um', label: 'UM', href: '/configurare-anexa/servicii-standard/um' },
    { key: 'tva', label: 'TVA', href: '/configurare-anexa/servicii-standard/tva' },
    { key: 'tip_calcul', label: 'Tip calcul', href: '/configurare-anexa/servicii-standard/tip_calcul' },
];

export default function Index({ anexe = [], imobile = [], selectedImobilId = null }) {
    function selectImobil(imobilId) {
        router.get('/configurare-anexa', imobilId ? { imobil_id: imobilId } : {}, { preserveScroll: true });
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
                <nav className="standard-tabs">
                    {serviciiStandardTabs.map((tab) => (
                        <Link key={tab.key} href={tab.href} className="standard-tab">
                            {tab.label}
                        </Link>
                    ))}
                </nav>
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
                            </tr>
                        </thead>
                        <tbody>
                            {anexe.map((anexa) => {
                                const anexaHref = `/configurare-anexa/${anexa.id}/editare`;

                                return (
                                <tr key={anexa.id} className="clickable-row" data-prefetch-href={anexaHref} onClick={() => router.visit(anexaHref)}>
                                    <td><span className="table-name-link">{anexa.denumire}</span></td>
                                    <td>{anexa.imobil}</td>
                                    <td>{anexa.implicit ? 'Da' : 'Nu'}</td>
                                    <td>{anexa.activ ? 'Da' : 'Nu'}</td>
                                    <td>{anexa.linii_count}</td>
                                </tr>
                                );
                            })}
                            {anexe.length === 0 ? (
                                <tr>
                                    <td colSpan="5">Nu există anexe configurate. Adaugă prima anexă.</td>
                                </tr>
                            ) : null}
                        </tbody>
                    </table>
                </div>
            </section>
        </AppLayout>
    );
}
