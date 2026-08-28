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
    <label class="span-2">Peralatan
        <textarea name="equipment" rows="3">{{ old('equipment', $session->equipment) }}</textarea>
    </label>
    <label class="span-2">Catatan
        <textarea name="notes" rows="4">{{ old('notes', $session->notes) }}</textarea>
    </label>
</div>
