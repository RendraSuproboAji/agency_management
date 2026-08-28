@php $oldItems = old('items', $quotation->items->toArray() ?: [['description' => '', 'qty' => 1, 'unit' => 'paket', 'unit_price' => '']]); @endphp

<div class="form-grid">
    <label>Tanggal terbit *
        <input type="date" name="issued_at" value="{{ old('issued_at', $quotation->issued_at?->format('Y-m-d')) }}" required>
    </label>
    <label>Berlaku sampai
        <input type="date" name="valid_until" value="{{ old('valid_until', $quotation->valid_until?->format('Y-m-d')) }}">
    </label>
    <label>Pajak (%) *
        <input type="number" step="0.01" min="0" max="100" name="tax_percent" value="{{ old('tax_percent', $quotation->tax_percent) }}" required>
    </label>
    <label>Status *
        <select name="status" required>
            @foreach (\App\Models\Quotation::STATUSES as $option)
                <option value="{{ $option }}" @selected(old('status', $quotation->status) === $option)>{{ $option }}</option>
            @endforeach
        </select>
    </label>
    <label class="span-2">Catatan
        <textarea name="notes" rows="3">{{ old('notes', $quotation->notes) }}</textarea>
    </label>
</div>

<h3>Item penawaran</h3>
<table class="table" data-items>
    <thead><tr><th>Deskripsi</th><th>Qty</th><th>Satuan</th><th>Harga satuan</th><th></th></tr></thead>
    <tbody>
    @foreach ($oldItems as $i => $item)
        <tr>
            <td><input type="text" name="items[{{ $i }}][description]" value="{{ $item['description'] ?? '' }}" required></td>
            <td><input type="number" step="0.01" min="0" class="mini" name="items[{{ $i }}][qty]" value="{{ $item['qty'] ?? 1 }}" required></td>
            <td><input type="text" class="mini" name="items[{{ $i }}][unit]" value="{{ $item['unit'] ?? '' }}"></td>
            <td><input type="number" step="0.01" min="0" name="items[{{ $i }}][unit_price]" value="{{ $item['unit_price'] ?? '' }}" required></td>
            <td><button type="button" class="btn btn-mini btn-danger" data-remove-row>Hapus</button></td>
        </tr>
    @endforeach
    </tbody>
</table>
<button type="button" class="btn btn-mini" data-add-row>Tambah baris</button>
