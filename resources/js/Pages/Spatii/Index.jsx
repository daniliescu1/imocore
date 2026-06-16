import React, { useEffect, useRef, useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { GripVertical } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';

const STATUS_LABELS = {
    liber: 'Liber',
    rezervat: 'Rezervat',
    inchiriat: 'Închiriat',
    comun: 'Spațiu comun',
    administrativ: 'Administrativ',
};

const REGIM_INCALZIRE_LABELS = {
    integral: 'Încălzit integral',
    partial: 'Țevi încălzire / parțial',
    neincalzit: 'Neîncălzit',
    manual: 'Excepție',
};

function statusLabel(status) {
    return STATUS_LABELS[status] || status;
}

function showFaraAnexaIndicator(spatiu) {
    return !spatiu.are_anexa_alocata;
}

function showFaraContractIndicator(spatiu) {
    return spatiu.status === 'inchiriat' && !spatiu.are_contract_activ;
}

function showDocumentIndicator(spatiu) {
    return showFaraAnexaIndicator(spatiu) || showFaraContractIndicator(spatiu);
}

function marcajRowClass(spatiu) {
    if (spatiu.de_lamurit) {
        return ' is-de-lamurit';
    }

    if (spatiu.marcat_galben) {
        return ' is-marcat-galben';
    }

    if (spatiu.marcat_verde) {
        return ' is-marcat-verde';
    }

    return '';
}

function buildFilters(filters, overrides = {}) {
    return {
        localitate: filters.localitate || '',
        search: filters.search || '',
        status: filters.status || '',
        regim_incalzire: filters.regim_incalzire || '',
        imobil_id: filters.imobil_id || '',
        ...overrides,
    };
}

function reorderItems(items, draggedId, targetId, idKey = 'id') {
    if (!draggedId || draggedId === targetId) {
        return items;
    }

    const next = [...items];
    const fromIndex = next.findIndex((item) => item[idKey] === draggedId);
    const toIndex = next.findIndex((item) => item[idKey] === targetId);

    if (fromIndex === -1 || toIndex === -1) {
        return items;
    }

    const [moved] = next.splice(fromIndex, 1);
    next.splice(toIndex, 0, moved);

    return next;
}

function reorderSpatii(items, draggedId, targetId) {
    return reorderItems(items, draggedId, targetId);
}

function reorderImobile(items, draggedId, targetId) {
    return reorderItems(items, draggedId, targetId);
}

function ImobilListRow({
    imobil,
    onOpen,
    canReorder,
    isDragging,
    onDragStart,
    onDragOver,
    onDrop,
    onDragEnd,
}) {
    const skipClickRef = useRef(false);

    function handleClick() {
        if (skipClickRef.current) {
            skipClickRef.current = false;
            return;
        }

        onOpen(imobil);
    }

    function handleDragEnd() {
        skipClickRef.current = true;
        onDragEnd();
    }

    return (
        <tr
            className={`clickable-row${isDragging ? ' is-dragging' : ''}${canReorder ? ' draggable-row' : ''}`}
            draggable={canReorder}
            onDragStart={(event) => onDragStart(event, imobil.id)}
            onDragOver={(event) => onDragOver(event, imobil.id)}
            onDrop={(event) => onDrop(event, imobil.id)}
            onDragEnd={handleDragEnd}
            onClick={handleClick}
        >
            {canReorder ? (
                <td className="drag-handle-cell" onClick={(event) => event.stopPropagation()}>
                    <span className="drag-handle" title="Trage pentru a reordona">
                        <GripVertical size={16} strokeWidth={2.2} />
                    </span>
                </td>
            ) : null}
            <td><span className="table-name-link">{imobil.nume}</span></td>
            <td>{imobil.localitate}</td>
            <td>{imobil.spatii_total}</td>
            <td>{imobil.spatii_libere}</td>
            <td>{imobil.spatii_inchiriate}</td>
            <td>{imobil.spatii_comune}</td>
            <td>{imobil.spatii_administrative}</td>
        </tr>
    );
}

function SpatiuRow({
    spatiu,
    onOpen,
    canReorder,
    isDragging,
    onDragStart,
    onDragOver,
    onDrop,
    onDragEnd,
}) {
    const skipClickRef = useRef(false);

    function handleClick() {
        if (skipClickRef.current) {
            skipClickRef.current = false;
            return;
        }

        onOpen(spatiu);
    }

    function handleDragEnd() {
        skipClickRef.current = true;
        onDragEnd();
    }

    return (
        <tr
            className={`clickable-row${marcajRowClass(spatiu)}${isDragging ? ' is-dragging' : ''}${canReorder ? ' draggable-row' : ''}`}
            title={spatiu.de_lamurit && spatiu.de_lamurit_detaliu ? spatiu.de_lamurit_detaliu : undefined}
            draggable={canReorder}
            onDragStart={(event) => onDragStart(event, spatiu.id)}
            onDragOver={(event) => onDragOver(event, spatiu.id)}
            onDrop={(event) => onDrop(event, spatiu.id)}
            onDragEnd={handleDragEnd}
            onClick={handleClick}
        >
            {canReorder ? (
                <td className="drag-handle-cell" onClick={(event) => event.stopPropagation()}>
                    <span className="drag-handle" title="Trage pentru a reordona">
                        <GripVertical size={16} strokeWidth={2.2} />
                    </span>
                </td>
            ) : null}
            <td className="spatiu-indicator-cell" aria-hidden="true">
                {showDocumentIndicator(spatiu) ? (
                    <div className="spatiu-indicator-stripes">
                        {showFaraAnexaIndicator(spatiu) ? (
                            <span className="spatiu-indicator-stripe is-fara-anexa" title="Fără anexă" />
                        ) : null}
                        {showFaraContractIndicator(spatiu) ? (
                            <span className="spatiu-indicator-stripe is-fara-contract" title="Fără contract activ" />
                        ) : null}
                    </div>
                ) : null}
            </td>
            <td className="spatiu-identificator-cell" title={spatiu.identificator}>
                <Link className="table-name-link" href={`/spatii/${spatiu.id}/editare`} onClick={(event) => event.stopPropagation()}>{spatiu.identificator}</Link>
            </td>
            <td>{spatiu.etaj || '—'}</td>
            <td>{spatiu.suprafata_contractuala_mp ? `${spatiu.suprafata_contractuala_mp} mp` : '—'}</td>
            <td>{statusLabel(spatiu.status)}</td>
            <td>
                {spatiu.chirie_lunara_curenta ? (
                    <div className="stacked-cell">
                        <strong>{spatiu.chirie_lunara_curenta} {spatiu.moneda_label || spatiu.moneda}</strong>
                        {spatiu.sursa_chirie_curenta ? <small>{spatiu.sursa_chirie_curenta}</small> : null}
                    </div>
                ) : '—'}
            </td>
            <td>{spatiu.pret_mp_curent ? `${spatiu.pret_mp_curent} ${spatiu.moneda_label || spatiu.moneda}/mp` : '—'}</td>
            <td>{spatiu.locator}</td>
            <td>{spatiu.chirias}</td>
        </tr>
    );
}

export default function Index({ imobile = [], imobil = null, spatii = [], localitati, filters }) {
    const isInsideImobil = Boolean(imobil);
    const canReorderSpatii = isInsideImobil && !filters.search && !filters.status && !filters.regim_incalzire && spatii.length > 1;
    const canReorderImobile = !isInsideImobil && !filters.search && !filters.localitate && imobile.length > 1;
    const [orderedSpatii, setOrderedSpatii] = useState(spatii);
    const [orderedImobile, setOrderedImobile] = useState(imobile);
    const [draggingId, setDraggingId] = useState(null);
    const totalSpatii = isInsideImobil
        ? spatii.length
        : imobile.reduce((sum, row) => sum + row.spatii_total, 0);

    useEffect(() => {
        setOrderedSpatii(spatii);
    }, [spatii]);

    useEffect(() => {
        setOrderedImobile(imobile);
    }, [imobile]);

    function updateFilters(overrides = {}) {
        router.get('/spatii', buildFilters(filters, overrides), { preserveState: true, preserveScroll: true });
    }

    function openImobil(row) {
        router.get('/spatii', buildFilters(filters, { imobil_id: row.id, search: '', status: '', regim_incalzire: '' }), { preserveState: true });
    }

    function openSpatiu(spatiu) {
        router.visit(`/spatii/${spatiu.id}/editare`);
    }

    function handleDragStart(event, spatiuId) {
        setDraggingId(spatiuId);
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', String(spatiuId));
    }

    function handleDragOver(event) {
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
    }

    function handleDrop(event, targetId) {
        event.preventDefault();

        if (!draggingId) {
            return;
        }

        if (isInsideImobil) {
            const next = reorderSpatii(orderedSpatii, draggingId, targetId);

            if (next === orderedSpatii) {
                setDraggingId(null);
                return;
            }

            setOrderedSpatii(next);
            setDraggingId(null);

            router.put('/spatii/reordonare', {
                imobil_id: imobil.id,
                ordine: next.map((spatiu) => spatiu.id),
            }, {
                preserveScroll: true,
                preserveState: true,
            });

            return;
        }

        const next = reorderImobile(orderedImobile, draggingId, targetId);

        if (next === orderedImobile) {
            setDraggingId(null);
            return;
        }

        setOrderedImobile(next);
        setDraggingId(null);

        router.put('/imobile/reordonare', {
            ordine: next.map((row) => row.id),
        }, {
            preserveScroll: true,
            preserveState: true,
        });
    }

    function handleDragEnd() {
        setDraggingId(null);
    }

    const topbarActions = (
        <div className="spaces-topbar-toolbar">
            {isInsideImobil ? (
                <button type="button" className="secondary-button topbar-back-button" onClick={() => updateFilters({ imobil_id: '', search: '', status: '', regim_incalzire: '' })}>
                    ← Înapoi
                </button>
            ) : null}
            <div className="spaces-topbar-filters">
                {isInsideImobil ? (
                    <>
                        <label className="inline-topbar-field spaces-topbar-field">
                            <span>Status</span>
                            <select className="filter-input topbar-filter" value={filters.status || ''} onChange={(event) => updateFilters({ status: event.target.value, imobil_id: imobil.id })}>
                                <option value="">Toate</option>
                                {Object.entries(STATUS_LABELS).map(([value, label]) => (
                                    <option value={value} key={value}>{label}</option>
                                ))}
                            </select>
                        </label>
                        <label className="inline-topbar-field spaces-topbar-field">
                            <span>Încălzire</span>
                            <select className="filter-input topbar-filter" value={filters.regim_incalzire || ''} onChange={(event) => updateFilters({ regim_incalzire: event.target.value, imobil_id: imobil.id })}>
                                <option value="">Toate</option>
                                {Object.entries(REGIM_INCALZIRE_LABELS)
                                    .filter(([value]) => value !== 'manual')
                                    .map(([value, label]) => (
                                    <option value={value} key={value}>{label}</option>
                                ))}
                            </select>
                        </label>
                    </>
                ) : (
                    <label className="inline-topbar-field spaces-topbar-field">
                        <span>Localitate</span>
                        <select className="filter-input topbar-filter" value={filters.localitate || ''} onChange={(event) => updateFilters({ localitate: event.target.value })}>
                            <option value="">Toate</option>
                            {localitati.map((localitate) => <option value={localitate} key={localitate}>{localitate}</option>)}
                        </select>
                    </label>
                )}
                <input
                    className="filter-input topbar-search"
                    type="search"
                    value={filters.search || ''}
                    placeholder={isInsideImobil ? 'Caută spațiu...' : 'Caută imobil...'}
                    onChange={(event) => updateFilters({
                        search: event.target.value,
                        imobil_id: isInsideImobil ? imobil.id : '',
                        status: isInsideImobil ? (filters.status || '') : '',
                        regim_incalzire: isInsideImobil ? (filters.regim_incalzire || '') : '',
                    })}
                />
            </div>
            <Link
                className="primary-button button-link topbar-primary-button"
                href={isInsideImobil ? `/spatii/adauga?imobil_id=${imobil.id}` : '/spatii/adauga'}
            >
                + Spațiu nou
            </Link>
        </div>
    );

    const tableHead = (
        <thead>
            <tr>
                {canReorderSpatii ? <th className="drag-handle-header" aria-label="Reordonează" /> : null}
                <th className="spatiu-indicator-header" aria-hidden="true" />
                <th className="spatiu-identificator-header">Identificat</th>
                <th>Etaj</th>
                <th>Suprafață</th>
                <th>Status</th>
                <th>
                    <span className="stacked-heading">
                        <span>Chirie curentă</span>
                        <small>/ lună</small>
                    </span>
                </th>
                <th>Preț / mp</th>
                <th>Locator</th>
                <th>Chiriaș</th>
            </tr>
        </thead>
    );

    return (
        <AppLayout
            title={isInsideImobil ? `${imobil.nume} (${spatii.length})` : `Spații (${totalSpatii})`}
            subtitle={isInsideImobil ? null : 'Alege un imobil pentru a vedea spațiile'}
            showGlobalSearch={false}
            topbarActions={topbarActions}
        >
            {isInsideImobil ? (
                spatii.length === 0 ? (
                    <section className="table-card module-table-card">
                        <div className="empty-state-card">
                            Nu există spații în acest imobil. Adaugă primul spațiu.
                        </div>
                    </section>
                ) : (
                    <section className="table-card module-table-card">
                        {canReorderSpatii ? (
                            <p className="spaces-reorder-hint">Trage rândurile pentru a reordona spațiile.</p>
                        ) : null}
                        <div className="responsive-table">
                            <table className="spaces-table">
                                {tableHead}
                                <tbody>
                                    {orderedSpatii.map((spatiu) => (
                                        <SpatiuRow
                                            key={spatiu.id}
                                            spatiu={spatiu}
                                            onOpen={openSpatiu}
                                            canReorder={canReorderSpatii}
                                            isDragging={draggingId === spatiu.id}
                                            onDragStart={handleDragStart}
                                            onDragOver={handleDragOver}
                                            onDrop={handleDrop}
                                            onDragEnd={handleDragEnd}
                                        />
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </section>
                )
            ) : (
                <section className="table-card module-table-card">
                    {canReorderImobile ? (
                        <p className="spaces-reorder-hint">Trage rândurile pentru a reordona imobilele.</p>
                    ) : null}
                    <div className="responsive-table">
                        <table className="spaces-table">
                            <thead>
                                <tr>
                                    {canReorderImobile ? <th className="drag-handle-header" aria-label="Reordonează" /> : null}
                                    <th>Imobil</th>
                                    <th>Localitate</th>
                                    <th>Spații</th>
                                    <th>Libere</th>
                                    <th>Închiriate</th>
                                    <th>Spații comune</th>
                                    <th>Administrativ</th>
                                </tr>
                            </thead>
                            <tbody>
                                {orderedImobile.map((row) => (
                                    <ImobilListRow
                                        key={row.id}
                                        imobil={row}
                                        onOpen={openImobil}
                                        canReorder={canReorderImobile}
                                        isDragging={draggingId === row.id}
                                        onDragStart={handleDragStart}
                                        onDragOver={handleDragOver}
                                        onDrop={handleDrop}
                                        onDragEnd={handleDragEnd}
                                    />
                                ))}
                                {orderedImobile.length === 0 ? (
                                    <tr>
                                        <td colSpan={canReorderImobile ? 8 : 7}>Nu există imobile pentru filtrul selectat.</td>
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
