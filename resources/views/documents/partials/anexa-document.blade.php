@php
    use App\Support\DocumentFormatter;

    $anexa = $anexa ?? [];
    $moneyFormat = $moneyFormat ?? false;
    $numericColumns = $numericColumns ?? false;
    $tableClass = trim('generated-annex-table pdf-invoice-lines-table '.($tableClass ?? ''));
@endphp

<div class="generated-annex-header generated-annex-header-centered-meta{{ ! empty($compactHeader) ? ' compact-annex-header' : '' }}">
    <div>
        @if (! empty($pdfMode))
            <p class="pdf-invoice-kicker">Anexa utilitati</p>
        @endif
        <h2>ANEXA nr.{{ $anexa['numar'] ?? '01' }}</h2>
        <p>din luna {{ DocumentFormatter::pdfText(DocumentFormatter::lunaText($anexa['luna'] ?? null)) }}</p>
    </div>
    <div class="generated-annex-meta">
        <span>Perioada citire contoare</span>
        <strong>{{ DocumentFormatter::pdfText($anexa['perioada_citire'] ?? null) }}</strong>
    </div>
    <div class="generated-annex-header-balance"></div>
</div>

<table class="generated-annex-parties-table">
    <tr>
        <td>
            <span>Imobil</span>
            <strong>{{ DocumentFormatter::pdfText($anexa['imobil']['nume'] ?? null) }}</strong>
            <small>{{ DocumentFormatter::pdfText(trim(implode(', ', array_filter([$anexa['imobil']['adresa'] ?? null, $anexa['imobil']['localitate'] ?? null]))) ?: null) }}</small>
        </td>
        <td>
            <span>Nume locator</span>
            <strong>{{ DocumentFormatter::pdfText($anexa['spatiu']['locator'] ?? null) }}</strong>
        </td>
        <td>
            <span>Nume locatar</span>
            <strong>{{ DocumentFormatter::pdfText($anexa['spatiu']['chirias'] ?? $anexa['contract']['chirias'] ?? null) }}</strong>
        </td>
        <td>
            <span>ID spatiu</span>
            <strong>{{ DocumentFormatter::pdfText($anexa['spatiu']['identificator'] ?? null) }}</strong>
        </td>
        <td>
            <span>Contract</span>
            <strong>{{ DocumentFormatter::pdfText($anexa['contract']['numar'] ?? null) }}</strong>
        </td>
    </tr>
</table>

<table class="{{ $tableClass }}">
    <thead>
        <tr>
            <th>Nr. crt</th>
            <th>Denumire serviciu</th>
            <th class="{{ $numericColumns ? 'col-numeric' : '' }}">Index vechi</th>
            <th class="{{ $numericColumns ? 'col-numeric' : '' }}">Index nou</th>
            <th class="{{ $numericColumns ? 'col-numeric' : '' }}">Facturat</th>
            <th>UM</th>
            <th class="{{ $numericColumns ? 'col-numeric' : '' }}">Pret unitar</th>
            <th class="{{ $numericColumns ? 'col-numeric' : '' }}">Valoare</th>
            <th class="{{ $numericColumns ? 'col-numeric' : '' }}">TVA</th>
        </tr>
    </thead>
    <tbody>
        @include('documents.partials.annex-table-body', [
            'linii' => $anexa['linii'] ?? [],
            'moneyFormat' => $moneyFormat,
            'numericColumns' => $numericColumns,
        ])
    </tbody>
</table>

@if (! empty($pdfMode))
    <table class="invoice-totals-panel annex-totals-panel">
        <tr>
            <td>Total fara TVA:</td>
            <td class="col-numeric">{{ DocumentFormatter::amount($anexa['subtotal'] ?? null) }}</td>
        </tr>
        <tr>
            <td>TVA 21%:</td>
            <td class="col-numeric">{{ DocumentFormatter::amount($anexa['total_tva'] ?? null) }}</td>
        </tr>
        <tr class="invoice-totals-grand-row">
            <td>Total anexa</td>
            <td class="col-numeric">{{ DocumentFormatter::amount($anexa['total'] ?? null) }} Lei</td>
        </tr>
    </table>

    <p class="pdf-annex-footer-note">
        Document generat din IMO Core. Valorile sunt exprimate in lei, fara diacritice in PDF.
    </p>
@endif
