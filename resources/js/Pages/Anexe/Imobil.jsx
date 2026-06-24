import React from 'react';
import { Link, router, useForm } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import { useDebouncedSearch } from '../../lib/useDebouncedSearch';

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

function buildFilters(filters, overrides = {}) {
    return {
        search: filters.search || '',
        ...overrides,
    };
}

export default function Imobil({ imobil, anexe = [], lunaImplicita = '', contracteEligibile = 0, filters = {} }) {
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

    function updateFilters(overrides = {}) {
        router.get(`/anexe/imobil/${imobil.id}`, buildFilters(filters, overrides), {
            preserveState: true,
            preserveScroll: true,
        });
    }

    const [searchDraft, handleSearchChange] = useDebouncedSearch(filters.search, (value) => {
        updateFilters({ search: value });
    });

    function generate(event) {
        event.preventDefault();
        router.post('/anexe/generare', {
            luna: lunaPentruBackend,
            imobil_id: imobil.id,
        }, { preserveScroll: true });
    }

    function deleteAnexa(event, anexa) {
        event.stopPropagation();
        if (!window.confirm(`Ștergi anexa pentru contractul ${anexa.contract}?`)) return;

        router.delete(`/anexe/${anexa.id}`, { preserveScroll: true });
    }

    function deleteAllAnexe() {
        if (anexe.length === 0) return;

        const confirmMessage = hasActiveFilters
            ? `Ștergi cele ${anexe.length} anexe afișate (filtrate)?`
            : `Ștergi toate cele ${anexe.length} anexe generate pentru acest imobil?`;

        if (!window.confirm(confirmMessage)) return;

        router.delete(`/anexe/imobil/${imobil.id}`, {
            data: buildFilters(filters),
            preserveScroll: true,
        });
    }

    const hasActiveFilters = Boolean(filters.search);

    const topbarActions = (
        <>
            <input
                className="filter-input topbar-search"
                type="search"
                value={searchDraft}
                placeholder="Caută contract, spațiu, chiriaș..."
                onChange={(event) => handleSearchChange(event.target.value)}
            />
            <form className="topbar-actions" onSubmit={generate}>
                <select className="filter-input topbar-filter" value={data.luna} onChange={(event) => setData('luna', event.target.value)}>
                    {luni.map(([value, label]) => <option value={value} key={value}>{value} - {label}</option>)}
                </select>
                <input className="filter-input topbar-filter" type="number" min="2000" max="2100" value={data.an} onChange={(event) => setData('an', event.target.value)} />
                <button className="primary-button topbar-primary-button" type="submit" disabled={processing}>
                    {processing ? 'Se generează...' : 'Generează pentru imobil'}
                </button>
            </form>
        </>
    );

    const emptyMessage = hasActiveFilters
        ? 'Nu există anexe care să corespundă căutării.'
        : 'Nu există anexe generate pentru acest imobil. Alege luna și apasă Generează pentru imobil.';

    return (
        <AppLayout title={`Anexe ${imobil.nume}`} showGlobalSearch={false} topbarActions={topbarActions}>
            <section className="facturare-imobil-toolbar">
                <div className="facturare-imobil-toolbar-main">
                    <strong className="facturare-imobil-count">{contracteEligibile} spații eligibile</strong>
                    <p className="facturare-imobil-hint">
                        Anexe pentru spațiile din <strong>{imobil.nume} ({imobil.localitate})</strong> cu contract activ.
                        {contracteEligibile === 0 ? ' Adaugă contract activ și anexă pe spațiu.' : ''}
                    </p>
                </div>
                <div className="facturare-imobil-toolbar-meta">
                    <span className="facturare-imobil-curs">
                        Utilități {lunaUtilitati} {anUtilitati} · facturare {lunaSelectata} {data.an}
                    </span>
                    <Link className="secondary-button button-link facturare-imobil-back" href="/anexe">Înapoi la imobile</Link>
                </div>
            </section>

            <section className="table-card module-table-card anexe-list-table-card">
                <div className="responsive-table">
                    <table className="anexe-list-table">
                        <thead>
                            <tr>
                                <th className="anexe-contract-header">Contract</th>
                                <th className="anexe-spatiu-header">Spațiu</th>
                                <th className="anexe-chirias-header">Chiriaș</th>
                                <th className="anexe-luna-header">Luna</th>
                                <th className="anexe-total-header">Total</th>
                                <th className="anexe-status-header">Status</th>
                                <th className="table-action-cell">
                                    {anexe.length > 0 ? (
                                        <button className="delete-all-header-button" type="button" onClick={deleteAllAnexe} aria-label="Șterge toate anexele">
                                            Șterge toate
                                        </button>
                                    ) : null}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {anexe.map((anexa) => {
                                const anexaHref = `/anexe/${anexa.id}`;

                                return (
                                    <tr key={anexa.id} className="clickable-row" data-prefetch-href={anexaHref} onClick={() => router.visit(anexaHref)}>
                                        <td className="anexe-contract-cell">{anexa.contract}</td>
                                        <td className="anexe-spatiu-cell">
                                            <span className="anexe-spatiu-cell-text" title={anexa.spatiu}>{anexa.spatiu}</span>
                                        </td>
                                        <td className="anexe-chirias-cell">
                                            <span className="anexe-chirias-cell-text" title={anexa.chirias}>{anexa.chirias}</span>
                                        </td>
                                        <td className="anexe-luna-cell">{anexa.luna}</td>
                                        <td className="anexe-total-cell">{formatMoney(anexa.total)}</td>
                                        <td className="anexe-status-cell">{anexa.status}</td>
                                        <td className="table-action-cell">
                                            <button className="delete-inline-button" type="button" onClick={(event) => deleteAnexa(event, anexa)} aria-label="Șterge anexa">
                                                <Trash2 size={15} strokeWidth={2.4} />
                                            </button>
                                        </td>
                                    </tr>
                                );
                            })}
                            {anexe.length === 0 ? (
                                <tr>
                                    <td colSpan="7">{emptyMessage}</td>
                                </tr>
                            ) : null}
                        </tbody>
                    </table>
                </div>
            </section>
        </AppLayout>
    );
}
