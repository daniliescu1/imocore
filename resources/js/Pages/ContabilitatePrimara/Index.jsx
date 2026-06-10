import React from 'react';
import { router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';

function formatNumber(value) {
    return Number(value || 0).toLocaleString('ro-RO', { maximumFractionDigits: 2 });
}

export default function Index({ luna, imobile }) {
    const topbarActions = (
        <input
            className="filter-input topbar-filter"
            type="month"
            value={luna}
            onChange={(event) => router.get('/contabilitate-primara', { luna: event.target.value }, { preserveState: true })}
        />
    );

    return (
        <AppLayout title="Contabilitate primară" subtitle="Verificare lunară pe imobil" showGlobalSearch={false} topbarActions={topbarActions}>
            <section className="table-card module-table-card">
                <div className="responsive-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Imobil</th>
                                <th>Mp închiriați</th>
                                <th>Mp încălziți</th>
                                <th>Pers. declarate</th>
                                <th>Pers. standard</th>
                                <th>Diferență pers.</th>
                                <th>Total utilități</th>
                                <th>Utilități neaprobate</th>
                            </tr>
                        </thead>
                        <tbody>
                            {imobile.map((imobil) => (
                                <tr key={imobil.id}>
                                    <td>{imobil.nume}</td>
                                    <td>{formatNumber(imobil.mp_inchiriati)}</td>
                                    <td>{formatNumber(imobil.mp_incalziti)}</td>
                                    <td>{imobil.persoane_declarate}</td>
                                    <td>{imobil.persoane_standard}</td>
                                    <td className={imobil.diferenta_persoane > 0 ? 'danger-text' : ''}>{imobil.diferenta_persoane}</td>
                                    <td>{formatNumber(imobil.total_utilitati)} RON</td>
                                    <td>{imobil.utilitati_neaprobate}</td>
                                </tr>
                            ))}
                            {imobile.length === 0 ? (
                                <tr>
                                    <td colSpan="8">Nu există imobile pentru verificare.</td>
                                </tr>
                            ) : null}
                        </tbody>
                    </table>
                </div>
            </section>
        </AppLayout>
    );
}
