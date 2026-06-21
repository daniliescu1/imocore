import React from 'react';
import { router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';

export default function Index({ imobile = [] }) {
    return (
        <AppLayout
            title="Configurare contoare"
            subtitle="Imobile cu anexe alocate care conțin linii de tip „Contor configurabil”."
            showGlobalSearch={false}
        >
            <section className="table-card module-table-card">
                <div className="responsive-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Imobil</th>
                                <th>Contoare configurabile</th>
                            </tr>
                        </thead>
                        <tbody>
                            {imobile.map((imobil) => {
                                const rowHref = `/configurare-contoare/imobil/${imobil.id}`;

                                return (
                                    <tr
                                        key={imobil.id}
                                        className="clickable-row"
                                        data-prefetch-href={rowHref}
                                        onClick={() => router.visit(rowHref)}
                                    >
                                        <td><strong>{imobil.nume}</strong> ({imobil.localitate})</td>
                                        <td>{imobil.contoare_configurabile_count || '—'}</td>
                                    </tr>
                                );
                            })}
                            {imobile.length === 0 ? (
                                <tr>
                                    <td colSpan="2">
                                        Nu există imobile cu anexe alocate care conțin contoare configurabile.
                                        Adaugă tipul „Contor configurabil” pe o linie de anexă și alocă anexa pe spații.
                                    </td>
                                </tr>
                            ) : null}
                        </tbody>
                    </table>
                </div>
            </section>
        </AppLayout>
    );
}
