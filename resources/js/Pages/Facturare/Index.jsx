import React from 'react';
import { Deferred, router, useForm } from '@inertiajs/react';
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
                            <tr
                                key={imobil.id}
                                className="clickable-row"
                                data-prefetch-href={`/facturare/imobil/${imobil.id}`}
                                onClick={() => router.visit(`/facturare/imobil/${imobil.id}`)}
                            >
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

export default function Index({ anexeNefacturate = 0, rezumatImobile = [], cursImplicit = 5, cursSursa = '' }) {
    const { processing } = useForm({});

    function generate(event) {
        event.preventDefault();
        router.post('/facturare/generare', {}, { preserveScroll: true });
    }

    const topbarActions = (
        <button className="primary-button topbar-primary-button" type="button" onClick={generate} disabled={processing}>
            {processing ? 'Se generează...' : 'Generează facturi'}
        </button>
    );

    return (
        <AppLayout title="Facturare" subtitle="Generează facturi din anexele existente" showGlobalSearch={false} topbarActions={topbarActions}>
            <section className="readonly-info-card annex-generation-status">
                <h2>{anexeNefacturate} anexe nefacturate</h2>
                <p>Se vor genera facturi pentru anexele care nu au încă factură. Chiria spațiului se calculează în lei cu cursul vânzare EUR setat în Configurare anexă.</p>
                <strong>Curs folosit: {cursImplicit} RON/EUR ({cursSursa})</strong>
            </section>

            <Deferred data="rezumatImobile" fallback={<section className="readonly-info-card">Se încarcă rezumatul pe imobile...</section>}>
                <RezumatImobileTable rezumatImobile={rezumatImobile} />
            </Deferred>
        </AppLayout>
    );
}
