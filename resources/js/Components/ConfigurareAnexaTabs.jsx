import React, { useEffect } from 'react';
import { Link, router, useForm } from '@inertiajs/react';
import { formatDecimal } from '../lib/formatDecimal';

const serviciiStandardTabs = [
    { key: 'denumire', label: 'Denumire serviciu', href: '/configurare-anexa/servicii-standard/denumire' },
    { key: 'um', label: 'UM', href: '/configurare-anexa/servicii-standard/um' },
    { key: 'tva', label: 'TVA', href: '/configurare-anexa/servicii-standard/tva' },
    { key: 'tip_calcul', label: 'Tip calcul', href: '/configurare-anexa/servicii-standard/tip_calcul' },
    { key: 'pret', label: 'Prețuri', href: '/configurare-anexa/servicii-standard/pret' },
];

export default function ConfigurareAnexaTabs({ tipActiv = null, cursImplicit = 5 }) {
    const { data, setData, processing } = useForm({
        curs_eur: formatDecimal(cursImplicit),
    });

    useEffect(() => {
        setData('curs_eur', formatDecimal(cursImplicit));
    }, [cursImplicit, setData]);

    function applyCurs(event) {
        event.preventDefault();
        router.put('/configurare-anexa/curs', { curs_eur: data.curs_eur }, { preserveScroll: true });
    }

    return (
        <div className="standard-tabs-row">
            <nav className="standard-tabs">
                {serviciiStandardTabs.map((tab) => (
                    <Link
                        key={tab.key}
                        href={tab.href}
                        className={`standard-tab${tab.key === tipActiv ? ' is-active' : ''}`}
                    >
                        {tab.label}
                    </Link>
                ))}
            </nav>
            <form className="standard-tabs-curs" onSubmit={applyCurs}>
                <span className="standard-tabs-curs-heading">
                    <strong>Curs valutar Banca Transilvania</strong>
                    <span className="standard-tabs-curs-subtext">introdus manual</span>
                </span>
                <input
                    className="standard-tabs-curs-input"
                    type="text"
                    inputMode="decimal"
                    aria-label="Curs valutar Banca Transilvania"
                    value={data.curs_eur}
                    onChange={(event) => setData('curs_eur', event.target.value.replace(',', '.'))}
                    onBlur={() => setData('curs_eur', formatDecimal(data.curs_eur))}
                />
                <button className="secondary-button" type="submit" disabled={processing}>
                    Aplică
                </button>
            </form>
        </div>
    );
}
