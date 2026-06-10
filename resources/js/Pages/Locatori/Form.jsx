import React from 'react';
import { Link, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';

export default function Form({ locator = null, imobile = [] }) {
    const isEditing = Boolean(locator);
    const { data, setData, post, put, processing, errors } = useForm({
        nume: locator?.nume || '',
        imobil_id: locator?.imobil_id || '',
        cui_are_ro: Boolean(locator?.cui_are_ro),
        cui: locator?.cui || '',
        registrul_comertului: locator?.registrul_comertului || '',
        adresa: locator?.adresa || '',
        banca: locator?.banca || '',
        cont_bancar: locator?.cont_bancar || '',
        chirie_cu_tva: Boolean(locator?.chirie_cu_tva),
    });

    function submit(event) {
        event.preventDefault();

        if (isEditing) {
            put(`/locatori/${locator.id}`);
            return;
        }

        post('/locatori');
    }

    const topbarActions = <Link className="secondary-button button-link" href="/locatori">Înapoi la locatori</Link>;

    return (
        <AppLayout title={isEditing ? `Editare ${locator.nume}` : 'Adaugă locator'} subtitle="Locatorii pot fi selectați pe orice spațiu, indiferent de imobil" showGlobalSearch={false} topbarActions={topbarActions}>
            <form className="form-card" onSubmit={submit}>
                <div className="form-grid">
                    <label className="form-field">
                        <span>Nume locator *</span>
                        <input type="text" value={data.nume} onChange={(event) => setData('nume', event.target.value)} />
                        {errors.nume ? <small>{errors.nume}</small> : null}
                    </label>

                    <label className="form-field">
                        <span>Imobil asociat opțional</span>
                        <select value={data.imobil_id || ''} onChange={(event) => setData('imobil_id', event.target.value)}>
                            <option value="">Global, disponibil peste tot</option>
                            {imobile.map((imobil) => <option value={imobil.id} key={imobil.id}>{imobil.label}</option>)}
                        </select>
                        {errors.imobil_id ? <small>{errors.imobil_id}</small> : null}
                    </label>

                    <label className="form-field">
                        <span>CUI - prefix</span>
                        <select value={data.cui_are_ro ? '1' : '0'} onChange={(event) => setData('cui_are_ro', event.target.value === '1')}>
                            <option value="0">Fără RO</option>
                            <option value="1">RO</option>
                        </select>
                        {errors.cui_are_ro ? <small>{errors.cui_are_ro}</small> : null}
                    </label>

                    <label className="form-field">
                        <span>CUI</span>
                        <input type="text" value={data.cui} onChange={(event) => setData('cui', event.target.value)} />
                        {errors.cui ? <small>{errors.cui}</small> : null}
                    </label>

                    <label className="form-field">
                        <span>Registrul Comerțului</span>
                        <input type="text" value={data.registrul_comertului} onChange={(event) => setData('registrul_comertului', event.target.value)} placeholder="J..." />
                        {errors.registrul_comertului ? <small>{errors.registrul_comertului}</small> : null}
                    </label>

                    <label className="form-field">
                        <span>Adresă firmă</span>
                        <input type="text" value={data.adresa} onChange={(event) => setData('adresa', event.target.value)} />
                        {errors.adresa ? <small>{errors.adresa}</small> : null}
                    </label>

                    <label className="form-field">
                        <span>Bancă</span>
                        <input type="text" value={data.banca} onChange={(event) => setData('banca', event.target.value)} />
                        {errors.banca ? <small>{errors.banca}</small> : null}
                    </label>

                    <label className="form-field">
                        <span>Cont bancar</span>
                        <input type="text" value={data.cont_bancar} onChange={(event) => setData('cont_bancar', event.target.value)} />
                        {errors.cont_bancar ? <small>{errors.cont_bancar}</small> : null}
                    </label>

                    <label className="form-field">
                        <span>Chirie facturată</span>
                        <select value={data.chirie_cu_tva ? '1' : '0'} onChange={(event) => setData('chirie_cu_tva', event.target.value === '1')}>
                            <option value="0">Fără TVA</option>
                            <option value="1">Chirie + TVA</option>
                        </select>
                        {errors.chirie_cu_tva ? <small>{errors.chirie_cu_tva}</small> : null}
                    </label>
                </div>

                <div className="form-footer-actions">
                    <span />
                    <div className="form-actions">
                        <Link className="secondary-button button-link" href="/locatori">Anulează</Link>
                        <button className="primary-button" type="submit" disabled={processing}>{processing ? 'Se salvează...' : 'Salvează locator'}</button>
                    </div>
                </div>
            </form>
        </AppLayout>
    );
}
