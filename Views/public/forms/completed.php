<?= $this->extend('App\\Modules\\Declarations\\Views\\public\\layout') ?>

<?= $this->section('content') ?>

<div class="submitted-layout">
    <aside class="submitted-side">
        <a href="<?= esc($startUrl) ?>" class="back-link">← Vissza az összesítőhöz</a>

        <div class="eyebrow">Beküldött dokumentum</div>
        <h1><?= esc($item->template_name ?? 'Nyilatkozat') ?></h1>

        <div class="submitted-status-card">
            <span class="submitted-status-dot"></span>
            <div>
                <strong>Rögzítve</strong>
                <?php if ($submission && !empty($submission->submitted_at)): ?>
                    <span><?= esc($submission->submitted_at) ?></span>
                <?php else: ?>
                    <span>A link érvényességéig megtekinthető</span>
                <?php endif; ?>
            </div>
        </div>

        <p class="submitted-help">
            Ha módosítás szükséges, azt a kapcsolattartó jelzi, és a dokumentum újranyitható javításra.
        </p>
    </aside>

    <main class="submitted-main">
        <section class="submitted-card">
            <div class="submitted-head">
                <div>
                    <div class="eyebrow">Ellenőrzött adatok</div>
                    <h2>Beküldött adatok</h2>
                    <p>Ezek az adatok kerültek mentésre ehhez a dokumentumhoz.</p>
                </div>
                <span class="badge badge-completed">Sikeresen rögzítve</span>
            </div>

            <?php if (!empty($displayRows)): ?>
                <div class="data-review-card">
                    <div class="data-review-title"><?= esc($item->template_name ?? 'Nyilatkozat') ?></div>
                    <dl class="data-review-list">
                        <?php foreach ($displayRows as $label => $value): ?>
                            <div>
                                <dt><?= esc($label) ?></dt>
                                <dd><?= esc($value !== '' ? $value : '-') ?></dd>
                            </div>
                        <?php endforeach; ?>
                    </dl>
                </div>
            <?php else: ?>
                <div class="empty-state">Ehhez a dokumentumhoz nincs megjeleníthető adat.</div>
            <?php endif; ?>
        </section>
    </main>
</div>

<?= $this->endSection() ?>
