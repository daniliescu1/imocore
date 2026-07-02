import React, { useEffect, useMemo, useState } from 'react';
import { Link, router, useForm } from '@inertiajs/react';
import { Pencil, Trash2 } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import ConfigurareAnexaTabs from '../../Components/ConfigurareAnexaTabs';
import { formatDecimal } from '../../lib/formatDecimal';

const GUNOI_MENAJER_DENUMIRE = 'Servicii Gunoi Menajer';

function isGunoiMenajer(denumire) {
    return String(denumire || '').trim().toLowerCase() === GUNOI_MENAJER_DENUMIRE.toLowerCase();
}

function hasPret(value) {
    return value !== null && value !== undefined && String(value).trim() !== '';
}

function groupPreturi(valori) {
    const groups = new Map();

    valori.forEach((item) => {
        const key = item.valoare;
        if (!groups.has(key)) {
            groups.set(key, { denumire: key, variants: [] });
        }
        groups.get(key).variants.push(item);
    });

    return Array.from(groups.values());
}

function PreturiGrid({ valori, tvaOptiuni = [], umOptiuni = [] }) {
    const groups = useMemo(() => groupPreturi(valori), [valori]);
    const [preturi, setPreturi] = useState(() => (
        Object.fromEntries(valori.map((item) => [item.id, {
            label: item.label || 'Standard',
            pret: formatDecimal(item.coeficient),
            pret_persoana_suplimentara: formatDecimal(item.pret_persoana_suplimentara),
            coeficient_cantitate: item.coeficient_cantitate ?? '100',
            moneda: item.moneda || 'RON',
            tva: item.tva || '',
            um: item.um || '',
        }]))
    ));
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        setPreturi(Object.fromEntries(valori.map((item) => [item.id, {
            label: item.label || 'Standard',
            pret: formatDecimal(item.coeficient),
            pret_persoana_suplimentara: formatDecimal(item.pret_persoana_suplimentara),
            coeficient_cantitate: item.coeficient_cantitate ?? '100',
            moneda: item.moneda || 'RON',
            tva: item.tva || '',
            um: item.um || '',
        }])));
    }, [valori]);

    function updateField(id, field, value) {
        setPreturi((current) => ({
            ...current,
            [id]: { ...current[id], [field]: value },
        }));
    }

    function updatePret(id, value) {
        updateField(id, 'pret', value.replace(',', '.'));
    }

    function blurPret(id, field = 'pret') {
        setPreturi((current) => ({
            ...current,
            [id]: { ...current[id], [field]: formatDecimal(current[id]?.[field]) },
        }));
    }

    function submit(event) {
        event.preventDefault();
        router.put('/configurare-anexa/servicii-standard/pret/bulk', {
            preturi: valori.map((item) => ({
                id: item.id,
                label: preturi[item.id]?.label || item.label || 'Standard',
                coeficient: formatDecimal(preturi[item.id]?.pret) || '',
                pret_persoana_suplimentara: isGunoiMenajer(item.valoare)
                    ? formatDecimal(preturi[item.id]?.pret_persoana_suplimentara) || ''
                    : '',
                coeficient_cantitate: formatDecimal(preturi[item.id]?.coeficient_cantitate) || '100',
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

    function addVariant(group) {
        const first = group.variants[0];
        const row = first ? preturi[first.id] : null;
        const label = window.prompt('Numele variantei de preț (ex. Repartizare 20%)', 'Variantă nouă');

        if (!label || !String(label).trim()) {
            return;
        }

        router.post('/configurare-anexa/servicii-standard/pret/variant', {
            valoare: group.denumire,
            label: String(label).trim(),
            coeficient: row?.pret || '',
            coeficient_cantitate: '100',
            moneda: row?.moneda || 'RON',
            tva: row?.tva || '',
            um: row?.um || '',
        }, { preserveScroll: true });
    }

    function deleteVariant(item, group) {
        if (group.variants.length <= 1) {
            window.alert('Nu poți șterge singura variantă de preț a serviciului.');
            return;
        }

        if (!window.confirm(`Ștergi varianta «${item.label}»?`)) {
            return;
        }

        router.delete(`/configurare-anexa/servicii-standard/pret/${item.id}`, { preserveScroll: true });
    }

    return (
        <form className="standard-values-grid-wrap" onSubmit={submit}>
            <div className="preturi-variant-groups">
                {groups.map((group) => (
                    <section className="preturi-variant-group" key={group.denumire}>
                        <div className="preturi-variant-group-head">
                            <h3>{group.denumire}</h3>
                            <button type="button" className="secondary-button" onClick={() => addVariant(group)}>
                                + Preț nou
                            </button>
                        </div>
                        <div className="preturi-variant-rows">
                            {group.variants.map((item) => {
                                const isGunoi = isGunoiMenajer(group.denumire);
                                const row = preturi[item.id] || {
                                    label: item.label,
                                    pret: '',
                                    pret_persoana_suplimentara: '',
                                    coeficient_cantitate: '100',
                                    moneda: 'RON',
                                    tva: '',
                                    um: '',
                                };
                                const lipsestePret = !hasPret(row.pret);
                                const lipsesteTva = !hasPret(row.tva);
                                const lipsesteUm = !hasPret(row.um);

                                return (
                                    <div className={`preturi-variant-row${isGunoi ? ' preturi-variant-row-gunoi' : ''}`} key={item.id}>
                                        <input
                                            type="text"
                                            className="preturi-variant-label"
                                            value={row.label}
                                            aria-label={`Variantă ${group.denumire}`}
                                            onChange={(event) => updateField(item.id, 'label', event.target.value)}
                                        />
                                        <div className="preturi-grid-field">
                                            <input
                                                type="text"
                                                inputMode="decimal"
                                                className="preturi-grid-input"
                                                value={row.pret}
                                                placeholder="—"
                                                aria-label={isGunoi ? `Preț prima persoană ${row.label}` : `Preț ${row.label}`}
                                                onChange={(event) => updatePret(item.id, event.target.value)}
                                                onBlur={() => blurPret(item.id)}
                                            />
                                            <span className="preturi-grid-unit">
                                                <select
                                                    className="preturi-grid-moneda"
                                                    value={row.moneda || 'RON'}
                                                    aria-label={`Monedă ${row.label}`}
                                                    onChange={(event) => updateField(item.id, 'moneda', event.target.value)}
                                                >
                                                    <option value="RON">lei</option>
                                                    <option value="EUR">EUR</option>
                                                </select>
                                            </span>
                                        </div>
                                        {isGunoi ? (
                                            <div className="preturi-grid-field preturi-gunoi-sup-field">
                                                <input
                                                    type="text"
                                                    inputMode="decimal"
                                                    className="preturi-grid-input"
                                                    value={row.pret_persoana_suplimentara ?? ''}
                                                    placeholder="—"
                                                    aria-label={`Preț persoană suplimentară ${row.label}`}
                                                    onChange={(event) => updateField(item.id, 'pret_persoana_suplimentara', event.target.value.replace(',', '.'))}
                                                    onBlur={() => blurPret(item.id, 'pret_persoana_suplimentara')}
                                                />
                                                <span className="preturi-grid-unit">lei / pers. supl.</span>
                                            </div>
                                        ) : null}
                                        <div className="preturi-grid-field preturi-coef-field">
                                            <input
                                                type="text"
                                                inputMode="decimal"
                                                className="preturi-grid-input"
                                                value={row.coeficient_cantitate}
                                                aria-label={`Coeficient cantitate ${row.label}`}
                                                onChange={(event) => updateField(item.id, 'coeficient_cantitate', event.target.value.replace(',', '.'))}
                                            />
                                            <span className="preturi-grid-unit">% cant.</span>
                                        </div>
                                        <div className="preturi-grid-meta-row">
                                            <select
                                                className="preturi-grid-um"
                                                value={row.um}
                                                aria-label={`UM ${row.label}`}
                                                onChange={(event) => updateField(item.id, 'um', event.target.value)}
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
                                                aria-label={`TVA ${row.label}`}
                                                onChange={(event) => updateField(item.id, 'tva', event.target.value)}
                                            >
                                                <option value="">TVA</option>
                                                {tvaOptiuni.map((opt) => (
                                                    <option value={opt.valoare} key={opt.valoare}>{opt.label}</option>
                                                ))}
                                            </select>
                                        </div>
                                        <div className="preturi-variant-row-actions">
                                            {lipsestePret ? <small className="preturi-missing">fără preț</small> : null}
                                            {!lipsestePret && lipsesteUm ? <small className="preturi-missing">fără UM</small> : null}
                                            {!lipsestePret && !lipsesteUm && lipsesteTva ? <small className="preturi-missing">fără TVA</small> : null}
                                            <button
                                                type="button"
                                                className="delete-inline-button"
                                                aria-label={`Șterge ${row.label}`}
                                                onClick={() => deleteVariant(item, group)}
                                            >
                                                <Trash2 size={14} strokeWidth={2.4} />
                                            </button>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </section>
                ))}
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
            subtitle={isPret ? 'Preț unitar, coeficient % cantitate, UM și TVA pentru fiecare serviciu' : 'Valorile apar ca dropdown la configurarea liniilor de anexă'}
            showGlobalSearch={false}
            topbarActions={topbarActions}
        >
            <section className="table-card module-table-card standard-values-card">
                <ConfigurareAnexaTabs tipActiv={tipActiv} cursImplicit={cursImplicit} cursSursa={cursSursa} />

                {isPret ? (
                    <>
                        <p className="standard-values-note">Poți adăuga mai multe variante de preț pe aceeași denumire de serviciu. Coeficientul % cantitate se aplică la generarea anexei (ex. 20 = 20% din consumul contorului). La «Servicii Gunoi Menajer» poți seta opțional preț pentru persoana suplimentară; dacă îl lași gol, se facturează același preț pentru fiecare persoană. Alege varianta în editarea anexei.</p>
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
