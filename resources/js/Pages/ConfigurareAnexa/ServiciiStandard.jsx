import React, { useEffect, useState } from 'react';
import { Link, router, useForm } from '@inertiajs/react';
import { Pencil, Trash2 } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import ConfigurareAnexaTabs from '../../Components/ConfigurareAnexaTabs';
import { formatDecimal } from '../../lib/formatDecimal';

function hasPret(value) {
    return value !== null && value !== undefined && String(value).trim() !== '';
}

function PreturiGrid({ valori, tvaOptiuni = [], umOptiuni = [] }) {
    const [preturi, setPreturi] = useState(() => (
        Object.fromEntries(valori.map((item) => [item.id, {
            pret: formatDecimal(item.coeficient),
            moneda: item.moneda || 'RON',
            tva: item.tva || '',
            um: item.um || '',
        }]))
    ));
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        setPreturi(Object.fromEntries(valori.map((item) => [item.id, {
            pret: formatDecimal(item.coeficient),
            moneda: item.moneda || 'RON',
            tva: item.tva || '',
            um: item.um || '',
        }])));
    }, [valori]);

    function updatePret(id, value) {
        setPreturi((current) => ({
            ...current,
            [id]: { ...current[id], pret: value.replace(',', '.') },
        }));
    }

    function updateTva(id, value) {
        setPreturi((current) => ({
            ...current,
            [id]: { ...current[id], tva: value },
        }));
    }

    function updateUm(id, value) {
        setPreturi((current) => ({
            ...current,
            [id]: { ...current[id], um: value },
        }));
    }

    function updateMoneda(id, value) {
        setPreturi((current) => ({
            ...current,
            [id]: { ...current[id], moneda: value },
        }));
    }

    function blurPret(id) {
        setPreturi((current) => ({
            ...current,
            [id]: { ...current[id], pret: formatDecimal(current[id]?.pret) },
        }));
    }

    function submit(event) {
        event.preventDefault();
        router.put('/configurare-anexa/servicii-standard/pret/bulk', {
            preturi: valori.map((item) => ({
                id: item.id,
                coeficient: formatDecimal(preturi[item.id]?.pret) || '',
                moneda: preturi[item.id]?.moneda || 'RON',
                tva: preturi[item.id]?.tva || '',
                um: preturi[item.id]?.um || '',
            })),
        }, {
            preserveScroll: true,
            onStart: () => setSaving(true),
            onFinish: () => setSaving(false),
        });
    }

    return (
        <form className="standard-values-grid-wrap" onSubmit={submit}>
            <div className="standard-values-grid">
                {valori.map((item) => {
                    const row = preturi[item.id] || { pret: '', moneda: 'RON', tva: '', um: '' };
                    const lipsestePret = !hasPret(row.pret);
                    const lipsesteTva = !hasPret(row.tva);
                    const lipsesteUm = !hasPret(row.um);

                    return (
                        <div key={item.id} className="standard-values-grid-item">
                            <div className="standard-values-grid-content">
                                <strong className="standard-values-grid-label">{item.label}</strong>
                                <div className="preturi-grid-field">
                                    <input
                                        type="text"
                                        inputMode="decimal"
                                        className="preturi-grid-input"
                                        value={row.pret}
                                        placeholder="—"
                                        aria-label={`Preț ${item.label}`}
                                        onChange={(event) => updatePret(item.id, event.target.value)}
                                        onBlur={() => blurPret(item.id)}
                                    />
                                    <span className="preturi-grid-unit">
                                        <select
                                            className="preturi-grid-moneda"
                                            value={row.moneda || 'RON'}
                                            aria-label={`Monedă ${item.label}`}
                                            onChange={(event) => updateMoneda(item.id, event.target.value)}
                                        >
                                            <option value="RON">lei</option>
                                            <option value="EUR">EUR</option>
                                        </select>
                                    </span>
                                </div>
                                <div className="preturi-grid-meta-row">
                                    <select
                                        className="preturi-grid-um"
                                        value={row.um}
                                        aria-label={`UM ${item.label}`}
                                        onChange={(event) => updateUm(item.id, event.target.value)}
                                    >
                                        <option value="">UM</option>
                                        {umOptiuni.map((opt) => (
                                            <option value={opt.valoare} key={opt.valoare}>{opt.label}</option>
                                        ))}
                                        {row.um && !umOptiuni.some((opt) => opt.valoare === row.um) ? (
                                            <option value={row.um}>{row.um}</option>
                                        ) : null}
                                    </select>
                                    <select
                                        className="preturi-grid-tva"
                                        value={row.tva}
                                        aria-label={`TVA ${item.label}`}
                                        onChange={(event) => updateTva(item.id, event.target.value)}
                                    >
                                        <option value="">TVA</option>
                                        {tvaOptiuni.map((opt) => (
                                            <option value={opt.valoare} key={opt.valoare}>{opt.label}</option>
                                        ))}
                                    </select>
                                </div>
                                {lipsestePret ? (
                                    <small className="preturi-missing">fără preț setat</small>
                                ) : null}
                                {!lipsestePret && lipsesteUm ? (
                                    <small className="preturi-missing">fără UM setată</small>
                                ) : null}
                                {!lipsestePret && !lipsesteUm && lipsesteTva ? (
                                    <small className="preturi-missing">fără TVA setat</small>
                                ) : null}
                            </div>
                        </div>
                    );
                })}
            </div>
            {valori.length > 0 ? (
                <div className="preturi-grid-actions">
                    <button className="primary-button" type="submit" disabled={saving}>
                        Salvează prețurile
                    </button>
                </div>
            ) : null}
        </form>
    );
}

function EditValueForm({ tip, item, onCancel }) {
    const isPret = tip === 'pret';
    const { data, setData, put, processing } = useForm({
        valoare: item.valoare,
        coeficient: formatDecimal(item.coeficient),
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
        <form className="standard-inline-form standard-values-edit-form" onSubmit={submit}>
            {isPret ? (
                <strong>{item.label || item.valoare}</strong>
            ) : (
                <input type="text" value={data.valoare} onChange={(event) => setData('valoare', event.target.value)} />
            )}
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
            {isPret ? (
                <input
                    type="number"
                    min="0"
                    step="0.0001"
                    value={data.coeficient}
                    onChange={(event) => setData('coeficient', event.target.value)}
                    placeholder="Preț unitar"
                />
            ) : null}
            <button className="primary-button" type="submit" disabled={processing}>Salvează</button>
            <button className="secondary-button" type="button" onClick={onCancel}>Anulează</button>
        </form>
    );
}

function ValoriStandardGrid({ tipActiv, valori, editingId, setEditingId, onDelete }) {
    return (
        <div className="standard-values-grid">
            {valori.map((item) => (
                editingId === item.id ? (
                    <div key={item.id} className="standard-values-grid-item standard-values-grid-item-editing">
                        <EditValueForm tip={tipActiv} item={item} onCancel={() => setEditingId(null)} />
                    </div>
                ) : (
                    <div key={item.id} className="standard-values-grid-item">
                        <div className="standard-values-grid-content">
                            <strong className="standard-values-grid-label">{item.label}</strong>
                            {tipActiv === 'tip_calcul' && item.valoare === 'mp_coeficient' && item.coeficient ? (
                                <small className="standard-value-meta">coeficient {formatDecimal(item.coeficient)}</small>
                            ) : null}
                        </div>
                        <div className="standard-values-grid-actions">
                            <button className="annex-order-button" type="button" onClick={() => setEditingId(item.id)} aria-label="Editează">
                                <Pencil size={14} strokeWidth={2.4} />
                            </button>
                            <button className="delete-inline-button" type="button" onClick={() => onDelete(item)} aria-label="Șterge">
                                <Trash2 size={14} strokeWidth={2.4} />
                            </button>
                        </div>
                    </div>
                )
            ))}
        </div>
    );
}

export default function ServiciiStandard({ tipActiv, tipuri = [], valori = [], tvaOptiuni = [], umOptiuni = [], cursImplicit = 5, cursSursa = '' }) {
    const [editingId, setEditingId] = useState(null);
    const activeTip = tipuri.find((tip) => tip.key === tipActiv);
    const isPret = tipActiv === 'pret';
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
            subtitle={isPret ? 'Preț unitar, UM și TVA standard pentru fiecare denumire de serviciu' : 'Valorile apar ca dropdown la configurarea liniilor de anexă'}
            showGlobalSearch={false}
            topbarActions={topbarActions}
        >
            <section className="table-card module-table-card standard-values-card">
                <ConfigurareAnexaTabs tipActiv={tipActiv} cursImplicit={cursImplicit} cursSursa={cursSursa} />

                {isPret ? (
                    <>
                        <p className="standard-values-note">Lista urmează denumirile din tab-ul Denumire serviciu. Setează prețul, moneda, UM și TVA pentru fiecare serviciu. Prețurile în EUR se convertesc automat la lei pe anexă folosind cursul valutar.</p>
                        {valori.length === 0 ? (
                            <p className="preturi-grid-empty">Nu există denumiri de serviciu. Adaugă servicii în tab-ul Denumire serviciu.</p>
                        ) : (
                            <PreturiGrid valori={valori} tvaOptiuni={tvaOptiuni} umOptiuni={umOptiuni} />
                        )}
                    </>
                ) : (
                    <>
                        <form className="standard-inline-form standard-add-row" onSubmit={addValue}>
                            <input
                                type="text"
                                placeholder={`Adaugă ${activeTip?.label?.toLowerCase() || 'valoare'}`}
                                value={data.valoare}
                                onChange={(event) => setData('valoare', event.target.value)}
                            />
                            <button className="primary-button" type="submit" disabled={processing}>+ Adaugă</button>
                        </form>

                        {valori.length === 0 ? (
                            <p className="preturi-grid-empty">Nu există valori. Adaugă una mai sus.</p>
                        ) : (
                            <ValoriStandardGrid
                                tipActiv={tipActiv}
                                valori={valori}
                                editingId={editingId}
                                setEditingId={setEditingId}
                                onDelete={deleteValue}
                            />
                        )}
                    </>
                )}
            </section>
        </AppLayout>
    );
}
