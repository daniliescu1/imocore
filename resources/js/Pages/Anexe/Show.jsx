import React from 'react';
import { Link } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import AnnexDocumentPreview from '../../Components/AnnexDocumentPreview';

export default function Show({
    anexa,
    downloadUrl = null,
    previewMode = false,
    returnUrl = null,
    returnLabel = null,
}) {
    const imobilId = anexa?.imobil?.id;
    const defaultBackHref = imobilId ? `/anexe/imobil/${imobilId}` : '/anexe';
    const defaultBackLabel = imobilId ? 'Înapoi la imobil' : 'Înapoi la anexe';
    const backHref = returnUrl || defaultBackHref;
    const backLabel = returnLabel || defaultBackLabel;
    const configurareDenumire = anexa?.configurare?.denumire;
    const subtitle = previewMode
        ? `Estimare pentru anexa alocată${configurareDenumire ? `: ${configurareDenumire}` : ''}`
        : 'Previzualizare anexă generată';

    const topbarActions = (
        <>
            {downloadUrl ? (
                <a className="secondary-button button-link" href={downloadUrl}>Descarcă PDF</a>
            ) : null}
            <Link className="secondary-button button-link" href={backHref}>{backLabel}</Link>
        </>
    );

    return (
        <AppLayout
            title={previewMode ? 'Previzualizare anexă alocată' : `Anexa nr.${anexa.numar}`}
            subtitle={subtitle}
            showGlobalSearch={false}
            topbarActions={topbarActions}
        >
            {previewMode ? (
                <div className="spatiu-context-banner spatiu-context-banner-compact">
                    Previzualizarea folosește datele curente ale spațiului și citirile disponibile. Nu salvează o anexă generată.
                </div>
            ) : null}
            <section className="generated-annex">
                <AnnexDocumentPreview anexa={anexa} />
            </section>
        </AppLayout>
    );
}
