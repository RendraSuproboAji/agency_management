<div class="form-grid">
    <label>Judul project *
        <input type="text" name="title" value="{{ old('title', $project->title) }}" required>
    </label>
    <label>Klien *
        <select name="client_id" required>
            <option value="">— pilih klien —</option>
            @foreach ($clients as $client)
                <option value="{{ $client->id }}" @selected((int) old('client_id', $project->client_id) === $client->id)>{{ $client->name }}</option>
            @endforeach
        </select>
    </label>
    <label>Jenis layanan *
        <select name="service_type" required>
            @foreach (\App\Models\Project::SERVICE_TYPES as $option)
                <option value="{{ $option }}" @selected(old('service_type', $project->service_type) === $option)>{{ $option }}</option>
            @endforeach
        </select>
    </label>
    <label>Status *
        <select name="status" required>
            @foreach (\App\Models\Project::STATUSES as $option)
                <option value="{{ $option }}" @selected(old('status', $project->status) === $option)>{{ $option }}</option>
            @endforeach
        </select>
    </label>
    <label>Penanggung jawab
        <select name="owner_id">
            <option value="">— belum ditentukan —</option>
            @foreach ($owners as $owner)
                <option value="{{ $owner->id }}" @selected((int) old('owner_id', $project->owner_id) === $owner->id)>{{ $owner->name }}</option>
            @endforeach
        </select>
    </label>
    <label>Deadline
        <input type="date" name="deadline" value="{{ old('deadline', $project->deadline?->format('Y-m-d')) }}">
    </label>
    <label>Budget (Rp)
        <input type="number" step="0.01" min="0" name="budget" value="{{ old('budget', $project->budget) }}">
    </label>
    <label>Luas area (m²)
        <input type="number" min="0" name="area_sqm" value="{{ old('area_sqm', $project->area_sqm) }}">
    </label>
    <label class="span-2">Lokasi site
        <input type="text" name="site_location" value="{{ old('site_location', $project->site_location) }}">
    </label>
    <label class="span-2">Tautan virtual tour (GalleryVT)
        <input type="url" name="gallery_url" value="{{ old('gallery_url', $project->gallery_url) }}" placeholder="https://…">
    </label>
    <label class="span-2">Brief / request klien
        <textarea name="brief" rows="6">{{ old('brief', $project->brief) }}</textarea>
    </label>
</div>
