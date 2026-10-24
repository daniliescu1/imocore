@extends('documents.legal-layout')

@section('title', 'Drepturi de autor IMOCORE')

@section('content')
    <h1>Declarație privind drepturile de autor</h1>
    <h1 style="font-size:16px;color:#285948;margin-top:0;">Aplicația IMOCORE (Imo Core) și modulul Operr App</h1>

    <div class="meta">
        <p><strong>Titular drepturi de autor:</strong> Dan Iliescu</p>
        <p><strong>Email:</strong> citgrup@icloud.com</p>
        <p><strong>Cont Git:</strong> daniliescu1</p>
        <p><strong>Denumire produs:</strong> IMOCORE (repo) — interfață „Imo Core”</p>
        <p><strong>Modul planificat:</strong> Operr App — produs separat, entry point în meniul Owner (rută /operr-app, placeholder UI v1)</p>
        <p><strong>Data document:</strong> {{ $generatedAt }}</p>
        <p><strong>Perioada dezvoltare (Git):</strong> {{ $stats['first_commit_date'] }} — {{ $stats['last_commit_date'] }} ({{ $stats['commits_count'] }} commit-uri, autor unic)</p>
    </div>

    <h2>1. Obiectul protecției</h2>
    <p>
        Prezentul document descrie obiectul asupra căruia Dan Iliescu deține drepturile de autor,
        în calitate de autor și dezvoltator al aplicației software <strong>IMOCORE</strong>,
        inclusiv a componentelor frontend, backend, bazei de date, regulilor de business,
        interfeței utilizator, documentelor PDF generate și a modulului <strong>Operr App</strong>
        integrat în aceeași aplicație.
    </p>
    <p>
        IMOCORE este o aplicație web proprietară de administrare imobiliară (imobile, spații, contracte,
        citiri contoare, anexe utilități, facturare), dezvoltată de la zero ca proiect Laravel + React,
        fără a fi un produs generic off-the-shelf.
    </p>

    <h2>2. Componente protejate</h2>
    <table>
        <tr><th>Categorie</th><th>Descriere</th></tr>
        <tr><td>Cod sursă backend</td><td>Controllere, modele Eloquent, migrări, validări, rute, comenzi artisan, logică PDF</td></tr>
        <tr><td>Cod sursă frontend</td><td>Pagini React/Inertia, layout, componente UI, formulare, tabele, fluxuri interactive</td></tr>
        <tr><td>Logică de domeniu</td><td>Clase Support: calcule anexă, tipuri contor, coeficienți, prețuri standard, sincronizări</td></tr>
        <tr><td>Design UI</td><td>Tema IMO Core v1 (cream, verde #00553f, componente vizuale, structură sidebar/topbar)</td></tr>
        <tr><td>Template-uri documente</td><td>Blade PDF anexe, facturi, layout documente</td></tr>
        <tr><td>Schema bazei de date</td><td>{{ $stats['migrations_count'] }} migrări Laravel, relații între entități</td></tr>
        <tr><td>Suite de teste</td><td>{{ $stats['tests_files'] }} fișiere ({{ number_format($stats['tests_lines']) }} linii): {{ $stats['feature_tests_count'] }} Feature + {{ $stats['unit_tests_count'] }} Unit</td></tr>
        <tr><td>Documentație produs</td><td>Reguli .cursor/rules, PRD, prezent document</td></tr>
        <tr><td>Operr App</td><td>Entry point planificat către produs separat; pagină placeholder în IMOCORE v1 (UI demo, fără backend dedicat)</td></tr>
    </table>

    <h2>3. Statistici tehnice — volum cod (măsurat {{ $generatedAt }})</h2>
    <table>
        <tr><th>Componentă</th><th>Fișiere</th><th>Linii de cod</th></tr>
        <tr><td>PHP backend — app/</td><td>{{ $stats['app_files'] }}</td><td>{{ number_format($stats['app_lines']) }}</td></tr>
        <tr><td>JavaScript/JSX — resources/js/</td><td>{{ $stats['js_files'] }}</td><td>{{ number_format($stats['js_lines']) }}</td></tr>
        <tr><td>CSS — resources/css/</td><td>{{ $stats['css_files'] }}</td><td>{{ number_format($stats['css_lines']) }}</td></tr>
        <tr><td>Blade views — resources/views/</td><td>{{ $stats['views_files'] }}</td><td>{{ number_format($stats['views_lines']) }}</td></tr>
        <tr><td>Rute — routes/</td><td>{{ $stats['routes_files'] }}</td><td>{{ number_format($stats['routes_lines']) }}</td></tr>
        <tr><td><strong>Subtotal aplicație (cod operațional)</strong></td><td>—</td><td><strong>{{ number_format($stats['app_total_lines']) }}</strong></td></tr>
        <tr><td>Teste automate — tests/</td><td>{{ $stats['tests_files'] }}</td><td>{{ number_format($stats['tests_lines']) }}</td></tr>
        <tr><td>Migrări bază de date</td><td>{{ $stats['migrations_count'] }}</td><td>{{ number_format($stats['migrations_lines']) }}</td></tr>
        <tr><td><strong>TOTAL general cod custom IMOCORE</strong></td><td>{{ $stats['custom_files_count'] }} fișiere</td><td><strong>{{ number_format($stats['grand_total_lines']) }}</strong></td></tr>
    </table>
    <p>
        <em>Notă:</em> statisticile exclud <code>vendor/</code>, <code>node_modules/</code> și <code>public/build/</code>
        (dependențe terțe și artefacte de build). Reprezintă cod scris/maintained în proiectul IMOCORE.
    </p>

    <h2>4. Inventar tehnic detaliat</h2>
    <table>
        <tr><th>Element</th><th>Valoare</th></tr>
        <tr><td>Commit-uri Git (autor daniliescu1)</td><td>{{ $stats['commits_count'] }}</td></tr>
        <tr><td>Prima commit</td><td>{{ $stats['first_commit_date'] }}</td></tr>
        <tr><td>Ultima commit (la generare document)</td><td>{{ $stats['last_commit_date'] }}</td></tr>
        <tr><td>Modele Eloquent (app/Models/)</td><td>{{ $stats['models_count'] }}</td></tr>
        <tr><td>Controllere HTTP</td><td>{{ $stats['controllers_count'] }}</td></tr>
        <tr><td>Clase Support (app/Support/)</td><td>{{ $stats['support_count'] }}</td></tr>
        <tr><td>Pagini React (resources/js/Pages/)</td><td>{{ $stats['jsx_pages'] }}</td></tr>
        <tr><td>Fișiere Feature tests</td><td>{{ $stats['feature_tests_count'] }}</td></tr>
        <tr><td>Fișiere Unit tests</td><td>{{ $stats['unit_tests_count'] }}</td></tr>
        <tr><td>Total teste automate</td><td>{{ $stats['tests_total_count'] }}</td></tr>
        <tr><td>PHP runtime</td><td>{{ $phpVersion }}</td></tr>
        <tr><td>Laravel Framework</td><td>{{ $laravelVersion }}</td></tr>
        <tr><td>React</td><td>{{ $reactVersion }}</td></tr>
        <tr><td>Inertia.js (Laravel + React)</td><td>{{ $inertiaVersion }}</td></tr>
        <tr><td>Vite</td><td>{{ $viteVersion }}</td></tr>
        <tr><td>Bază de date</td><td>SQLite (database/database.sqlite)</td></tr>
        <tr><td>Generare PDF</td><td>barryvdh/laravel-dompdf ^3.1</td></tr>
    </table>

    <h3>4.1 Modele de date (app/Models/)</h3>
    <p>{{ implode(', ', $models) }}</p>

    <h3>4.2 Controllere (app/Http/Controllers/)</h3>
    <p>{{ implode(', ', $controllers) }}</p>

    <h3>4.3 Clase Support — logică business proprietară</h3>
    <p>{{ implode(', ', $supportClasses) }}</p>

    <div class="page-break"></div>

    <h2>5. Funcționalități dezvoltate (rezumat pentru atribuire paternitate)</h2>
    <p>Următoarele module au fost concepute și implementate în cadrul IMOCORE:</p>
    <h3>5.1 Pipeline production-grade</h3>
    <ul>
        <li>Gestionare imobile cu CF cadastral multiplu și fișiere atașate</li>
        <li>Gestionare spații cu recalcul automat numărător imobil; chirii EUR/RON</li>
        <li>Configurare anexă cu linii, tip calcul (contor/pausal/fix/configurabil), personalizare per spațiu</li>
        <li>Catalog servicii standard cu variante preț, coeficient cantitate, TVA 21%, preț persoană suplimentară (gunoi)</li>
        <li>Citiri contoare lunare cu închidere lună, preia din lună ulterioară, ștergere lună</li>
        <li>Generare anexe utilități cu calcule complexe (GenerareAnexaLinieCalculator)</li>
        <li>Facturare din anexe + facturare chirie fără anexă; conversie EUR→RON la curs BT/manual</li>
        <li>Indexare chirii anuală, persoane declarate, reguli imobile, contabilitate primară</li>
        <li>Export/backup date, PDF anexe și facturi</li>
        <li>Interfață IMO Core v1 (React + CSS custom ~{{ number_format($stats['css_lines']) }} linii)</li>
    </ul>
    <h3>5.2 Module auxiliare</h3>
    <ul>
        <li>CRUD simplu (SimpleCrudController): Rezervări, PV Predare, Utilități — listă/formular generic</li>
        <li>Placeholder UI: Cheltuieli, Operr App — pagini demo (ModulePage), fără backend dedicat în v1</li>
    </ul>
    <h3>5.3 Context domeniu România implementat</h3>
    <ul>
        <li>CF cadastral, chirii EUR/RON, TVA chirie 21%, curs valutar EUR/RON pentru facturare</li>
        <li>Referințe planificate HICP/INS indexation, SPV/e-Factura în modulul Operr App (produs separat)</li>
    </ul>

    <h2>6. Drepturi recunoscute (descriere)</h2>
    <p>În sensul legii dreptului de autor (Legea nr. 8/1996, România, transpunere directive UE), autorul Dan Iliescu deține, în calitate de creator al programului de calculator IMOCORE:</p>
    <ul>
        <li><strong>Dreptul de autor moral:</strong> dreptul de a fi recunoscut ca autor al aplicației; dreptul la integritatea operei (opoziție la modificări prejudiciabile); dreptul de a decide asupra divulgării.</li>
        <li><strong>Dreptul de autor patrimonial:</strong> dreptul exclusiv de a autoriza sau interzice reproducerea, distribuirea, comunicarea publică, transformarea (inclusiv dezvoltări derivate) și exploatarea economică a programului.</li>
        <li><strong>Drepturi asupra codului sursă:</strong> codul PHP, JavaScript/JSX, CSS, Blade, migrări și teste enumerate în secțiunea 3.</li>
        <li><strong>Drepturi asupra bazei de date:</strong> schema și structura relațională definită prin migrări.</li>
        <li><strong>Drepturi asupra elementelor de interfață:</strong> design vizual IMO Core, layout, iconografie, flux UX.</li>
    </ul>

    <h2>7. Exclusivitate dezvoltare</h2>
    <p>
        Conform istoricului Git al repository-ului IMOCORE, toate cele {{ $stats['commits_count'] }} commit-uri
        analizate sunt atribuite autorului <strong>daniliescu1</strong> (Dan Iliescu, citgrup@icloud.com).
        Nu există alți autori de cod în istoricul repository-ului la data generării acestui document.
    </p>

    <h2>8. Relația IMOCORE — Operr App</h2>
    <p>
        <strong>IMOCORE (Imo Core)</strong> este aplicația principală de administrare imobiliară.
        <strong>Operr App</strong> este un produs separat planificat, accesibil din meniul Owner al IMOCORE
        printr-un entry point (rută /operr-app). În v1, pagina Operr App este un placeholder UI cu date demo;
        integrările planificate (HICP/INS, SPV, SAGA) vor fi dezvoltate în cadrul produsului Operr App.
        Drepturile de autor acoperă întregul codebase IMOCORE, inclusiv componenta placeholder Operr App.
    </p>

    <h2>9. Declarație autor</h2>
    <p>
        Subsemnatul, <strong>Dan Iliescu</strong>, declar că sunt autorul original al aplicației software IMOCORE,
        inclusiv a codului sursă, structurii bazei de date, interfeței utilizator, regulilor de business implementate
        și a documentației tehnice asociate, conform statisticilor din prezentul document.
    </p>
    <p>
        Prezentul document are scop informativ și probatoriu privind paternitatea operei software.
        Nu constituie contract de cesiune/licență decât dacă este semnat separat de părți.
    </p>

    <table style="margin-top:24px;">
        <tr>
            <td style="width:50%;height:60px;vertical-align:bottom;">
                <strong>Autor:</strong> Dan Iliescu<br>
                Email: citgrup@icloud.com
            </td>
            <td style="width:50%;vertical-align:bottom;">
                <strong>Data:</strong> {{ $generatedAt }}<br>
                <strong>Loc:</strong> România
            </td>
        </tr>
    </table>

    <div class="note">
        Acest document nu reprezintă consultanță juridică. Pentru înregistrări oficiale, contracte de licență
        sau cesiune, se recomandă consultarea unui avocat specializat în proprietate intelectuală.
    </div>

    <div class="footer">
        Declarație drepturi de autor IMOCORE — generat {{ $generatedAt }} — Dan Iliescu
    </div>
@endsection
