import React from 'react';
import { Link, router } from '@inertiajs/react';
import { Download, HardDriveDownload } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';

function formatDateTime(value) {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString('ro-RO', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function formatSize(bytes) {
    if (!bytes) {
        return '—';
    }

    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function triggerLabel(trigger) {
    if (trigger === 'automatic') {
        return 'Automat zilnic';
    }

    if (trigger === 'manual') {
        return 'Manual';
    }

    return '—';
}

function DownloadLink({ href, icon: Icon, label, size }) {
    if (!href) {
        return <span>—</span>;
    }

    return (
        <a className="backup-download-link" href={href}>
            <Icon size={16} />
            <span className="backup-download-copy">
                <strong>{label}</strong>
                <small>{formatSize(size)}</small>
            </span>
        </a>
    );
}

export default function BackupIndex({
    backups = [],
    retentionDays = 7,
    latestBackupAt = null,
    nextScheduledAt = null,
}) {
    const topbarActions = (
        <button
            type="button"
            className="primary-button"
            onClick={() => router.post('/backup')}
        >
            Backup acum
        </button>
    );

    return (
        <AppLayout
            title="Backup"
            subtitle="Backup zilnic al bazei de date și export CSV imobile, spații, chiriași"
            showGlobalSearch={false}
            topbarActions={topbarActions}
        >
            <section className="table-card module-table-card">
                <div className="section-heading">
                    <h2>Rezumat</h2>
                </div>
                <div className="form-grid backup-summary-grid">
                    <div className="backup-summary-card">
                        <span>Ultimul backup</span>
                        <strong>{formatDateTime(latestBackupAt)}</strong>
                    </div>
                    <div className="backup-summary-card">
                        <span>Păstrate</span>
                        <strong>{retentionDays} zile</strong>
                    </div>
                    <div className="backup-summary-card">
                        <span>Următorul backup automat</span>
                        <strong>{formatDateTime(nextScheduledAt)}</strong>
                    </div>
                </div>
                <p className="backup-help-text">
                    Backup-ul manual de sus se actualizează când apeși <strong>Backup acum</strong>.
                    Mai jos apar backup-urile automate zilnice din ultimele {retentionDays} zile — câte un rând pe zi.
                </p>
            </section>

            <section className="table-card">
                <div className="section-heading">
                    <h2>Istoric backup-uri ({backups.length})</h2>
                </div>
                <div className="responsive-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Data și ora</th>
                                <th>Tip</th>
                                <th>Bază de date</th>
                                <th>CSV imobile</th>
                                <th>CSV spații</th>
                                <th>CSV chiriași</th>
                            </tr>
                        </thead>
                        <tbody>
                            {backups.map((backup) => (
                                <tr key={`${backup.date}-${backup.trigger}-${backup.created_at}`}>
                                    <td>{formatDateTime(backup.created_at || `${backup.date}T03:00:00`)}</td>
                                    <td>{triggerLabel(backup.trigger)}</td>
                                    <td>
                                        <DownloadLink
                                            href={backup.database_url}
                                            icon={HardDriveDownload}
                                            label="Descarcă DB"
                                            size={backup.database_size}
                                        />
                                    </td>
                                    <td>
                                        <DownloadLink
                                            href={backup.imobile_csv_url}
                                            icon={Download}
                                            label="Descarcă imobile"
                                            size={backup.imobile_csv_size}
                                        />
                                    </td>
                                    <td>
                                        <div className="backup-spatii-downloads">
                                            {(backup.spatii_files || []).map((file) => (
                                                <DownloadLink
                                                    key={file.filename}
                                                    href={file.url}
                                                    icon={Download}
                                                    label={file.imobil}
                                                    size={file.size}
                                                />
                                            ))}
                                            {(backup.spatii_files || []).length === 0 ? <span>—</span> : null}
                                        </div>
                                    </td>
                                    <td>
                                        <DownloadLink
                                            href={backup.chiriasi_csv_url}
                                            icon={Download}
                                            label="Descarcă chiriași"
                                            size={backup.chiriasi_csv_size}
                                        />
                                    </td>
                                </tr>
                            ))}
                            {backups.length === 0 ? (
                                <tr>
                                    <td colSpan="6">
                                        Nu există backup-uri încă. Apasă <strong>Backup acum</strong> ca să creezi primul fișier,
                                        apoi îl vei putea descărca direct din tabel.
                                    </td>
                                </tr>
                            ) : null}
                        </tbody>
                    </table>
                </div>
            </section>

            <section className="table-card">
                <div className="section-heading">
                    <h2>Restaurare</h2>
                </div>
                <p className="backup-help-text">
                    Descarcă backup-ul dorit din tabel. Restaurarea completă din aplicație nu este activă încă,
                    ca să evităm suprascrierea accidentală a datelor.
                </p>
                <Link className="secondary-button button-link" href="/setari">Înapoi la setări</Link>
            </section>
        </AppLayout>
    );
}
