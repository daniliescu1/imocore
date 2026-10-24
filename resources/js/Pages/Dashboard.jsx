import React from 'react';
import { usePage } from '@inertiajs/react';
import { Building2, DoorOpen, UsersRound } from 'lucide-react';
import AppLayout from '../Layouts/AppLayout';
import { navigateTo } from '../navigation';

function StatCard({ tone, icon: Icon, label, value, href, children }) {
    const card = (
        <section className={`stat-card stat-card-${tone}`}>
            <div className="stat-icon"><Icon size={36} /></div>
            <div className="stat-copy">
                <div className="stat-label">{label}</div>
                <div className="stat-value">{value}</div>
                {children ? <div className="stat-meta">{children}</div> : null}
            </div>
        </section>
    );

    if (!href) {
        return card;
    }

    return (
        <a
            href={href}
            className="stat-card-link"
            onClick={(event) => {
                event.preventDefault();
                navigateTo(href);
            }}
        >
            {card}
        </a>
    );
}

function TableCard({ title, action = 'Vezi toate', actionHref = null, children }) {
    return (
        <section className="table-card">
            <div className="section-heading">
                <h2>{title}</h2>
                {actionHref ? (
                    <a
                        href={actionHref}
                        className="section-heading-action"
                        onClick={(event) => {
                            event.preventDefault();
                            navigateTo(actionHref);
                        }}
                    >
                        {action} <span>→</span>
                    </a>
                ) : (
                    <span>{action}</span>
                )}
            </div>
            {children}
        </section>
    );
}

function formatNumber(value) {
    return Number(value || 0).toLocaleString('ro-RO', { maximumFractionDigits: 2 });
}

function CurrencyTotals({ sumaEur, mpEur, sumaLei, mpLei }) {
    return (
        <>
            <div className="stat-meta-block">
                <div><strong>Total sumă:</strong> {formatNumber(sumaEur)} EUR</div>
                <div><strong>Total suprafață:</strong> {formatNumber(mpEur)} mp</div>
            </div>
            <div className="stat-meta-block">
                <div><strong>Total sumă:</strong> {formatNumber(sumaLei)} Lei</div>
                <div><strong>Total suprafață:</strong> {formatNumber(mpLei)} mp</div>
            </div>
        </>
    );
}

export default function Dashboard({ today, stats, freeSpaces, overdue, endings }) {
    const { isOwner = false } = usePage().props;

    return (
        <AppLayout title="Panou principal" subtitle={today}>
            <div className="stats-grid">
                <StatCard tone="total" icon={Building2} label="Spații totale" value={stats.total} />
                <StatCard tone="free" icon={DoorOpen} label="Spații libere" value={stats.libere} href="/spatii?status=liber">
                    <CurrencyTotals
                        sumaEur={stats.libere_suma_eur}
                        mpEur={stats.libere_mp_eur}
                        sumaLei={stats.libere_suma_lei}
                        mpLei={stats.libere_mp_lei}
                    />
                </StatCard>
                <StatCard tone="rented" icon={UsersRound} label="Spații închiriate" value={stats.inchiriate}>
                    {isOwner ? (
                        <CurrencyTotals
                            sumaEur={stats.inchiriate_suma_eur}
                            mpEur={stats.inchiriate_mp_eur}
                            sumaLei={stats.inchiriate_suma_lei}
                            mpLei={stats.inchiriate_mp_lei}
                        />
                    ) : null}
                </StatCard>
            </div>

            <TableCard title="Spații libere" actionHref="/spatii?status=liber">
                <div className="responsive-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Spațiu</th>
                                <th>Imobil</th>
                                <th>Suprafață</th>
                                <th>Preț / lună</th>
                                <th>Data de la care este liber</th>
                            </tr>
                        </thead>
                        <tbody>
                            {freeSpaces.map((row) => (
                                <tr key={row.spatiu}>
                                    <td>{row.spatiu}</td>
                                    <td>{row.imobil}</td>
                                    <td>{row.suprafata}</td>
                                    <td>{row.pret}</td>
                                    <td>{row.data_liber}</td>
                                </tr>
                            ))}
                            {freeSpaces.length === 0 ? (
                                <tr>
                                    <td colSpan="5">Nu există spații libere introduse.</td>
                                </tr>
                            ) : null}
                        </tbody>
                    </table>
                </div>
            </TableCard>

            <div className="dashboard-bottom-grid">
                <TableCard title="Neîncasate 30 zile">
                    <div className="responsive-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Spațiu</th>
                                    <th>Chiriaș</th>
                                    <th>Suma</th>
                                    <th>Zile întârziere</th>
                                </tr>
                            </thead>
                            <tbody>
                                {overdue.map((row) => (
                                    <tr key={row[0]}>
                                        <td>{row[0]}</td>
                                        <td>{row[1]}</td>
                                        <td>{row[2]}</td>
                                        <td className="danger-text">{row[3]}</td>
                                    </tr>
                                ))}
                                {overdue.length === 0 ? (
                                    <tr>
                                        <td colSpan="4">Nu există date de neîncasate încă.</td>
                                    </tr>
                                ) : null}
                            </tbody>
                        </table>
                    </div>
                </TableCard>

                <TableCard title="Eliberare 6 luni">
                    <div className="responsive-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Spațiu</th>
                                    <th>Imobil</th>
                                    <th>mp</th>
                                    <th>Dată eliberare</th>
                                </tr>
                            </thead>
                            <tbody>
                                {endings.map((row) => (
                                    <tr key={row[0]}>
                                        {row.map((cell) => <td key={cell}>{cell}</td>)}
                                    </tr>
                                ))}
                                {endings.length === 0 ? (
                                    <tr>
                                        <td colSpan="4">Nu există contracte cu eliberare în următoarele 6 luni.</td>
                                    </tr>
                                ) : null}
                            </tbody>
                        </table>
                    </div>
                </TableCard>
            </div>
        </AppLayout>
    );
}
