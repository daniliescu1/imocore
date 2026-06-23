import React from 'react';
import { Deferred, router, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import ModuleSpatiuSearchToolbar from '../../Components/ModuleSpatiuSearchToolbar';
import SpatiuModuleSearchTable from '../../Components/SpatiuModuleSearchTable';

function formatMoney(value) {
    if (value === null || value === undefined || value === '') return '—';
    return `${String(Number(value)).replace(/(\.\d*?)0+$/, '$1').replace(/\.$/, '')} lei`;
}

function formatEuro(value) {
    if (value === null || value === undefined || value === '') return '—';
    return `${String(Number(value)).replace(/(\.\d*?)0+$/, '$1').replace(/\.$/, '')} EUR`;
}

function facturareStatusLine(anexeFacturate, anexeNefacturate, cursImplicit) {
    const facturateLabel = anexeFacturate === 1 ? 'facturată' : 'facturate';

    const nefacturateLabel = anexeNefacturate === 1 ? 'anexă nefacturată' : 'anexe nefacturate';

    return `${anexeFacturate} ${facturateLabel} · ${anexeNefacturate} ${nefacturateLabel} — Curs ${cursImplicit} RON/EUR`;
}

function RezumatImobileTable({ rezumatImobile = [], search = '' }) {
    return (
        <section className="table-card module-table-card facturare-table-card">
            <div className="responsive-table">
                <table className="facturare-rezumat-table">
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
                                <td colSpan="8">
                                    {search
                                        ? 'Nu există imobile care să corespundă căutării.'
                                        : 'Nu există imobile introduse.'}
                                </td>
                            </tr>
                        ) : null}
                    </tbody>
                </table>
            </div>
        </section>
    );
}

export default function Index({
    anexeFacturate = 0,
    anexeNefacturate = 0,
    rezumatImobile = [],
    spatii = [],
    localitati = [],
    filters = {},
    cursImplicit = 5,
}) {
    const isRootSpatiiSearchView = Boolean(filters.search_spatii);
    const showImobilColumn = isRootSpatiiSearchView && new Set(spatii.map((spatiu) => spatiu.imobil_id)).size > 1;
    const { processing } = useForm({});

    function generate(event) {
        event.preventDefault();
        router.post('/facturare/generare', {}, { preserveScroll: true });
    }

    const topbarActions = (
        <ModuleSpatiuSearchToolbar
            filters={filters}
            localitati={localitati}
            routePath="/facturare"
            showBack={isRootSpatiiSearchView}
            extraActions={(
                <button className="primary-button topbar-primary-button" type="button" onClick={generate} disabled={processing}>
                    {processing ? 'Se generează...' : 'Generează facturi'}
                </button>
            )}
        />
    );

    const statusLine = facturareStatusLine(anexeFacturate, anexeNefacturate, cursImplicit);
    const topbarTitle = (
        <div className="topbar-page-title">
            <h1>{isRootSpatiiSearchView ? `Rezultate căutare (${spatii.length})` : 'Facturare'}</h1>
            <p>{statusLine}</p>
        </div>
    );

    function openSpatiu(spatiu) {
        const params = new URLSearchParams({ search_spatiu: filters.search || '' });
        router.visit(`/facturare/imobil/${spatiu.imobil_id}?${params.toString()}`);
    }

    return (
        <AppLayout title={isRootSpatiiSearchView ? `Rezultate căutare (${spatii.length})` : 'Facturare'} showGlobalSearch={false} topbarActions={topbarActions} topbarTitle={topbarTitle}>
            <div className="page-compact-list">
                {isRootSpatiiSearchView ? (
                    <SpatiuModuleSearchTable
                        spatii={spatii}
                        onOpen={openSpatiu}
                        showImobilColumn={showImobilColumn}
                        getRowHref={(spatiu) => {
                            const params = new URLSearchParams({ search_spatiu: filters.search || '' });
                            return `/facturare/imobil/${spatiu.imobil_id}?${params.toString()}`;
                        }}
                    />
                ) : (
                    <Deferred data="rezumatImobile" fallback={<p className="facturare-loading-note">Se încarcă rezumatul pe imobile...</p>}>
                        <RezumatImobileTable rezumatImobile={rezumatImobile} search={filters.search} />
                    </Deferred>
                )}
            </div>
        </AppLayout>
    );
}
