import React, { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';

function emptyScadere() {
    return { spatiu_id: '', configurare_anexa_linie_id: '' };
}

function formatDecimal(value) {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    return String(value).replace(/(\.\d*?)0+$/, '$1').replace(/\.$/, '');
}

function consumPerSpatiuCalculat(contor, data, spatiiOptions) {
    const consumBrut = Number(contor.ultima_citire?.consum);

    if (!Number.isFinite(consumBrut)) {
        return null;
    }

    const alocariCount = data.foloseste_scaderi
        ? data.alocari.length
        : spatiiOptions.length;

    if (alocariCount === 0) {
        return null;
    }

    let scaderiTotal = 0;

    if (data.foloseste_scaderi) {
        data.scaderi
            .filter((item) => item.spatiu_id && item.configurare_anexa_linie_id)
            .forEach((item) => {
                const key = `${item.spatiu_id}-${item.configurare_anexa_linie_id}`;
                scaderiTotal += Number(contor.citiriScadere?.[key] ?? 0);
            });
    }

    const rest = Math.max(0, consumBrut - scaderiTotal);

    return Math.round((rest / alocariCount) * 1000) / 1000;
}

function UltimaCitireCard({ contor, imobilId }) {
    const citire = contor.ultima_citire;
    const isPausal = Boolean(contor.is_pausal || citire?.is_pausal);

    return (
        <section className="readonly-info-card contor-config-citire-card">
            <div className="contor-config-citire-header">
                <div>
                    <h2>Ultima citire {isPausal ? 'pausală' : 'contor'}</h2>
                    <p>Citirea pentru „{contor.denumire}” se introduce din pagina Citiri contoare, la nivel de imobil.</p>
                </div>
                <Link
                    className="secondary-button"
                    href={`/citiri-contoare/imobil/${imobilId}${citire?.luna ? `?luna=${citire.luna}&mode=history` : '?mode=new'}`}
                >
                    Deschide citiri contoare
                </Link>
            </div>

            {citire ? (
                <div className="contor-config-citire-grid">
                    <div className="contor-config-citire-item">
                        <span>Lună</span>
                        <strong>{citire.luna_label}</strong>
                    </div>
                    <div className="contor-config-citire-item">
                        <span>Data citire</span>
                        <strong>{citire.data_citire || '—'}</strong>
                    </div>
                    {!isPausal ? (
                        <>
                            <div className="contor-config-citire-item">
                                <span>Index vechi</span>
                                <strong>{formatDecimal(citire.index_vechi)}</strong>
                            </div>
                            <div className="contor-config-citire-item">
                                <span>Index nou</span>
                                <strong>{formatDecimal(citire.index_nou)}</strong>
                            </div>
                        </>
                    ) : null}
                    <div className="contor-config-citire-item">
                        <span>{isPausal ? 'Cantitate' : 'Consum'}</span>
                        <strong>{formatDecimal(citire.consum)} {contor.um || ''}</strong>
                    </div>
                </div>
            ) : (
                <p>Nu există încă citiri salvate pentru „{contor.denumire}”.</p>
            )}
        </section>
    );
}

function ContorConfigurabilForm({ contor }) {
    const spatiiOptions = contor.spatiiOptions || [];
    const liniiScadereOptions = contor.liniiScadereOptions || [];
    const [data, setData] = useState({
        foloseste_scaderi: Boolean(contor.foloseste_scaderi),
        scaderi: contor.scaderi?.length ? contor.scaderi : [],
        alocari: contor.alocari?.length ? contor.alocari.map(String) : [],
    });
    const [processing, setProcessing] = useState(false);

    function liniiForSpatiu(spatiuId) {
        return liniiScadereOptions.find((item) => Number(item.spatiu_id) === Number(spatiuId))?.linii || [];
    }

    function updateFolosesteScaderi(value) {
        const folosesteScaderi = value === 'da';

        setData((current) => ({
            ...current,
            foloseste_scaderi: folosesteScaderi,
            scaderi: folosesteScaderi ? (current.scaderi.length ? current.scaderi : [emptyScadere()]) : [],
        }));
    }

    function updateScadere(index, field, value) {
        setData((current) => {
            const next = [...(current.scaderi.length ? current.scaderi : [])];
            next[index] = { ...next[index], [field]: value };

            if (field === 'spatiu_id') {
                next[index].configurare_anexa_linie_id = '';
            }

            return { ...current, scaderi: next };
        });
    }

    function addScadere() {
        setData((current) => ({
            ...current,
            scaderi: [...current.scaderi, emptyScadere()],
        }));
    }

    function removeScadere(index) {
        setData((current) => ({
            ...current,
            scaderi: current.scaderi.filter((_, currentIndex) => currentIndex !== index),
        }));
    }

    function toggleAlocare(spatiuId) {
        const value = String(spatiuId);
        setData((current) => ({
            ...current,
            alocari: current.alocari.includes(value)
                ? current.alocari.filter((item) => item !== value)
                : [...current.alocari, value],
        }));
    }

    function submit(event) {
        event.preventDefault();
        setProcessing(true);

        const payload = {
            foloseste_scaderi: data.foloseste_scaderi,
            scaderi: [],
            alocari: [],
        };

        if (data.foloseste_scaderi) {
            payload.scaderi = data.scaderi
                .filter((item) => item.spatiu_id && item.configurare_anexa_linie_id)
                .map((item) => ({
                    spatiu_id: Number(item.spatiu_id),
                    configurare_anexa_linie_id: Number(item.configurare_anexa_linie_id),
                }));
            payload.alocari = data.alocari.map(Number);
        }

        router.put(`/configurare-contoare/${contor.id}`, payload, {
            preserveScroll: true,
            onFinish: () => setProcessing(false),
        });
    }

    const canSave = data.foloseste_scaderi ? data.alocari.length > 0 : spatiiOptions.length > 0;
    const alocariCount = data.foloseste_scaderi ? data.alocari.length : spatiiOptions.length;
    const alocariAfisate = data.foloseste_scaderi
        ? data.alocari
        : spatiiOptions.map((spatiu) => String(spatiu.id));
    const consumPerSpatiu = consumPerSpatiuCalculat(contor, data, spatiiOptions);

    return (
        <form className="rules-card contor-config-card" onSubmit={submit}>
            <div className="rules-card-title">
                <h2>Reguli repartizare</h2>
                <p className="contor-config-consum-summary">
                    Consum: <strong>{formatDecimal(contor.ultima_citire?.consum)}{contor.um ? ` ${contor.um}` : ''}</strong>
                </p>
            </div>

            <div className="contor-config-section">
                <label className="form-field contor-config-scaderi-toggle">
                    <span>Scăderi din consum</span>
                    <select
                        value={data.foloseste_scaderi ? 'da' : 'nu'}
                        onChange={(event) => updateFolosesteScaderi(event.target.value)}
                    >
                        <option value="nu">Nu scădem</option>
                        <option value="da">Scădem</option>
                    </select>
                </label>
            </div>

            {data.foloseste_scaderi && (
                <div className="contor-config-section">
                    {data.scaderi.map((scadere, index) => (
                        <div className="contor-config-scadere-row" key={`scadere-${index}`}>
                            <label className="form-field">
                                <span>Spațiu</span>
                                <select
                                    value={scadere.spatiu_id}
                                    onChange={(event) => updateScadere(index, 'spatiu_id', event.target.value)}
                                >
                                    <option value="">Alege spațiul</option>
                                    {spatiiOptions.map((spatiu) => (
                                        <option value={spatiu.id} key={spatiu.id}>{spatiu.label}</option>
                                    ))}
                                </select>
                            </label>
                            <label className="form-field">
                                <span>Serviciu</span>
                                <select
                                    value={scadere.configurare_anexa_linie_id}
                                    onChange={(event) => updateScadere(index, 'configurare_anexa_linie_id', event.target.value)}
                                    disabled={!scadere.spatiu_id}
                                >
                                    <option value="">Alege serviciul</option>
                                    {liniiForSpatiu(scadere.spatiu_id).map((linie) => (
                                        <option value={linie.id} key={linie.id}>{linie.label}</option>
                                    ))}
                                </select>
                            </label>
                            <button className="secondary-button" type="button" onClick={() => removeScadere(index)}>Elimină</button>
                        </div>
                    ))}
                    <button className="secondary-button" type="button" onClick={addScadere}>+ Adaugă scădere</button>
                </div>
            )}

            <div className="contor-config-section">
                <h3 className="contor-config-alocari-title">
                    {alocariCount} spații închiriate alocate
                    {' — '}
                    rezultând un consum calculat / spațiu:{' '}
                    <strong>
                        {consumPerSpatiu === null ? '—' : formatDecimal(consumPerSpatiu)}
                        {consumPerSpatiu !== null && contor.um ? ` ${contor.um}` : ''}
                    </strong>
                </h3>
                {!data.foloseste_scaderi ? (
                    <p className="contor-config-help">Fără scăderi, consumul se împarte egal la toate spațiile închiriate din anexă.</p>
                ) : null}
                <div className="contor-config-alocari">
                    {spatiiOptions.map((spatiu) => (
                        <label className="inline-checkbox" key={spatiu.id}>
                            <input
                                type="checkbox"
                                checked={alocariAfisate.includes(String(spatiu.id))}
                                disabled={!data.foloseste_scaderi}
                                onChange={() => toggleAlocare(spatiu.id)}
                            />
                            <span>{spatiu.label}</span>
                        </label>
                    ))}
                </div>
            </div>

            <div className="form-actions">
                <button className="primary-button" type="submit" disabled={processing || !canSave}>
                    {processing ? 'Se salvează...' : 'Salvează regula'}
                </button>
            </div>
        </form>
    );
}

export default function Contor({
    imobil,
    contor,
}) {
    return (
        <AppLayout
            title={contor.denumire}
            subtitle={`${imobil.nume} · ${contor.tip_label || 'contor configurabil'}`}
            showGlobalSearch={false}
            topbarActions={(
                <Link className="secondary-button" href={`/configurare-contoare/imobil/${imobil.id}`}>
                    Înapoi la contoare
                </Link>
            )}
        >
            <UltimaCitireCard contor={contor} imobilId={imobil.id} />
            <ContorConfigurabilForm contor={contor} />
        </AppLayout>
    );
}
