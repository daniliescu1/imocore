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
    $hasAttachedAnnex = ! empty($factura['anexa_detaliu']);
@endphp

@section('content')
    <section class="generated-annex pdf-invoice-document pdf-invoice-pro{{ $hasAttachedAnnex ? ' pdf-invoice-with-annex' : '' }}">
        <div class="pdf-invoice-page">
            <div class="generated-annex-header invoice-document-header">
                <div>
                    <p class="pdf-invoice-kicker">Document fiscal</p>
                    <h2>FACTURA</h2>
                    <p class="invoice-number">{{ DocumentFormatter::pdfText($factura['numar_factura'] ?? null) }}</p>
                    <p class="invoice-period-note">pentru anexa din luna {{ DocumentFormatter::pdfText($factura['luna'] ?? null) }}</p>
                </div>
                <div class="generated-annex-meta invoice-dates-meta">
                    <table class="invoice-dates-table">
                        <tr>
                            <td class="invoice-date-label">Data emitere:</td>
                            <td class="invoice-date-value">{{ DocumentFormatter::pdfText($factura['data_emitere'] ?? null) }}</td>
                        </tr>
                        <tr>
                            <td class="invoice-date-label">Data scadenta:</td>
                            <td class="invoice-date-value">{{ DocumentFormatter::pdfText($factura['data_scadenta'] ?? null) }}</td>
                        </tr>
                    </table>
                </div>
                <div class="invoice-document-header-balance"></div>
            </div>

            <table class="invoice-parties-table">
                <tr>
                    <td class="invoice-party-card">
                        <span class="invoice-party-heading">Locator</span>
                        <strong class="invoice-party-name">{{ DocumentFormatter::pdfText($factura['locator']['nume'] ?? null) }}</strong>
                        <p class="invoice-party-detail"><span>CUI</span> {{ DocumentFormatter::pdfText($factura['locator']['cui'] ?? null) }}</p>
                        <p class="invoice-party-detail"><span>Reg. Com.</span> {{ DocumentFormatter::pdfText($factura['locator']['reg_com'] ?? null) }}</p>
                        <p class="invoice-party-detail"><span>Adresa</span> {{ DocumentFormatter::pdfText($factura['locator']['adresa'] ?? null) }}</p>
                        <p class="invoice-party-detail"><span>Banca</span> {{ DocumentFormatter::pdfText($factura['locator']['banca'] ?? null) }}</p>
                        <p class="invoice-party-detail"><span>Cont</span> {{ DocumentFormatter::pdfText($factura['locator']['cont_bancar'] ?? null) }}</p>
                        <p class="invoice-party-detail"><span>Email</span> {{ DocumentFormatter::pdfText($factura['locator']['email'] ?? null) }}</p>
                    </td>
                    <td class="invoice-party-card">
                        <span class="invoice-party-heading">Locatar</span>
                        <strong class="invoice-party-name">{{ DocumentFormatter::pdfText($factura['locatar']['nume'] ?? null) }}</strong>
                        <p class="invoice-party-detail"><span>{{ DocumentFormatter::pdfText($factura['locatar']['identificator_label'] ?? 'CUI') }}</span> {{ DocumentFormatter::pdfText($factura['locatar']['identificator'] ?? null) }}</p>
                        @if (($factura['locatar']['tip'] ?? null) === 'pf')
                            <p class="invoice-party-detail"><span>CI</span> {{ DocumentFormatter::pdfText($factura['locatar']['ci'] ?? null) }}</p>
                        @endif
                        <p class="invoice-party-detail"><span>Adresa</span> {{ DocumentFormatter::pdfText($factura['locatar']['adresa'] ?? null) }}</p>
                        <p class="invoice-party-detail"><span>Telefon</span> {{ DocumentFormatter::pdfText($factura['locatar']['telefon'] ?? null) }}</p>
                        <p class="invoice-party-detail"><span>Email</span> {{ DocumentFormatter::pdfText($factura['locatar']['email'] ?? null) }}</p>
                    </td>
                </tr>
            </table>

            <table class="generated-annex-table pdf-invoice-lines-table">
                <thead>
                    <tr>
                        <th>Nr. crt</th>
                        <th>Denumire serviciu</th>
                        <th class="col-numeric">Cantitate</th>
                        <th>UM</th>
                        <th class="col-numeric">Pret unitar</th>
                        <th class="col-numeric">Valoare</th>
                        <th class="col-numeric">TVA</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($factura['linii'] as $index => $linie)
                        <tr>
                            <td>{{ $linie['nr_crt'] ?? ($index + 1) }}</td>
                            <td>{{ DocumentFormatter::pdfText($linie['denumire'] ?? null) }}</td>
                            <td class="col-numeric">{{ DocumentFormatter::decimal($linie['cantitate'] ?? null) }}</td>
                            <td>{{ DocumentFormatter::pdfText($linie['um'] ?? null) }}</td>
                            <td class="col-numeric">{{ DocumentFormatter::moneyValue($linie['pret_unitar'] ?? null) }}</td>
                            <td class="col-numeric">{{ DocumentFormatter::money($linie['valoare'] ?? null) }}</td>
                            <td class="col-numeric">{{ DocumentFormatter::money($linie['tva'] ?? null) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <table class="invoice-totals-panel">
                <tr>
                    <td>Total fara TVA:</td>
                    <td class="col-numeric">{{ DocumentFormatter::amount($sumar['total_fara_tva'] ?? null) }}</td>
                </tr>
                <tr>
                    <td>TVA 21%:</td>
                    <td class="col-numeric">{{ DocumentFormatter::amount($sumar['tva_21'] ?? null) }}</td>
                </tr>
                <tr>
                    <td>TVA 11%:</td>
                    <td class="col-numeric">{{ DocumentFormatter::amount($sumar['tva_11'] ?? null) }}</td>
                </tr>
                <tr class="invoice-totals-grand-row">
                    <td>Total de plata</td>
                    <td class="col-numeric">{{ DocumentFormatter::amount($sumar['total'] ?? null) }} Lei</td>
                </tr>
            </table>

            <section class="invoice-payment-footer">
                <table class="invoice-footer-grid">
                    <tr>
                        <td class="invoice-payment-panel">
                            <strong>Instructiuni de plata</strong>
                            <p>Banca: {{ DocumentFormatter::pdfText($factura['locator']['banca'] ?? null) }}</p>
                            <p>Cont: {{ DocumentFormatter::pdfText($factura['locator']['cont_bancar'] ?? null) }}</p>
                        </td>
                        <td class="invoice-payment-summary-panel">
                            <strong>Scadenta</strong>
                            <p>{{ DocumentFormatter::pdfText($factura['numar_factura'] ?? null) }}</p>
                            <p>{{ DocumentFormatter::amount($sumar['total'] ?? null) }} Lei</p>
                            <p>pana la {{ DocumentFormatter::pdfText($factura['data_scadenta'] ?? null) }}</p>
                        </td>
                    </tr>
                </table>

                <div class="invoice-legal-panel">
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
            </section>
        </div>

        @if ($hasAttachedAnnex)
            <section class="invoice-attached-annex pdf-annex-page">
                @include('documents.partials.anexa-document', [
                    'anexa' => $factura['anexa_detaliu'],
                    'numericColumns' => true,
                ])
            </section>
        @endif
    </section>
@endsection
