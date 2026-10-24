@extends('documents.legal-layout')

@section('title', 'PRD IMOCORE')

@section('content')
    <h1>Product Requirements Document (PRD)</h1>
    <h1 style="font-size:16px;color:#285948;margin-top:0;">IMOCORE (Imo Core) — v1</h1>

    <div class="meta">
        <p><strong>Produs:</strong> IMOCORE (repo) — interfață „Imo Core”</p>
        <p><strong>Autor / Product Owner:</strong> Dan Iliescu (Git: daniliescu1, citgrup@icloud.com)</p>
        <p><strong>Versiune document:</strong> 1.1 — {{ $generatedAt }}</p>
        <p><strong>Repository:</strong> imocore (Laravel + React/Inertia) — {{ $stats['commits_count'] }} commit-uri, iunie–iulie 2026</p>
        <p><strong>Mediu local:</strong> imocore.test | <strong>Producție:</strong> Laravel Forge (deploy la push)</p>
        <p><strong>Branding:</strong> „Imo Core” = denumire UI; „IMOCORE” = denumire repo/produs; „Operr App” = produs separat planificat, entry point în meniul Owner</p>
    </div>

    <h2>1. Rezumat executiv</h2>
    <p>
        IMOCORE este o aplicație web de administrare a portofoliului imobiliar comercial, destinată gestionării
        integrată a imobilelor, spațiilor închiriate, contractelor, citirilor de contoare, generării anexelor
        de utilități, facturării și documentelor PDF aferente. Aplicația înlocuiește procese manuale
        (Excel, documente disparate) cu un flux digital unic, auditabil, pe baza de date relațională SQLite.
    </p>
    <p>
        IMOCORE include integrarea cu modulul <strong>Operr App</strong> (entry point în meniul Owner),
        ca parte a ecosistemului software de operare imobiliară.
    </p>

    <h2>2. Problema și obiective</h2>
    <h3>2.1 Problema</h3>
    <ul>
        <li>Date dispersate între fișiere, formulare și calcule manuale pentru chirie + utilități.</li>
        <li>Risc de erori la citiri contoare, coeficienți, repartizări configurabile și generare anexe.</li>
        <li>Lipsa unui istoric clar pe lună/imobil/spațiu pentru citiri, anexe și facturi.</li>
    </ul>
    <h3>2.2 Obiective produs</h3>
    <ul>
        <li>Centralizarea datelor operaționale (imobile, spații, contracte, contoare).</li>
        <li>Automatizarea calculelor de anexă (contor, pausal, contor fix, contor configurabil imobil).</li>
        <li>Generare PDF anexe și facturi din aceeași sursă de date.</li>
        <li>Flux lunar: citiri → închidere lună → generare anexe → facturare.</li>
        <li>UI consistent (temă IMO Core v1), fără date mock în listări.</li>
    </ul>

    <h2>3. Utilizatori țintă</h2>
    <table>
        <tr><th>Rol</th><th>Descriere</th><th>Acțiuni principale</th></tr>
        <tr>
            <td>Owner</td>
            <td>Administrator cu vizibilitate financiară completă; în v1, middleware tratează utilizatorul neautentificat ca Owner (<code>isOwner = true</code>)</td>
            <td>CRUD imobile/spații, ștergere imobile, configurare anexe, citiri, anexe, facturi, backup, acces Operr App</td>
        </tr>
        <tr>
            <td>Personal operațional / proprietăți</td>
            <td>Utilizator viitor (multi-user neimplementat în v1); rol pentru introducere citiri și generare documente fără acces financiar complet</td>
            <td>Citiri contoare, generare anexe, export documente</td>
        </tr>
    </table>
    <p>
        <em>Autentificare v1:</em> nu există rute de login în <code>web.php</code>; aplicația rulează fără gate de autentificare,
        iar <code>HandleInertiaRequests</code> expune <code>isOwner = true</code> când <code>$request->user()</code> este null.
    </p>

    <h2>4. Scope produs — maturitate module</h2>
    <p>
        Pipeline-ul central (imobile → spații → configurare → citiri → anexe → facturare) este <strong>production-grade</strong>,
        cu logică de domeniu în clase Support și suite extinsă de teste Feature. Modulele marcate Placeholder sau CRUD simplu
        sunt funcționale la nivel de listă/formular, fără logică business complexă.
    </p>
    <table>
        <tr><th>Modul</th><th>Rută</th><th>Status</th><th>Descriere</th></tr>
        <tr><td>Dashboard</td><td>/</td><td>Implementat</td><td>Pagină de start, acces rapid la module.</td></tr>
        <tr><td>Imobile</td><td>/imobile</td><td>Implementat</td><td>Identitate imobil, adresă, CF cadastral multiplu cu fișiere, numărătoare spații derivate.</td></tr>
        <tr><td>Spații</td><td>/spatii</td><td>Implementat</td><td>Spații reale legate de imobil; status liber/rezervat/inchiriat/comun/administrativ; chirie EUR/RON.</td></tr>
        <tr><td>Configurare anexă</td><td>/configurare-anexa</td><td>Implementat</td><td>Template-uri anexă per imobil: linii servicii, tip calcul, prețuri, curs EUR, personalizare per spațiu.</td></tr>
        <tr><td>Servicii standard</td><td>/configurare-anexa/servicii-standard/{tip}</td><td>Implementat</td><td>Catalog global servicii + variante preț + coeficient cantitate + TVA 21% + P2 gunoi menajer.</td></tr>
        <tr><td>Configurare contoare</td><td>/configurare-contoare</td><td>Implementat</td><td>Contoare configurabile la nivel imobil (alocări spații/persoane).</td></tr>
        <tr><td>Locatori</td><td>/locatori</td><td>Implementat</td><td>Proprietari / entități locator.</td></tr>
        <tr><td>Contracte</td><td>/contracte</td><td>Implementat</td><td>Contracte active pe spații; legătură chiriaș, date contract, completitudine.</td></tr>
        <tr><td>Indexare chirii</td><td>/indexare-chirii</td><td>Implementat</td><td>Introducere indexare anuală (ex. indexare_2026) pe spații închiriate; filtre indexate/neindexate; sursă chirie curentă pentru facturare.</td></tr>
        <tr><td>Persoane declarate</td><td>/persoane-declarate</td><td>Implementat</td><td>Număr persoane declarate per spațiu închiriat; impact direct pe calcule pausal și contabilitate primară.</td></tr>
        <tr><td>Citiri contoare</td><td>/citiri-contoare</td><td>Implementat</td><td>Citiri lunare: contor, pausal, fix, configurabil; preia din lună ulterioară; șterge lună; închide lună.</td></tr>
        <tr><td>Generare anexe</td><td>/anexe</td><td>Implementat</td><td>Generare anexe draft din citiri; luna utilități = luna facturare − 1; PDF download.</td></tr>
        <tr><td>Facturare</td><td>/facturare</td><td>Implementat</td><td>Generare facturi din anexe; chirie fără anexă; conversie EUR→RON la curs BT/manual; PDF facturi.</td></tr>
        <tr><td>Contabilitate primară</td><td>/contabilitate-primara</td><td>Implementat</td><td>Rezumat lunar per imobil: mp închiriați/încălziți, persoane declarate vs standard, total utilități, aprobări.</td></tr>
        <tr><td>Reguli imobile</td><td>/reguli-imobile</td><td>Implementat</td><td>Reguli per imobil: metode curent/apă/canalizare, coeficienți apă pluvială, încălzire parțială, spații comune, RETIM.</td></tr>
        <tr><td>Backup</td><td>/backup</td><td>Implementat</td><td>Backup DB + exporturi (spații, indexare, persoane); cron backup.</td></tr>
        <tr><td>Setări</td><td>/setari</td><td>Implementat</td><td>Setări aplicație (ex. curs EUR facturare).</td></tr>
        <tr><td>Rezervări</td><td>/rezervari</td><td>CRUD simplu</td><td>SimpleCrudController — listă/formular generic, fără logică rezervări avansată.</td></tr>
        <tr><td>PV Predare</td><td>/pv-predare</td><td>CRUD simplu</td><td>SimpleCrudController — procese verbale predare/primire.</td></tr>
        <tr><td>Utilități</td><td>/utilitati</td><td>CRUD simplu</td><td>SimpleCrudController — înregistrări utilități lunare (legat parțial de Contabilitate primară).</td></tr>
        <tr><td>Cheltuieli</td><td>/cheltuieli</td><td>Placeholder</td><td>Pagină UI cu date demo (ModulePage); fără backend dedicat.</td></tr>
        <tr><td>Operr App</td><td>/operr-app</td><td>Placeholder</td><td>Entry point planificat către produs separat Operr App; pagină UI demo în meniul Owner.</td></tr>
    </table>

    <h2>4.1 Context domeniu România</h2>
    <ul>
        <li><strong>CF cadastral:</strong> Carte Funciară multiplă per imobil, cu număr, observații și fișier atașat.</li>
        <li><strong>Chirii EUR/RON:</strong> spațiile comerciale în EUR, parcări în RON; facturarea convertește EUR la curs RON/EUR.</li>
        <li><strong>Curs valutar:</strong> curs EUR salvat manual sau preluat din ultima factură (<code>cursEurBt()</code>); afișat pe PDF factură (referință BCR/BT).</li>
        <li><strong>TVA chirie 21%:</strong> calcul TVA pe linii anexă (<code>tva_21</code>); servicii standard cu tab dedicat TVA.</li>
        <li><strong>Indexare chirii:</strong> coloane anuale (ex. indexare_2026) pentru actualizare chirie; legătură cu index HICP/INS (planificat în Operr App).</li>
        <li><strong>SPV / e-Factura:</strong> referințe planificate în modulul Operr App (integrări SAGA, SPV); neimplementat în pipeline IMOCORE v1.</li>
    </ul>

    <div class="page-break"></div>

    <h2>5. Reguli de business critice</h2>
    <h3>5.1 Imobile și spații</h3>
    <ul>
        <li>Numărătorile de spații pe imobil se calculează din relația <code>spatii</code>, nu manual.</li>
        <li>La mutarea unui spațiu între imobile se recalculează ambele imobile.</li>
        <li><code>rezervat</code> contează ca liber în logica de disponibilitate (implicit).</li>
        <li>CF: multiple numere, observații, atașament fișier; UI ascunde input după upload.</li>
    </ul>
    <h3>5.2 Citiri contoare</h3>
    <ul>
        <li>Tipuri linie: Contor, Pausal, Contor fix, Contor configurabil (imobil).</li>
        <li>Luna editabilă până la „Salvează și închide”; după închidere read-only.</li>
        <li>Coloana listă „Ultima lună închisă” (nu max luna din DB).</li>
        <li>Preia din lună ulterioară: copiere valori din cea mai apropiată lună cu citiri salvate, ulterioară celei curente.</li>
        <li>Ștergere citiri lună neînchisă (buton dedicat).</li>
        <li>Index vechi contor = index nou luna anterioară (automat la lună nouă).</li>
    </ul>
    <h3>5.3 Anexe și facturare</h3>
    <ul>
        <li>Selectare facturare Iunie → utilități Mai; anexa se salvează cu <code>luna = luna utilități</code>.</li>
        <li>Coeficient cantitate (ex. 120% = multiplicator 1.2); variantă preț per imobil.</li>
        <li>Gunoi menajer: P1 + (N−1)×P2 opțional, strict pe serviciul dedicat.</li>
        <li>Facturi: din anexă sau doar chirie (contract fără anexă alocată).</li>
        <li>PDF anexe/facturi via DomPDF.</li>
    </ul>

    <h2>6. Fluxuri utilizator principale</h2>
    <ol>
        <li><strong>Setup imobil:</strong> Imobil → Spații → Configurare anexă → Servicii standard → Configurare contoare.</li>
        <li><strong>Contract:</strong> Contract activ pe spațiu inchiriat cu anexă alocată.</li>
        <li><strong>Luna operațională:</strong> Citiri contoare → salvare → opțional preia/șterge → închidere lună.</li>
        <li><strong>Anexă:</strong> Generare anexe (luna facturare) → verificare draft → PDF.</li>
        <li><strong>Factură:</strong> Generare facturi → PDF → arhivare.</li>
        <li><strong>Corecție retroactivă:</strong> Preia din lună ulterioară în lună anterioară goală → salvează → șterge citiri lună ulterioară greșită.</li>
    </ol>

    <h2>7. Arhitectură tehnică</h2>
    <table>
        <tr><th>Strat</th><th>Tehnologie</th><th>Detalii</th></tr>
        <tr><td>Backend</td><td>PHP {{ $phpVersion }}, Laravel {{ $laravelVersion }}</td><td>MVC, Eloquent ORM, validare request, Inertia responses</td></tr>
        <tr><td>Frontend</td><td>React {{ $reactVersion }}, Inertia.js {{ $inertiaVersion }}, Vite {{ $viteVersion }}</td><td>SPA-like, fără API REST separat pentru UI principal</td></tr>
        <tr><td>Bază de date</td><td>SQLite</td><td>Local: database/database.sqlite; teste: database_testing.sqlite separat</td></tr>
        <tr><td>PDF</td><td>barryvdh/laravel-dompdf ^3.1</td><td>Anexe, facturi, documente legale</td></tr>
        <tr><td>Export</td><td>openspout/openspout ^5.7</td><td>Export tabular (backup/export)</td></tr>
        <tr><td>Teste</td><td>PHPUnit ^10.5</td><td>{{ $stats['feature_tests_count'] }} Feature + {{ $stats['unit_tests_count'] }} Unit = {{ $stats['tests_total_count'] }} teste</td></tr>
        <tr><td>Deploy</td><td>Laravel Forge</td><td>npm run build la deploy; public/build gitignored local</td></tr>
    </table>

    <h3>7.1 Statistici cod sursă ({{ $generatedAt }})</h3>
    <table>
        <tr><th>Componentă</th><th>Fișiere</th><th>Linii cod</th></tr>
        <tr><td>Backend PHP (app/)</td><td>{{ $stats['app_files'] }}</td><td>{{ number_format($stats['app_lines']) }}</td></tr>
        <tr><td>Frontend JS/JSX (resources/js/)</td><td>{{ $stats['js_files'] }}</td><td>{{ number_format($stats['js_lines']) }}</td></tr>
        <tr><td>CSS (resources/css/)</td><td>{{ $stats['css_files'] }}</td><td>{{ number_format($stats['css_lines']) }}</td></tr>
        <tr><td>Blade / views (resources/views/)</td><td>{{ $stats['views_files'] }}</td><td>{{ number_format($stats['views_lines']) }}</td></tr>
        <tr><td>Rute (routes/)</td><td>{{ $stats['routes_files'] }}</td><td>{{ number_format($stats['routes_lines']) }}</td></tr>
        <tr><td><strong>Total aplicație (fără teste)</strong></td><td>—</td><td><strong>{{ number_format($stats['app_total_lines']) }}</strong></td></tr>
        <tr><td>Teste (tests/)</td><td>{{ $stats['tests_files'] }}</td><td>{{ number_format($stats['tests_lines']) }}</td></tr>
        <tr><td>Migrări DB (database/migrations/)</td><td>{{ $stats['migrations_count'] }}</td><td>{{ number_format($stats['migrations_lines']) }}</td></tr>
        <tr><td><strong>Total proiect (custom)</strong></td><td>{{ $stats['custom_files_count'] }} fișiere</td><td><strong>{{ number_format($stats['grand_total_lines']) }}</strong></td></tr>
    </table>

    <h3>7.2 Componente backend</h3>
    <table>
        <tr><th>Tip</th><th>Număr</th><th>Exemple</th></tr>
        <tr><td>Modele Eloquent</td><td>{{ $stats['models_count'] }}</td><td>Imobil, Spatiu, Contract, CitireContor, Anexa, Factura…</td></tr>
        <tr><td>Controllere HTTP</td><td>{{ $stats['controllers_count'] }}</td><td>ImobilController, CitireContorController, AnexaController…</td></tr>
        <tr><td>Clase Support (logică business)</td><td>{{ $stats['support_count'] }}</td><td>GenerareAnexaLinieCalculator, TipCalculAnexa, ContorConfigurabilSync…</td></tr>
        <tr><td>Pagini React (resources/js/Pages)</td><td>{{ $stats['jsx_pages'] }}</td><td>Imobile, Spatii, CitiriContoare/Imobil, Anexe, Facturare…</td></tr>
        <tr><td>Commit-uri Git</td><td>{{ $stats['commits_count'] }}</td><td>Autor unic: Dan Iliescu (daniliescu1, citgrup@icloud.com), iunie–iulie 2026</td></tr>
        <tr><td>Prima commit</td><td colspan="2">{{ $stats['first_commit_date'] }}</td></tr>
        <tr><td>Ultima commit</td><td colspan="2">{{ $stats['last_commit_date'] }}</td></tr>
    </table>

    <h3>7.3 Modele de date (entități principale)</h3>
    <p>{{ implode(', ', $models) }}</p>

    <h3>7.4 Clase Support (logică de domeniu)</h3>
    <p>{{ implode(', ', $supportClasses) }}</p>

    <div class="page-break"></div>

    <h2>8. UI / UX (IMO Core v1)</h2>
    <ul>
        <li>Fundal cream, sidebar/header verde închis (#00553f), accent portocaliu unde e stabilit.</li>
        <li>Carduri albe/cream, colțuri rotunjite, umbre subtile, tabele curate.</li>
        <li>Header partajat: titlu + subtitlu în topbar; filtre/acțiuni lângă Owner.</li>
        <li>Rânduri listă clickabile pe toată lățimea → edit/details.</li>
        <li>Fără date demo; empty-state când nu există înregistrări.</li>
        <li>Butoane funcționale doar dacă există rută/pagină.</li>
    </ul>

    <h2>9. Cerințe non-funcționale</h2>
    <table>
        <tr><th>Categorie</th><th>Cerință</th></tr>
        <tr><td>Performanță</td><td>Listări paginate/filtrate; build Vite pentru assets producție.</td></tr>
        <tr><td>Siguranță date</td><td>DB locală protejată; fără migrate automat pe DB real; backup înainte de operații destructive.</td></tr>
        <tr><td>Testare</td><td>PHPUnit pe DB de test separat; feature tests pentru fluxuri critice (citiri, anexe, facturare).</td></tr>
        <tr><td>Portabilitate</td><td>Laravel standard; SQLite ușor de backup/restore.</td></tr>
        <tr><td>Mentenanță</td><td>Reguli produs în .cursor/rules; deploy Forge la push.</td></tr>
    </table>

    <h2>10. Out of scope v1 / roadmap</h2>
    <ul>
        <li>Multi-utilizator cu roluri granulare (doar Owner implicit; neautentificat = Owner).</li>
        <li>Autentificare / login (fără rute auth în web.php).</li>
        <li>Sincronizare automată apă → canalizare la citiri (cerut, neimplementat).</li>
        <li>Integrare contabilitate externă și SPV/e-Factura (planificat în Operr App).</li>
        <li>Indexare automată HICP/INS (planificat în Operr App).</li>
        <li>Aplicație mobilă nativă.</li>
        <li>Module placeholder: Cheltuieli, Operr App — extinderi viitoare cu backend dedicat.</li>
    </ul>

    <h2>11. Criterii de succes</h2>
    <ul>
        <li>Generare anexă corectă pentru toate tipurile de calcul pe date reale.</li>
        <li>Flux citiri → anexă → factură fără reintroducere manuală a indexurilor.</li>
        <li>PDF anexe/facturi conform template-ului IMO Core.</li>
        <li>Zero pierdere date la backup/restore local.</li>
        <li>Toate testele Feature critice trec la release.</li>
    </ul>

    <div class="footer">
        Document generat automat din codebase IMOCORE — {{ $generatedAt }} — Dan Iliescu
    </div>
@endsection
