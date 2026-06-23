<!doctype html>
<html lang="hu">

<head>
    <meta charset="utf-8">
    <title>Belépéshez szükséges dokumentumok kitöltése</title>
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
                                Miell Group
                            </div>
                            <div style="font-size:14px; color:#eefbea; margin-top:4px;">
                                Belépéshez szükséges dokumentumok
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:30px 28px 10px;">
                            <h1 style="margin:0 0 18px; font-size:24px; line-height:1.3; color:#162018;">
                                Dokumentumok kitöltése
                            </h1>

                            <p style="margin:0 0 16px; font-size:15px; line-height:1.7;">
                                Kedves <?= esc($personName ?: 'Munkavállaló') ?>!
                            </p>

                            <p style="margin:0 0 16px; font-size:15px; line-height:1.7;">
                                A belépéshez szükséges dokumentumokat az alábbi biztonságos linken tudja kitölteni és
                                beküldeni.
                            </p>

                            <p style="margin:0 0 20px; font-size:15px; line-height:1.7;">
                                Kérjük, az adatokat pontosan, a hivatalos okmányokon szereplő adatokkal egyezően adja
                                meg.
                            </p>

                            <table role="presentation" cellspacing="0" cellpadding="0" style="margin:26px 0;">
                                <tr>
                                    <td style="background:rgb(80,184,72); border-radius:10px;">
                                        <a href="<?= esc($invitationUrl) ?>"
                                            style="display:inline-block; padding:13px 20px; color:#ffffff; text-decoration:none; font-size:15px; font-weight:700;">
                                            Dokumentumok kitöltése
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <div
                                style="background:#f8faf7; border:1px solid #dfe5dc; border-radius:12px; padding:14px 16px; margin:22px 0;">
                                <p style="margin:0 0 8px; font-size:13px; line-height:1.6; color:#667085;">
                                    Ha a gomb nem működik, másolja be ezt a linket a böngészőbe:
                                </p>
                                <p
                                    style="margin:0; font-size:13px; line-height:1.6; word-break:break-all; color:#344054;">
                                    <?= esc($invitationUrl) ?>
                                </p>
                            </div>

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