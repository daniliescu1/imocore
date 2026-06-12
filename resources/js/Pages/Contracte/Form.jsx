import React from 'react';
import { Link, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';

function formatDecimal(value) {
    if (value === null || value === undefined || value === '') return '';
    return String(Number(value)).replace(/(\.\d*?)0+$/, '$1').replace(/\.$/, '');
}

function spatiuInfo(spatii, spatiuId) {
    return spatii.find((spatiu) => Number(spatiu.id) === Number(spatiuId)) || null;
}

export default function Form({ imobile = [], spatii = [], contract = null }) {
    const isEditing = Boolean(contract);
    const { data, setData, post, put, processing, errors } = useForm({
        imobil_id: contract?.imobil_id || '',
        spatiu_id: contract?.spatiu_id || '',
        locator_nume: contract?.locator_nume || '',
        numar_contract: contract?.numar_contract || '',
        chirias: contract?.chirias || '',
        persoane_declarate: contract?.persoane_declarate ?? '',
        data_start: contract?.data_start || '',
        data_end: contract?.data_end || '',
        chirie: formatDecimal(contract?.chirie) || '',
        moneda: contract?.moneda || 'EUR',
        status: contract?.status || 'activ',
        observatii: contract?.observatii || '',
    });

    const spatiiPentruImobil = data.imobil_id ? spatii.filter((spatiu) => Number(spatiu.imobil_id) === Number(data.imobil_id)) : [];
    const selectedSpatiu = spatiuInfo(spatii, data.spatiu_id);
    const spatiuAdministrativ = selectedSpatiu?.status === 'administrativ';

    function applySpatiu(spatiuId) {
        const spatiu = spatiuInfo(spatii, spatiuId);
        setData({
            ...data,
            spatiu_id: spatiuId,
            locator_nume: spatiu?.locator_nume || data.locator_nume,
            chirias: spatiu?.chirias || data.chirias,
            persoane_declarate: spatiu?.status === 'administrativ' ? '' : (spatiu?.persoane_declarate ?? data.persoane_declarate),
            chirie: formatDecimal(spatiu?.chirie_curenta) || data.chirie,
            moneda: 'EUR',
        });
    }

    function submit(event) {
        event.preventDefault();
        if (isEditing) {
            put(`/contracte/${contract.id}`);
            return;
        }

        post('/contracte');
    }

    const topbarActions = <Link className="secondary-button button-link" href="/contracte">Înapoi</Link>;

    return (
        <AppLayout title={isEditing ? `Editare contract ${contract.numar_contract}` : 'Adaugă contract'} subtitle="Alege imobilul, apoi spațiul; datele spațiului completează contractul." showGlobalSearch={false} topbarActions={topbarActions}>
            <form className="form-card" onSubmit={submit}>
                <div className="form-grid">
                    <label className="form-field">
                        <span>Imobil *</span>
                        <select value={data.imobil_id} onChange={(event) => {
                            setData({
                                ...data,
                                imobil_id: event.target.value,
                                spatiu_id: '',
                            });
                        }}>
                            <option value="">Alege imobilul</option>
                            {imobile.map((imobil) => <option value={imobil.id} key={imobil.id}>{imobil.label}</option>)}
                        </select>
                    </label>

                    <label className="form-field">
                        <span>Spațiu *</span>
                        <select value={data.spatiu_id} onChange={(event) => applySpatiu(event.target.value)} disabled={!data.imobil_id}>
                            <option value="">{data.imobil_id ? 'Alege spațiul' : 'Alege mai întâi imobilul'}</option>
                            {spatiiPentruImobil.map((spatiu) => <option value={spatiu.id} key={spatiu.id}>{spatiu.identificator}</option>)}
                        </select>
                        {errors.spatiu_id ? <small>{errors.spatiu_id}</small> : null}
                    </label>

                    <label className="form-field">
                        <span>Număr contract *</span>
                        <input type="text" value={data.numar_contract} onChange={(event) => setData('numar_contract', event.target.value)} />
                        {errors.numar_contract ? <small>{errors.numar_contract}</small> : null}
                    </label>

                    <label className="form-field">
                        <span>Nume locator</span>
                        <input type="text" value={data.locator_nume} onChange={(event) => setData('locator_nume', event.target.value)} />
                        {errors.locator_nume ? <small>{errors.locator_nume}</small> : null}
                    </label>

                    <label className="form-field">
                        <span>Chiriaș *</span>
                        <input type="text" value={data.chirias} onChange={(event) => setData('chirias', event.target.value)} />
                        {errors.chirias ? <small>{errors.chirias}</small> : null}
                    </label>

                    {!spatiuAdministrativ ? (
                        <label className="form-field">
                            <span>Persoane declarate</span>
                            <input type="number" min="0" step="1" value={data.persoane_declarate} onChange={(event) => setData('persoane_declarate', event.target.value)} />
                            {errors.persoane_declarate ? <small>{errors.persoane_declarate}</small> : null}
                        </label>
                    ) : null}

                    <label className="form-field">
                        <span>Data start *</span>
                        <input type="date" value={data.data_start} onChange={(event) => setData('data_start', event.target.value)} />
                        {errors.data_start ? <small>{errors.data_start}</small> : null}
                    </label>

                    <label className="form-field">
                        <span>Data end</span>
                        <input type="date" value={data.data_end} onChange={(event) => setData('data_end', event.target.value)} />
                        {errors.data_end ? <small>{errors.data_end}</small> : null}
                    </label>

                    <label className="form-field">
                        <span>Chirie lunară EUR *</span>
                        <input type="number" min="0" step="0.01" value={data.chirie} onChange={(event) => setData('chirie', event.target.value)} />
                        {errors.chirie ? <small>{errors.chirie}</small> : null}
                    </label>

                    <label className="form-field">
                        <span>Status</span>
                        <select value={data.status} onChange={(event) => setData('status', event.target.value)}>
                            <option value="activ">Activ</option>
                            <option value="inactiv">Inactiv</option>
                            <option value="incetat">Încetat</option>
                        </select>
                        {errors.status ? <small>{errors.status}</small> : null}
                    </label>
                </div>

                {selectedSpatiu ? (
                    <div className="readonly-info-card contract-space-summary">
                        <h2>Informații din spațiu</h2>
                        <div className="readonly-stats">
                            <div><span>Locator</span><strong>{selectedSpatiu.locator_nume || '—'}</strong></div>
                            <div><span>Suprafață</span><strong>{formatDecimal(selectedSpatiu.suprafata_contractuala_mp) || '—'} mp</strong></div>
                            <div><span>Chirie contractuală</span><strong>{formatDecimal(selectedSpatiu.pret_lunar) || '—'} EUR</strong></div>
                            <div><span>Indexare 2026</span><strong>{formatDecimal(selectedSpatiu.indexare_2026) || '—'} EUR</strong></div>
                            <div><span>Chirie curentă</span><strong>{formatDecimal(selectedSpatiu.chirie_curenta) || '—'} EUR</strong></div>
                            <div><span>Anexă spațiu</span><strong>{selectedSpatiu.configurare_anexa || '—'}</strong></div>
                        </div>
                    </div>
                ) : null}

                <label className="form-field form-field-full">
                    <span>Observații</span>
                    <textarea value={data.observatii} onChange={(event) => setData('observatii', event.target.value)} rows="4" />
                    {errors.observatii ? <small>{errors.observatii}</small> : null}
                </label>

                <div className="form-footer-actions">
                    <span />
                    <div className="form-actions">
                        <Link className="secondary-button button-link" href="/contracte">Anulează</Link>
                        <button className="primary-button" type="submit" disabled={processing}>{processing ? 'Se salvează...' : 'Salvează contract'}</button>
                    </div>
                </div>
            </form>
        </AppLayout>
    );
}
