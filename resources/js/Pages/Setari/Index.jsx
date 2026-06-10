import React from 'react';
import { Link } from '@inertiajs/react';
import { HardDriveUpload } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';

export default function Index({ users, auditLogs }) {
    return (
        <AppLayout title="Setări" subtitle="Roluri, permisiuni și loguri" showGlobalSearch={false}>
            <section className="table-card module-table-card">
                <div className="section-heading"><h2>Administrare</h2></div>
                <div className="form-grid backup-summary-grid">
                    <Link className="backup-summary-card settings-link-card" href="/backup">
                        <HardDriveUpload size={22} />
                        <div>
                            <strong>Backup</strong>
                            <span>Backup zilnic bază de date și export CSV spații</span>
                        </div>
                    </Link>
                </div>
            </section>

            <section className="table-card module-table-card">
                <div className="section-heading"><h2>Roluri utilizatori</h2></div>
                <div className="responsive-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Nume</th>
                                <th>Email</th>
                                <th>Rol</th>
                            </tr>
                        </thead>
                        <tbody>
                            {users.map((user) => (
                                <tr key={user.id}>
                                    <td>{user.name}</td>
                                    <td>{user.email}</td>
                                    <td>{user.role}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </section>

            <section className="table-card">
                <div className="section-heading"><h2>Ultimele loguri</h2></div>
                <div className="responsive-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Utilizator</th>
                                <th>Acțiune</th>
                                <th>Câmp</th>
                                <th>Vechi</th>
                                <th>Nou</th>
                                <th>Motiv</th>
                            </tr>
                        </thead>
                        <tbody>
                            {auditLogs.map((log) => (
                                <tr key={log.id}>
                                    <td>{log.created_at}</td>
                                    <td>{log.user_name}</td>
                                    <td>{log.actiune}</td>
                                    <td>{log.camp}</td>
                                    <td>{log.valoare_veche}</td>
                                    <td>{log.valoare_noua}</td>
                                    <td>{log.motiv}</td>
                                </tr>
                            ))}
                            {auditLogs.length === 0 ? (
                                <tr>
                                    <td colSpan="7">Nu există loguri încă.</td>
                                </tr>
                            ) : null}
                        </tbody>
                    </table>
                </div>
            </section>
        </AppLayout>
    );
}
