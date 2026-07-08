<!doctype html>
<html lang="hu">
<head>
    <meta charset="utf-8">
    <title>Nyilatkozat kitöltése</title>
</head>
<body style="font-family: Arial, sans-serif; color: #222; line-height: 1.5;">
    <p>Kedves <?= esc($personName ?? '') ?>!</p>

    <p>
        Az intranetes felületen nyilatkozat kitöltését indítottad el
        <?= !empty($packet->tax_year) ? esc($packet->tax_year) . '. évre' : 'a saját adataidhoz kapcsolódóan' ?>.
    </p>

    <p>A kitöltést az alábbi linken tudod folytatni:</p>

    <p>
        <a href="<?= esc($invitationUrl ?? '') ?>" style="display: inline-block; padding: 10px 16px; background: #50b848; color: #fff; text-decoration: none; border-radius: 4px;">
            Nyilatkozat kitöltése
        </a>
    </p>

    <p style="font-size: 13px; color: #555;">
        Ha a gomb nem működik, másold be ezt a linket a böngészőbe:<br>
        <?= esc($invitationUrl ?? '') ?>
    </p>

    <p>
        A beküldés előtt lehetőséged lesz ellenőrizni a megadott adatokat.
    </p>

    <p>Üdvözlettel:<br>Miell Group</p>
</body>
</html>
