import React from 'react';
import { Link, router, useForm } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';

function formatMoney(value) {
    if (value === null || value === undefined || value === '') return '—';
    return `${String(Number(value)).replace(/(\.\d*?)0+$/, '$1').replace(/\.$/, '')} lei`;
}

export default function Imobil({ imobil, facturi = [], anexeNefacturate = 0, cursImplicit = 5, cursSursa = '' }) {
    const { processing } = useForm({});

    function generate(event) {
        event.preventDefault();
        router.post('/facturare/generare', { imobil_id: imobil.id }, { preserveScroll: true });
    }

    function deleteFactura(event, factura) {
        event.stopPropagation();
        if (!window.confirm(`Ștergi factura ${factura.numar_factura}?`)) return;

        router.delete(`/facturare/${factura.id}`, { preserveScroll: true });
    }

    const topbarActions = (
        <button className="primary-button topbar-primary-button" type="button" onClick={generate} disabled={processing}>
            {processing ? 'Se generează...' : 'Generează pentru imobil'}
        </button>
    );

    return (
        <AppLayout title={`Facturare ${imobil.nume}`} subtitle={`${imobil.localitate || '—'} - facturile acestui imobil`} showGlobalSearch={false} topbarActions={topbarActions}>
            <section className="readonly-info-card annex-generation-status">
                <h2>{anexeNefacturate} anexe nefacturate</h2>
                <p>
                    Se generează facturi doar pentru anexele din <strong>{imobil.nume} ({imobil.localitate})</strong> care nu au încă factură.
                    {anexeNefacturate === 0 ? ' Generează mai întâi anexele pentru acest imobil.' : ''}
                </p>
                <strong>Curs folosit: {cursImplicit} RON/EUR ({cursSursa})</strong>
                <Link className="secondary-button button-link annex-clear-building-button" href="/facturare">Înapoi la toate imobilele</Link>
            </section>

            <section className="table-card module-table-card">
                <div className="responsive-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Număr factură</th>
                                <th>Anexă</th>
                                <th>Contract</th>
                                <th>Spațiu</th>
                                <th>Chiriaș</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Email chiriaș</th>
                                <th />
                            </tr>
                        </thead>
                        <tbody>
                            {facturi.map((factura) => {
                                const facturaHref = `/facturare/${factura.id}`;

                                return (
                                    <tr key={factura.id} className="clickable-row" data-prefetch-href={facturaHref} onClick={() => router.visit(facturaHref)}>
                                        <td>{factura.numar_factura}</td>
                                        <td>{factura.anexa}</td>
                                        <td>{factura.contract}</td>
                                        <td>{factura.spatiu}</td>
                                        <td>{factura.chirias}</td>
                                        <td>{formatMoney(factura.total)}</td>
                                        <td>{factura.status}</td>
                                        <td>{factura.email_chirias}</td>
                                        <td className="table-action-cell">
                                            <button className="delete-inline-button" type="button" onClick={(event) => deleteFactura(event, factura)} aria-label="Șterge factura">
                                                <Trash2 size={15} strokeWidth={2.4} />
                                            </button>
                                        </td>
                                    </tr>
                                );
                            })}
                            {facturi.length === 0 ? (
                                <tr>
                                    <td colSpan="9">Nu există facturi generate pentru acest imobil.</td>
                                </tr>
                            ) : null}
                        </tbody>
                    </table>
                </div>
            </section>
        </AppLayout>
    );
}
