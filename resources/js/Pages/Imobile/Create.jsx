import React from 'react';
import { Link, router, useForm } from '@inertiajs/react';
import { Eye, Trash2 } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';

const fields = [
    ['nume', 'Nume imobil', 'text', true],
    ['strada', 'Stradă', 'text', true],
    ['numar', 'Număr', 'text', true],
    ['localitate', 'Localitate', 'text', true],
    ['judet', 'Județ', 'text', false],
    ['cod_postal', 'Cod poștal', 'text', false],
];

const emptyCf = { numar: '', observatii: '', poza: null, poza_path: null, poza_nume: null, poza_url: null, sterge_fisier: false };
const emptyAnexaLine = {
    nr_crt: '',
    denumire: '',
    index_vechi: '',
    index_nou: '',
    facturat: '',
    um: '',
    pret_unitar: '',
    valoare: '',
    tva_21: '',
    tip_calcul: 'manual',
    apare_cu_zero: true,
    activ: true,
    observatii: '',
};
const emptyConfigurareAnexa = { denumire: '', implicit: false, activ: true, observatii: '', linii: [{ ...emptyAnexaLine, nr_crt: 1 }] };
const defaultCampuriSpatiuConfigurabile = [
    'suprafata_contractuala_mp',
    'corp',
    'etaj',
    'persoane_standard',
    'pret_lunar',
    'indexare_2026',
    'pret_mp_ultima_indexare',
    'regim_incalzire',
    'procent_incalzire_override',
    'locator_id',
    'configurare_anexa_id',
    'chirias',
    'observatii',
];

function numericValue(value) {
    if (value === null || value === undefined) return null;
    const normalized = String(value).trim().replace(',', '.');
    if (normalized === '') return null;

    const number = Number(normalized);
    return Number.isFinite(number) ? number : null;
}

function calculatedFacturat(indexVechi, indexNou) {
    const vechi = numericValue(indexVechi);
    const nou = numericValue(indexNou);

    if (vechi === null || nou === null) {
        return '';
    }

    return String(Number((nou - vechi).toFixed(3)));
}

function calculatedValoare(facturat, pretUnitar) {
    const cantitate = numericValue(facturat);
    const pret = numericValue(pretUnitar);

    if (cantitate === null || pret === null) {
        return '';
    }

    return (cantitate * pret).toFixed(2);
}

function renumberLines(lines) {
    return lines.map((line, index) => ({ ...line, nr_crt: index + 1 }));
}

function createEmptyConfigurareAnexa(overrides = {}) {
    return {
        ...emptyConfigurareAnexa,
        ...overrides,
        linii: renumberLines(overrides.linii?.length ? overrides.linii : [{ ...emptyAnexaLine }]),
    };
}

export default function Create({ imobil = null, campuriSpatiuConfigurabile = [], canDeleteImobile = false }) {
    const isEditing = Boolean(imobil);
    const campuriSpatiuOptions = campuriSpatiuConfigurabile.length
        ? campuriSpatiuConfigurabile
        : defaultCampuriSpatiuConfigurabile.map((key) => ({ key, label: key }));
    const { data, setData, post, processing, errors, transform } = useForm({
        nume: imobil?.nume || '',
        strada: imobil?.strada || '',
        numar: imobil?.numar || '',
        localitate: imobil?.localitate || '',
        judet: imobil?.judet || 'Timiș',
        cod_postal: imobil?.cod_postal || '',
        numere_cf: imobil?.numere_cf?.length ? imobil.numere_cf : [emptyCf],
        campuri_spatiu_vizibile: imobil?.campuri_spatiu_vizibile?.length
            ? imobil.campuri_spatiu_vizibile
            : campuriSpatiuOptions.map((field) => field.key),
        configurari_anexe: imobil?.configurari_anexe?.length
            ? imobil.configurari_anexe.map((configurare) => createEmptyConfigurareAnexa(configurare))
            : [createEmptyConfigurareAnexa({ implicit: true })],
        observatii: imobil?.observatii || '',
    });

    function submit(event) {
        event.preventDefault();
        saveImobil();
    }

    function saveImobil() {
        if (isEditing) {
            transform((formData) => ({ ...formData, _method: 'put' }));
            post(`/imobile/${imobil.id}`, { forceFormData: true });
            return;
        }

        transform((formData) => formData);
        post('/imobile', { forceFormData: true });
    }

    function updateCf(index, field, value) {
        setData('numere_cf', data.numere_cf.map((cf, currentIndex) => (
            currentIndex === index ? { ...cf, [field]: value } : cf
        )));
    }

    function addCf() {
        setData('numere_cf', [...data.numere_cf, { ...emptyCf }]);
    }

    function removeCf(index) {
        const nextCf = data.numere_cf.filter((_, currentIndex) => currentIndex !== index);
        setData('numere_cf', nextCf.length ? nextCf : [{ ...emptyCf }]);
    }

    function removeCfFile(index) {
        setData('numere_cf', data.numere_cf.map((cf, currentIndex) => (
            currentIndex === index ? { ...cf, poza: null, poza_path: null, poza_nume: null, poza_url: null, sterge_fisier: true } : cf
        )));
    }

    function toggleCampSpatiu(fieldKey) {
        const currentFields = data.campuri_spatiu_vizibile || [];
        const nextFields = currentFields.includes(fieldKey)
            ? currentFields.filter((key) => key !== fieldKey)
            : [...currentFields, fieldKey];

        setData('campuri_spatiu_vizibile', nextFields);
    }

    function deleteImobil() {
        if (!window.confirm('Are you sure?')) {
            return;
        }

        router.delete(`/imobile/${imobil.id}`);
    }

    const topbarActions = (
        <>
            <Link className="secondary-button button-link" href="/imobile">Înapoi la imobile</Link>
        </>
    );

    return (
        <AppLayout
            title={isEditing ? `Editare ${imobil.nume}` : 'Adaugă imobil'}
            subtitle="Completează numele imobilului, adresa, numerele CF și fișierele aferente."
            showGlobalSearch={false}
            topbarActions={topbarActions}
        >
            <form className="form-card" onSubmit={submit}>
                <div className="form-grid">
                    {fields.map(([name, label, type, required]) => (
                        <label className="form-field" key={name}>
                            <span>{label}{required ? ' *' : ''}</span>
                            <input
                                type={type}
                                value={data[name]}
                                min={type === 'number' ? 0 : undefined}
                                onChange={(event) => setData(name, event.target.value)}
                            />
                            {errors[name] ? <small>{errors[name]}</small> : null}
                        </label>
                    ))}
                </div>

                <div className="cf-card">
                    <div className="cf-card-heading">
                        <div>
                            <h2>Numere CF</h2>
                            <p>Poți adăuga mai multe numere CF și observații pentru fiecare.</p>
                        </div>
                        <button type="button" className="secondary-button" onClick={addCf}>+ Adaugă CF</button>
                    </div>

                    <div className="cf-list">
                        {data.numere_cf.map((cf, index) => (
                            <div className="cf-row" key={index}>
                                <label className="form-field">
                                    <span>Număr CF</span>
                                    <input type="text" value={cf.numar} onChange={(event) => updateCf(index, 'numar', event.target.value)} />
                                    {errors[`numere_cf.${index}.numar`] ? <small>{errors[`numere_cf.${index}.numar`]}</small> : null}
                                </label>
                                <label className="form-field">
                                    <span>Observații CF</span>
                                    <input type="text" value={cf.observatii} onChange={(event) => updateCf(index, 'observatii', event.target.value)} />
                                    {errors[`numere_cf.${index}.observatii`] ? <small>{errors[`numere_cf.${index}.observatii`]}</small> : null}
                                </label>
                                <label className="form-field cf-photo-field">
                                    <span className="cf-file-label">
                                        <span>Fișier CF</span>
                                        {cf.preview_url ? (
                                            <a className="preview-file-button" href={cf.preview_url} target="_blank" rel="noreferrer" aria-label="Vizualizează fișier CF" title="Vizualizează">
                                                <Eye size={14} strokeWidth={2.4} />
                                            </a>
                                        ) : null}
                                    </span>
                                    {cf.poza_url || cf.poza ? (
                                        <div className="cf-file-current">
                                            {cf.poza_url ? (
                                                <a href={cf.download_url || cf.poza_url}>{cf.poza_nume || cf.poza_path?.split('/').pop()}</a>
                                            ) : (
                                                <span>{cf.poza?.name}</span>
                                            )}
                                            <button type="button" className="delete-inline-button" onClick={() => removeCfFile(index)} aria-label="Șterge fișier CF" title="Șterge fișier CF">
                                                <Trash2 size={14} strokeWidth={2.4} />
                                            </button>
                                        </div>
                                    ) : (
                                        <input type="file" onChange={(event) => updateCf(index, 'poza', event.target.files[0] || null)} />
                                    )}
                                    {errors[`numere_cf.${index}.poza`] ? <small>{errors[`numere_cf.${index}.poza`]}</small> : null}
                                </label>
                                {cf.poza_url || cf.poza ? null : (
                                    <button type="button" className="delete-inline-button cf-remove-button" onClick={() => removeCf(index)} aria-label="Șterge CF" title="Șterge CF">
                                        <Trash2 size={14} strokeWidth={2.4} />
                                    </button>
                                )}
                            </div>
                        ))}
                    </div>
                </div>

                <div className="readonly-info-card">
                    <h2>Spații în acest imobil</h2>
                    <p>Aceste valori se calculează automat după ce adaugi spațiile aferente imobilului.</p>
                    <div className="readonly-stats">
                        <div><span>Spații total</span><strong>{imobil?.spatii_total || 0}</strong></div>
                        <div><span>Libere</span><strong>{imobil?.spatii_libere || 0}</strong></div>
                        <div><span>Închiriate</span><strong>{imobil?.spatii_inchiriate || 0}</strong></div>
                        <div><span>Spații comune</span><strong>{imobil?.spatii_comune || 0}</strong></div>
                    </div>
                </div>

                <div className="cf-card">
                    <div className="cf-card-heading">
                        <div>
                            <h2>Câmpuri formular adăugare spațiu</h2>
                            <p>Bifează câmpurile care apar când adaugi sau editezi un spațiu pentru acest imobil.</p>
                        </div>
                    </div>

                    <div className="space-fields-grid">
                        {campuriSpatiuOptions.map((field) => (
                            <label className="space-field-toggle" key={field.key}>
                                <input
                                    type="checkbox"
                                    checked={(data.campuri_spatiu_vizibile || []).includes(field.key)}
                                    onChange={() => toggleCampSpatiu(field.key)}
                                />
                                <span>{field.label}</span>
                            </label>
                        ))}
                    </div>
                </div>

                {isEditing ? (
                    <div className="cf-card">
                        <div className="cf-card-heading">
                            <div>
                                <h2>Configurare anexă</h2>
                                <p>Serviciile anexei se administrează acum din pagina dedicată de configurare.</p>
                            </div>
                            <Link className="secondary-button button-link" href={`/configurare-anexa?imobil_id=${imobil.id}`}>Vezi anexele configurate</Link>
                        </div>
                        <div className="readonly-stats">
                            <div><span>Configurări</span><strong>{data.configurari_anexe?.length || 0}</strong></div>
                            <div><span>Servicii anexă</span><strong>{(data.configurari_anexe || []).reduce((total, configurare) => total + (configurare.linii?.length || 0), 0)}</strong></div>
                            <div><span>Implicită</span><strong>{(data.configurari_anexe || []).find((configurare) => configurare.implicit)?.denumire || '—'}</strong></div>
                            <div><span>Alocare</span><strong>Acest imobil</strong></div>
                        </div>
                    </div>
                ) : null}

                <label className="form-field form-field-full">
                    <span>Observații</span>
                    <textarea value={data.observatii} onChange={(event) => setData('observatii', event.target.value)} rows="4" />
                    {errors.observatii ? <small>{errors.observatii}</small> : null}
                </label>

                <div className="form-footer-actions">
                    {isEditing && canDeleteImobile ? (
                        <button type="button" className="delete-imobil-button" onClick={deleteImobil}>
                            <Trash2 size={16} strokeWidth={2.4} />
                            <span>Șterge imobil</span>
                        </button>
                    ) : <span />}
                    <div className="form-actions">
                        <Link className="secondary-button button-link" href="/imobile">Anulează</Link>
                        <button className="primary-button" type="submit" disabled={processing}>{processing ? 'Se salvează...' : (isEditing ? 'Salvează modificările' : 'Salvează imobil')}</button>
                    </div>
                </div>
            </form>
        </AppLayout>
    );
}