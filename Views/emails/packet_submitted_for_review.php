<!doctype html>
<html lang="hu">

<head>
    <meta charset="utf-8">
    <title>Nyilatkozatcsomag ellenőrzésre vár</title>
</head>

<body style="margin:0; padding:0; background:#f3f6f2; font-family:Arial, Helvetica, sans-serif; color:#1f2933;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0"
        style="background:#f3f6f2; margin:0; padding:28px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0"
                    style="max-width:680px; background:#ffffff; border:1px solid #dfe5dc; border-radius:18px; overflow:hidden;">
                    <tr>
                        <td style="background:rgb(80,184,72); padding:22px 28px;">
                            <div style="font-size:20px; line-height:1.3; font-weight:700; color:#ffffff;">
                                Nyilatkozatcsomag ellenőrzésre vár
                            </div>
                            <div style="font-size:14px; color:#eefbea; margin-top:4px;">
                                Miell Group dokumentumkitöltés
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:30px 28px 10px;">
                            <p style="margin:0 0 16px; font-size:15px; line-height:1.7;">
                                A beálló véglegesen beküldte a nyilatkozatcsomagot, ellenőrzésre vár.
                            </p>

                            <div
                                style="background:#f8faf7; border:1px solid #dfe5dc; border-radius:12px; padding:14px 16px; margin:18px 0;">
                                <p style="margin:0 0 8px; font-size:14px; line-height:1.6;">
                                    <strong>Beálló:</strong> <?= esc($personName ?: '-') ?>
                                </p>
                                <p style="margin:0 0 8px; font-size:14px; line-height:1.6;">
                                    <strong>Cég:</strong> <?= esc($companyName ?: '-') ?>
                                </p>
                                <p style="margin:0 0 8px; font-size:14px; line-height:1.6;">
                                    <strong>Adóév:</strong> <?= esc($packet->tax_year ?: '-') ?>
                                </p>
                                <p style="margin:0; font-size:14px; line-height:1.6;">
                                    <strong>Elsődleges toborzó:</strong> <?= esc($recruiterName ?: '-') ?>
                                </p>
                            </div>

                            <p style="margin:22px 0 0; font-size:15px; line-height:1.7;">
                                Kérjük, nyissátok meg az intranetes nyilatkozatcsomagot, és végezzétek el a szükséges
                                toborzói vagy munkaügyi ellenőrzést.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:18px 28px 28px;">
                            <div
                                style="border-top:1px solid #eaecf0; padding-top:16px; color:#667085; font-size:12px; line-height:1.6;">
                                Ez egy automatikus értesítés, kérjük, ne erre az e-mailre válaszoljatok.
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>
