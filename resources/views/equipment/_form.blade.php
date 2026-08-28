<div class="form-grid">
    <label>Nama *
        <input type="text" name="name" value="{{ old('name', $item->name) }}" required placeholder="Mis. Sony A7IV">
    </label>
    <label>Kode *
        <input type="text" name="code" value="{{ old('code', $item->code) }}" required placeholder="Mis. CAM-01">
    </label>
    <label>Kategori *
        <select name="category" required>
            @foreach (\App\Models\Equipment::CATEGORIES as $option)
                <option value="{{ $option }}" @selected(old('category', $item->category) === $option)>{{ $option }}</option>
            @endforeach
        </select>
    </label>
    <label>Status *
        <select name="status" required>
            @foreach (\App\Models\Equipment::STATUSES as $option)
                <option value="{{ $option }}" @selected(old('status', $item->status) === $option)>{{ $option }}</option>
            @endforeach
        </select>
    </label>
    <label class="span-2">Nomor seri
        <input type="text" name="serial_number" value="{{ old('serial_number', $item->serial_number) }}">
    </label>
    <label class="span-2">Catatan
        <textarea name="notes" rows="3">{{ old('notes', $item->notes) }}</textarea>
    </label>
</div>
