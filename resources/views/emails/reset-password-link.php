<html>

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta name="viewport" content="width=device-width; initial-scale=1.0; maximum-scale=1.0;">
    <title>Trinity House</title>
</head>

<!-- password reset email -->
<body style="-webkit-text-size-adjust:none !important;padding:0;">

    <p>Dear <?= e($user->name) ?>,</p>
    <?php $resetUrl = rtrim(config('mail.admin_url'), '/') . '/reset/' . rawurlencode($user->password_reset_token) . '/password'; ?>
    <p>Please click <a href="<?= e($resetUrl) ?>">here</a> to reset your password.</p>
    <p>Or copy this link into your browser: <a href="<?= e($resetUrl) ?>"><?= e($resetUrl) ?></a></p>
    <p>Best Regards,</p>
    <p>Trinity House Team</p>

</body>

</html>