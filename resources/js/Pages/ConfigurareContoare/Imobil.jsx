import React from 'react';
import { Link, router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';

function formatDecimal(value) {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    return String(value).replace(/(\.\d*?)0+$/, '$1').replace(/\.$/, '');
}

export default function Imobil({
    imobil,
    contoare = [],
}) {
    return (
        <AppLayout
            title={`Configurare contoare ${imobil.nume}`}
            subtitle={`Contoare configurabile și pausale · ${imobil.localitate}`}
            showGlobalSearch={false}
            topbarActions={(
                <Link className="secondary-button" href="/configurare-contoare">Înapoi la imobile</Link>
            )}
        >
            {contoare.length === 0 ? (
                <div className="readonly-info-card">
                    <h2>Nu există contoare de configurat</h2>
                    <p>Niciun spațiu al acestui imobil nu are anexă alocată cu linii de tip „Contor configurabil” sau „Pausal”.</p>
                </div>
            ) : (
                <section className="table-card module-table-card contor-config-list-table-card">
                    <div className="responsive-table">
                        <table className="contor-config-list-table">
                            <thead>
                                <tr>
                                    <th>Serviciu</th>
                                    <th>Tip</th>
                                    <th>Anexă</th>
                                    <th>UM</th>
                                    <th>Ultima lună</th>
                                    <th>Index vechi</th>
                                    <th>Index nou</th>
                                    <th>Consum</th>
                                    <th>Regulă</th>
                                </tr>
                            </thead>
                            <tbody>
                                {contoare.map((contor) => {
                                    const rowHref = `/configurare-contoare/imobil/${imobil.id}/contor/${contor.id}`;
                                    const citire = contor.ultima_citire;

                                    return (
                                        <tr
                                            key={contor.id}
                                            className="clickable-row"
                                            data-prefetch-href={rowHref}
                                            onClick={() => router.visit(rowHref)}
                                        >
                                            <td title={contor.denumire}><strong>{contor.denumire}</strong></td>
                                            <td>{contor.tip_label || '—'}</td>
                                            <td title={contor.anexa}>{contor.anexa}</td>
                                            <td>{contor.um || '—'}</td>
                                            <td>{citire?.luna_label || '—'}</td>
                                            <td>{formatDecimal(citire?.index_vechi)}</td>
                                            <td>{formatDecimal(citire?.index_nou)}</td>
                                            <td>{formatDecimal(citire?.consum)}</td>
                                            <td>{contor.configurata ? `${contor.alocari_count} spații` : 'Neconfigurată'}</td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                </section>
            )}
        </AppLayout>
    );
}
