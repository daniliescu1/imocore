import React from 'react';
import { Deferred, router, useForm } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';

function formatMoney(value) {
    if (value === null || value === undefined || value === '') return '—';
    return `${String(Number(value)).replace(/(\.\d*?)0+$/, '$1').replace(/\.$/, '')} lei`;
}

function formatEuro(value) {
    if (value === null || value === undefined || value === '') return '—';
    return `${String(Number(value)).replace(/(\.\d*?)0+$/, '$1').replace(/\.$/, '')} EUR`;
}

function RezumatImobileTable({ rezumatImobile = [] }) {
    return (
        <section className="table-card module-table-card">
            <div className="responsive-table">
                <table>
                    <thead>
                        <tr>
                            <th>Imobil</th>
                            <th>Spații închiriate</th>
                            <th>Anexe emise</th>
                            <th>Facturi emise</th>
                            <th>Total chirie EUR</th>
                            <th>Total chirie lei</th>
                            <th>Total utilități</th>
                            <th>Total facturat</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rezumatImobile.map((imobil) => (
                            <tr key={imobil.id}>
                                <td>{imobil.nume} ({imobil.localitate})</td>
                                <td>{imobil.spatii_inchiriate}</td>
                                <td>{imobil.anexe_emise}</td>
                                <td>{imobil.facturi_emise}</td>
                                <td>{formatEuro(imobil.total_chirie_eur)}</td>
                                <td>{formatMoney(imobil.total_chirie_lei)}</td>
                                <td>{formatMoney(imobil.total_utilitati)}</td>
                                <td>{formatMoney(imobil.total_facturat)}</td>
                            </tr>
                        ))}
                        {rezumatImobile.length === 0 ? (
                            <tr>
                                <td colSpan="8">Nu există imobile introduse.</td>
                            </tr>
                        ) : null}
                    </tbody>
                </table>
            </div>
        </section>
    );
}

export default function Index({ facturi = [], anexeNefacturate = 0, rezumatImobile = [], cursImplicit = 5, cursSursa = '' }) {
    const { data, setData, processing } = useForm({
        curs_eur: cursImplicit,
    });

    function applyCurs(event) {
        event.preventDefault();
        router.put('/facturare/curs', { curs_eur: data.curs_eur }, { preserveScroll: true });
    }

    function generate(event) {
        event.preventDefault();
        router.post('/facturare/generare', { curs_eur: data.curs_eur }, { preserveScroll: true });
    }

    function deleteFactura(event, factura) {
        event.stopPropagation();
        if (!window.confirm(`Ștergi factura ${factura.numar_factura}?`)) return;

        router.delete(`/facturare/${factura.id}`, { preserveScroll: true });
    }

    const topbarActions = (
        <div className="topbar-actions">
            <label className="inline-topbar-field">
                <span>Curs vânzare EUR</span>
                <input className="filter-input topbar-filter" type="number" min="0" step="0.0001" value={data.curs_eur} onChange={(event) => setData('curs_eur', event.target.value)} />
            </label>
            <button className="secondary-button topbar-primary-button" type="button" onClick={applyCurs} disabled={processing}>
                Aplică
            </button>
            <button className="primary-button topbar-primary-button" type="button" onClick={generate} disabled={processing}>
                Generează facturi
            </button>
        </div>
    );

    return (
        <AppLayout title={`Facturare (${facturi.length})`} subtitle="Generează facturi din anexele existente" showGlobalSearch={false} topbarActions={topbarActions}>
            <section className="readonly-info-card annex-generation-status">
                <h2>{anexeNefacturate} anexe nefacturate</h2>
                <p>Se vor genera facturi pentru anexele care nu au încă factură. Chiria spațiului se calculează în lei cu cursul vânzare EUR afișat sus.</p>
                <strong>Curs folosit: {data.curs_eur} RON/EUR ({cursSursa})</strong>
            </section>

            <Deferred data="rezumatImobile" fallback={<section className="readonly-info-card">Se încarcă rezumatul pe imobile...</section>}>
                <RezumatImobileTable rezumatImobile={rezumatImobile} />
            </Deferred>

            <section className="table-card module-table-card">
                <div className="responsive-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Număr factură</th>
                                <th>Anexă</th>
                                <th>Contract</th>
                                <th>Imobil</th>
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
                                    <td>{factura.imobil}</td>
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
                                    <td colSpan="10">Nu există facturi generate. Apasă Generează facturi după ce ai generat anexele.</td>
                                </tr>
                            ) : null}
                        </tbody>
                    </table>
                </div>
            </section>
        </AppLayout>
    );
}
