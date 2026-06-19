@extends('documents.layout')

@section('title', 'Anexa nr.'.$anexa['numar'])

@section('content')
    <section class="generated-annex">
        @include('documents.partials.anexa-document', ['anexa' => $anexa])
    </section>
@endsection
