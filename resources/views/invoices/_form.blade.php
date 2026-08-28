<div class="form-grid">
    <label>Tanggal terbit *
        <input type="date" name="issued_at" value="{{ old('issued_at', $invoice->issued_at?->format('Y-m-d')) }}" required>
    </label>
    <label>Jatuh tempo
        <input type="date" name="due_at" value="{{ old('due_at', $invoice->due_at?->format('Y-m-d')) }}">
    </label>
    <label>Nilai tagihan (Rp) *
        <input type="number" step="0.01" min="0" name="amount" value="{{ old('amount', $invoice->amount) }}" required>
    </label>
    <label>Status *
        <select name="status" required>
            @foreach (\App\Models\Invoice::STATUSES as $option)
                <option value="{{ $option }}" @selected(old('status', $invoice->status) === $option)>{{ $option }}</option>
            @endforeach
        </select>
    </label>
    <label class="span-2">Penawaran terkait
        <select name="quotation_id">
            <option value="">— tidak ada —</option>
            @foreach ($project->quotations as $option)
                <option value="{{ $option->id }}" @selected((int) old('quotation_id', $invoice->quotation_id) === $option->id)>{{ $option->number }}</option>
            @endforeach
        </select>
    </label>
    <label class="span-2">Catatan
        <textarea name="notes" rows="3">{{ old('notes', $invoice->notes) }}</textarea>
    </label>
</div>
