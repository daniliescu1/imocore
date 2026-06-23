@php
    use App\Support\DocumentFormatter;

    $sectionTotal = ['valoare' => 0.0, 'tva' => 0.0, 'count' => 0];
    $moneyFormat = $moneyFormat ?? false;
    $numericColumns = $numericColumns ?? false;
    $numericClass = $numericColumns ? ' col-numeric' : '';
@endphp

@foreach ($linii as $index => $linie)
    @if (($linie['tip_linie'] ?? 'serviciu') === 'header')
        @if ($sectionTotal['count'] > 0)
            <tr class="generated-annex-section-total">
                <td colspan="6"></td>
                <td>Total</td>
                <td class="{{ trim($numericClass) }}">{{ DocumentFormatter::moneyValue($sectionTotal['valoare']) }}</td>
                <td class="{{ trim($numericClass) }}">{{ DocumentFormatter::moneyValue($sectionTotal['tva']) }}</td>
            </tr>
        @endif
        <tr class="generated-annex-section-header">
            <th>Nr. crt</th>
            <th>Denumire serviciu</th>
            <th class="{{ trim($numericClass) }}">Index vechi</th>
            <th class="{{ trim($numericClass) }}">Index nou</th>
            <th class="{{ trim($numericClass) }}">Facturat</th>
            <th>UM</th>
            <th class="{{ trim($numericClass) }}">Pret unitar</th>
            <th class="{{ trim($numericClass) }}">Valoare</th>
            <th class="{{ trim($numericClass) }}">TVA</th>
        </tr>
        @php $sectionTotal = ['valoare' => 0.0, 'tva' => 0.0, 'count' => 0]; @endphp
        @continue
    @endif

    @php
        $sectionTotal['valoare'] += (float) ($linie['valoare'] ?? 0);
        $sectionTotal['tva'] += (float) ($linie['tva_21'] ?? 0);
        $sectionTotal['count']++;
    @endphp
    <tr>
        <td>{{ $linie['nr_crt'] ?? ($index + 1) }}</td>
        <td>{{ DocumentFormatter::pdfText($linie['denumire'] ?? null) }}</td>
        <td class="{{ trim($numericClass) }}">{{ DocumentFormatter::decimal($linie['index_vechi'] ?? null) }}</td>
        <td class="{{ trim($numericClass) }}">{{ DocumentFormatter::decimal($linie['index_nou'] ?? null) }}</td>
        <td class="{{ trim($numericClass) }}">{{ DocumentFormatter::decimal($linie['cantitate'] ?? null) }}</td>
        <td>{{ DocumentFormatter::pdfText($linie['um'] ?? null) }}</td>
        <td class="{{ trim($numericClass) }}">{{ DocumentFormatter::decimal($linie['pret_unitar'] ?? null) }}</td>
        <td class="{{ trim($numericClass) }}">{{ $moneyFormat ? DocumentFormatter::moneyValue($linie['valoare'] ?? null) : DocumentFormatter::decimal($linie['valoare'] ?? null) }}</td>
        <td class="{{ trim($numericClass) }}">{{ $moneyFormat ? DocumentFormatter::moneyValue($linie['tva_21'] ?? null) : DocumentFormatter::decimal($linie['tva_21'] ?? null) }}</td>
    </tr>
@endforeach

@if ($sectionTotal['count'] > 0)
    <tr class="generated-annex-section-total">
        <td colspan="6"></td>
        <td>Total</td>
        <td class="{{ trim($numericClass) }}">{{ DocumentFormatter::moneyValue($sectionTotal['valoare']) }}</td>
        <td class="{{ trim($numericClass) }}">{{ DocumentFormatter::moneyValue($sectionTotal['tva']) }}</td>
    </tr>
@endif
