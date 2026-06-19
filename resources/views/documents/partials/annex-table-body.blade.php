@php
    use App\Support\DocumentFormatter;

    $sectionTotal = ['valoare' => 0.0, 'tva' => 0.0, 'count' => 0];
    $moneyFormat = $moneyFormat ?? false;
@endphp

@foreach ($linii as $index => $linie)
    @if (($linie['tip_linie'] ?? 'serviciu') === 'header')
        @if ($sectionTotal['count'] > 0)
            <tr class="generated-annex-section-total">
                <td colspan="6"></td>
                <td>Total</td>
                <td>{{ $moneyFormat ? DocumentFormatter::moneyValue($sectionTotal['valoare']) : DocumentFormatter::decimal($sectionTotal['valoare']) }}</td>
                <td>{{ $moneyFormat ? DocumentFormatter::moneyValue($sectionTotal['tva']) : DocumentFormatter::decimal($sectionTotal['tva']) }}</td>
            </tr>
        @endif
        <tr class="generated-annex-section-header">
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
        <td>{{ $linie['denumire'] ?? '—' }}</td>
        <td>{{ DocumentFormatter::decimal($linie['index_vechi'] ?? null) }}</td>
        <td>{{ DocumentFormatter::decimal($linie['index_nou'] ?? null) }}</td>
        <td>{{ DocumentFormatter::decimal($linie['cantitate'] ?? null) }}</td>
        <td>{{ $linie['um'] ?? '—' }}</td>
        <td>{{ DocumentFormatter::decimal($linie['pret_unitar'] ?? null) }}</td>
        <td>{{ $moneyFormat ? DocumentFormatter::moneyValue($linie['valoare'] ?? null) : DocumentFormatter::decimal($linie['valoare'] ?? null) }}</td>
        <td>{{ $moneyFormat ? DocumentFormatter::moneyValue($linie['tva_21'] ?? null) : DocumentFormatter::decimal($linie['tva_21'] ?? null) }}</td>
    </tr>
@endforeach

@if ($sectionTotal['count'] > 0)
    <tr class="generated-annex-section-total">
        <td colspan="6"></td>
        <td>Total</td>
        <td>{{ $moneyFormat ? DocumentFormatter::moneyValue($sectionTotal['valoare']) : DocumentFormatter::decimal($sectionTotal['valoare']) }}</td>
        <td>{{ $moneyFormat ? DocumentFormatter::moneyValue($sectionTotal['tva']) : DocumentFormatter::decimal($sectionTotal['tva']) }}</td>
    </tr>
@endif
