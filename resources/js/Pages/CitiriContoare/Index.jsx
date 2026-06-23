import React from 'react';
import { router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import ModuleSpatiuSearchToolbar from '../../Components/ModuleSpatiuSearchToolbar';
import SpatiuModuleSearchTable from '../../Components/SpatiuModuleSearchTable';

function formatLunaLabel(luna) {
    if (!luna) return '—';
    const [an, lunaNumar] = String(luna).split('-');

    return `${lunaNumar}.${an}`;
}

export default function Index({
    imobile = [],
    spatii = [],
    localitati = [],
    filters = {},
}) {
    const isRootSpatiiSearchView = Boolean(filters.search_spatii);
    const showImobilColumn = isRootSpatiiSearchView && new Set(spatii.map((spatiu) => spatiu.imobil_id)).size > 1;

    function openSpatiu(spatiu) {
        const params = new URLSearchParams({ mode: 'new' });

        if (filters.search) {
            params.set('search', filters.search);
        }

        router.visit(`/citiri-contoare/imobil/${spatiu.imobil_id}?${params.toString()}`);
    }

    function openImobil(imobil) {
        router.visit(`/citiri-contoare/imobil/${imobil.id}?mode=new`);
    }

    const topbarActions = (
        <ModuleSpatiuSearchToolbar
            filters={filters}
            localitati={localitati}
            routePath="/citiri-contoare"
            showBack={isRootSpatiiSearchView}
        />
    );

    return (
        <AppLayout
            title={isRootSpatiiSearchView ? `Rezultate căutare (${spatii.length})` : 'Citiri contoare'}
            subtitle="Alege imobilul pentru a introduce indexurile contoarelor din anexele alocate spațiilor."
            showGlobalSearch={false}
            topbarActions={topbarActions}
        >
            {isRootSpatiiSearchView ? (
                <SpatiuModuleSearchTable
                    spatii={spatii}
                    onOpen={openSpatiu}
                    showImobilColumn={showImobilColumn}
                    getRowHref={(spatiu) => {
                        const params = new URLSearchParams({ mode: 'new' });

                        if (filters.search) {
                            params.set('search', filters.search);
                        }

                        return `/citiri-contoare/imobil/${spatiu.imobil_id}?${params.toString()}`;
                    }}
                />
            ) : (
                <section className="table-card module-table-card">
                    <div className="responsive-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Imobil</th>
                                    <th>Contoare de citit</th>
                                    <th>Ultima lună citită</th>
                                </tr>
                            </thead>
                            <tbody>
                                {imobile.map((imobil) => {
                                    const rowHref = `/citiri-contoare/imobil/${imobil.id}?mode=new`;

                                    return (
                                        <tr
                                            key={imobil.id}
                                            className="clickable-row"
                                            data-prefetch-href={rowHref}
                                            onClick={() => openImobil(imobil)}
                                        >
                                            <td><strong>{imobil.nume}</strong> ({imobil.localitate})</td>
                                            <td>{imobil.contoare_count || '—'}</td>
                                            <td>{formatLunaLabel(imobil.ultima_luna_citita)}</td>
                                        </tr>
                                    );
                                })}
                                {imobile.length === 0 ? (
                                    <tr>
                                        <td colSpan="3">
                                            {filters.search
                                                ? 'Nu există imobile care să corespundă căutării.'
                                                : 'Nu există imobile introduse.'}
                                        </td>
                                    </tr>
                                ) : null}
                            </tbody>
                        </table>
                    </div>
                </section>
            )}
        </AppLayout>
    );
}
