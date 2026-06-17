import React from 'react';
import { Link, router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';

function formatMoney(value, moneda = 'EUR') {
    if (value === null || value === undefined || value === '') return '—';
    return `${String(Number(value)).replace(/(\.\d*?)0+$/, '$1').replace(/\.$/, '')} ${moneda}`;
}

export default function Index({ contracte = [] }) {
    const topbarActions = <Link className="primary-button button-link topbar-primary-button" href="/contracte/adauga">+ Adaugă</Link>;

    return (
        <AppLayout title={`Contracte (${contracte.length})`} subtitle="Contracte legate de spații și imobile" showGlobalSearch={false} topbarActions={topbarActions}>
            <section className="table-card module-table-card">
                <div className="responsive-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Număr contract</th>
                                <th>Imobil</th>
                                <th>Spațiu</th>
                                <th>Chiriaș</th>
                                <th>Chirie</th>
                                <th>Perioadă</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            {contracte.map((contract) => {
                                const contractHref = `/contracte/${contract.id}/editare`;

                                return (
                                <tr key={contract.id} className="clickable-row" data-prefetch-href={contractHref} onClick={() => router.visit(contractHref)}>
                                    <td>{contract.numar_contract}</td>
                                    <td>{contract.imobil}</td>
                                    <td>{contract.spatiu}</td>
                                    <td>{contract.chirias}</td>
                                    <td>{formatMoney(contract.chirie, contract.moneda)}</td>
                                    <td>{contract.perioada}</td>
                                    <td>{contract.status}</td>
                                </tr>
                                );
                            })}
                            {contracte.length === 0 ? (
                                <tr>
                                    <td colSpan="7">Nu există contracte introduse.</td>
                                </tr>
                            ) : null}
                        </tbody>
                    </table>
                </div>
            </section>
        </AppLayout>
    );
}
