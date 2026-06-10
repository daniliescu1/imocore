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

function reorderSpatii(items, draggedId, targetId) {
    if (!draggedId || draggedId === targetId) {
        return items;
    }

    const next = [...items];
    const fromIndex = next.findIndex((spatiu) => spatiu.id === draggedId);
    const toIndex = next.findIndex((spatiu) => spatiu.id === targetId);

    if (fromIndex === -1 || toIndex === -1) {
        return items;
    }

    const [moved] = next.splice(fromIndex, 1);
    next.splice(toIndex, 0, moved);

    return next;
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
            <td
                className={`spatiu-anexa-indicator-cell${spatiu.are_anexa_alocata ? '' : ' is-fara-anexa'}`}
                aria-hidden="true"
            />
            <td><Link className="table-name-link" href={`/spatii/${spatiu.id}/editare`} onClick={(event) => event.stopPropagation()}>{spatiu.identificator}</Link></td>
            <td>{spatiu.suprafata_contractuala_mp ? `${spatiu.suprafata_contractuala_mp} mp` : '—'}</td>
            <td>{statusLabel(spatiu.status)}</td>
            <td>
                {spatiu.chirie_lunara_curenta ? (
                    <div className="stacked-cell">
                        <strong>{spatiu.chirie_lunara_curenta} {spatiu.moneda}</strong>
                        {spatiu.sursa_chirie_curenta ? <small>{spatiu.sursa_chirie_curenta}</small> : null}
                    </div>
                ) : '—'}
            </td>
            <td>{spatiu.pret_mp_curent ? `${spatiu.pret_mp_curent} ${spatiu.moneda}/mp` : '—'}</td>
            <td>{spatiu.locator}</td>
            <td>{spatiu.chirias}</td>
        </tr>
    );
}

export default function Index({ imobile = [], imobil = null, spatii = [], localitati, filters }) {
    const isInsideImobil = Boolean(imobil);
    const canReorder = isInsideImobil && !filters.search && !filters.status && !filters.regim_incalzire && spatii.length > 1;
    const [orderedSpatii, setOrderedSpatii] = useState(spatii);
    const [draggingId, setDraggingId] = useState(null);
    const totalSpatii = isInsideImobil
        ? spatii.length
        : imobile.reduce((sum, row) => sum + row.spatii_total, 0);

    useEffect(() => {
        setOrderedSpatii(spatii);
    }, [spatii]);

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
                {canReorder ? <th className="drag-handle-header" aria-label="Reordonează" /> : null}
                <th className="spatiu-anexa-indicator-header" aria-hidden="true" />
                <th>Identificat</th>
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
                        {canReorder ? (
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
                                            canReorder={canReorder}
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
                    <div className="responsive-table">
                        <table className="spaces-table">
                            <thead>
                                <tr>
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
                                {imobile.map((row) => (
                                    <tr key={row.id} className="clickable-row" onClick={() => openImobil(row)}>
                                        <td><span className="table-name-link">{row.nume}</span></td>
                                        <td>{row.localitate}</td>
                                        <td>{row.spatii_total}</td>
                                        <td>{row.spatii_libere}</td>
                                        <td>{row.spatii_inchiriate}</td>
                                        <td>{row.spatii_comune}</td>
                                        <td>{row.spatii_administrative}</td>
                                    </tr>
                                ))}
                                {imobile.length === 0 ? (
                                    <tr>
                                        <td colSpan="7">Nu există imobile pentru filtrul selectat.</td>
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
