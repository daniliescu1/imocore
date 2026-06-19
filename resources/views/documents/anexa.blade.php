@extends('documents.layout')

@section('title', 'Anexa nr.'.$anexa['numar'])

@section('content')
    <section class="pdf-invoice-document pdf-invoice-pro pdf-annex-standalone">
        <div class="pdf-annex-page">
            @include('documents.partials.anexa-document', [
                'anexa' => $anexa,
                'numericColumns' => true,
                'pdfMode' => true,
            ])
        </div>
    </section>
@endsection
