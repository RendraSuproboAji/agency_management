<div class="form-grid">
    <label>Jadwal *
        <input type="datetime-local" name="scheduled_at"
               value="{{ old('scheduled_at', $session->scheduled_at?->format('Y-m-d\TH:i')) }}" required>
    </label>
    <label>Kru
        <select name="crew_id">
            <option value="">— belum ditentukan —</option>
            @foreach ($crew as $member)
                <option value="{{ $member->id }}" @selected((int) old('crew_id', $session->crew_id) === $member->id)>{{ $member->name }}</option>
            @endforeach
        </select>
    </label>
    <label>Status *
        <select name="status" required>
            @foreach (\App\Models\CaptureSession::STATUSES as $option)
                <option value="{{ $option }}" @selected(old('status', $session->status) === $option)>{{ $option }}</option>
            @endforeach
        </select>
    </label>
    <label>Jumlah shot
        <input type="number" min="0" name="shot_count" value="{{ old('shot_count', $session->shot_count) }}">
    </label>
    <label class="span-2">Lokasi
        <input type="text" name="location" value="{{ old('location', $session->location) }}">
    </label>
    <label class="span-2">Catatan cuaca / kondisi
        <input type="text" name="weather_note" value="{{ old('weather_note', $session->weather_note) }}">
    </label>
    <label>Ukuran data mentah (GB)
        <input type="number" step="0.01" min="0" name="raw_size_gb" value="{{ old('raw_size_gb', $session->raw_size_gb) }}">
    </label>
    <label>Jumlah frame
        <input type="number" min="0" name="frame_count" value="{{ old('frame_count', $session->frame_count) }}">
    </label>
    <label class="span-2">Lokasi backup
        <input type="text" name="backup_location" value="{{ old('backup_location', $session->backup_location) }}"
               placeholder="Mis. NAS/2026/showroom-kemang, HDD-03">
    </label>
    <label class="span-2">Catatan peralatan
        <textarea name="equipment_note" rows="3">{{ old('equipment_note', $session->equipment_note) }}</textarea>
    </label>
    <label class="span-2">Catatan
        <textarea name="notes" rows="4">{{ old('notes', $session->notes) }}</textarea>
    </label>
</div>

<h3>Peralatan dari inventaris</h3>
@php $chosen = collect(old('equipment', $session->exists ? $session->equipment->pluck('id')->all() : [])); @endphp
@if ($equipment->isEmpty())
    <p class="muted">Belum ada peralatan tersedia di inventaris.</p>
@else
    <div class="checkbox-grid">
        @foreach ($equipment as $item)
            <label class="inline">
                <input type="checkbox" name="equipment[]" value="{{ $item->id }}" @checked($chosen->contains($item->id))>
                {{ $item->name }} <span class="muted">{{ $item->code }} · {{ $item->category }}</span>
            </label>
        @endforeach
    </div>
@endif
