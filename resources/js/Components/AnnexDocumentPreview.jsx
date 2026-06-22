import React from 'react';
import { AnnexTableBodyRows } from './AnnexTableRows';

function formatDecimal(value) {
    if (value === null || value === undefined || value === '') return '—';
    return String(Number(value)).replace(/(\.\d*?)0+$/, '$1').replace(/\.$/, '');
}

function lunaText(luna) {
    const [year, month] = String(luna || '').split('-');
    if (!year || !month) return '—';

    const luni = {
        '01': 'Ianuarie',
        '02': 'Februarie',
        '03': 'Martie',
        '04': 'Aprilie',
        '05': 'Mai',
        '06': 'Iunie',
        '07': 'Iulie',
        '08': 'August',
        '09': 'Septembrie',
        '10': 'Octombrie',
        '11': 'Noiembrie',
        '12': 'Decembrie',
    };

    return `${luni[month] || ''} ${year}`.trim();
}

export default function AnnexDocumentPreview({ anexa, compactHeader = false }) {
    if (!anexa) {
        return null;
    }

    return (
        <>
            <div className={`generated-annex-header generated-annex-header-centered-meta${compactHeader ? ' compact-annex-header' : ''}`}>
                <div>
                    <h2>ANEXA nr.{anexa.numar}</h2>
                    <p>din luna {lunaText(anexa.luna)}</p>
                </div>
                <div className="generated-annex-meta">
                    <span>Perioada citire contoare</span>
                    <strong>{anexa.perioada_citire || '—'}</strong>
                </div>
                <div className="generated-annex-header-balance" aria-hidden="true" />
            </div>

            <div className="generated-annex-parties">
                <div>
                    <span>Imobil</span>
                    <strong>{anexa.imobil?.nume || '—'}</strong>
                    <small>{[anexa.imobil?.adresa, anexa.imobil?.localitate].filter(Boolean).join(', ') || '—'}</small>
                </div>
                <div>
                    <span>Nume locator</span>
                    <strong>{anexa.spatiu?.locator || '—'}</strong>
                </div>
                <div>
                    <span>Nume locatar</span>
                    <strong>{anexa.spatiu?.chirias || anexa.contract?.chirias || '—'}</strong>
                </div>
                <div>
                    <span>ID spațiu</span>
                    <strong>{anexa.spatiu?.identificator || '—'}</strong>
                </div>
                <div>
                    <span>Contract</span>
                    <strong>{anexa.contract?.numar || '—'}</strong>
                </div>
                <div>
                    <span>Email facturare</span>
                    <strong>{anexa.contract?.email_facturare || '—'}</strong>
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
                        <AnnexTableBodyRows linii={anexa.linii} formatDecimal={formatDecimal} />
                    </tbody>
                </table>
            </div>
        </>
    );
}
