@if (config('site.company.bank.account'))
    <div class="bank">
        <h3>Pembayaran</h3>
        {{ config('site.company.bank.name') }} —
        {{ config('site.company.bank.account') }}
        a.n. {{ config('site.company.bank.holder') }}
    </div>
@endif
