{{-- Expects: $bill, $society, $qrSvgBase64, $ad. Rendered once per bill; used standalone (pdf.bill) and looped (pdf.bill-run). --}}
@php
    $copies = ['Resident Copy', 'Bank Copy', 'Society Copy'];
@endphp

<div class="bill-block">
@foreach ($copies as $copyLabel)
<div class="copy">
    <div class="copy-label">{{ $copyLabel }}</div>
    <div class="header">
        <div class="left">
            <div class="society-name">{{ $society->name }}</div>
            <div class="doc-title">Monthly Maintenance Bill</div>
            <div>Bank: {{ $society->bank_name }} | A/C: {{ $society->bank_account_number }}</div>
            <div>IBAN: {{ $society->bank_iban }}</div>
        </div>
        <div class="right">
            <div>Scan to Pay</div>
            <img src="data:image/svg+xml;base64,{{ $qrSvgBase64 }}" width="80" height="80">
        </div>
    </div>

    <table class="ids">
        <tr>
            <td><strong>Reference No:</strong> {{ $bill->reference_number }}</td>
            <td><strong>Bill No:</strong> {{ $bill->bill_number }}</td>
            <td><strong>Tariff Type:</strong> {{ $bill->tariff_type_snapshot }}</td>
        </tr>
        <tr>
            <td colspan="2"><strong>Name:</strong> {{ $bill->owner_name_snapshot }}</td>
            <td><strong>Status:</strong> {{ ucfirst($bill->residence_status_snapshot) }}</td>
        </tr>
        <tr>
            <td colspan="3"><strong>Address:</strong> {{ $bill->address_snapshot }}</td>
        </tr>
        <tr>
            <td><strong>Billing Month:</strong> {{ $bill->billing_month->format('M Y') }}</td>
            <td><strong>Issued On:</strong> {{ $bill->issued_on->format('d-m-Y') }}</td>
            <td><strong>Due Date:</strong> {{ $bill->due_date->format('d-m-Y') }}</td>
        </tr>
    </table>

    @if ($copyLabel === 'Resident Copy')
    <table class="items">
        <thead>
            <tr><th>#</th><th>Charge Head</th><th style="text-align:right">Amount (PKR)</th></tr>
        </thead>
        <tbody>
            @foreach ($bill->items as $item)
            <tr>
                <td>{{ $item->serial }}</td>
                <td>{{ $item->description }}</td>
                <td style="text-align:right">{{ number_format($item->amount, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td>This Month Bill</td><td style="text-align:right">{{ number_format($bill->this_month_total, 2) }}</td></tr>
        <tr><td>Arrears</td><td style="text-align:right">{{ number_format($bill->arrears, 2) }}</td></tr>
        <tr><td>Adjustments</td><td style="text-align:right">{{ number_format($bill->adjustments_total, 2) }}</td></tr>
        <tr class="grand"><td>Payable within Due Date</td><td style="text-align:right">{{ number_format($bill->payable_within_due, 2) }}</td></tr>
        <tr><td>Surcharge on Late Payment</td><td style="text-align:right">{{ number_format($bill->surcharge_amount, 2) }}</td></tr>
        <tr class="grand"><td>Payable after Due Date</td><td style="text-align:right">{{ number_format($bill->payable_after_due, 2) }}</td></tr>
    </table>

    @if ($bill->billRun->announcement)
    <div class="announcement">{{ $bill->billRun->announcement }}</div>
    @endif

    @if ($ad)
    <div class="ad-slot"><img src="{{ storage_path('app/public/'.$ad->creative_path) }}"></div>
    @endif
    @else
    <table class="totals">
        <tr class="grand"><td>Payable within Due Date</td><td style="text-align:right">{{ number_format($bill->payable_within_due, 2) }}</td></tr>
        <tr><td>Payable after Due Date</td><td style="text-align:right">{{ number_format($bill->payable_after_due, 2) }}</td></tr>
    </table>
    @endif

    <div class="linked-status {{ $bill->app_linked_snapshot ? 'linked' : 'unlinked' }}">
        @if ($bill->app_linked_snapshot)
            Mobile app linked: Yes (since {{ optional($bill->app_linked_since_snapshot)->format('d-m-Y') }})
        @else
            Mobile app linked: Not linked - if this is your property, please download the app and verify your account.
        @endif
    </div>

    <div class="footer">
        Printed by {{ $printedBy ?? (auth('sanctum')->user()->name ?? 'System') }} on {{ now()->format('d-m-Y H:i') }}
    </div>
</div>
@endforeach
</div>
