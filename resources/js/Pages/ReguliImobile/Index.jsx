import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';

function RuleRow({ imobil }) {
    const [data, setData] = useState({
        ...imobil.reguli,
        motiv: '',
    });

    function update(field, value) {
        setData((current) => ({ ...current, [field]: value }));
    }

    function submit(event) {
        event.preventDefault();
        router.put(`/reguli-imobile/${imobil.id}`, data, { preserveScroll: true });
    }

    return (
        <form className="rules-card" onSubmit={submit}>
            <div className="rules-card-title">
                <h2>{imobil.nume}</h2>
                <p>{imobil.localitate}</p>
            </div>
            <div className="rules-grid">
                <label className="form-field">
                    <span>Metodă curent</span>
                    <select value={data.metoda_curent} onChange={(event) => update('metoda_curent', event.target.value)}>
                        <option value="standard">Standard</option>
                        <option value="pierdere_procent">Consum cu pierdere procentuală</option>
                        <option value="general_vs_citite">Contor general vs contoare citite</option>
                    </select>
                </label>
                <label className="form-field">
                    <span>Pierdere curent %</span>
                    <input type="number" min="0" max="100" step="0.01" value={data.procent_pierdere_curent || ''} onChange={(event) => update('procent_pierdere_curent', event.target.value)} />
                </label>
                <label className="form-field">
                    <span>Metodă apă</span>
                    <select value={data.metoda_apa} onChange={(event) => update('metoda_apa', event.target.value)}>
                        <option value="contoare_si_persoane">Contoare și persoane</option>
                        <option value="doar_contoare">Doar contoare</option>
                    </select>
                </label>
                <label className="form-field">
                    <span>Coeficient apă pluvială</span>
                    <input type="number" min="0" step="0.0001" value={data.coeficient_apa_pluviala || ''} onChange={(event) => update('coeficient_apa_pluviala', event.target.value)} />
                </label>
                <label className="form-field">
                    <span>Încălzire parțială %</span>
                    <input type="number" min="0" max="100" step="0.01" value={data.procent_incalzire_partial || ''} onChange={(event) => update('procent_incalzire_partial', event.target.value)} />
                </label>
                <label className="form-field">
                    <span>Metodă spații comune</span>
                    <select value={data.metoda_spatii_comune} onChange={(event) => update('metoda_spatii_comune', event.target.value)}>
                        <option value="sub_50_persoane_peste_50_mp">Sub 50 persoane, 50+ mp</option>
                        <option value="doar_mp">Doar mp</option>
                        <option value="doar_persoane">Doar persoane</option>
                    </select>
                </label>
                <label className="form-field">
                    <span>Metodă RETIM</span>
                    <select value={data.metoda_retim} onChange={(event) => update('metoda_retim', event.target.value)}>
                        <option value="persoane">Persoane</option>
                        <option value="cost_fix">Cost fix</option>
                    </select>
                </label>
                <label className="form-field">
                    <span>Motiv modificare</span>
                    <input type="text" value={data.motiv} onChange={(event) => update('motiv', event.target.value)} placeholder="Recomandat pentru audit" />
                </label>
                <label className="form-field checkbox-field">
                    <span>Coeficient pluvial aprobat</span>
                    <label className="inline-checkbox">
                        <input type="checkbox" checked={Boolean(data.coeficient_apa_pluviala_aprobat)} onChange={(event) => update('coeficient_apa_pluviala_aprobat', event.target.checked)} />
                        <span>Aprobat</span>
                    </label>
                </label>
            </div>
            <div className="form-actions rules-actions">
                <button className="primary-button" type="submit">Salvează reguli</button>
            </div>
        </form>
    );
}

export default function Index({ imobile }) {
    return (
        <AppLayout title={`Reguli imobile (${imobile.length})`} subtitle="Configurare reguli de calcul pe fiecare imobil" showGlobalSearch={false}>
            <div className="rules-list">
                {imobile.map((imobil) => <RuleRow imobil={imobil} key={imobil.id} />)}
            </div>
        </AppLayout>
    );
}
