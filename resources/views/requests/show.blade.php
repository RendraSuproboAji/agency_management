@extends('layouts.app')
@section('title', 'Request · '.config('site.name'))

@section('content')
<div class="page-head">
    <div>
        <h1>{{ $serviceRequest->company ?: $serviceRequest->name }}</h1>
        <p class="muted">
            Masuk {{ $serviceRequest->created_at->format('d M Y H:i') }}
            @include('partials.status-badge', ['status' => $serviceRequest->status])
        </p>
    </div>
    @if (auth()->user()->isAdmin())
        <form method="post" action="{{ route('requests.destroy', $serviceRequest) }}" data-confirm="Hapus request ini?">
            @csrf @method('delete')
            <button class="btn btn-danger">Hapus</button>
        </form>
    @endif
</div>

<section class="panel">
    <dl class="detail">
        <div><dt>Nama</dt><dd>{{ $serviceRequest->name }}</dd></div>
        <div><dt>Email</dt><dd>{{ $serviceRequest->email }}</dd></div>
        <div><dt>Telepon</dt><dd>{{ $serviceRequest->phone ?: '—' }}</dd></div>
        <div><dt>Layanan</dt><dd>{{ $serviceRequest->service_type }}</dd></div>
        <div><dt>Lokasi</dt><dd>{{ $serviceRequest->site_location ?: '—' }}</dd></div>
        <div><dt>Luas area</dt><dd>{{ $serviceRequest->area_sqm ? $serviceRequest->area_sqm.' m²' : '—' }}</dd></div>
    </dl>

    @if ($serviceRequest->message)
        <h3>Kebutuhan</h3>
        <p class="notes">{{ $serviceRequest->message }}</p>
    @endif

    <form class="inline-form" method="post" action="{{ route('requests.status', $serviceRequest) }}">
        @csrf @method('put')
        <label class="inline">Status
            <select name="status">
                @foreach (\App\Models\ServiceRequest::STATUSES as $option)
                    <option value="{{ $option }}" @selected($serviceRequest->status === $option)>{{ $option }}</option>
                @endforeach
            </select>
        </label>
        <button class="btn">Simpan status</button>
    </form>
</section>

<section class="panel">
    <h2>Konversi jadi project</h2>

    @if ($serviceRequest->convertedProject)
        <p>Sudah dikonversi menjadi
            <a href="{{ route('projects.show', $serviceRequest->convertedProject) }}">{{ $serviceRequest->convertedProject->title }}</a>.
        </p>
    @else
        <form method="post" action="{{ route('requests.convert', $serviceRequest) }}">
            @csrf
            <div class="form-grid">
                <label>Judul project *
                    <input type="text" name="title" required
                           value="{{ old('title', 'Tur 3D '.($serviceRequest->company ?: $serviceRequest->name)) }}">
                </label>
                <label>Klien
                    <select name="client_id">
                        <option value="">— buat klien baru dari data request —</option>
                        @foreach ($clients as $client)
                            <option value="{{ $client->id }}" @selected((int) old('client_id') === $client->id)>{{ $client->name }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
            <button class="btn btn-primary">Konversi</button>
        </form>
    @endif
</section>
@endsection
