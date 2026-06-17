import React from 'react';
import { Link, router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';

export default function Index({ moduleKey, title, rows, columns }) {
    const topbarActions = <Link className="primary-button button-link topbar-primary-button" href={`/${moduleKey}/adauga`}>+ Adaugă</Link>;

    return (
        <AppLayout title={`${title} (${rows.length})`} subtitle="Date reale introduse în aplicație" showGlobalSearch={false} topbarActions={topbarActions}>
            <section className="table-card module-table-card">
                <div className="responsive-table">
                    <table>
                        <thead>
                            <tr>
                                {columns.map((column) => <th key={column}>{column}</th>)}
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row) => {
                                const rowHref = `/${moduleKey}/${row.id}/editare`;

                                return (
                                <tr key={row.id} className="clickable-row" data-prefetch-href={rowHref} onClick={() => router.visit(rowHref)}>
                                    {columns.map((column) => <td key={column}>{row.data[column] || '—'}</td>)}
                                </tr>
                                );
                            })}
                            {rows.length === 0 ? (
                                <tr>
                                    <td colSpan={columns.length}>Nu există înregistrări introduse.</td>
                                </tr>
                            ) : null}
                        </tbody>
                    </table>
                </div>
            </section>
        </AppLayout>
    );
}
