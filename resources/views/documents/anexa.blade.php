@extends('documents.layout')

@section('title', 'Anexa nr.'.$anexa['numar'])

@php
    use App\Support\DocumentFormatter;
@endphp

@section('content')
    <section class="generated-annex">
        <div class="generated-annex-header generated-annex-header-centered-meta">
            <div>
                <h2>ANEXA nr.{{ $anexa['numar'] ?? '01' }}</h2>
                <p>din luna {{ DocumentFormatter::lunaText($anexa['luna'] ?? null) }}</p>
            </div>
            <div class="generated-annex-meta">
                <span>Perioada citire contoare</span>
                <strong>{{ DocumentFormatter::display($anexa['perioada_citire'] ?? null) }}</strong>
            </div>
            <div class="generated-annex-header-balance"></div>
        </div>

        <div class="generated-annex-parties">
            <div>
                <span>Imobil</span>
                <strong>{{ DocumentFormatter::display($anexa['imobil']['nume'] ?? null) }}</strong>
                <small>{{ trim(implode(', ', array_filter([$anexa['imobil']['adresa'] ?? null, $anexa['imobil']['localitate'] ?? null]))) ?: '—' }}</small>
            </div>
            <div>
                <span>Nume locator</span>
                <strong>{{ DocumentFormatter::display($anexa['spatiu']['locator'] ?? null) }}</strong>
            </div>
            <div>
                <span>Nume locatar</span>
                <strong>{{ DocumentFormatter::display($anexa['spatiu']['chirias'] ?? $anexa['contract']['chirias'] ?? null) }}</strong>
            </div>
            <div>
                <span>ID spațiu</span>
                <strong>{{ DocumentFormatter::display($anexa['spatiu']['identificator'] ?? null) }}</strong>
            </div>
            <div>
                <span>Contract</span>
                <strong>{{ DocumentFormatter::display($anexa['contract']['numar'] ?? null) }}</strong>
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
                    'linii' => $anexa['linii'] ?? [],
                ])
            </tbody>
        </table>
    </section>
@endsection
