<?= $this->extend('App\\Modules\\Declarations\\Views\\public\\layout') ?>

<?= $this->section('content') ?>

<div class="form-layout">
    <aside class="form-context-panel">
        <div class="eyebrow">Beküldött dokumentum</div>
        <h1><?= esc($item->template_name ?? 'Nyilatkozat') ?></h1>

        <?php if ($submission && !empty($submission->submitted_at)): ?>
            <p>Beküldés ideje: <strong><?= esc($submission->submitted_at) ?></strong></p>
        <?php else: ?>
            <p>A dokumentum adatai a link érvényességéig megtekinthetők.</p>
        <?php endif; ?>

        <div class="helper-card">
            <div class="helper-title">Állapot</div>
            <p>A beküldés rögzítve van. Ha módosítás szükséges, azt a kapcsolattartó jelzi.</p>
        </div>

        <a href="<?= esc($startUrl) ?>" class="btn btn-secondary btn-block">Vissza az összesítőhöz</a>
    </aside>

    <main class="form-main-panel">
        <section class="content-card">
            <div class="section-heading">
                <div>
                    <h2>Beküldött adatok</h2>
                    <p class="section-note">Ezek az adatok kerültek mentésre ehhez a dokumentumhoz.</p>
                </div>
            </div>

            <div class="notice notice-success">A dokumentum beküldése sikeresen rögzítve van.</div>

            <?php if (!empty($displayRows)): ?>
                <div class="summary-card">
                    <div class="summary-title"><?= esc($item->template_name ?? 'Nyilatkozat') ?></div>
                    <dl class="summary-list">
                        <?php foreach ($displayRows as $label => $value): ?>
                            <dt><?= esc($label) ?></dt>
                            <dd><?= esc($value !== '' ? $value : '-') ?></dd>
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
