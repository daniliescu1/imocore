import React from 'react';
import { Link, router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';

export default function Index({ locatori }) {
    const topbarActions = <Link className="primary-button button-link topbar-primary-button" href="/locatori/adauga">+ Adaugă locator</Link>;

    return (
        <AppLayout title={`Locatori (${locatori.length})`} subtitle="Administrare locatori reutilizabili în toate imobilele" showGlobalSearch={false} topbarActions={topbarActions}>
            <section className="table-card module-table-card">
                <div className="responsive-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Nume locator</th>
                                <th>CUI</th>
                                <th>Chirie</th>
                                <th>Imobil asociat</th>
                                <th>Spații</th>
                                <th>Folosit la</th>
                            </tr>
                        </thead>
                        <tbody>
                            {locatori.map((locator) => {
                                const locatorHref = `/locatori/${locator.id}/editare`;

                                return (
                                <tr key={locator.id} className="clickable-row" data-prefetch-href={locatorHref} onClick={() => router.visit(locatorHref)}>
                                    <td><span className="table-name-link">{locator.nume}</span></td>
                                    <td>{locator.cui || '—'}</td>
                                    <td>{locator.chirie_cu_tva}</td>
                                    <td>{locator.imobil}</td>
                                    <td>{locator.spatii_count}</td>
                                    <td>{locator.spatii || '—'}</td>
                                </tr>
                                );
                            })}
                            {locatori.length === 0 ? (
                                <tr>
                                    <td colSpan="6">Nu există locatori încă.</td>
                                </tr>
                            ) : null}
                        </tbody>
                    </table>
                </div>
            </section>
        </AppLayout>
    );
}
