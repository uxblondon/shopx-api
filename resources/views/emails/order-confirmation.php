<html>

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta name="viewport" content="width=device-width; initial-scale=1.0; maximum-scale=1.0;">
    <title></title>
    <style type="text/css" media="screen">
        table {
            font-family: "Trebuchet MS", Arial, Helvetica, sans-serif;
            border-collapse: collapse;
            width: 90%;
        }

        table td,
        table th {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: center;
        }

        table tr:nth-child(even) {
            background-color: #f2f2f2;
        }

        table tr:hover {
            background-color: #ddd;
        }

        table th {
            padding-top: 12px;
            padding-bottom: 12px;
            text-align: left;
            background-color: #474C51;
            color: white;
        }
    </style>
</head>

<body style="-webkit-text-size-adjust:none !important;padding:0;">

    <p>Dear <?= $order->name ?>,</p>
    <p>Thank you for your order.</p>

    <!-- Order Details -->
    <table>
        <thead>
            <tr>
                <th colspan="3" style="background-color: #556C78; text-align: center;">
                    <img width="16%" src="https://www.trinityhouse.co.uk/vendor/boomcms/themes/th/img/white-logo.png" alt="Trinity House Logo" />
                </th>
            </tr>
            <tr>
                <th width="34%">Order Details</th>
                <th width="33%">Shipping Address</th>
                <th width="33%">Billing Address</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="text-align: left;">
                    <div>Order ID: <?= sprintf("%'.06d", $order->id) ?>
                    </div>
                    <div>Date: <?= $order->date ?>
                    </div>
                    <div>Name: <?= $order->name ?>
                    </div>
                    <div>Email: <?= $order->email ?>
                    </div>
                </td>
                <td style="text-align: left;">
                    <div><?= $order->shipping_address_street ?>
                    </div>
                    <div><?= $order->shipping_address_city ?>
                    </div>
                    <div><?= $order->shipping_address_postcode ?>
                    </div>
                    <div><?= $order->shipping_address_country ?>
                    </div>
                </td>
                <td style="text-align: left;">
                    <div><?= $order->billing_address_street ?>
                    </div>
                    <div><?= $order->billing_address_city ?>
                    </div>
                    <div><?= $order->billing_address_postcode ?>
                    </div>
                    <div><?= $order->billing_address_country ?>
                    </div>
                </td>
            </tr>
        </tbody>
    </table>


<p>We will process the following items:</p>
    <!-- Order items -->
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Description</th>
                <th>Quantity</th>
                <th>Unit Price</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($order_items as $key => $order_item) { ?>
            <tr>
                <td><?= $key+1 ?>
                </td>
                <td style="text-align: left;"><?= $order_item->description ?>
                </td>
                <td style="text-align: center;"><?= $order_item->quantity ?>
                </td>
                <td style="text-align: right;"><?= $order_item->unit_price ?>
                </td>
            </tr>
            <?php } ?>
        </tbody>
        <tfoot>
            <tr>
                <th colspan="2" style="text-align: right; font-weight: bold;">Via <?= $order->stripe_charge_id == '' ? 'Paypal' : 'Stripe' ?></th>
                <th style="font-weight: bold;">Total </th>
                <th style="text-align: right; font-weight: bold;">£<?= $order->amount ?></th>
            </tr>
        </tfoot>
    </table>

    <p>Best Regards,</p>
    <p>Trinity House Team</p>

</body>

</html>