<html>

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta name="viewport" content="width=device-width; initial-scale=1.0; maximum-scale=1.0;">
    <title>Trinity House</title>
</head>

<body style="-webkit-text-size-adjust:none !important;padding:0;">

    <p>Dear <?= $user->name ?>,</p>
    <p>Please click <a href="<?= url('/password/' . $user->password_reset_token . '/reset') ?>">here</a> to reset your password.</p>
    <p>Or copy this link <?= url('/password/' . $user->password_reset_token . '/reset') ?> into your browser url.</p>
    <p>Best Regards,</p>
    <p>Trinity House Team</p>

</body>

</html>