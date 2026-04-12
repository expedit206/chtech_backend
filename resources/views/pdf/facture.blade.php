<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Facture SASAYEE - {{ $order->id }}</title>
    <style>
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            color: #333;
            line-height: 1.5;
            margin: 0;
            padding: 0;
        }
        .container {
            width: 100%;
            margin: auto;
            padding: 20px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #6366f1;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .logo {
            font-size: 28px;
            font-weight: bold;
            color: #6366f1;
            text-transform: uppercase;
        }
        .invoice-title {
            text-align: right;
            font-size: 24px;
            font-weight: bold;
            color: #333;
        }
        .details {
            width: 100%;
            margin-bottom: 30px;
        }
        .details td {
            vertical-align: top;
            width: 50%;
        }
        .section-title {
            font-size: 14px;
            font-weight: bold;
            color: #6366f1;
            margin-bottom: 10px;
            text-transform: uppercase;
        }
        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .items th {
            background-color: #f3f4f6;
            text-align: left;
            padding: 12px;
            font-size: 13px;
        }
        .items td {
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 13px;
        }
        .total-section {
            width: 100%;
            text-align: right;
        }
        .total-table {
            float: right;
            width: 250px;
        }
        .total-table td {
            padding: 8px;
        }
        .total-value {
            font-size: 18px;
            font-weight: bold;
            color: #6366f1;
        }
        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 11px;
            color: #999;
            border-top: 1px solid #e5e7eb;
            padding-top: 20px;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-paid { background-color: #d1fae5; color: #065f46; }
    </style>
</head>
<body>
    <div class="container">
        <table style="width: 100%">
            <tr>
                <td>
                    <div class="logo">SASAYEE</div>
                    <div style="font-size: 12px; color: #666;">La meilleure boutique en ligne du Cameroun</div>
                </td>
                <td style="text-align: right">
                    <div class="invoice-title">FACTURE n° {{ substr($order->id, 0, 8) }}</div>
                    <div style="font-size: 13px;">Date: {{ $date }}</div>
                </td>
            </tr>
        </table>

        <div style="height: 30px;"></div>

        <table class="details">
            <tr>
                <td>
                    <div class="section-title">De :</div>
                    <strong>SASAYEE Marketplace</strong><br>
                    Bafoussam, Ouest<br>
                    Cameroun<br>
                    Email: contact@sasayee.com
                </td>
                <td>
                    <div class="section-title">Facturé à :</div>
                    <strong>{{ $user->nom }}</strong><br>
                    Email: {{ $user->email }}<br>
                    Téléphone: {{ $order->contact_phone }}<br>
                    Adresse: {{ $order->delivery_address }}
                </td>
            </tr>
        </table>

        <table class="items">
            <thead>
                <tr>
                    <th>Désignation</th>
                    <th style="text-align: center">Quantité</th>
                    <th style="text-align: right">Prix Unitaire</th>
                    <th style="text-align: right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $item)
                <tr>
                    <td>
                        <strong>{{ $item->produit->nom }}</strong><br>
                        <span style="font-size: 11px; color: #666;">Ref: {{ $item->produit->id }}</span>
                    </td>
                    <td style="text-align: center">{{ $item->quantity }}</td>
                    <td style="text-align: right">{{ number_format($item->price, 0, ',', ' ') }} CFA</td>
                    <td style="text-align: right">{{ number_format($item->quantity * $item->price, 0, ',', ' ') }} CFA</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="total-section">
            <table class="total-table">
                <tr>
                    <td>Sous-total :</td>
                    <td>{{ number_format($order->total_amount, 0, ',', ' ') }} CFA</td>
                </tr>
                <tr>
                    <td>Taxes (0%) :</td>
                    <td>0 CFA</td>
                </tr>
                <tr style="border-top: 1px solid #333">
                    <td style="font-weight: bold">TOTAL :</td>
                    <td class="total-value">{{ number_format($order->total_amount, 0, ',', ' ') }} CFA</td>
                </tr>
            </table>
            <div style="clear: both;"></div>
        </div>

        <div style="margin-top: 20px;">
            <div class="section-title">État du Paiement :</div>
            <div class="status-badge status-paid">Payé via {{ $order->payment_method ?? 'Wallet' }}</div>
        </div>

        <div class="footer">
            Merci de votre confiance en SASAYEE.<br>
            Ceci est une facture générée électroniquement.
        </div>
    </div>
</body>
</html>
