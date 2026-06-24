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

    return 'Automat zilnic';
}

function DownloadLink({ href, icon: Icon, label, size }) {
    if (!href) {
        return null;
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

function BackupDownloads({ backup }) {
    return (
        <>
            <td>
                {backup.database_url ? (
                    <DownloadLink
                        href={backup.database_url}
                        icon={HardDriveDownload}
                        label={backup.database_format === 'sqlite' ? 'Descarcă DB SQLite' : 'Descarcă DB'}
                        size={backup.database_size}
                    />
                ) : (
                    <span>—</span>
                )}
            </td>
            <td>
                <div className="backup-spatii-downloads">
                    <DownloadLink href={backup.imobile_csv_url} icon={Download} label="Imobile" size={backup.imobile_csv_size} />
                    <DownloadLink href={backup.spatii_toate_csv_url} icon={Download} label="Spații toate" size={backup.spatii_toate_csv_size} />
                    <DownloadLink href={backup.chiriasi_csv_url} icon={Download} label="Chiriași" size={backup.chiriasi_csv_size} />
                    <DownloadLink href={backup.indexare_chirii_csv_url} icon={Download} label="Indexare chirii" size={backup.indexare_chirii_csv_size} />
                    <DownloadLink href={backup.persoane_declarate_csv_url} icon={Download} label="Persoane declarate" size={backup.persoane_declarate_csv_size} />
                    <DownloadLink href={backup.contracte_csv_url} icon={Download} label="Contracte" size={backup.contracte_csv_size} />
                    <DownloadLink href={backup.locatori_csv_url} icon={Download} label="Locatari" size={backup.locatori_csv_size} />
                </div>
            </td>
        </>
    );
}

export default function BackupIndex({
    backups = [],
    manualBackup = null,
    retentionDays = 7,
    latestBackupAt = null,
    nextScheduledAt = null,
    allSpatiiDownloadUrl = null,
    indexareChiriiDownloadUrl = null,
    persoaneDeclarateDownloadUrl = null,
}) {
    const topbarActions = (
        <>
            {allSpatiiDownloadUrl ? (
                <a className="secondary-button button-link" href={allSpatiiDownloadUrl}>
                    Descarcă toate spațiile
                </a>
            ) : null}
            {indexareChiriiDownloadUrl ? (
                <a className="secondary-button button-link" href={indexareChiriiDownloadUrl}>
                    Descarcă indexare chirii
                </a>
            ) : null}
            {persoaneDeclarateDownloadUrl ? (
                <a className="secondary-button button-link" href={persoaneDeclarateDownloadUrl}>
                    Descarcă persoane declarate
                </a>
            ) : null}
            <button
                type="button"
                className="primary-button"
                onClick={() => router.post('/backup')}
            >
                Backup acum
            </button>
        </>
    );

    return (
        <AppLayout
            title="Backup"
            subtitle="Backup automat zilnic al bazei de date și export CSV"
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
                    În fiecare noapte la 03:00 (ora României) se creează automat un backup zilnic. Mai jos apar ultimele {retentionDays} zile —
                    câte un rând pe zi. Dacă lipsește backup-ul de azi, pagina îl creează automat la deschidere.
                    Butonul <strong>Backup acum</strong> creează un snapshot manual imediat, afișat separat mai jos.
                </p>
            </section>

            {manualBackup ? (
                <section className="table-card">
                    <div className="section-heading">
                        <h2>Backup manual curent</h2>
                    </div>
                    <div className="responsive-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Data și ora</th>
                                    <th>Tip</th>
                                    <th>Bază de date</th>
                                    <th>Exporturi CSV</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>{formatDateTime(manualBackup.created_at)}</td>
                                    <td>{triggerLabel(manualBackup.trigger)}</td>
                                    <BackupDownloads backup={manualBackup} />
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p className="backup-help-text">
                        Acesta este ultimul snapshot creat cu <strong>Backup acum</strong>. Următorul click înlocuiește fișierele de mai sus.
                    </p>
                </section>
            ) : null}

            <section className="table-card">
                <div className="section-heading">
                    <h2>Backup-uri automate ({backups.length})</h2>
                </div>
                <div className="responsive-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Data și ora</th>
                                <th>Tip</th>
                                <th>Bază de date</th>
                                <th>Exporturi CSV</th>
                            </tr>
                        </thead>
                        <tbody>
                            {backups.map((backup) => (
                                <tr key={`${backup.date}-${backup.created_at}`}>
                                    <td>{formatDateTime(backup.created_at || `${backup.date}T03:00:00`)}</td>
                                    <td>{triggerLabel(backup.trigger)}</td>
                                    <BackupDownloads backup={backup} />
                                </tr>
                            ))}
                            {backups.length === 0 ? (
                                <tr>
                                    <td colSpan="4">
                                        Nu există încă backup-uri automate. Reîncarcă pagina ca să generezi backup-ul de azi,
                                        sau așteaptă rularea programată de la 03:00.
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
