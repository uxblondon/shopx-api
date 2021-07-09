<html>

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta name="viewport" content="width=device-width; initial-scale=1.0; maximum-scale=1.0;">
    <title>Trinity House</title>
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
                <th width="50%">Order Details</th>
                <th width="50%">Payment Details</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="text-align: left; vertical-align: top;">
                    <div>Order Ref: <?= $order->ref ?></div>
                    <div>Date: <?= date('d-M-Y H:i a', strtotime($order->created_at)) ?></div>
                    <div>Name: <?= $order->name ?></div>
                    <div>Email: <?= $order->email ?></div>
                </td>
                <td style="text-align: left; vertical-align: top;">
                    <div>Name: <?= $order->payment->billing_details->name ?></div>
                    <div><?= ($order->payment->billing_details->email !== '' ? 'Email Address: ' . $order->payment->billing_details->email : '') ?></div>
                    <div><strong>Billing Address:</strong></div>
                    <div><?= $order->payment->billing_details->address_line_1 ?></div>
                    <div><?= $order->payment->billing_details->address_line_2 ?></div>
                    <div><?= $order->payment->billing_details->city ?></div>
                    <div><?= $order->payment->billing_details->county ?></div>
                    <div><?= $order->payment->billing_details->postcode ?></div>
                    <div><?= $order->payment->billing_details->country_code ?></div>
                    <div><strong>Total Order Amount:</strong> £<?= $order->payment->amount ?> (<?= $order->payment->payment_type ?>)</div>
                </td>
            </tr>
        </tbody>
    </table>

    <p>We will process the order with following deliveries:</p>
    <!-- Order deliveries -->
    <?php foreach ($order->deliveries as $delivery) { ?>

        <table>
            <thead>
                <tr>
                    <th colspan="4"><?= $delivery->method === 'collection' ? 'Collection' : 'Delivery' ?> Details</th>
                </tr>
            </thead>

            <tbody>
                <tr>
                    <td colspan="2" style="text-align: left;">
                        <div><strong>Type: </strong><?= $delivery->provider . ' - ' . $delivery->service ?></div>
                        <div><strong>Cost: </strong> £<?= $delivery->cost ?></div>
                    </td>
                    <td colspan="2" style="text-align: left;">
                        <div><strong><?= $delivery->method === 'collection' ? 'Collection' : 'Delivery' ?> Address:</strong></div>
                        <div><?= $delivery->address->name ?></div>
                        <div><?= $delivery->address->address_line_1 ?></div>
                        <div><?= $delivery->address->address_line_2 ?></div>
                        <div><?= $delivery->address->city ?></div>
                        <div><?= $delivery->address->county ?></div>
                        <div><?= $delivery->address->postcode ?></div>
                        <div><?= $delivery->address->country_code ?></div>
                        <div><?= $delivery->address->phone ? 'Phone: ' . $delivery->address->phone : '' ?></div>
                    </td>
                </tr>
                <tr>
                    <td style="text-align: center;">#</td>
                    <td style="text-align: left;">Item</td>
                    <td style="text-align: center;">Quantity</td>
                    <td style="text-align: center;">Unit Price</td>
                </tr>
                <?php foreach ($delivery->items as $key => $order_item) { ?>
                    <tr>
                        <td><?= $key + 1 ?>
                        </td>
                        <td style="text-align: left;">
                            <?= $order_item->title ?>
                            <?= $order_item->variant_1_value ? '(' . $order_item->variant_1_name . ':' . $order_item->variant_1_value . ')' : '' ?>
                            <?= $order_item->variant_2_value ? '(' . $order_item->variant_2_name . ':' . $order_item->variant_2_value . ')' : '' ?>
                            <?= $order_item->variant_3_value ? '(' . $order_item->variant_3_name . ':' . $order_item->variant_3_value . ')' : '' ?>
                        </td>
                        <td style="text-align: center;"><?= $order_item->quantity ?>
                        </td>
                        <td style="text-align: right;"><?= $order_item->price ?>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    <?php } ?>

    <p>Best Regards,</p>
    <p>Trinity House Team</p>

</body>

</html>