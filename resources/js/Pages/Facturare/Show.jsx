import React from 'react';
import { Link } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { AnnexTableBodyRows } from '../../Components/AnnexTableRows';

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

export default function Show({ factura }) {
    const topbarActions = <Link className="secondary-button button-link" href="/facturare">Înapoi la facturi</Link>;
    const totalValoare = factura.linii.reduce((sum, linie) => sum + Number(linie.valoare || 0), 0);
    const totalTva = factura.linii.reduce((sum, linie) => sum + Number(linie.tva || 0), 0);

    return (
        <AppLayout title={`Factura ${factura.numar_factura}`} subtitle="Previzualizare factură generată" showGlobalSearch={false} topbarActions={topbarActions}>
            <section className="generated-annex">
                <div className="generated-annex-header">
                    <div>
                        <h2>FACTURA {factura.numar_factura}</h2>
                        <p>pentru anexa din luna {factura.luna}</p>
                    </div>
                    <div className="generated-annex-meta">
                        <span>Status</span>
                        <strong>{factura.status}</strong>
                    </div>
                </div>

                <div className="generated-annex-parties">
                    <div>
                        <span>Imobil</span>
                        <strong>{factura.imobil.nume || '—'}</strong>
                        <small>{[factura.imobil.adresa, factura.imobil.localitate].filter(Boolean).join(', ') || '—'}</small>
                    </div>
                    <div>
                        <span>Locator</span>
                        <strong>{factura.spatiu.locator || '—'}</strong>
                    </div>
                    <div>
                        <span>Client / chiriaș</span>
                        <strong>{factura.spatiu.chirias || factura.contract.chirias || '—'}</strong>
                    </div>
                    <div>
                        <span>ID spațiu</span>
                        <strong>{factura.spatiu.identificator || '—'}</strong>
                    </div>
                    <div>
                        <span>Contract</span>
                        <strong>{factura.contract.numar || '—'}</strong>
                    </div>
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
                        <tfoot>
                            <tr>
                                <td colSpan="5">Total factură</td>
                                <td>{formatMoney(totalValoare)}</td>
                                <td>{formatMoney(totalTva)}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <p className="invoice-exchange-rate-note">
                    Curs valutar folosit: 1 EUR = {formatDecimal(factura.curs_eur)} lei, curs vânzare EUR Banca Transilvania.
                </p>

                {factura.anexa_detaliu ? (
                    <section className="invoice-attached-annex">
                        <div className="generated-annex-header compact-annex-header">
                            <div>
                                <h2>ANEXA nr.{factura.anexa_detaliu.numar}</h2>
                                <p>utilități {factura.anexa_detaliu.luna_utilitati}</p>
                            </div>
                        </div>

                        <div className="responsive-table generated-annex-table-wrap">
                            <table className="generated-annex-table">
                                <thead>
                                    <tr>
                                        <th>Nr. crt</th>
                                        <th>Denumire serviciu</th>
                                        <th>Index vechi</th>
                                        <th>Index nou</th>
                                        <th>Facturat</th>
                                        <th>UM</th>
                                        <th>Preț unitar</th>
                                        <th>Valoare</th>
                                        <th>TVA</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <AnnexTableBodyRows linii={factura.anexa_detaliu.linii} formatDecimal={formatDecimal} formatMoney={formatMoneyValue} />
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colSpan="6" />
                                        <td>Total</td>
                                        <td>{formatMoney(factura.anexa_detaliu.subtotal)}</td>
                                        <td>{formatMoney(factura.anexa_detaliu.total_tva)}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </section>
                ) : null}
            </section>
        </AppLayout>
    );
}
