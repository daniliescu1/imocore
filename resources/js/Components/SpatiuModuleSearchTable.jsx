import React from 'react';
import { Link } from '@inertiajs/react';

const STATUS_LABELS = {
    liber: 'Liber',
    rezervat: 'Rezervat',
    inchiriat: 'Închiriat',
    comun: 'Spațiu comun',
    administrativ: 'Administrativ',
};

function statusLabel(status) {
    return STATUS_LABELS[status] || status;
}

function showFaraAnexaIndicator(spatiu) {
    return spatiu.necesita_anexa && !spatiu.are_anexa_alocata;
}

function showFaraContractIndicator(spatiu) {
    return spatiu.status === 'inchiriat' && !spatiu.are_contract_inregistrat;
}

function showDocumentIndicator(spatiu) {
    return showFaraAnexaIndicator(spatiu) || showFaraContractIndicator(spatiu);
}

function marcajRowClass(spatiu) {
    if (spatiu.de_lamurit) {
        return ' is-de-lamurit';
    }

    if (spatiu.marcat_galben) {
        return ' is-marcat-galben';
    }

    if (spatiu.marcat_verde) {
        return ' is-marcat-verde';
    }

    return '';
}

function SpatiuRow({ spatiu, onOpen, showImobil = false, href = null }) {
    const rowHref = href || `#spatiu-${spatiu.id}`;

    return (
        <tr
            className={`clickable-row${marcajRowClass(spatiu)}`}
            data-prefetch-href={rowHref}
            data-prefetch-on-intent="true"
            onClick={() => onOpen(spatiu)}
        >
            <td className="spatiu-indicator-cell" aria-hidden="true">
                {showDocumentIndicator(spatiu) ? (
                    <div className="spatiu-indicator-stripes">
                        {showFaraAnexaIndicator(spatiu) ? (
                            <span className="spatiu-indicator-stripe is-fara-anexa" title="Fără anexă" />
                        ) : null}
                        {showFaraContractIndicator(spatiu) ? (
                            <span className="spatiu-indicator-stripe is-fara-contract" title="Fără contract înregistrat" />
                        ) : null}
                    </div>
                ) : null}
            </td>
            <td className="spatiu-identificator-cell" title={spatiu.identificator}>
                {href ? (
                    <Link className="table-name-link" href={href} onClick={(event) => event.stopPropagation()}>{spatiu.identificator}</Link>
                ) : (
                    <strong>{spatiu.identificator}</strong>
                )}
            </td>
            {showImobil ? <td>{spatiu.imobil}</td> : null}
            {showImobil ? <td>{spatiu.localitate}</td> : null}
            <td>{spatiu.etaj || '—'}</td>
            <td>{spatiu.suprafata_contractuala_mp ? `${spatiu.suprafata_contractuala_mp} mp` : '—'}</td>
            <td>{statusLabel(spatiu.status)}</td>
            <td>
                {spatiu.chirie_lunara_curenta ? (
                    <div className="stacked-cell">
                        <strong>{spatiu.chirie_lunara_curenta} {spatiu.moneda_label || spatiu.moneda}</strong>
                        {spatiu.sursa_chirie_curenta ? <small>{spatiu.sursa_chirie_curenta}</small> : null}
                    </div>
                ) : '—'}
            </td>
            <td>{spatiu.pret_mp_curent ? `${spatiu.pret_mp_curent} ${spatiu.moneda_label || spatiu.moneda}/mp` : '—'}</td>
            <td>{spatiu.locator}</td>
            <td>{spatiu.chirias}</td>
        </tr>
    );
}

export default function SpatiuModuleSearchTable({ spatii = [], onOpen, showImobilColumn = false, getRowHref = null }) {
    return (
        <section className="table-card module-table-card page-compact-list">
            {spatii.length === 0 ? (
                <div className="empty-state-card">Nu există spații care să corespundă căutării.</div>
            ) : (
                <div className="responsive-table">
                    <table className="spaces-table">
                        <thead>
                            <tr>
                                <th className="spatiu-indicator-header" aria-hidden="true" />
                                <th className="spatiu-identificator-header">Identificat</th>
                                {showImobilColumn ? <th>Imobil</th> : null}
                                {showImobilColumn ? <th>Localitate</th> : null}
                                <th>Etaj</th>
                                <th>Suprafață</th>
                                <th>Status</th>
                                <th>
                                    <span className="stacked-heading">
                                        <span>Chirie curentă</span>
                                        <small>/ lună</small>
                                    </span>
                                </th>
                                <th>Preț / mp</th>
                                <th>Locator</th>
                                <th>Chiriaș</th>
                            </tr>
                        </thead>
                        <tbody>
                            {spatii.map((spatiu) => (
                                <SpatiuRow
                                    key={spatiu.id}
                                    spatiu={spatiu}
                                    onOpen={onOpen}
                                    showImobil={showImobilColumn}
                                    href={getRowHref ? getRowHref(spatiu) : null}
                                />
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </section>
    );
}
