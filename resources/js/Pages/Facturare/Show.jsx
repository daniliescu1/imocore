import React from 'react';
import { Link } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import AnnexDocumentPreview from '../../Components/AnnexDocumentPreview';

function formatDecimal(value) {
    if (value === null || value === undefined || value === '') return '—';
    return String(Number(value)).replace(/(\.\d*?)0+$/, '$1').replace(/\.$/, '');
}

function formatMoney(value) {
    if (value === null || value === undefined || value === '') return '—';
    return `${Number(value).toFixed(2)} lei`;
}

function formatMoneyValue(value) {
    if (value === null || value === undefined || value === '') return '—';
    return Number(value).toFixed(2);
}

function formatAmount(value) {
    if (value === null || value === undefined || value === '') return '—';
    return Number(value).toLocaleString('ro-RO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function displayValue(value) {
    if (value === null || value === undefined || String(value).trim() === '') return '—';
    return value;
}

function InvoicePartyDetail({ label, value }) {
    return (
        <p className="invoice-party-detail">
            <span>{label}</span>
            {displayValue(value)}
        </p>
    );
}

function InvoiceLocatorCard({ locator }) {
    return (
        <div className="invoice-party-card">
            <span className="invoice-party-heading">Locator</span>
            <strong className="invoice-party-name">{displayValue(locator?.nume)}</strong>
            <InvoicePartyDetail label="CUI" value={locator?.cui} />
            <InvoicePartyDetail label="Reg. Com." value={locator?.reg_com} />
            <InvoicePartyDetail label="Adresă" value={locator?.adresa} />
            <InvoicePartyDetail label="Bancă" value={locator?.banca} />
            <InvoicePartyDetail label="Cont" value={locator?.cont_bancar} />
            <InvoicePartyDetail label="Email" value={locator?.email} />
        </div>
    );
}

function InvoiceLocatarCard({ locatar }) {
    return (
        <div className="invoice-party-card">
            <span className="invoice-party-heading">Locatar</span>
            <strong className="invoice-party-name">{displayValue(locatar?.nume)}</strong>
            <InvoicePartyDetail label={locatar?.identificator_label || 'CUI'} value={locatar?.identificator} />
            {locatar?.tip === 'pf' ? <InvoicePartyDetail label="CI" value={locatar?.ci} /> : null}
            <InvoicePartyDetail label="Adresă" value={locatar?.adresa} />
            <InvoicePartyDetail label="Telefon" value={locatar?.telefon} />
            <InvoicePartyDetail label="Email" value={locatar?.email} />
        </div>
    );
}

export default function Show({ factura, downloadUrl }) {
    const topbarActions = (
        <>
            <a className="secondary-button button-link" href={downloadUrl}>Descarcă PDF</a>
            <Link className="secondary-button button-link" href="/facturare">Înapoi la facturi</Link>
        </>
    );
    const sumar = factura.sumar || {
        total_fara_tva: factura.linii.reduce((sum, linie) => sum + Number(linie.valoare || 0), 0),
        tva_21: 0,
        tva_11: 0,
        total: factura.total,
    };

    return (
        <AppLayout title={`Factura ${factura.numar_factura}`} subtitle="Previzualizare factură generată" showGlobalSearch={false} topbarActions={topbarActions}>
            <section className="generated-annex">
                <div className="generated-annex-header invoice-document-header">
                    <div>
                        <h2>FACTURA</h2>
                        <p className="invoice-number">{factura.numar_factura || '—'}</p>
                        <p className="invoice-period-note">pentru anexa din luna {factura.luna}</p>
                    </div>
                    <div className="generated-annex-meta invoice-dates-meta">
                        <div className="invoice-date-row">
                            <span>Data emitere:</span>
                            <strong>{factura.data_emitere || '—'}</strong>
                        </div>
                        <div className="invoice-date-row">
                            <span>Data scadenta:</span>
                            <strong>{factura.data_scadenta || '—'}</strong>
                        </div>
                    </div>
                </div>

                <div className="invoice-parties-grid">
                    <InvoiceLocatorCard locator={factura.locator} />
                    <InvoiceLocatarCard locatar={factura.locatar} />
                </div>

                <div className="responsive-table generated-annex-table-wrap">
                    <table className="generated-annex-table">
                        <thead>
                            <tr>
                                <th>Nr. crt</th>
                                <th>Denumire serviciu</th>
                                <th>Cantitate</th>
                                <th>UM</th>
                                <th>Preț unitar</th>
                                <th>Valoare</th>
                                <th>TVA</th>
                            </tr>
                        </thead>
                        <tbody>
                            {factura.linii.map((linie, index) => (
                                <tr key={`${linie.nr_crt}-${linie.denumire}`}>
                                    <td>{linie.nr_crt || index + 1}</td>
                                    <td>{linie.denumire}</td>
                                    <td>{formatDecimal(linie.cantitate)}</td>
                                    <td>{linie.um || '—'}</td>
                                    <td>{formatMoneyValue(linie.pret_unitar)}</td>
                                    <td>{formatMoney(linie.valoare)}</td>
                                    <td>{formatMoney(linie.tva)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <div className="invoice-totals-summary">
                    <div className="invoice-totals-row">
                        <span>Total fără TVA:</span>
                        <strong>{formatAmount(sumar.total_fara_tva)}</strong>
                    </div>
                    <div className="invoice-totals-row">
                        <span>TVA 21%:</span>
                        <strong>{formatAmount(sumar.tva_21)}</strong>
                    </div>
                    <div className="invoice-totals-row">
                        <span>TVA 11%:</span>
                        <strong>{formatAmount(sumar.tva_11)}</strong>
                    </div>
                    <div className="invoice-totals-row invoice-totals-grand-total">
                        <span>Total</span>
                        <strong>{formatAmount(sumar.total)} Lei</strong>
                    </div>
                </div>

                <section className="invoice-payment-footer">
                    <div className="invoice-payment-instructions">
                        <strong>Instructiuni de plata</strong>
                        <p>Banca: {displayValue(factura.locator?.banca)}</p>
                        <p>Cont: {displayValue(factura.locator?.cont_bancar)}</p>
                    </div>

                    <div className="invoice-legal-notes">
                        <p>
                            SCUTIT DE TVA IN BAZA LG 227/2015, ART 292, AL.2, LIT E. CURS BCR: 1 EURO={formatDecimal(factura.curs_eur)} RON.
                            Factura circula fara semnatura si stampila cf Legii 227/2015, ART.39, ALIN.29
                        </p>
                        <p>Factura si conditiile de plata au fost acceptate de catre beneficiar.</p>
                        <p>
                            In cazul depasirii termenelor de plata convenite, penalizarile sunt de 1% pentru fiecare zi de intarziere,
                            aplicate la valoarea facturilor emise, preluate si neachitate.
                        </p>
                        <p>
                            Factura circula fara semnatura si stampila cf. art.V, alin (2) din Ordonanta nr.17/2015 si art. 319 alin (29)
                            din Legea nr. 227/2015 privind Codul fiscal.
                        </p>
                    </div>

                    <p className="invoice-payment-summary">
                        {factura.numar_factura || '—'} {formatAmount(sumar.total)} Lei scadenta la {factura.data_scadenta || '—'}
                    </p>
                </section>

                {factura.anexa_detaliu ? (
                    <section className="invoice-attached-annex">
                        <AnnexDocumentPreview anexa={factura.anexa_detaliu} />
                    </section>
                ) : null}
            </section>
        </AppLayout>
    );
}
