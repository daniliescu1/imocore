@extends('documents.layout')

@section('title', 'Factura '.$factura['numar_factura'])

@php
    use App\Support\DocumentFormatter;
@endphp

@section('content')
    <div class="doc-header">
        <table class="doc-header-row">
            <tr>
                <td style="width: 62%;">
                    <h2>FACTURA</h2>
                    <p><strong>{{ DocumentFormatter::display($factura['numar_factura']) }}</strong></p>
                    <p class="muted">pentru anexa din luna {{ $factura['luna'] ?? '—' }}</p>
                </td>
                <td style="width: 38%;">
                    <div class="meta-box">
                        <div style="margin-bottom: 8px;">
                            <span>Data emitere</span>
                            <strong>{{ DocumentFormatter::display($factura['data_emitere']) }}</strong>
                        </div>
                        <div>
                            <span>Data scadenta</span>
                            <strong>{{ DocumentFormatter::display($factura['data_scadenta']) }}</strong>
                        </div>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <table class="parties-table">
        <tr>
            <td class="party-card" style="width: 50%;">
                <span class="party-heading">Locator</span>
                <strong class="party-name">{{ DocumentFormatter::display($factura['locator']['nume'] ?? null) }}</strong>
                <p class="party-detail"><span>CUI</span> {{ DocumentFormatter::display($factura['locator']['cui'] ?? null) }}</p>
                <p class="party-detail"><span>Reg. Com.</span> {{ DocumentFormatter::display($factura['locator']['reg_com'] ?? null) }}</p>
                <p class="party-detail"><span>Adresă</span> {{ DocumentFormatter::display($factura['locator']['adresa'] ?? null) }}</p>
                <p class="party-detail"><span>Bancă</span> {{ DocumentFormatter::display($factura['locator']['banca'] ?? null) }}</p>
                <p class="party-detail"><span>Cont</span> {{ DocumentFormatter::display($factura['locator']['cont_bancar'] ?? null) }}</p>
                <p class="party-detail"><span>Email</span> {{ DocumentFormatter::display($factura['locator']['email'] ?? null) }}</p>
            </td>
            <td class="party-card" style="width: 50%;">
                <span class="party-heading">Locatar</span>
                <strong class="party-name">{{ DocumentFormatter::display($factura['locatar']['nume'] ?? null) }}</strong>
                <p class="party-detail"><span>{{ $factura['locatar']['identificator_label'] ?? 'CUI' }}</span> {{ DocumentFormatter::display($factura['locatar']['identificator'] ?? null) }}</p>
                @if (($factura['locatar']['tip'] ?? null) === 'pf')
                    <p class="party-detail"><span>CI</span> {{ DocumentFormatter::display($factura['locatar']['ci'] ?? null) }}</p>
                @endif
                <p class="party-detail"><span>Adresă</span> {{ DocumentFormatter::display($factura['locatar']['adresa'] ?? null) }}</p>
                <p class="party-detail"><span>Telefon</span> {{ DocumentFormatter::display($factura['locatar']['telefon'] ?? null) }}</p>
                <p class="party-detail"><span>Email</span> {{ DocumentFormatter::display($factura['locatar']['email'] ?? null) }}</p>
            </td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 7%;">Nr. crt</th>
                <th style="width: 34%;">Denumire serviciu</th>
                <th style="width: 9%;">Cantitate</th>
                <th style="width: 9%;">UM</th>
                <th style="width: 12%;">Preț unitar</th>
                <th style="width: 14%;">Valoare</th>
                <th style="width: 15%;">TVA</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($factura['linii'] as $index => $linie)
                <tr>
                    <td>{{ $linie['nr_crt'] ?? ($index + 1) }}</td>
                    <td>{{ $linie['denumire'] ?? '—' }}</td>
                    <td>{{ DocumentFormatter::decimal($linie['cantitate'] ?? null) }}</td>
                    <td>{{ $linie['um'] ?? '—' }}</td>
                    <td>{{ DocumentFormatter::moneyValue($linie['pret_unitar'] ?? null) }}</td>
                    <td>{{ DocumentFormatter::money($linie['valoare'] ?? null) }}</td>
                    <td>{{ DocumentFormatter::money($linie['tva'] ?? null) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals-box">
        <table class="totals-row">
            <tr>
                <td>Total fără TVA:</td>
                <td>{{ DocumentFormatter::amount($factura['sumar']['total_fara_tva'] ?? null) }}</td>
            </tr>
            <tr>
                <td>TVA 21%:</td>
                <td>{{ DocumentFormatter::amount($factura['sumar']['tva_21'] ?? null) }}</td>
            </tr>
            <tr>
                <td>TVA 11%:</td>
                <td>{{ DocumentFormatter::amount($factura['sumar']['tva_11'] ?? null) }}</td>
            </tr>
            <tr class="totals-grand">
                <td>Total</td>
                <td>{{ DocumentFormatter::amount($factura['sumar']['total'] ?? null) }} Lei</td>
            </tr>
        </table>
    </div>

    <div class="footer-block">
        <p><strong>Instructiuni de plata</strong></p>
        <p>Banca: {{ DocumentFormatter::display($factura['locator']['banca'] ?? null) }}</p>
        <p>Cont: {{ DocumentFormatter::display($factura['locator']['cont_bancar'] ?? null) }}</p>
        <p style="margin-top: 12px;">
            SCUTIT DE TVA IN BAZA LG 227/2015, ART 292, AL.2, LIT E. CURS BCR: 1 EURO={{ DocumentFormatter::decimal($factura['curs_eur'] ?? null) }} RON.
            Factura circula fara semnatura si stampila cf Legii 227/2015, ART.39, ALIN.29
        </p>
        <p>Factura si conditiile de plata au fost acceptate de catre beneficiar.</p>
        <p>
            In cazul depasirii termenelor de plata convenite, penalizarile sunt de 1% pentru fiecare zi de intarziere,
            aplicate la valoarea facturilor emise, preluate si neachitate.
        </p>
        <p>
            Factura circula fara semnatura si stampila cf. art.V, alin (2) din Ordonanta nr.17/2015 si art. 319 alin (29)
            din Legea nr. 227/2015 privind Codul fiscal.
        </p>
        <p><strong>{{ DocumentFormatter::display($factura['numar_factura']) }} {{ DocumentFormatter::amount($factura['sumar']['total'] ?? null) }} Lei scadenta la {{ DocumentFormatter::display($factura['data_scadenta']) }}</strong></p>
    </div>

    @if (! empty($factura['anexa_detaliu']))
        <div class="attached-annex">
            <div class="doc-header">
                <h2>ANEXA nr.{{ $factura['anexa_detaliu']['numar'] ?? '01' }}</h2>
                <p class="muted">utilități {{ $factura['anexa_detaliu']['luna_utilitati'] ?? '—' }}</p>
            </div>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>Nr. crt</th>
                        <th>Denumire serviciu</th>
                        <th>Index vechi</th>
                        <th>Index nou</th>
                        <th>Facturat</th>
                        <th>UM</th>
                        <th>Preț unitar</th>
                        <th>Valoare</th>
                        <th>TVA</th>
                    </tr>
                </thead>
                <tbody>
                    @include('documents.partials.annex-table-body', [
                        'linii' => $factura['anexa_detaliu']['linii'] ?? [],
                        'moneyFormat' => true,
                    ])
                </tbody>
            </table>
        </div>
    @endif
@endsection
