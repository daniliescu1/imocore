import React, { useState } from 'react';
import { Link, router, useForm } from '@inertiajs/react';
import { Pencil, Trash2 } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';

function StandardTabs({ tipuri, tipActiv }) {
    return (
        <nav className="standard-tabs">
            {tipuri.map((tip) => (
                <Link
                    key={tip.key}
                    href={tip.href}
                    className={`standard-tab${tip.key === tipActiv ? ' is-active' : ''}`}
                >
                    {tip.label}
                </Link>
            ))}
        </nav>
    );
}

function EditRow({ tip, item, onCancel }) {
    const { data, setData, put, processing } = useForm({
        valoare: item.valoare,
        coeficient: item.coeficient || '',
    });
    const showCoeficient = tip === 'tip_calcul' && data.valoare === 'mp_coeficient';

    function submit(event) {
        event.preventDefault();
        put(`/configurare-anexa/servicii-standard/${tip}/${item.id}`, {
            preserveScroll: true,
            onSuccess: onCancel,
        });
    }

    return (
        <tr>
            <td colSpan="2">
                <form className="standard-inline-form" onSubmit={submit}>
                    <input type="text" value={data.valoare} onChange={(event) => setData('valoare', event.target.value)} />
                    {showCoeficient ? (
                        <input
                            type="number"
                            min="0"
                            step="0.0001"
                            value={data.coeficient}
                            onChange={(event) => setData('coeficient', event.target.value)}
                            placeholder="Coeficient, ex. 0.09"
                        />
                    ) : null}
                    <button className="primary-button" type="submit" disabled={processing}>Salvează</button>
                    <button className="secondary-button" type="button" onClick={onCancel}>Anulează</button>
                </form>
            </td>
        </tr>
    );
}

export default function ServiciiStandard({ tipActiv, tipuri = [], valori = [] }) {
    const [editingId, setEditingId] = useState(null);
    const activeTip = tipuri.find((tip) => tip.key === tipActiv);
    const { data, setData, post, processing } = useForm({
        valoare: '',
    });

    function addValue(event) {
        event.preventDefault();
        post(`/configurare-anexa/servicii-standard/${tipActiv}`, {
            preserveScroll: true,
            onSuccess: () => setData('valoare', ''),
        });
    }

    function deleteValue(item) {
        if (!window.confirm(`Ștergi valoarea "${item.label}"?`)) return;

        router.delete(`/configurare-anexa/servicii-standard/${tipActiv}/${item.id}`, { preserveScroll: true });
    }

    const topbarActions = (
        <Link className="secondary-button button-link" href="/configurare-anexa">Înapoi la anexe</Link>
    );

    return (
        <AppLayout
            title={`Valori standard — ${activeTip?.label || ''}`}
            subtitle="Valorile apar ca dropdown la configurarea liniilor de anexă"
            showGlobalSearch={false}
            topbarActions={topbarActions}
        >
            <section className="table-card module-table-card standard-values-card">
                <StandardTabs tipuri={tipuri} tipActiv={tipActiv} />

                <form className="standard-inline-form standard-add-row" onSubmit={addValue}>
                    <input
                        type="text"
                        placeholder={`Adaugă ${activeTip?.label?.toLowerCase() || 'valoare'}`}
                        value={data.valoare}
                        onChange={(event) => setData('valoare', event.target.value)}
                    />
                    <button className="primary-button" type="submit" disabled={processing}>+ Adaugă</button>
                </form>

                <div className="responsive-table">
                    <table>
                        <tbody>
                            {valori.map((item) => (
                                editingId === item.id ? (
                                    <EditRow key={item.id} tip={tipActiv} item={item} onCancel={() => setEditingId(null)} />
                                ) : (
                                    <tr key={item.id}>
                                        <td>
                                            {item.label}
                                            {tipActiv === 'tip_calcul' && item.valoare === 'mp_coeficient' && item.coeficient ? (
                                                <small className="standard-value-meta">coeficient {item.coeficient}</small>
                                            ) : null}
                                        </td>
                                        <td className="table-action-cell">
                                            <button className="annex-order-button" type="button" onClick={() => setEditingId(item.id)} aria-label="Editează">
                                                <Pencil size={14} strokeWidth={2.4} />
                                            </button>
                                            <button className="delete-inline-button" type="button" onClick={() => deleteValue(item)} aria-label="Șterge">
                                                <Trash2 size={14} strokeWidth={2.4} />
                                            </button>
                                        </td>
                                    </tr>
                                )
                            ))}
                            {valori.length === 0 ? (
                                <tr>
                                    <td colSpan="2">Nu există valori. Adaugă una mai sus.</td>
                                </tr>
                            ) : null}
                        </tbody>
                    </table>
                </div>
            </section>
        </AppLayout>
    );
}
