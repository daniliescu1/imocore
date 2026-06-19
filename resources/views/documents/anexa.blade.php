@extends('documents.layout')

@section('title', 'Anexa nr.'.$anexa['numar'])

@php
    use App\Support\DocumentFormatter;
@endphp

@section('content')
    <div class="doc-header">
        <table class="doc-header-row">
            <tr>
                <td style="width: 33%; vertical-align: top;">
                    <h2>ANEXA nr.{{ $anexa['numar'] ?? '01' }}</h2>
                    <p class="muted">din luna {{ DocumentFormatter::lunaText($anexa['luna'] ?? null) }}</p>
                </td>
                <td style="width: 34%; vertical-align: top; text-align: center;">
                    <div class="meta-box" style="display: inline-block; text-align: center;">
                        <span>Perioada citire contoare</span>
                        <strong>{{ DocumentFormatter::display($anexa['perioada_citire'] ?? null) }}</strong>
                    </div>
                </td>
                <td style="width: 33%;"></td>
            </tr>
        </table>
    </div>

    <table class="annex-parties">
        <tr>
            <td>
                <span>Imobil</span>
                <strong>{{ DocumentFormatter::display($anexa['imobil']['nume'] ?? null) }}</strong>
                <small>{{ trim(implode(', ', array_filter([$anexa['imobil']['adresa'] ?? null, $anexa['imobil']['localitate'] ?? null]))) ?: '—' }}</small>
            </td>
            <td>
                <span>Nume locator</span>
                <strong>{{ DocumentFormatter::display($anexa['spatiu']['locator'] ?? null) }}</strong>
            </td>
            <td>
                <span>Nume locatar</span>
                <strong>{{ DocumentFormatter::display($anexa['spatiu']['chirias'] ?? $anexa['contract']['chirias'] ?? null) }}</strong>
            </td>
            <td>
                <span>ID spațiu</span>
                <strong>{{ DocumentFormatter::display($anexa['spatiu']['identificator'] ?? null) }}</strong>
            </td>
            <td>
                <span>Contract</span>
                <strong>{{ DocumentFormatter::display($anexa['contract']['numar'] ?? null) }}</strong>
            </td>
        </tr>
    </table>

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
                'linii' => $anexa['linii'] ?? [],
            ])
        </tbody>
    </table>
@endsection
