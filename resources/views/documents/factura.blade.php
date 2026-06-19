@extends('documents.layout')

@section('title', 'Factura '.$factura['numar_factura'])

@php
    use App\Support\DocumentFormatter;

    $sumar = $factura['sumar'] ?? [
        'total_fara_tva' => collect($factura['linii'] ?? [])->sum(fn ($linie) => (float) ($linie['valoare'] ?? 0)),
        'tva_21' => 0,
        'tva_11' => 0,
        'total' => $factura['total'] ?? null,
    ];
@endphp

@section('content')
    <section class="generated-annex">
        <div class="generated-annex-header invoice-document-header">
            <div>
                <h2>FACTURA</h2>
                <p class="invoice-number">{{ DocumentFormatter::display($factura['numar_factura'] ?? null) }}</p>
                <p class="invoice-period-note">pentru anexa din luna {{ $factura['luna'] ?? '—' }}</p>
            </div>
            <div class="generated-annex-meta invoice-dates-meta">
                <div class="invoice-date-row">
                    <span>Data emitere:</span>
                    <strong>{{ DocumentFormatter::display($factura['data_emitere'] ?? null) }}</strong>
                </div>
                <div class="invoice-date-row">
                    <span>Data scadenta:</span>
                    <strong>{{ DocumentFormatter::display($factura['data_scadenta'] ?? null) }}</strong>
                </div>
            </div>
            <div class="invoice-document-header-balance"></div>
        </div>

        <div class="invoice-parties-grid">
            <div class="invoice-party-card">
                <span class="invoice-party-heading">Locator</span>
                <strong class="invoice-party-name">{{ DocumentFormatter::display($factura['locator']['nume'] ?? null) }}</strong>
                <p class="invoice-party-detail"><span>CUI</span> {{ DocumentFormatter::display($factura['locator']['cui'] ?? null) }}</p>
                <p class="invoice-party-detail"><span>Reg. Com.</span> {{ DocumentFormatter::display($factura['locator']['reg_com'] ?? null) }}</p>
                <p class="invoice-party-detail"><span>Adresă</span> {{ DocumentFormatter::display($factura['locator']['adresa'] ?? null) }}</p>
                <p class="invoice-party-detail"><span>Bancă</span> {{ DocumentFormatter::display($factura['locator']['banca'] ?? null) }}</p>
                <p class="invoice-party-detail"><span>Cont</span> {{ DocumentFormatter::display($factura['locator']['cont_bancar'] ?? null) }}</p>
                <p class="invoice-party-detail"><span>Email</span> {{ DocumentFormatter::display($factura['locator']['email'] ?? null) }}</p>
            </div>
            <div class="invoice-party-card">
                <span class="invoice-party-heading">Locatar</span>
                <strong class="invoice-party-name">{{ DocumentFormatter::display($factura['locatar']['nume'] ?? null) }}</strong>
                <p class="invoice-party-detail"><span>{{ $factura['locatar']['identificator_label'] ?? 'CUI' }}</span> {{ DocumentFormatter::display($factura['locatar']['identificator'] ?? null) }}</p>
                @if (($factura['locatar']['tip'] ?? null) === 'pf')
                    <p class="invoice-party-detail"><span>CI</span> {{ DocumentFormatter::display($factura['locatar']['ci'] ?? null) }}</p>
                @endif
                <p class="invoice-party-detail"><span>Adresă</span> {{ DocumentFormatter::display($factura['locatar']['adresa'] ?? null) }}</p>
                <p class="invoice-party-detail"><span>Telefon</span> {{ DocumentFormatter::display($factura['locatar']['telefon'] ?? null) }}</p>
                <p class="invoice-party-detail"><span>Email</span> {{ DocumentFormatter::display($factura['locatar']['email'] ?? null) }}</p>
            </div>
        </div>

        <table class="generated-annex-table">
            <thead>
                <tr>
                    <th>Nr. crt</th>
                    <th>Denumire serviciu</th>
                    <th>Cantitate</th>
                    <th>UM</th>
                    <th>Preț unitar</th>
                    <th>Valoare</th>
                    <th>TVA</th>
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

        <div class="invoice-totals-summary">
            <div class="invoice-totals-row">
                <span>Total fără TVA:</span>
                <strong>{{ DocumentFormatter::amount($sumar['total_fara_tva'] ?? null) }}</strong>
            </div>
            <div class="invoice-totals-row">
                <span>TVA 21%:</span>
                <strong>{{ DocumentFormatter::amount($sumar['tva_21'] ?? null) }}</strong>
            </div>
            <div class="invoice-totals-row">
                <span>TVA 11%:</span>
                <strong>{{ DocumentFormatter::amount($sumar['tva_11'] ?? null) }}</strong>
            </div>
            <div class="invoice-totals-row invoice-totals-grand-total">
                <span>Total</span>
                <strong>{{ DocumentFormatter::amount($sumar['total'] ?? null) }} Lei</strong>
            </div>
        </div>

        <section class="invoice-payment-footer">
            <div class="invoice-payment-instructions">
                <strong>Instructiuni de plata</strong>
                <p>Banca: {{ DocumentFormatter::display($factura['locator']['banca'] ?? null) }}</p>
                <p>Cont: {{ DocumentFormatter::display($factura['locator']['cont_bancar'] ?? null) }}</p>
            </div>

            <div class="invoice-legal-notes">
                <p>
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
            </div>

            <p class="invoice-payment-summary">
                {{ DocumentFormatter::display($factura['numar_factura'] ?? null) }} {{ DocumentFormatter::amount($sumar['total'] ?? null) }} Lei scadenta la {{ DocumentFormatter::display($factura['data_scadenta'] ?? null) }}
            </p>
        </section>

        @if (! empty($factura['anexa_detaliu']))
            <section class="invoice-attached-annex">
                <div class="generated-annex-header compact-annex-header">
                    <div>
                        <h2>ANEXA nr.{{ $factura['anexa_detaliu']['numar'] ?? '01' }}</h2>
                        <p>utilități {{ $factura['anexa_detaliu']['luna_utilitati'] ?? '—' }}</p>
                    </div>
                </div>

                <table class="generated-annex-table">
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
            </section>
        @endif
    </section>
@endsection
