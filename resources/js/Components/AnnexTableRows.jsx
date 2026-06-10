import React from 'react';

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

export function AnnexTableBodyRows({ linii, formatDecimal, formatMoney = null }) {
    return linii.map((linie, index) => {
        if (linie.tip_linie === 'header') {
            return <AnnexSectionHeaderRow key={`header-${index}`} />;
        }

        return (
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
}
