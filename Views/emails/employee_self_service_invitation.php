<!doctype html>
<html lang="hu">
<head>
    <meta charset="utf-8">
    <title>Nyilatkozat kitöltése</title>
</head>
<body style="font-family: Arial, sans-serif; color: #222; line-height: 1.5;">
    <p>Kedves <?= esc($personName ?? '') ?>!</p>

    <p>
        Az intranetes felületen nyilatkozat kitöltését indított el.
    </p>

    <p>A kitöltést az alábbi linken tudja folytatni:</p>

    <?php if (!empty($antraId)): ?>
        <p style="padding: 12px 14px; background: #f3f8f1; border: 1px solid #d9ead3; border-radius: 8px;">
            A link megnyitásakor megadandó ANTRA azonosító:<br>
            <strong style="font-size: 18px;"><?= esc($antraId) ?></strong>
        </p>
    <?php endif; ?>

    <p>
        <a href="<?= esc($invitationUrl ?? '') ?>" style="display: inline-block; padding: 10px 16px; background: #50b848; color: #fff; text-decoration: none; border-radius: 4px;">
            Nyilatkozat kitöltése
        </a>
    </p>

    <p style="font-size: 13px; color: #555;">
        Ha a gomb nem működik, másolja be ezt a linket a böngészőbe:<br>
        <?= esc($invitationUrl ?? '') ?>
    </p>

    <p>
        A beküldés előtt lehetősége lesz ellenőrizni a megadott adatokat.
    </p>

    <p>Üdvözlettel:<br>Miell Group</p>
</body>
</html>
