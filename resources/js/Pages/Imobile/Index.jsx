import React, { useEffect, useRef, useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { GripVertical, Trash2 } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';

function reorderImobile(items, draggedId, targetId) {
    if (!draggedId || draggedId === targetId) {
        return items;
    }

    const next = [...items];
    const fromIndex = next.findIndex((imobil) => imobil.id === draggedId);
    const toIndex = next.findIndex((imobil) => imobil.id === targetId);

    if (fromIndex === -1 || toIndex === -1) {
        return items;
    }

    const [moved] = next.splice(fromIndex, 1);
    next.splice(toIndex, 0, moved);

    return next;
}

function ImobilRow({
    imobil,
    onOpen,
    onDelete,
    canReorder,
    isDragging,
    onDragStart,
    onDragOver,
    onDrop,
    onDragEnd,
}) {
    const skipClickRef = useRef(false);
    const imobilHref = `/imobile/${imobil.id}/editare`;

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
            data-prefetch-href={imobilHref}
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
            <td>
                <div className="name-with-action">
                    <Link className="table-name-link" href={imobilHref} onClick={(event) => event.stopPropagation()}>{imobil.nume}</Link>
                </div>
            </td>
            <td>{imobil.adresa}</td>
            <td>{imobil.numere_cf}</td>
            <td>{imobil.spatii_total}</td>
            <td>{imobil.spatii_libere}</td>
            <td>{imobil.spatii_inchiriate}</td>
            <td>{imobil.spatii_comune}</td>
            <td className="table-action-cell">
                <button type="button" className="delete-inline-button" onClick={(event) => onDelete(event, imobil)} aria-label={`Șterge ${imobil.nume}`} title="Șterge imobil">
                    <Trash2 size={14} strokeWidth={2.4} />
                </button>
            </td>
        </tr>
    );
}

export default function Index({ imobile, localitati, filters }) {
    const canReorder = !filters.search && !filters.localitate && imobile.length > 1;
    const [orderedImobile, setOrderedImobile] = useState(imobile);
    const [draggingId, setDraggingId] = useState(null);

    useEffect(() => {
        setOrderedImobile(imobile);
    }, [imobile]);

    function updateFilters(nextFilters) {
        router.get('/imobile', {
            localitate: filters.localitate || '',
            search: filters.search || '',
            ...nextFilters,
        }, { preserveState: true, preserveScroll: true });
    }

    function openImobil(imobil) {
        router.visit(`/imobile/${imobil.id}/editare`);
    }

    function deleteImobil(event, imobil) {
        event.stopPropagation();

        if (!window.confirm(`Ștergi imobilul „${imobil.nume}”?`)) {
            return;
        }

        router.delete(`/imobile/${imobil.id}`, { preserveScroll: true });
    }

    function handleDragStart(event, imobilId) {
        setDraggingId(imobilId);
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', String(imobilId));
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

        const next = reorderImobile(orderedImobile, draggingId, targetId);

        if (next === orderedImobile) {
            setDraggingId(null);
            return;
        }

        setOrderedImobile(next);
        setDraggingId(null);

        router.put('/imobile/reordonare', {
            ordine: next.map((imobil) => imobil.id),
        }, {
            preserveScroll: true,
            preserveState: true,
        });
    }

    function handleDragEnd() {
        setDraggingId(null);
    }

    const topbarActions = (
        <>
            <select className="filter-input topbar-filter" value={filters.localitate || ''} onChange={(event) => updateFilters({ localitate: event.target.value })}>
                <option value="">Localitate: Toate</option>
                {localitati.map((localitate) => <option value={localitate} key={localitate}>{localitate}</option>)}
            </select>
            <input
                className="filter-input topbar-search"
                type="search"
                value={filters.search || ''}
                placeholder="Caută în imobile..."
                onChange={(event) => updateFilters({ search: event.target.value })}
            />
            <Link className="primary-button button-link topbar-primary-button" href="/imobile/adauga">+ Imobil nou</Link>
        </>
    );

    return (
        <AppLayout
            title={`Imobile (${imobile.length})`}
            showGlobalSearch={false}
            topbarActions={topbarActions}
        >
            <section className="table-card module-table-card page-compact-list">
                <div className="responsive-table">
                    <table>
                        <thead>
                            <tr>
                                {canReorder ? <th className="drag-handle-header" aria-label="Reordonează" /> : null}
                                <th>Nume imobil</th>
                                <th>Adresă</th>
                                <th>Nr. CF</th>
                                <th>Spații</th>
                                <th>Libere</th>
                                <th>Închiriate</th>
                                <th>Spații comune</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            {orderedImobile.map((imobil) => (
                                <ImobilRow
                                    key={imobil.id}
                                    imobil={imobil}
                                    onOpen={openImobil}
                                    onDelete={deleteImobil}
                                    canReorder={canReorder}
                                    isDragging={draggingId === imobil.id}
                                    onDragStart={handleDragStart}
                                    onDragOver={handleDragOver}
                                    onDrop={handleDrop}
                                    onDragEnd={handleDragEnd}
                                />
                            ))}
                            {orderedImobile.length === 0 ? (
                                <tr>
                                    <td colSpan={canReorder ? 9 : 8}>Nu există imobile pentru filtrul selectat.</td>
                                </tr>
                            ) : null}
                        </tbody>
                    </table>
                </div>
            </section>
        </AppLayout>
    );
}
