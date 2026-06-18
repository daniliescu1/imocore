import React from 'react';
import { router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';

function formatLunaLabel(luna) {
    if (!luna) return '—';
    const [an, lunaNumar] = String(luna).split('-');

    return `${lunaNumar}.${an}`;
}

export default function Index({ imobile = [] }) {
    return (
        <AppLayout
            title="Citiri contoare"
            subtitle="Alege imobilul pentru a introduce indexurile contoarelor din anexele alocate spațiilor."
            showGlobalSearch={false}
        >
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
                                const rowHref = `/citiri-contoare/imobil/${imobil.id}`;

                                return (
                                    <tr
                                        key={imobil.id}
                                        className="clickable-row"
                                        data-prefetch-href={rowHref}
                                        onClick={() => router.visit(`${rowHref}?mode=new`)}
                                    >
                                        <td><strong>{imobil.nume}</strong> ({imobil.localitate})</td>
                                        <td>{imobil.contoare_count || '—'}</td>
                                        <td>{formatLunaLabel(imobil.ultima_luna_citita)}</td>
                                    </tr>
                                );
                            })}
                            {imobile.length === 0 ? (
                                <tr>
                                    <td colSpan="3">Nu există imobile introduse.</td>
                                </tr>
                            ) : null}
                        </tbody>
                    </table>
                </div>
            </section>
        </AppLayout>
    );
}
