import React from 'react';
import AppLayout from '../Layouts/AppLayout';

const pages = {
    Imobile: {
        subtitle: 'Administrare nume imobil și adresă',
        count: 2,
        action: '+ Imobil nou',
        filters: ['Localitate'],
        columns: ['Nume imobil', 'Adresă', 'Nr. CF', 'Nr. topo', 'Spații', 'Libere', 'Închiriate'],
        rows: [
            ['Dumbrăvița Office Conac 60', 'Str. Conac 60, Dumbrăvița', '123456', '7890', '60', '6', '52'],
            ['700 Office Gheorghe Lazăr 9', 'Str. Gheorghe Lazăr 9, Timișoara', '234567', '8901', '187', '12', '171'],
        ],
    },
    'Spații': {
        subtitle: 'Administrare spații închiriabile',
        count: 247,
        action: '+ Spațiu nou',
        filters: ['Localitate', 'Caută identificat la locator sau chiriaș...'],
        tabs: ['Toate (247)', 'Libere (18)', 'Rezervate (6)', 'Închiriate (223)'],
        columns: ['Identificat la locator', 'Imobil', 'Suprafață', 'Status', 'Preț / lună', 'Locator', 'Chiriaș'],
        rows: [
            ['HQD 12', 'Dumbrăvița Office Conac 60', '120 mp', 'LIBER', '1200 EUR', 'SC Proprietate Alpha', '—'],
            ['HQD 98', 'Dumbrăvița Office Conac 60', '55 mp', 'REZERVAT', '950 EUR', 'SC Proprietate Alpha', 'SC Alpha SRL'],
            ['HQD 89', 'Dumbrăvița Office Conac 60', '95 mp', 'ÎNCHIRIAT', '1200 EUR', 'SC Proprietate Alpha', 'SC Beta SRL'],
            ['TMI 156', '700 Office Gheorghe Lazăr 9', '110 mp', 'ÎNCHIRIAT', '1450 EUR', 'SC Proprietate Beta', 'SC Gamma SA'],
            ['TMI 3', '700 Office Gheorghe Lazăr 9', '50 mp', 'LIBER', '620 EUR', 'SC Proprietate Beta', '—'],
        ],
    },
    'Rezervări': {
        subtitle: 'Garanții și termen semnare contract',
        action: '+ Rezervare nouă',
        tabs: ['Active', 'Expirate', 'Anulate', 'Transformate'],
        columns: ['Spațiu', 'Imobil', 'Prospect', 'Garanție', 'Termen semnare', 'Zile rămase', 'Plată'],
        rows: [
            ['HQD 98', 'Dumbrăvița Office Conac 60', 'SC Alpha SRL', '950 EUR', '15.07.2026', '9 zile', 'Încasat'],
            ['TMI 22', '700 Office Gheorghe Lazăr 9', 'Popescu Ion', '780 EUR cash', '01.08.2026', '27 zile', 'Neîncasat'],
        ],
    },
    Contracte: {
        subtitle: 'Contracte de închiriere active și istoric',
        action: '+ Contract nou',
        tabs: ['Active', 'Expirate', 'În grație', 'De indexat'],
        columns: ['Nr. contract', 'Spațiu', 'Imobil', 'Chiriaș', 'Chirie EUR', 'Perioadă', 'Indexare'],
        rows: [
            ['C-2024-089', 'HQD 89', 'Dumbrăvița Office Conac 60', 'SC Beta SRL', '1200', '01.01.2024 – 31.12.2026', 'HICP'],
            ['C-2023-156', 'TMI 156', '700 Office Gheorghe Lazăr 9', 'SC Gamma SA', '1450', '15.03.2023 – 22.09.2026', 'INS România'],
        ],
    },
    'PV Predare': {
        subtitle: 'Procese-verbale predare și primire spații',
        action: '+ PV nou',
        tabs: ['Predare', 'Primire'],
        columns: ['Spațiu', 'Imobil', 'Tip', 'Data', 'Status', 'Contoare'],
        rows: [
            ['HQD 12', 'Dumbrăvița Office Conac 60', 'Predare către chiriaș', '05.06.2026', 'Semnat', 'Da'],
            ['HQD 89', 'Dumbrăvița Office Conac 60', 'Primire de la chiriaș', '28.12.2023', 'Draft', 'Da'],
        ],
    },
    'Utilități': {
        subtitle: 'Contracte furnizori și citiri contoare',
        action: 'Introdu citire',
        tabs: ['Contracte furnizori', 'Citiri lunare'],
        columns: ['Imobil', 'Contor', 'Tip', 'Index vechi', 'Index nou', 'Consum', 'Status'],
        rows: [
            ['Dumbrăvița Office Conac 60', 'Contor electric principal', 'Curent', '125430', '126890', '1460 kW', 'Complet'],
            ['700 Office Gheorghe Lazăr 9', 'Contor gaz', 'Gaz', '1200', '1285', '85 mc', 'Lipsă poză'],
        ],
    },
    Anexe: {
        subtitle: 'Anexe calculate per spațiu și lună',
        action: 'Generează anexe',
        columns: ['Spațiu', 'Imobil', 'Chiriaș', 'Lună', 'Total RON', 'Status'],
        rows: [
            ['HQD 89', 'Dumbrăvița Office Conac 60', 'SC Beta SRL', '05.2026', '2.450 RON', 'Emisă'],
            ['TMI 156', '700 Office Gheorghe Lazăr 9', 'SC Gamma SA', '05.2026', '3.120 RON', 'Emisă'],
        ],
    },
    Facturare: {
        subtitle: 'Facturi chirie și utilități — iunie 2026',
        action: 'Aprobă trimitere',
        columns: ['Chiriaș', 'Spațiu', 'Chirie RON', 'Anexă RON', 'Total RON', 'Status', 'SPV', 'Email'],
        rows: [
            ['SC Beta SRL', 'HQD 89', '6.120', '2.450', '8.570', 'OK', 'Da', 'Trimis'],
            ['SC Zeta', 'TMI 88', '4.990', '2.780', '7.770', 'Eroare', 'Nu', 'Blocat'],
        ],
    },
    'Contabilitate primară': {
        subtitle: 'Verificare lunară pe adresă',
        action: 'Aplică prețuri',
        columns: ['Secțiune', 'Indicator 1', 'Indicator 2', 'Diferență', 'Status'],
        rows: [
            ['Chirie', '5.420 mp', '48.500 EUR', '0', 'OK'],
            ['Curent electric', '1.460 kW', '1.320 kW citite', '140 kW', 'OK'],
            ['Apă', '125 mc total', '98 mc contoare', '27 mc', 'Verificare'],
        ],
    },
    Cheltuieli: {
        subtitle: 'Cheltuieli pe imobil și spațiu',
        action: '+ Cheltuială nouă',
        columns: ['Data', 'Imobil', 'Spațiu', 'Tip', 'Denumire', 'Sumă', 'Plată'],
        rows: [
            ['03.06.2026', 'Dumbrăvița Office Conac 60', '—', 'Operațională', 'Curățenie exterioară', '2.400 RON', 'OP bancă'],
            ['20.05.2026', 'Dumbrăvița Office Conac 60', '—', 'Investiție', 'Amenajare parcare', '45.000 RON', 'OP'],
        ],
    },
    'Indexare chirii': {
        subtitle: 'Propuneri de indexare anuală',
        action: 'Aprobă selecție',
        columns: ['Imobil / Spațiu', 'Chiriaș', 'Vechime', 'Tip indexare', 'Chirie actuală', 'Procent', 'Chirie nouă', 'Status'],
        rows: [
            ['HQD 89 Dumbrăvița', 'SC Beta SRL', '24 luni', 'HICP', '1200 EUR', '5,2%', '1262 EUR', 'Propus'],
            ['TMI 156 700 Office', 'SC Gamma SA', '36 luni', 'INS', '1450 EUR', '4,8%', '1520 EUR', 'Aprobat'],
        ],
    },
    'Setări': {
        subtitle: 'Configurare aplicație IMO Core',
        action: 'Salvează',
        columns: ['Secțiune', 'Descriere', 'Status'],
        rows: [
            ['General', 'Nume aplicație, logo, culori', 'Configurat'],
            ['Curs valutar', 'URL BT și aprobare curs', 'De configurat'],
            ['Integrări', 'SAGA, SPV, WhatsApp, Operr App', 'Planificat'],
        ],
    },
    'Operr App': {
        subtitle: 'Taskuri, mentenanță, sesizări și intervenții operaționale',
        action: 'Deschide Operr App',
        columns: ['Zonă', 'Descriere', 'Status'],
        rows: [
            ['Legătură', 'Pagină dedicată pentru integrarea cu Operr App', 'Planificat'],
            ['Preview taskuri', 'Ultimele intervenții pe imobile', 'Planificat'],
        ],
    },
};

function renderStatus(value) {
    const key = String(value).toLowerCase();
    if (['liber', 'încasat', 'semnat', 'complet', 'ok', 'emisă', 'trimis', 'aprobat', 'configurat'].includes(key)) {
        return <span className="badge badge-success">{value}</span>;
    }
    if (['rezervat', 'draft', 'verificare', 'propus', 'de configurat', 'planificat'].includes(key)) {
        return <span className="badge badge-warning">{value}</span>;
    }
    if (['închiriat'].includes(key)) {
        return <span className="badge badge-info">{value}</span>;
    }
    if (['neîncasat', 'lipsă poză', 'eroare', 'blocat'].includes(key)) {
        return <span className="badge badge-danger">{value}</span>;
    }
    return value;
}

export default function ModulePage({ module }) {
    const page = pages[module] || pages.Imobile;
    const title = typeof page.count === 'number' ? `${module} (${page.count})` : module;
    const topbarActions = page.action ? <button className="primary-button" type="button">{page.action}</button> : null;

    return (
        <AppLayout title={title} subtitle={page.subtitle} showGlobalSearch={false} topbarActions={topbarActions}>
            {page.filters ? (
                <div className="filter-row">
                    {page.filters.map((filter) => (
                        filter.includes('Caută') ? (
                            <input key={filter} className="filter-input" type="search" placeholder={filter} />
                        ) : (
                            <select key={filter} className="filter-input" defaultValue="">
                                <option value="">{filter}: Toate</option>
                            </select>
                        )
                    ))}
                </div>
            ) : null}

            {page.tabs ? (
                <div className="tabs-row">
                    {page.tabs.map((tab, index) => <button className={`tab-button ${index === 0 ? 'is-active' : ''}`} type="button" key={tab}>{tab}</button>)}
                </div>
            ) : null}

            <section className="table-card module-table-card">
                <div className="responsive-table">
                    <table>
                        <thead>
                            <tr>
                                {page.columns.map((column) => <th key={column}>{column}</th>)}
                            </tr>
                        </thead>
                        <tbody>
                            {page.rows.map((row, rowIndex) => (
                                <tr key={`${module}-${rowIndex}`}>
                                    {row.map((cell, cellIndex) => <td key={`${module}-${rowIndex}-${cellIndex}`}>{renderStatus(cell)}</td>)}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </section>
        </AppLayout>
    );
}