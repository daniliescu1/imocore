import React from 'react';
import { Link, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';

function defaultValue(field, record) {
    if (record?.[field] !== undefined && record?.[field] !== null) {
        if (typeof record[field] === 'boolean') {
            return record[field];
        }
        return String(record[field]).slice(0, 10);
    }

    if (field === 'moneda') return 'EUR';
    if (field === 'status') return 'activ';
    if (field === 'tip') return 'predare';
    if (field === 'garantie_incasata') return false;
    return '';
}

export default function Form({ moduleKey, title, fields, imobile = [], spatii = [], contoare = [], contracte = [], anexe = [], record = null }) {
    const isEditing = Boolean(record);
    const initialData = Object.fromEntries(fields.map((field) => [field, defaultValue(field, record)]));
    const { data, setData, post, put, processing, errors } = useForm(initialData);

    function submit(event) {
        event.preventDefault();
        if (isEditing) {
            put(`/${moduleKey}/${record.id}`);
            return;
        }
        post(`/${moduleKey}`);
    }

    const topbarActions = <Link className="secondary-button button-link" href={`/${moduleKey}`}>Înapoi</Link>;

    return (
        <AppLayout title={isEditing ? `Editare ${title}` : `Adaugă ${title}`} subtitle="Formular funcțional legat de baza de date" showGlobalSearch={false} topbarActions={topbarActions}>
            <form className="form-card" onSubmit={submit}>
                <div className="form-grid">
                    {fields.map((field) => (
                        <label className="form-field" key={field}>
                            <span>{field.replaceAll('_', ' ')}</span>
                            {field === 'imobil_id' ? (
                                <select value={data[field]} onChange={(event) => setData(field, event.target.value)}>
                                    <option value="">Alege imobilul</option>
                                    {imobile.map((imobil) => <option value={imobil.id} key={imobil.id}>{imobil.label}</option>)}
                                </select>
                            ) : field === 'spatiu_id' ? (
                                <select value={data[field]} onChange={(event) => setData(field, event.target.value)}>
                                    <option value="">Alege spațiul</option>
                                    {spatii.map((spatiu) => <option value={spatiu.id} key={spatiu.id}>{spatiu.label}</option>)}
                                </select>
                            ) : field === 'contor_id' ? (
                                <select value={data[field]} onChange={(event) => setData(field, event.target.value)}>
                                    <option value="">Alege contorul</option>
                                    {contoare.map((contor) => <option value={contor.id} key={contor.id}>{contor.label}</option>)}
                                </select>
                            ) : field === 'contract_id' ? (
                                <select value={data[field]} onChange={(event) => setData(field, event.target.value)}>
                                    <option value="">Alege contractul</option>
                                    {contracte.map((contract) => <option value={contract.id} key={contract.id}>{contract.label}</option>)}
                                </select>
                            ) : field === 'anexa_id' ? (
                                <select value={data[field]} onChange={(event) => setData(field, event.target.value)}>
                                    <option value="">Alege anexa</option>
                                    {anexe.map((anexa) => <option value={anexa.id} key={anexa.id}>{anexa.label}</option>)}
                                </select>
                            ) : field === 'observatii' ? (
                                <textarea value={data[field]} onChange={(event) => setData(field, event.target.value)} rows="4" />
                            ) : ['garantie_incasata', 'activ', 'aprobat'].includes(field) ? (
                                <label className="inline-checkbox">
                                    <input type="checkbox" checked={Boolean(data[field])} onChange={(event) => setData(field, event.target.checked)} />
                                    <span>Da</span>
                                </label>
                            ) : field === 'status' ? (
                                <input type="text" value={data[field]} onChange={(event) => setData(field, event.target.value)} />
                            ) : field === 'luna' ? (
                                <input type="month" value={data[field]} onChange={(event) => setData(field, event.target.value)} />
                            ) : field.includes('data') || field.includes('termen') ? (
                                <input type="date" value={data[field]} onChange={(event) => setData(field, event.target.value)} />
                            ) : ['chirie', 'garantie', 'index_vechi', 'index_nou', 'cantitate', 'cost_total', 'pret_unitar'].some((part) => field.includes(part)) ? (
                                <input type="number" min="0" step="0.01" value={data[field]} onChange={(event) => setData(field, event.target.value)} />
                            ) : (
                                <input type="text" value={data[field]} onChange={(event) => setData(field, event.target.value)} />
                            )}
                            {errors[field] ? <small>{errors[field]}</small> : null}
                        </label>
                    ))}
                </div>
                <div className="form-footer-actions">
                    <span />
                    <div className="form-actions">
                        <Link className="secondary-button button-link" href={`/${moduleKey}`}>Anulează</Link>
                        <button className="primary-button" type="submit" disabled={processing}>{processing ? 'Se salvează...' : 'Salvează'}</button>
                    </div>
                </div>
            </form>
        </AppLayout>
    );
}
