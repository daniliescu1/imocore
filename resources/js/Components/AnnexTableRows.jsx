import React from 'react';
import { formatAnnexMoney } from '../lib/formatDecimal';

export function AnnexSectionHeaderRow() {
    return (
        <tr className="generated-annex-section-header">
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
    );
}

function zeroSectionTotal() {
    return { valoare: 0, tva: 0, count: 0 };
}

function sectionTotalRow(total, key) {
    if (!total.count) {
        return null;
    }

    return (
        <tr className="generated-annex-section-total" key={key}>
            <td colSpan="6" />
            <td>Total</td>
            <td>{formatAnnexMoney(total.valoare)}</td>
            <td>{formatAnnexMoney(total.tva)}</td>
        </tr>
    );
}

export function AnnexTableBodyRows({ linii, formatDecimal, formatMoney = null }) {
    const rows = [];
    let sectionTotal = zeroSectionTotal();
    let sectionIndex = 1;

    linii.forEach((linie, index) => {
        if (linie.tip_linie === 'header') {
            rows.push(sectionTotalRow(sectionTotal, `section-total-${sectionIndex}`));
            rows.push(<AnnexSectionHeaderRow key={`header-${index}`} />);
            sectionTotal = zeroSectionTotal();
            sectionIndex += 1;
            return;
        }

        sectionTotal.valoare += Number(linie.valoare || 0);
        sectionTotal.tva += Number(linie.tva_21 || 0);
        sectionTotal.count += 1;

        rows.push(
            <tr key={`${linie.nr_crt}-${linie.denumire}-${index}`}>
                <td>{linie.nr_crt || index + 1}</td>
                <td>{linie.denumire}</td>
                <td>{formatDecimal(linie.index_vechi)}</td>
                <td>{formatDecimal(linie.index_nou)}</td>
                <td>{formatDecimal(linie.cantitate)}</td>
                <td>{linie.um || '—'}</td>
                <td>{formatDecimal(linie.pret_unitar)}</td>
                <td>{formatMoney ? formatMoney(linie.valoare) : formatDecimal(linie.valoare)}</td>
                <td>{formatMoney ? formatMoney(linie.tva_21) : formatDecimal(linie.tva_21)}</td>
            </tr>
        );
    });

    rows.push(sectionTotalRow(sectionTotal, `section-total-${sectionIndex}`));

    return rows;
}
