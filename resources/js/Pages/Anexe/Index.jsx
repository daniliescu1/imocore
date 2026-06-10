import React from 'react';
import { router, useForm } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';

function formatMoney(value) {
    if (value === null || value === undefined || value === '') return '—';
    return `${String(Number(value)).replace(/(\.\d*?)0+$/, '$1').replace(/\.$/, '')} lei`;
}

const luni = [
    ['01', 'Ianuarie'],
    ['02', 'Februarie'],
    ['03', 'Martie'],
    ['04', 'Aprilie'],
    ['05', 'Mai'],
    ['06', 'Iunie'],
    ['07', 'Iulie'],
    ['08', 'August'],
    ['09', 'Septembrie'],
    ['10', 'Octombrie'],
    ['11', 'Noiembrie'],
    ['12', 'Decembrie'],
];

export default function Index({ anexe = [], rezumatImobile = [], lunaImplicita = '', contracteEligibile = 0 }) {
    const [anImplicit, lunaImplicit] = String(lunaImplicita || '').split('-');
    const { data, setData, processing } = useForm({
        luna: lunaImplicit || String(new Date().getMonth() + 1).padStart(2, '0'),
        an: anImplicit || String(new Date().getFullYear()),
    });
    const lunaSelectata = luni.find(([value]) => value === data.luna)?.[1] || '—';
    const lunaUtilitatiValue = data.luna === '01' ? '12' : String(Number(data.luna) - 1).padStart(2, '0');
    const anUtilitati = data.luna === '01' ? String(Number(data.an) - 1) : data.an;
    const lunaUtilitati = luni.find(([value]) => value === lunaUtilitatiValue)?.[1] || '—';
    const lunaPentruBackend = `${data.an}-${data.luna}`;

    function generate(event) {
        event.preventDefault();
        router.post('/anexe/generare', { luna: lunaPentruBackend }, { preserveScroll: true });
    }

    function deleteAnexa(event, anexa) {
        event.stopPropagation();
        if (!window.confirm(`Ștergi anexa pentru contractul ${anexa.contract}?`)) return;

        router.delete(`/anexe/${anexa.id}`, { preserveScroll: true });
    }

    const topbarActions = (
        <form className="topbar-actions" onSubmit={generate}>
            <select className="filter-input topbar-filter" value={data.luna} onChange={(event) => setData('luna', event.target.value)}>
                {luni.map(([value, label]) => <option value={value} key={value}>{value} - {label}</option>)}
            </select>
            <input className="filter-input topbar-filter" type="number" min="2000" max="2100" value={data.an} onChange={(event) => setData('an', event.target.value)} />
            <button className="primary-button topbar-primary-button" type="submit" disabled={processing}>
                {processing ? 'Se generează...' : 'Generează anexele'}
            </button>
        </form>
    );

    return (
        <AppLayout title={`Generare anexe (${anexe.length})`} subtitle="Generează și previzualizează anexele pentru spațiile cu contract activ" showGlobalSearch={false} topbarActions={topbarActions}>
            <section className="readonly-info-card annex-generation-status">
                <h2>{contracteEligibile} spații eligibile pentru generare</h2>
                <p>
                    Se generează anexele pentru contractele active ale spațiilor care au selectată o configurare de anexă.
                    {contracteEligibile === 0 ? ' Adaugă un contract activ și selectează anexa pe spațiu ca să poți genera.' : ''}
                </p>
                <strong>Se vor genera anexele pentru utilități {lunaUtilitati} {anUtilitati}, aferente facturării din {lunaSelectata} {data.an}.</strong>
            </section>

            <section className="table-card module-table-card">
                <div className="responsive-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Imobil</th>
                                <th>Spații închiriate</th>
                                <th>Anexe generate</th>
                                <th>Total generat</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rezumatImobile.map((imobil) => (
                                <tr key={imobil.id}>
                                    <td>{imobil.nume} ({imobil.localitate})</td>
                                    <td>{imobil.spatii_inchiriate}</td>
                                    <td>{imobil.anexe_generate}</td>
                                    <td>{formatMoney(imobil.total_generat)}</td>
                                </tr>
                            ))}
                            {rezumatImobile.length === 0 ? (
                                <tr>
                                    <td colSpan="4">Nu există imobile introduse.</td>
                                </tr>
                            ) : null}
                        </tbody>
                    </table>
                </div>
            </section>

            <section className="table-card module-table-card">
                <div className="responsive-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Contract</th>
                                <th>Imobil</th>
                                <th>Spațiu</th>
                                <th>Chiriaș</th>
                                <th>Luna</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th />
                            </tr>
                        </thead>
                        <tbody>
                            {anexe.map((anexa) => (
                                <tr key={anexa.id} className="clickable-row" onClick={() => router.visit(`/anexe/${anexa.id}`)}>
                                    <td>{anexa.contract}</td>
                                    <td>{anexa.imobil}</td>
                                    <td>{anexa.spatiu}</td>
                                    <td>{anexa.chirias}</td>
                                    <td>{anexa.luna}</td>
                                    <td>{formatMoney(anexa.total)}</td>
                                    <td>{anexa.status}</td>
                                    <td className="table-action-cell">
                                        <button className="delete-inline-button" type="button" onClick={(event) => deleteAnexa(event, anexa)} aria-label="Șterge anexa">
                                            <Trash2 size={15} strokeWidth={2.4} />
                                        </button>
                                    </td>
                                </tr>
                            ))}
                            {anexe.length === 0 ? (
                                <tr>
                                    <td colSpan="8">Nu există anexe generate. Alege luna și apasă Generează anexele.</td>
                                </tr>
                            ) : null}
                        </tbody>
                    </table>
                </div>
            </section>
        </AppLayout>
    );
}
