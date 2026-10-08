<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Receipt - {{ $rcpNo }}</title>
    <style>
        @page {
            margin: 0;
            size: auto;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, monospace;
            font-size: 12px;
            line-height: 1.4;
            color: #000;
            background: #fff;
            margin: 0 auto;
            padding: 12px;
            {{ $paperWidth === '58mm' ? 'width: 58mm; max-width: 58mm;' : 'width: 80mm; max-width: 80mm;' }}
            box-sizing: border-box;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .divider { border-top: 1px dashed #000; margin: 8px 0; }
        .double-divider { border-top: 2px solid #000; margin: 8px 0; }
        table { width: 100%; border-collapse: collapse; }
        .btn-print {
            display: block;
            width: 100%;
            padding: 8px;
            background: #2563eb;
            color: #fff;
            text-align: center;
            border: none;
            border-radius: 4px;
            font-weight: bold;
            cursor: pointer;
            margin-bottom: 12px;
        }
        @media print {
            .no-print { display: none !important; }
            body { padding: 0; }
        }
    </style>
</head>
<body>
    <button class="btn-print no-print" onclick="window.print()">🖨️ Print Receipt</button>
    
    <div class="text-center">
        <h2 style="margin: 0; font-size: 16px; font-weight: 800; text-transform: uppercase;">{{ $companyName }}</h2>
        <div style="font-size: 11px; color: #444; margin-top: 2px;">Front-Office Point of Sale</div>
        <div style="font-size: 11px; font-weight: bold; margin-top: 4px;">{{ $rcpNo }}</div>
    </div>

    <div class="divider"></div>

    <div style="font-size: 11px;">
        <div><strong>Date:</strong> {{ $formattedDate }}</div>
        <div><strong>Ref:</strong> {{ $sale->client_reference_id }}</div>
        <div><strong>Client:</strong> {{ $sale->customer_name }}</div>
        <div><strong>Cashier:</strong> {{ $sale->created_by_user_name }}</div>
    </div>

    <div class="divider"></div>

    <table>
        <thead>
            <tr style="border-bottom: 1px solid #000; font-size: 11px; text-transform: uppercase;">
                <th style="text-align: left; padding-bottom: 4px;">Item / Service</th>
                <th style="text-align: right; padding-bottom: 4px;">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sale->items as $item)
                <tr>
                    <td style="text-align: left; padding: 4px 0; font-weight: 500;">
                        {{ $item->description }}
                        <div style="font-size: 10px; color: #666;">{{ (float)$item->quantity }} x {{ number_format((float)$item->unit_price, 2) }}</div>
                    </td>
                    <td style="text-align: right; padding: 4px 0; vertical-align: top; font-weight: 600;">
                        {{ number_format((float)$item->total_price, 2) }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="divider"></div>

    <table>
        <tr>
            <td style="font-size: 11px;">Gross Amount:</td>
            <td class="text-right" style="font-size: 11px;">Rs. {{ number_format($grossAmt, 2) }}</td>
        </tr>
        @if($taxAmt > 0)
            <tr>
                <td style="font-size: 11px;">Tax / VAT:</td>
                <td class="text-right" style="font-size: 11px;">Rs. {{ number_format($taxAmt, 2) }}</td>
            </tr>
        @endif
        <tr style="font-size: 14px; font-weight: bold;">
            <td style="padding-top: 4px;">Total Amount:</td>
            <td class="text-right" style="padding-top: 4px;">Rs. {{ number_format($totalAmt, 2) }}</td>
        </tr>
        <tr>
            <td style="font-size: 11px; padding-top: 4px;">Paid / Received:</td>
            <td class="text-right" style="font-size: 11px; padding-top: 4px; font-weight: bold; color: #166534;">Rs. {{ number_format($paidAmt, 2) }}</td>
        </tr>
        @if($dueAmt > 0)
            <tr style="font-size: 11px; color: #991b1b; font-weight: bold;">
                <td>Balance Due:</td>
                <td class="text-right">Rs. {{ number_format($dueAmt, 2) }}</td>
            </tr>
        @endif
    </table>

    @if($sale->sales_voucher_ref || $sale->receipt_voucher_ref)
        <div style="margin-top: 10px; padding-top: 8px; border-top: 1px dashed #bbb; font-size: 10px; color: #555;">
            @if($sale->sales_voucher_ref)
                <div><strong>Sales Voucher:</strong> {{ $sale->sales_voucher_ref }}</div>
            @endif
            @if($sale->receipt_voucher_ref)
                <div><strong>Receipt Voucher:</strong> {{ $sale->receipt_voucher_ref }}</div>
            @endif
        </div>
    @endif

    <div class="double-divider"></div>

    <div class="text-center" style="font-size: 10px; color: #555;">
        <div>Thank you for your business!</div>
        <div style="margin-top: 2px;">Alamia Accounts • Enterprise Financial ERP</div>
    </div>
</body>
</html>
