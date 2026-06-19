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
                <p>din luna {{ DocumentFormatter::pdfText(DocumentFormatter::lunaText($anexa['luna'] ?? null)) }}</p>
            </div>
            <div class="generated-annex-meta">
                <span>Perioada citire contoare</span>
                <strong>{{ DocumentFormatter::pdfText($anexa['perioada_citire'] ?? null) }}</strong>
            </div>
            <div class="generated-annex-header-balance"></div>
        </div>

        <div class="generated-annex-parties">
            <div>
                <span>Imobil</span>
                <strong>{{ DocumentFormatter::pdfText($anexa['imobil']['nume'] ?? null) }}</strong>
                <small>{{ DocumentFormatter::pdfText(trim(implode(', ', array_filter([$anexa['imobil']['adresa'] ?? null, $anexa['imobil']['localitate'] ?? null]))) ?: null) }}</small>
            </div>
            <div>
                <span>Nume locator</span>
                <strong>{{ DocumentFormatter::pdfText($anexa['spatiu']['locator'] ?? null) }}</strong>
            </div>
            <div>
                <span>Nume locatar</span>
                <strong>{{ DocumentFormatter::pdfText($anexa['spatiu']['chirias'] ?? $anexa['contract']['chirias'] ?? null) }}</strong>
            </div>
            <div>
                <span>ID spatiu</span>
                <strong>{{ DocumentFormatter::pdfText($anexa['spatiu']['identificator'] ?? null) }}</strong>
            </div>
            <div>
                <span>Contract</span>
                <strong>{{ DocumentFormatter::pdfText($anexa['contract']['numar'] ?? null) }}</strong>
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
                    <th>Pret unitar</th>
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
