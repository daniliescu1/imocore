import React from 'react';
import { Link } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import AnnexDocumentPreview from '../../Components/AnnexDocumentPreview';

export default function Show({ anexa, downloadUrl }) {
    const topbarActions = (
        <>
            <a className="secondary-button button-link" href={downloadUrl}>Descarcă PDF</a>
            <Link className="secondary-button button-link" href="/anexe">Înapoi la anexe</Link>
        </>
    );

    return (
        <AppLayout title={`Anexa nr.${anexa.numar}`} subtitle="Previzualizare anexă generată" showGlobalSearch={false} topbarActions={topbarActions}>
            <section className="generated-annex">
                <AnnexDocumentPreview anexa={anexa} />
            </section>
        </AppLayout>
    );
}
