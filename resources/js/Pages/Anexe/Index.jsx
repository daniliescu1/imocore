import React from 'react';
import { Deferred, router, useForm } from '@inertiajs/react';
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

function RezumatImobileTable({ rezumatImobile = [] }) {
    return (
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
                            <tr
                                key={imobil.id}
                                className="clickable-row"
                                data-prefetch-href={`/anexe/imobil/${imobil.id}`}
                                onClick={() => router.visit(`/anexe/imobil/${imobil.id}`)}
                            >
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
    );
}

export default function Index({ rezumatImobile = [], lunaImplicita = '', contracteEligibile = 0 }) {
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
        router.post('/anexe/generare', {
            luna: lunaPentruBackend,
        }, { preserveScroll: true });
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
        <AppLayout title="Generare anexe" subtitle="Generează și previzualizează anexele pentru spațiile cu contract activ" showGlobalSearch={false} topbarActions={topbarActions}>
            <section className="readonly-info-card annex-generation-status">
                <h2>{contracteEligibile} spații eligibile pentru generare</h2>
                <p>
                    Se generează anexele pentru contractele active ale spațiilor care au selectată o configurare de anexă.
                    {contracteEligibile === 0 ? ' Adaugă un contract activ și selectează anexa pe spațiu ca să poți genera.' : ''}
                </p>
                <strong>Se vor genera anexele pentru utilități {lunaUtilitati} {anUtilitati}, aferente facturării din {lunaSelectata} {data.an}.</strong>
            </section>

            <Deferred data="rezumatImobile" fallback={<section className="readonly-info-card">Se încarcă rezumatul pe imobile...</section>}>
                <RezumatImobileTable rezumatImobile={rezumatImobile} />
            </Deferred>
        </AppLayout>
    );
}
