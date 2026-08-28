<div class="form-grid">
    <label>Nama klien *
        <input type="text" name="name" value="{{ old('name', $client->name) }}" required>
    </label>
    <label>Status *
        <select name="status" required>
            @foreach (\App\Models\Client::STATUSES as $option)
                <option value="{{ $option }}" @selected(old('status', $client->status) === $option)>{{ $option }}</option>
            @endforeach
        </select>
    </label>
    <label>Nama narahubung
        <input type="text" name="contact_name" value="{{ old('contact_name', $client->contact_name) }}">
    </label>
    <label>Email
        <input type="email" name="email" value="{{ old('email', $client->email) }}">
    </label>
    <label>Telepon
        <input type="text" name="phone" value="{{ old('phone', $client->phone) }}">
    </label>
    <label>Industri
        <input type="text" name="industry" value="{{ old('industry', $client->industry) }}">
    </label>
    <label class="span-2">Alamat
        <input type="text" name="address" value="{{ old('address', $client->address) }}">
    </label>
    <label class="span-2">Catatan
        <textarea name="notes" rows="4">{{ old('notes', $client->notes) }}</textarea>
    </label>
</div>
