<!doctype html>
<html lang="hu">
<head>
    <meta charset="utf-8">
    <title><?= esc($title ?? 'Miell nyilatkozatok') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/assets/declarations/css/public.css">
</head>
<body>
<div class="page">
    <header class="topbar">
        <div class="topbar-inner">
            <div class="brand">
                <img src="/assets/declarations/img/logo.svg" alt="Miell Group" class="brand-logo-img">
                <div class="brand-text">
                    <div class="brand-name">Nyilatkozatkitöltő felület</div>
                    <div class="brand-subtitle">Biztonságos, meghívó linkes kitöltés</div>
                </div>
            </div>

            <div class="security-pill">
                <span aria-hidden="true">🔒</span>
                <span>Egyedi meghívó link</span>
            </div>
        </div>
    </header>

    <main class="main">
        <div class="container">
            <?php if (session()->getFlashdata('sSuccess')): ?>
                <div class="notice notice-success page-notice">
                    <?= esc(session()->getFlashdata('sSuccess')) ?>
                </div>
            <?php endif; ?>

            <?php if (session()->getFlashdata('sError')): ?>
                <div class="notice notice-danger page-notice">
                    <?= esc(session()->getFlashdata('sError')) ?>
                </div>
            <?php endif; ?>

            <?= $this->renderSection('content') ?>
        </div>
    </main>

    <footer class="footer">
        © <?= date('Y') ?> Miell Group · A felület kizárólag meghívó linkkel használható.
    </footer>
</div>
</body>
</html>
