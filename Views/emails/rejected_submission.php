<!doctype html>
<html lang="hu">

<head>
    <meta charset="utf-8">
    <title>Javítás szükséges</title>
</head>

<body style="margin:0; padding:0; background:#f3f6f2; font-family:Arial, Helvetica, sans-serif; color:#1f2933;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0"
        style="background:#f3f6f2; margin:0; padding:28px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0"
                    style="max-width:680px; background:#ffffff; border:1px solid #dfe5dc; border-radius:18px; overflow:hidden;">
                    <tr>
                        <td style="background:#b42318; padding:22px 28px;">
                            <div style="font-size:20px; line-height:1.3; font-weight:700; color:#ffffff;">
                                Javítás szükséges
                            </div>
                            <div style="font-size:14px; color:#ffe4e8; margin-top:4px;">
                                Miell Group dokumentumkitöltés
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:30px 28px 10px;">
                            <p style="margin:0 0 16px; font-size:15px; line-height:1.7;">
                                Kedves <?= esc($personName ?: 'Munkavállaló') ?>!
                            </p>

                            <p style="margin:0 0 16px; font-size:15px; line-height:1.7;">
                                Az alábbi dokumentum ellenőrzés után javításra visszaküldésre került:
                            </p>

                            <div
                                style="background:#f8faf7; border:1px solid #dfe5dc; border-radius:12px; padding:14px 16px; margin:18px 0;">
                                <div style="font-size:15px; font-weight:700; color:#162018;">
                                    <?= esc($declarationName) ?>
                                </div>
                            </div>

                            <p style="margin:22px 0 8px; font-size:15px; line-height:1.7; font-weight:700;">
                                Ellenőrzési megjegyzés:
                            </p>

                            <div
                                style="background:#fff1f2; border:1px solid #fecdd3; color:#b42318; border-radius:12px; padding:14px 16px; margin:0 0 20px; font-size:14px; line-height:1.7;">
                                <?= nl2br(esc($reviewNote)) ?>
                            </div>

                            <p style="margin:0 0 16px; font-size:15px; line-height:1.7;">
                                Kérjük, nyissa meg újra a korábban kapott dokumentumkitöltő linket, javítsa az adatokat,
                                majd küldje be ismét ellenőrzésre.
                            </p>

                            <p style="margin:22px 0 0; font-size:15px; line-height:1.7;">
                                Köszönjük!<br>
                                Miell Group
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:18px 28px 28px;">
                            <div
                                style="border-top:1px solid #eaecf0; padding-top:16px; color:#667085; font-size:12px; line-height:1.6;">
                                Ez egy automatikus értesítés, kérjük, ne erre az e-mailre válaszoljon.
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>