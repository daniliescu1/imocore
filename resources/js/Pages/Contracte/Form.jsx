import React, { useMemo, useState } from 'react';
import { Link, router, useForm } from '@inertiajs/react';
import { Calendar } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';

function formatDecimal(value) {
    if (value === null || value === undefined || value === '') return '';
    return String(Number(value)).replace(/(\.\d*?)0+$/, '$1').replace(/\.$/, '');
}

function formatDateForDisplay(value) {
    if (!value) {
        return '';
    }

    const isoMatch = String(value).match(/^(\d{4})-(\d{2})-(\d{2})$/);

    if (!isoMatch) {
        return value;
    }

    return `${isoMatch[3]}/${isoMatch[2]}/${isoMatch[1]}`;
}

function normalizeDateForSubmit(value) {
    if (!value) {
        return '';
    }

    const trimmed = String(value).trim();
    const roMatch = trimmed.match(/^(\d{1,2})[./-](\d{1,2})[./-](\d{4})$/);

    if (!roMatch) {
        return trimmed;
    }

    const day = roMatch[1].padStart(2, '0');
    const month = roMatch[2].padStart(2, '0');

    return `${roMatch[3]}-${month}-${day}`;
}

function formatDateDigits(value) {
    const digits = String(value || '').replace(/\D/g, '').slice(0, 8);
    const parts = [];

    if (digits.length > 0) {
        parts.push(digits.slice(0, 2));
    }

    if (digits.length > 2) {
        parts.push(digits.slice(2, 4));
    }

    if (digits.length > 4) {
        parts.push(digits.slice(4, 8));
    }

    return parts.join('/');
}

function formatPhoneNumber(value) {
    const raw = String(value || '');
    const digits = raw.replace(/\D/g, '');

    if (digits.length < 7 || digits.length > 10) {
        return raw;
    }

    const parts = [
        digits.slice(0, 4),
        digits.slice(4, 7),
        digits.slice(7, 10),
    ].filter(Boolean);

    return parts.join(' ');
}

function normalizeBankAccount(value) {
    return String(value || '').replace(/\s+/g, '').toUpperCase();
}

function formatBankAccount(value) {
    const normalized = normalizeBankAccount(value);

    if (!normalized) {
        return '';
    }

    return normalized.match(/.{1,4}/g)?.join(' ') || normalized;
}

function spatiuInfo(spatii, spatiuId) {
    return spatii.find((spatiu) => Number(spatiu.id) === Number(spatiuId)) || null;
}

function isBlank(value) {
    return value === null || value === undefined || String(value).trim() === '';
}

function normalizeCnpDigits(value) {
    const digits = String(value || '').replace(/\D/g, '');

    if (!digits) {
        return '';
    }

    return digits.length > 13 ? digits.slice(-13) : digits;
}

function isInvalidCnp(value, required = false) {
    if (isBlank(value)) {
        return required;
    }

    return normalizeCnpDigits(value).length !== 13;
}

function pfCiValue(chiriasPf) {
    const serie = String(chiriasPf?.serie_ci || '').trim();
    const numar = String(chiriasPf?.numar_ci || '').trim();

    if (serie !== '') {
        return serie;
    }

    return numar;
}

function missingFieldKeysForForm(data, spatiuAdministrativ = false) {
    const missing = [];

    if (!data.spatiu_id) missing.push('spatiu_id');
    if (!data.locator_id) missing.push('locator_id');
    if (isBlank(data.chirie) && data.chirie !== 0) missing.push('chirie');

    if (!spatiuAdministrativ && isBlank(data.persoane_declarate)) {
        missing.push('persoane_declarate');
    }

    if (data.chirias_tip === 'pf') {
        if (isBlank(pfCiValue(data.chirias_pf))) missing.push('chirias_pf.serie_ci');
        if (isBlank(data.chirias_pf?.cnp) || isInvalidCnp(data.chirias_pf?.cnp, true)) missing.push('chirias_pf.cnp');
        if (isBlank(data.chirias_pf?.domiciliu)) missing.push('chirias_pf.domiciliu');
        if (isBlank(data.chirias_pf?.telefon)) missing.push('chirias_pf.telefon');
    } else {
        if (isInvalidCnp(data.chirias_pj?.administrator?.cnp)) missing.push('chirias_pj.administrator.cnp');

        const admin2 = data.chirias_pj?.administrator_2;
        if (admin2 && !isBlank(admin2.nume_complet)) {
            if (isInvalidCnp(admin2.cnp)) missing.push('chirias_pj.administrator_2.cnp');
        }
    }

    return missing;
}

const fieldLabels = {
    spatiu_id: 'Spațiu',
    locator_id: 'Locator',
    numar_contract: 'Număr contract',
    data_start: 'Data start',
    data_end: 'Data end',
    chirie: 'Chirie lunară',
    'chirias_pf.nume_complet': 'Nume complet chiriaș',
    'chirias_pf.serie_ci': 'Serie CI chiriaș',
    'chirias_pf.cnp': 'CNP chiriaș',
    'chirias_pf.domiciliu': 'Domiciliu chiriaș',
    'chirias_pf.email': 'Email chiriaș',
    'chirias_pf.telefon': 'Telefon chiriaș',
    'chirias_pj.denumire': 'Denumire firmă',
    'chirias_pj.sediu_social': 'Sediu social',
    'chirias_pj.email': 'Email firmă',
    'chirias_pj.telefon': 'Telefon firmă',
    'chirias_pj.nr_reg_comert': 'Registrul Comerțului',
    'chirias_pj.cui': 'CUI',
    'chirias_pj.administrator.nume_complet': 'Nume administrator',
    'chirias_pj.administrator.serie_ci': 'Serie CI administrator',
    'chirias_pj.administrator.numar_ci': 'Număr CI administrator',
    'chirias_pj.administrator.cnp': 'CNP administrator',
    'chirias_pj.administrator.domiciliu': 'Domiciliu administrator',
    'chirias_pj.administrator.email': 'Email administrator',
    'chirias_pj.administrator_2.nume_complet': 'Nume al doilea reprezentant',
    'chirias_pj.administrator_2.serie_ci': 'Serie CI al doilea reprezentant',
    'chirias_pj.administrator_2.cnp': 'CNP al doilea reprezentant',
    'chirias_pj.administrator_2.domiciliu': 'Domiciliu al doilea reprezentant',
    'chirias_pj.administrator_2.email': 'Email al doilea reprezentant',
    persoane_declarate: 'Persoane declarate de chiriaș',
};

const emptyPf = {
    nume_complet: '',
    serie_ci: '',
    numar_ci: '',
    cnp: '',
    domiciliu: '',
    email: '',
    email_2: '',
    telefon: '',
    banca: '',
    cont_bancar: '',
};

const emptyAdministrator = {
    nume_complet: '',
    calitate: 'administrator',
    serie_ci: '',
    numar_ci: '',
    cnp: '',
    domiciliu: '',
    email: '',
    email_2: '',
    telefon: '',
};

const emptyPj = {
    denumire: '',
    sediu_social: '',
    telefon: '',
    email: '',
    email_2: '',
    nr_reg_comert: '',
    cui: '',
    banca: '',
    cont_bancar: '',
    administrator: { ...emptyAdministrator },
    administrator_2: { ...emptyAdministrator },
};

function administratorHasData(administrator) {
    if (!administrator) {
        return false;
    }

    return Object.entries(administrator).some(([key, value]) => {
        if (key === 'calitate' && (value === 'administrator' || isBlank(value))) {
            return false;
        }

        return !isBlank(value);
    });
}

function LegalRepresentativeFields({
    administrator,
    errorPrefix,
    errors,
    fieldIncomplete,
    onUpdate,
    requiredNume = false,
}) {
    return (
        <div className="form-grid form-grid-chirias">
            <PfField label="Nume complet" value={administrator.nume_complet} onChange={(value) => onUpdate('nume_complet', value)} error={errors[`${errorPrefix}.nume_complet`]} required={requiredNume} incomplete={fieldIncomplete(`${errorPrefix}.nume_complet`)} gridSpan={1} />
            <label className="form-field form-grid-span-1">
                <span>În calitate de</span>
                <select value={administrator.calitate || 'administrator'} onChange={(event) => onUpdate('calitate', event.target.value)}>
                    <option value="administrator">Administrator</option>
                    <option value="asociat">Asociat</option>
                    <option value="presedinte">Președinte</option>
                    <option value="director">Director</option>
                    <option value="reprezentant_legal">Reprezentant legal</option>
                    <option value="imputernicit_notarial">Împuternicit notarial</option>
                </select>
                {errors[`${errorPrefix}.calitate`] ? <small>{errors[`${errorPrefix}.calitate`]}</small> : null}
            </label>
            <PfField label="Serie CI Număr CI, eliberat de, la data." value={administrator.serie_ci} onChange={(value) => onUpdate('serie_ci', value)} error={errors[`${errorPrefix}.serie_ci`]} incomplete={fieldIncomplete(`${errorPrefix}.serie_ci`)} />
            <PfField label="Domiciliu" value={administrator.domiciliu} onChange={(value) => onUpdate('domiciliu', value)} error={errors[`${errorPrefix}.domiciliu`]} incomplete={fieldIncomplete(`${errorPrefix}.domiciliu`)} />
            <PfField label="CNP" value={administrator.cnp} onChange={(value) => onUpdate('cnp', value)} error={errors[`${errorPrefix}.cnp`]} incomplete={fieldIncomplete(`${errorPrefix}.cnp`)} gridSpan={1} />
            <PfField label="Email" value={administrator.email} onChange={(value) => onUpdate('email', value)} error={errors[`${errorPrefix}.email`]} incomplete={fieldIncomplete(`${errorPrefix}.email`)} gridSpan={1} />
            <PfField label="Telefon" value={administrator.telefon} onChange={(value) => onUpdate('telefon', value)} error={errors[`${errorPrefix}.telefon`]} gridSpan={1} formatter={formatPhoneNumber} incomplete={fieldIncomplete(`${errorPrefix}.telefon`)} />
        </div>
    );
}

function PfField({ label, value, onChange, error, type = 'text', required = false, incomplete = false, gridSpan = 2, formatter = null, normalize = null }) {
    function handleChange(event) {
        if (normalize) {
            onChange(normalize(event.target.value));
            return;
        }

        onChange(formatter ? formatter(event.target.value) : event.target.value);
    }

    const displayValue = formatter ? formatter(value) : value;

    return (
        <label className={`form-field form-grid-span-${gridSpan}${incomplete ? ' form-field-incomplete' : ''}`}>
            <span>{label}{required ? ' *' : ''}</span>
            <input type={type} value={displayValue} onChange={handleChange} />
            {error ? <small>{error}</small> : null}
        </label>
    );
}

function DateField({ label, value, onChange, error, incomplete = false, required = true }) {
    const calendarValue = normalizeDateForSubmit(value);

    function handleTextChange(event) {
        onChange(formatDateDigits(event.target.value));
    }

    function handleCalendarChange(event) {
        onChange(formatDateForDisplay(event.target.value));
    }

    return (
        <label className={`form-field form-grid-span-1${incomplete ? ' form-field-incomplete' : ''}`}>
            <span>{label}{required ? ' *' : ''}</span>
            <div className="date-input-combo">
                <input
                    type="text"
                    inputMode="numeric"
                    placeholder="zz/ll/aaaa"
                    value={formatDateForDisplay(value)}
                    onChange={handleTextChange}
                />
                <input
                    className="date-input-picker"
                    type="date"
                    aria-label={`${label} calendar`}
                    value={/^\d{4}-\d{2}-\d{2}$/.test(calendarValue) ? calendarValue : ''}
                    onChange={handleCalendarChange}
                    tabIndex="-1"
                />
                <span className="date-input-calendar-icon" aria-hidden="true">
                    <Calendar size={18} strokeWidth={2.2} />
                </span>
            </div>
            {error ? <small>{error}</small> : null}
        </label>
    );
}

export default function Form({
    imobile = [],
    spatii = [],
    locatori = [],
    configurariAnexe = {},
    contract = null,
    returnUrl = null,
    initialImobilId = null,
    initialSpatiuId = null,
}) {
    const isEditing = Boolean(contract);
    const [anexaEditDialogOpen, setAnexaEditDialogOpen] = useState(false);
    const [showSecondRepresentative, setShowSecondRepresentative] = useState(() => administratorHasData(contract?.chirias_pj?.administrator_2));
    const initialSpatiu = spatiuInfo(spatii, initialSpatiuId);
    const { data, setData, post, put, processing, errors, transform } = useForm({
        imobil_id: contract?.imobil_id || initialImobilId || initialSpatiu?.imobil_id || '',
        spatiu_id: contract?.spatiu_id || initialSpatiuId || '',
        locator_id: contract?.locator_id || initialSpatiu?.locator_id || '',
        numar_contract: contract?.numar_contract || '',
        chirias_tip: contract?.chirias_tip || 'pj',
        chirias_pf: contract?.chirias_pf || {
            ...emptyPf,
            nume_complet: contract?.chirias_tip === 'pf' ? (contract?.chirias || initialSpatiu?.chirias || '') : '',
        },
        chirias_pj: contract?.chirias_pj || {
            ...emptyPj,
            denumire: contract?.chirias_tip === 'pj' || !contract?.chirias_tip
                ? (contract?.chirias || initialSpatiu?.chirias || '')
                : '',
            administrator: { ...emptyAdministrator },
            administrator_2: { ...emptyAdministrator },
        },
        persoane_declarate: contract?.persoane_declarate ?? initialSpatiu?.persoane_declarate ?? '',
        data_start: contract?.data_start || '',
        data_end: contract?.data_end || '',
        chirie: formatDecimal(contract?.chirie) || formatDecimal(initialSpatiu?.chirie_curenta) || '',
        crestere_chirie_la: formatDecimal(contract?.crestere_chirie_la) || '',
        data_crestere_chirie: contract?.data_crestere_chirie || '',
        moneda: contract?.moneda || initialSpatiu?.moneda || 'EUR',
        observatii: contract?.observatii || '',
        configurare_anexa_id: contract?.configurare_anexa_id || initialSpatiu?.configurare_anexa_id || '',
    });

    transform((formData) => ({
        ...formData,
        data_start: normalizeDateForSubmit(formData.data_start),
        data_end: normalizeDateForSubmit(formData.data_end),
        data_crestere_chirie: normalizeDateForSubmit(formData.data_crestere_chirie),
        return_url: returnUrl || '',
    }));

    const spatiiPentruImobil = data.imobil_id ? spatii.filter((spatiu) => Number(spatiu.imobil_id) === Number(data.imobil_id)) : [];
    const selectedSpatiu = spatiuInfo(spatii, data.spatiu_id);
    const configurariPentruImobil = data.imobil_id ? (configurariAnexe[data.imobil_id] || []) : [];
    const anexaAlocataCurenta = configurariPentruImobil.find(
        (configurare) => Number(configurare.id) === Number(data.configurare_anexa_id),
    ) || null;
    const encodedOriginalReturnUrl = returnUrl ? encodeURIComponent(returnUrl) : '';
    const contractPageUrl = isEditing
        ? `/contracte/${contract.id}/editare${returnUrl ? `?return_url=${encodedOriginalReturnUrl}` : ''}`
        : `/contracte/adauga?imobil_id=${data.imobil_id || ''}${data.spatiu_id ? `&spatiu_id=${data.spatiu_id}` : ''}${returnUrl ? `&return_url=${encodedOriginalReturnUrl}` : ''}`;
    const encodedContractReturnUrl = encodeURIComponent(contractPageUrl);
    const anexaCreateUrl = data.imobil_id && data.spatiu_id
        ? `/configurare-anexa/adauga?imobil_id=${data.imobil_id}&spatiu_id=${data.spatiu_id}&return_url=${encodedContractReturnUrl}`
        : null;
    const anexaEditUrl = data.configurare_anexa_id
        ? `/configurare-anexa/${data.configurare_anexa_id}/editare?return_url=${encodedContractReturnUrl}`
        : null;
    const spatiuEditUrl = data.spatiu_id
        ? `/spatii/${data.spatiu_id}/editare?return_url=${encodedContractReturnUrl}`
        : null;
    const spatiuAdministrativ = selectedSpatiu?.status === 'administrativ';
    const estePf = data.chirias_tip === 'pf';
    const contractStatus = contract?.status || null;
    const isActiveContract = contractStatus === 'activ';
    const isIncompleteContract = contractStatus === 'incomplet';
    const missingKeys = useMemo(() => {
        if (isActiveContract) {
            return [];
        }

        return missingFieldKeysForForm(data, spatiuAdministrativ);
    }, [data, isActiveContract, spatiuAdministrativ]);
    const missingLabels = missingKeys.map((key) => fieldLabels[key] || key);
    const hasMissingFields = missingKeys.length > 0;
    const showIncompleteState = isIncompleteContract && hasMissingFields;
    const showIncompleteHints = showIncompleteState || (isEditing && hasMissingFields);
    const fieldIncomplete = (key) => showIncompleteHints && missingKeys.includes(key);

    function fieldClassName(key, extra = '') {
        return `form-field${extra ? ` ${extra}` : ''}${fieldIncomplete(key) ? ' form-field-incomplete' : ''}`;
    }

    function updatePf(field, value) {
        setData('chirias_pf', { ...data.chirias_pf, [field]: value });
    }

    function updatePj(field, value) {
        setData('chirias_pj', { ...data.chirias_pj, [field]: value });
    }

    function updateAdministrator(field, value) {
        setData('chirias_pj', {
            ...data.chirias_pj,
            administrator: { ...data.chirias_pj.administrator, [field]: value },
        });
    }

    function updateAdministrator2(field, value) {
        setData('chirias_pj', {
            ...data.chirias_pj,
            administrator_2: { ...(data.chirias_pj.administrator_2 || emptyAdministrator), [field]: value },
        });
    }

    function addSecondRepresentative() {
        setShowSecondRepresentative(true);
        if (!data.chirias_pj.administrator_2) {
            setData('chirias_pj', {
                ...data.chirias_pj,
                administrator_2: { ...emptyAdministrator },
            });
        }
    }

    function removeSecondRepresentative() {
        setShowSecondRepresentative(false);
        setData('chirias_pj', {
            ...data.chirias_pj,
            administrator_2: { ...emptyAdministrator },
        });
    }

    function applySpatiu(spatiuId) {
        const spatiu = spatiuInfo(spatii, spatiuId);
        setData({
            ...data,
            spatiu_id: spatiuId,
            locator_id: spatiu?.locator_id || data.locator_id,
            chirias_pf: {
                ...data.chirias_pf,
                nume_complet: spatiu?.chirias || data.chirias_pf.nume_complet,
            },
            chirias_pj: {
                ...data.chirias_pj,
                denumire: spatiu?.chirias || data.chirias_pj.denumire,
            },
            persoane_declarate: spatiu?.status === 'administrativ' ? '' : (spatiu?.persoane_declarate ?? data.persoane_declarate),
            chirie: formatDecimal(spatiu?.chirie_curenta) || data.chirie,
            moneda: 'EUR',
            configurare_anexa_id: spatiu?.configurare_anexa_id || '',
        });
    }

    function openAnexaEditFlow() {
        if (!anexaEditUrl || !anexaAlocataCurenta) {
            return;
        }

        if ((anexaAlocataCurenta.spatii_count ?? 1) <= 1) {
            window.location.href = anexaEditUrl;
            return;
        }

        setAnexaEditDialogOpen(true);
    }

    function editAnexaPartajata() {
        if (!anexaEditUrl) {
            return;
        }

        setAnexaEditDialogOpen(false);
        window.location.href = anexaEditUrl;
    }

    function createAnexaIndividuala() {
        if (!data.spatiu_id) {
            return;
        }

        setAnexaEditDialogOpen(false);
        router.post(`/spatii/${data.spatiu_id}/anexa-individuala`, {
            return_url: contractPageUrl,
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

    const topbarActions = <Link className="secondary-button button-link" href={returnUrl || '/contracte'}>Înapoi</Link>;
    const editTitle = isEditing ? `Editare contract ${contract.numar_contract || ''}`.trim() : '';
    const contractStatusBadge = isEditing && isActiveContract ? (
        <span className="contract-status-topbar-badge contract-status-topbar-badge-activ">Contract Activ. Date complete.</span>
    ) : isEditing && isIncompleteContract && hasMissingFields ? (
        <span className="contract-status-topbar-badge contract-status-topbar-badge-incomplet">Incomplet</span>
    ) : isEditing && isIncompleteContract ? (
        <span className="contract-status-topbar-badge contract-status-topbar-badge-activ">Gata de activare — salvează contractul</span>
    ) : null;
    const topbarTitle = isEditing ? (
        <div className="topbar-page-title">
            <div className="topbar-page-title-row">
                <h1>{editTitle}</h1>
                {contractStatusBadge}
            </div>
            <p>Alege imobilul, apoi spațiul; datele spațiului completează contractul.</p>
        </div>
    ) : null;

    return (
        <AppLayout title={isEditing ? editTitle : 'Adaugă contract'} subtitle="Alege imobilul, apoi spațiul; datele spațiului completează contractul." showGlobalSearch={false} topbarActions={topbarActions} topbarTitle={topbarTitle}>
            <form className="form-card" onSubmit={submit}>
                {isEditing && showIncompleteState ? (
                    <div className="contract-status-banner contract-status-banner-incomplet">
                        <span>
                            {`Contract Incomplet. Mai trebuie: ${missingLabels.slice(0, 5).join(', ')}${missingLabels.length > 5 ? ` (+${missingLabels.length - 5})` : ''}`}
                        </span>
                    </div>
                ) : null}
                {isEditing && isIncompleteContract && !hasMissingFields ? (
                    <div className="contract-status-banner contract-status-banner-incomplet">
                        <span>Date complete în formular. Salvează contractul pentru activare.</span>
                    </div>
                ) : null}

                <div className="form-grid form-grid-chirias">
                    <label className="form-field form-field-full">
                        <span>Imobil *</span>
                        <select value={data.imobil_id} onChange={(event) => {
                            setData({
                                ...data,
                                imobil_id: event.target.value,
                                spatiu_id: '',
                                configurare_anexa_id: '',
                            });
                        }}>
                            <option value="">Alege imobilul</option>
                            {imobile.map((imobil) => <option value={imobil.id} key={imobil.id}>{imobil.label}</option>)}
                        </select>
                    </label>

                    <label className={`${fieldClassName('spatiu_id')} form-grid-span-1`.trim()}>
                        <span>Spațiu *</span>
                        <select value={data.spatiu_id} onChange={(event) => applySpatiu(event.target.value)} disabled={!data.imobil_id}>
                            <option value="">{data.imobil_id ? 'Alege spațiul' : 'Alege mai întâi imobilul'}</option>
                            {spatiiPentruImobil.map((spatiu) => <option value={spatiu.id} key={spatiu.id}>{spatiu.identificator}</option>)}
                        </select>
                        {errors.spatiu_id ? <small>{errors.spatiu_id}</small> : null}
                    </label>

                    <label className={`${fieldClassName('locator_id')} form-grid-span-1`.trim()}>
                        <span>Locator *</span>
                        <select value={data.locator_id} onChange={(event) => setData('locator_id', event.target.value)}>
                            <option value="">Alege locator existent</option>
                            {locatori.map((locator) => <option value={locator.id} key={locator.id}>{locator.nume}</option>)}
                        </select>
                        {errors.locator_id ? <small>{errors.locator_id}</small> : null}
                    </label>

                    <label className={`${fieldClassName('numar_contract')} form-grid-span-1`.trim()}>
                        <span>Număr contract</span>
                        <input type="text" value={data.numar_contract} onChange={(event) => setData('numar_contract', event.target.value)} />
                        {errors.numar_contract ? <small>{errors.numar_contract}</small> : null}
                    </label>

                    <label className={`${fieldClassName('chirie')} form-grid-span-1`.trim()}>
                        <span>Chirie lunară EUR *</span>
                        <input type="number" min="0" step="0.01" value={data.chirie} onChange={(event) => setData('chirie', event.target.value)} />
                        {errors.chirie ? <small>{errors.chirie}</small> : null}
                    </label>

                    <DateField label="Data start" value={data.data_start} onChange={(value) => setData('data_start', value)} error={errors.data_start} incomplete={fieldIncomplete('data_start')} required={false} />

                    <DateField label="Data end" value={data.data_end} onChange={(value) => setData('data_end', value)} error={errors.data_end} incomplete={fieldIncomplete('data_end')} required={false} />

                    <label className="form-field form-grid-span-1">
                        <span>Creștere chirie la</span>
                        <input type="number" min="0" step="0.01" value={data.crestere_chirie_la} onChange={(event) => setData('crestere_chirie_la', event.target.value)} />
                        {errors.crestere_chirie_la ? <small>{errors.crestere_chirie_la}</small> : null}
                    </label>

                    <DateField label="Data creștere chirie" value={data.data_crestere_chirie} onChange={(value) => setData('data_crestere_chirie', value)} error={errors.data_crestere_chirie} required={false} />
                </div>

                <section className="contract-chirias-section">
                    <div className="contract-chirias-heading">
                        <h2>Date chiriaș</h2>
                        <div className="contract-chirias-tip-toggle">
                            <label className="contract-chirias-tip-option">
                                <input
                                    type="radio"
                                    name="chirias_tip"
                                    value="pj"
                                    checked={!estePf}
                                    onChange={() => setData('chirias_tip', 'pj')}
                                />
                                <span>Persoană juridică</span>
                            </label>
                            <label className="contract-chirias-tip-option">
                                <input
                                    type="radio"
                                    name="chirias_tip"
                                    value="pf"
                                    checked={estePf}
                                    onChange={() => setData('chirias_tip', 'pf')}
                                />
                                <span>Persoană fizică</span>
                            </label>
                        </div>
                    </div>

                    {errors.chirias_tip ? <small>{errors.chirias_tip}</small> : null}

                    {estePf ? (
                        <div className="form-grid form-grid-chirias">
                            <PfField label="Nume complet" value={data.chirias_pf.nume_complet} onChange={(value) => updatePf('nume_complet', value)} error={errors['chirias_pf.nume_complet']} incomplete={fieldIncomplete('chirias_pf.nume_complet')} />
                            <PfField label="Serie CI Număr CI, eliberat de, la data." value={data.chirias_pf.serie_ci} onChange={(value) => updatePf('serie_ci', value)} error={errors['chirias_pf.serie_ci']} required incomplete={fieldIncomplete('chirias_pf.serie_ci')} />
                            <PfField label="Domiciliu" value={data.chirias_pf.domiciliu} onChange={(value) => updatePf('domiciliu', value)} error={errors['chirias_pf.domiciliu']} required incomplete={fieldIncomplete('chirias_pf.domiciliu')} />
                            <PfField label="CNP" value={data.chirias_pf.cnp} onChange={(value) => updatePf('cnp', value)} error={errors['chirias_pf.cnp']} required incomplete={fieldIncomplete('chirias_pf.cnp')} />
                            <PfField label="Telefon" value={data.chirias_pf.telefon} onChange={(value) => updatePf('telefon', value)} error={errors['chirias_pf.telefon']} required incomplete={fieldIncomplete('chirias_pf.telefon')} formatter={formatPhoneNumber} />
                            <PfField label="Email" value={data.chirias_pf.email} onChange={(value) => updatePf('email', value)} error={errors['chirias_pf.email']} incomplete={fieldIncomplete('chirias_pf.email')} gridSpan={1} />
                            <PfField label="Email facturare" value={data.chirias_pf.email_2} onChange={(value) => updatePf('email_2', value)} error={errors['chirias_pf.email_2']} gridSpan={1} />
                            <PfField label="Cont bancar" value={data.chirias_pf.cont_bancar} onChange={(value) => updatePf('cont_bancar', value)} error={errors['chirias_pf.cont_bancar']} formatter={formatBankAccount} normalize={normalizeBankAccount} />
                            <PfField label="Banca" value={data.chirias_pf.banca} onChange={(value) => updatePf('banca', value)} error={errors['chirias_pf.banca']} />
                            {!spatiuAdministrativ ? (
                                <label className={fieldClassName('persoane_declarate', 'form-grid-span-2')}>
                                    <span>Persoane declarate de chiriaș *</span>
                                    <input type="number" min="0" step="1" value={data.persoane_declarate} onChange={(event) => setData('persoane_declarate', event.target.value)} />
                                    {errors.persoane_declarate ? <small>{errors.persoane_declarate}</small> : null}
                                </label>
                            ) : null}
                        </div>
                    ) : (
                        <>
                            <div className="form-grid form-grid-chirias">
                                <div className="contract-chirias-pj-primary-row">
                                    <PfField label="Denumire" value={data.chirias_pj.denumire} onChange={(value) => updatePj('denumire', value)} error={errors['chirias_pj.denumire']} incomplete={fieldIncomplete('chirias_pj.denumire')} gridSpan={1} />
                                    <PfField label="Sediul social" value={data.chirias_pj.sediu_social} onChange={(value) => updatePj('sediu_social', value)} error={errors['chirias_pj.sediu_social']} incomplete={fieldIncomplete('chirias_pj.sediu_social')} gridSpan={1} />
                                </div>
                                <PfField label="Telefon" value={data.chirias_pj.telefon} onChange={(value) => updatePj('telefon', value)} error={errors['chirias_pj.telefon']} incomplete={fieldIncomplete('chirias_pj.telefon')} gridSpan={1} formatter={formatPhoneNumber} />
                                <PfField label="Email" value={data.chirias_pj.email} onChange={(value) => updatePj('email', value)} error={errors['chirias_pj.email']} incomplete={fieldIncomplete('chirias_pj.email')} gridSpan={1} />
                                <PfField label="Email facturare" value={data.chirias_pj.email_2} onChange={(value) => updatePj('email_2', value)} error={errors['chirias_pj.email_2']} gridSpan={1} />
                                <PfField label="Registrul Comerțului" value={data.chirias_pj.nr_reg_comert} onChange={(value) => updatePj('nr_reg_comert', value)} error={errors['chirias_pj.nr_reg_comert']} incomplete={fieldIncomplete('chirias_pj.nr_reg_comert')} gridSpan={1} />
                                <PfField label="CUI" value={data.chirias_pj.cui} onChange={(value) => updatePj('cui', value)} error={errors['chirias_pj.cui']} incomplete={fieldIncomplete('chirias_pj.cui')} gridSpan={1} />
                                <PfField label="Cont bancar" value={data.chirias_pj.cont_bancar} onChange={(value) => updatePj('cont_bancar', value)} error={errors['chirias_pj.cont_bancar']} gridSpan={1} formatter={formatBankAccount} normalize={normalizeBankAccount} />
                                <PfField label="Banca" value={data.chirias_pj.banca} onChange={(value) => updatePj('banca', value)} error={errors['chirias_pj.banca']} gridSpan={1} />
                                {!spatiuAdministrativ ? (
                                    <label className={fieldClassName('persoane_declarate', 'form-grid-span-1')}>
                                        <span>Persoane declarate de chiriaș *</span>
                                        <input type="number" min="0" step="1" value={data.persoane_declarate} onChange={(event) => setData('persoane_declarate', event.target.value)} />
                                        {errors.persoane_declarate ? <small>{errors.persoane_declarate}</small> : null}
                                    </label>
                                ) : null}
                            </div>

                            <div className="contract-chirias-subsection">
                                <h3>Reprezentată legal prin</h3>
                                <LegalRepresentativeFields
                                    administrator={data.chirias_pj.administrator}
                                    errorPrefix="chirias_pj.administrator"
                                    errors={errors}
                                    fieldIncomplete={fieldIncomplete}
                                    onUpdate={updateAdministrator}
                                />
                                {showSecondRepresentative ? (
                                    <div className="contract-chirias-subsection contract-chirias-subsection-nested">
                                        <div className="contract-subsection-title-row">
                                            <h4>Al doilea reprezentant</h4>
                                            <button type="button" className="secondary-button" onClick={removeSecondRepresentative}>
                                                Elimină
                                            </button>
                                        </div>
                                        <LegalRepresentativeFields
                                            administrator={data.chirias_pj.administrator_2 || emptyAdministrator}
                                            errorPrefix="chirias_pj.administrator_2"
                                            errors={errors}
                                            fieldIncomplete={fieldIncomplete}
                                            onUpdate={updateAdministrator2}
                                        />
                                    </div>
                                ) : (
                                    <div className="contract-subsection-add-action">
                                        <button type="button" className="secondary-button" onClick={addSecondRepresentative}>
                                            + Adaugă al doilea reprezentant
                                        </button>
                                    </div>
                                )}
                            </div>
                        </>
                    )}
                </section>

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

                {data.spatiu_id ? (
                    <div className="spatiu-documente-zone">
                        <div className="spatiu-documente-row">
                            <span className="spatiu-documente-label">Anexă</span>
                            <div className="spatiu-documente-summary spatiu-documente-summary-anexa">
                                <select
                                    className="spatiu-documente-select"
                                    value={data.configurare_anexa_id}
                                    onChange={(event) => setData('configurare_anexa_id', event.target.value)}
                                    disabled={!data.imobil_id || configurariPentruImobil.length === 0}
                                >
                                    <option value="">{configurariPentruImobil.length ? 'Alege anexa alocată' : 'Nu există anexă pe imobil'}</option>
                                    {configurariPentruImobil.map((configurare) => (
                                        <option value={configurare.id} key={configurare.id}>{configurare.denumire}</option>
                                    ))}
                                </select>
                                {anexaAlocataCurenta ? (
                                    <span className="spatiu-documente-meta">
                                        {anexaAlocataCurenta.linii_count ?? '—'} servicii
                                        {(anexaAlocataCurenta.spatii_count ?? 0) > 1
                                            ? ` · folosită de ${anexaAlocataCurenta.spatii_count} spații`
                                            : ' · doar acest spațiu'}
                                    </span>
                                ) : (
                                    <span className="spatiu-documente-empty">Nicio anexă alocată</span>
                                )}
                                {errors.configurare_anexa_id ? <small>{errors.configurare_anexa_id}</small> : null}
                            </div>
                            <div className="spatiu-documente-actions">
                                {data.configurare_anexa_id ? (
                                    <button type="button" className="secondary-button" onClick={openAnexaEditFlow}>Editează anexa</button>
                                ) : null}
                                {anexaCreateUrl ? (
                                    <Link className="secondary-button button-link" href={anexaCreateUrl}>+ Adaugă anexă</Link>
                                ) : null}
                            </div>
                        </div>
                    </div>
                ) : null}

                <div className="form-footer-actions">
                    <span />
                    <div className="form-actions-column">
                        <div className="form-actions">
                            <Link className="secondary-button button-link" href={returnUrl || '/contracte'}>Anulează</Link>
                            <button className="primary-button" type="submit" disabled={processing}>{processing ? 'Se salvează...' : 'Salvează contract'}</button>
                        </div>
                        {spatiuEditUrl ? (
                            <div className="form-actions">
                                <Link className="secondary-button button-link" href={spatiuEditUrl}>Mergi la spațiu</Link>
                            </div>
                        ) : null}
                    </div>
                </div>
            </form>

            {anexaEditDialogOpen && anexaAlocataCurenta ? (
                <div className="spatiu-dialog-backdrop" onClick={() => setAnexaEditDialogOpen(false)}>
                    <div className="spatiu-dialog-card" onClick={(event) => event.stopPropagation()} role="dialog" aria-modal="true" aria-labelledby="contract-anexa-edit-dialog-title">
                        <h3 id="contract-anexa-edit-dialog-title">Anexa e folosită de {anexaAlocataCurenta.spatii_count} spații</h3>
                        <p>
                            Anexa «{anexaAlocataCurenta.denumire}» e alocată la mai multe spații.
                            Modificările se pot aplica tuturor sau doar acestui spațiu.
                        </p>
                        <div className="spatiu-dialog-actions">
                            <button type="button" className="primary-button" onClick={editAnexaPartajata}>
                                Schimb anexa celor {anexaAlocataCurenta.spatii_count} spații
                            </button>
                            <button type="button" className="secondary-button" onClick={createAnexaIndividuala}>
                                Creez anexă individuală doar pentru acest spațiu
                            </button>
                            <button type="button" className="secondary-button" onClick={() => setAnexaEditDialogOpen(false)}>
                                Anulează
                            </button>
                        </div>
                    </div>
                </div>
            ) : null}
        </AppLayout>
    );
}
