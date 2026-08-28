<div class="form-grid">
    <label>Judul *
        <input type="text" name="title" value="{{ old('title', $deliverable->title) }}" required>
    </label>
    <label>Jenis *
        <select name="type" required>
            @foreach (\App\Models\Deliverable::TYPES as $option)
                <option value="{{ $option }}" @selected(old('type', $deliverable->type) === $option)>{{ $option }}</option>
            @endforeach
        </select>
    </label>
    <label>Versi *
        <input type="number" min="1" name="version" value="{{ old('version', $deliverable->version) }}" required>
    </label>
    <label>Status *
        <select name="status" required>
            @foreach (\App\Models\Deliverable::STATUSES as $option)
                <option value="{{ $option }}" @selected(old('status', $deliverable->status) === $option)>{{ $option }}</option>
            @endforeach
        </select>
    </label>
    <label class="span-2">Tautan eksternal (mis. GalleryVT)
        <input type="url" name="external_url" value="{{ old('external_url', $deliverable->external_url) }}" placeholder="https://…">
    </label>
    <label class="span-2">Berkas
        <input type="file" name="file">
        @if ($deliverable->file_path)
            <small class="muted">Saat ini: {{ basename($deliverable->file_path) }} (unggah berkas baru untuk mengganti)</small>
        @endif
    </label>
    <label class="span-2">Catatan review
        <textarea name="review_note" rows="3">{{ old('review_note', $deliverable->review_note) }}</textarea>
    </label>
</div>
