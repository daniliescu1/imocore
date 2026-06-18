import React from 'react';
import { Link, router, useForm } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';

function formatMoney(value) {
    if (value === null || value === undefined || value === '') return '—';
    return `${String(Number(value)).replace(/(\.\d*?)0+$/, '$1').replace(/\.$/, '')} lei`;
}

function facturareStatusLine(anexeFacturate, anexeNefacturate, cursImplicit) {
    const facturateLabel = anexeFacturate === 1 ? 'facturată' : 'facturate';

    const nefacturateLabel = anexeNefacturate === 1 ? 'anexă nefacturată' : 'anexe nefacturate';

    return `${anexeFacturate} ${facturateLabel} · ${anexeNefacturate} ${nefacturateLabel} — Curs ${cursImplicit} RON/EUR`;
}

export default function Imobil({ imobil, facturi = [], anexeFacturate = 0, anexeNefacturate = 0, cursImplicit = 5 }) {
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
        <>
            <Link className="secondary-button button-link facturare-imobil-back" href="/facturare">Înapoi</Link>
            <button className="primary-button topbar-primary-button" type="button" onClick={generate} disabled={processing}>
                {processing ? 'Se generează...' : 'Generează pentru imobil'}
            </button>
        </>
    );

    const statusLine = facturareStatusLine(anexeFacturate, anexeNefacturate, cursImplicit);
    const topbarTitle = (
        <div className="topbar-page-title">
            <h1>Facturare {imobil.nume}</h1>
            <p>{statusLine}</p>
        </div>
    );

    return (
        <AppLayout title={`Facturare ${imobil.nume}`} showGlobalSearch={false} topbarActions={topbarActions} topbarTitle={topbarTitle}>
            <section className="table-card module-table-card page-compact-list">
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
