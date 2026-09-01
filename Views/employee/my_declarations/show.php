<?= $this->extend('admin/layout/default') ?>
<?= $this->section('content') ?>

<?php
$person = $person ?? null;
$packet = $packet ?? null;
$company = $company ?? null;
$itemDetails = $itemDetails ?? [];

$packetStatusLabels = [
    'draft' => ['Előkészítés alatt', 'secondary'],
    'sent' => ['Kiküldve', 'info'],
    'in_progress' => ['Kitöltés alatt', 'warning'],
    'submitted' => ['Beküldve, ellenőrzés alatt', 'primary'],
    'approved' => ['Elfogadva, lezárásra vár', 'warning'],
    'completed' => ['Elfogadva, lezárásra vár', 'warning'],
    'closed' => ['Lezárva', 'dark'],
    'cancelled' => ['Törölve', 'danger'],
];

$itemStatusLabels = [
    'pending' => ['Kitöltésre vár', 'warning'],
    'completed' => ['Beküldve, ellenőrzés alatt', 'primary'],
    'accepted' => ['Elfogadva', 'success'],
    'rejected' => ['Javítás szükséges', 'danger'],
    'cancelled' => ['Törölve', 'secondary'],
];

$packetFlowLabels = [
    'onboarding' => 'Első beléptetési csomag',
    'self_service' => 'Saját indítású csomag',
    'self_service_tax' => 'Saját adóügyi nyilatkozat',
    'self_service_change' => 'Saját adatmódosítás',
    'admin_manual' => 'Munkaügyi kiküldés',
];

$packetStatus = (string) ($packet->status ?? '');
[$packetStatusLabel, $packetStatusClass] = $packetStatusLabels[$packetStatus] ?? [$packetStatus ?: '-', 'secondary'];
$packetFlowType = (string) ($packet->flow_type ?? '');
?>

<style>
    .employee-declaration-summary {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .75rem;
    }

    .employee-declaration-field {
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        background: #f8fafc;
        padding: .7rem .8rem;
        min-width: 0;
    }

    .employee-declaration-field span {
        display: block;
        color: #64748b;
        font-size: .75rem;
        font-weight: 700;
        text-transform: uppercase;
    }

    .employee-declaration-field strong {
        display: block;
        margin-top: .2rem;
        color: #111827;
        overflow-wrap: anywhere;
    }

    .employee-declaration-table-wrap {
        width: 100%;
        overflow-x: auto;
    }

    .employee-declaration-table {
        min-width: 720px;
    }

    @media (max-width: 768px) {
        .employee-declaration-summary {
            grid-template-columns: 1fr;
        }
    }
</style>

<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-7">
                <h1>Nyilatkozatcsomag #<?= (int) ($packet->id ?? 0) ?></h1>
                <p class="text-muted mb-0">A saját nyilatkozataid beküldött és elfogadott adatai.</p>
            </div>
            <div class="col-sm-5">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="<?= url('/') ?>"><?= lang('App.home') ?></a></li>
                    <li class="breadcrumb-item"><a href="<?= url('declarations/my-declarations') ?>">Nyilatkozataim</a></li>
                    <li class="breadcrumb-item active">Csomag #<?= (int) ($packet->id ?? 0) ?></li>
                </ol>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <?php if (session()->getFlashdata('sError')): ?>
            <div class="alert alert-danger">
                <?= esc(session()->getFlashdata('sError')) ?>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Csomag adatai</h3>
                    </div>
                    <div class="card-body">
                        <strong>Státusz</strong>
                        <p><span class="badge badge-<?= esc($packetStatusClass) ?>"><?= esc($packetStatusLabel) ?></span></p>

                        <strong>Indítás módja</strong>
                        <p class="text-muted"><?= esc($packetFlowType !== '' ? ($packetFlowLabels[$packetFlowType] ?? $packetFlowType) : '-') ?></p>

                        <strong>Nyilatkozati év</strong>
                        <p class="text-muted"><?= esc($packet->tax_year ?: '-') ?></p>

                        <strong>Cég</strong>
                        <p class="text-muted"><?= esc($company->name ?? ('#' . ($packet->company_id ?? '-'))) ?></p>

                        <strong>Folyamat dátuma</strong>
                        <p class="text-muted"><?= esc($packet->process_date ?: '-') ?></p>

                        <strong>Létrehozva</strong>
                        <p class="text-muted"><?= esc($packet->created_at ?: '-') ?></p>

                        <strong>Beküldve / lezárva</strong>
                        <p class="text-muted mb-0"><?= esc($packet->completed_at ?: '-') ?></p>
                    </div>
                </div>

                <a href="<?= url('declarations/my-declarations') ?>" class="btn btn-default btn-block">
                    <i class="fas fa-arrow-left pr-1"></i> Vissza a Nyilatkozataim oldalra
                </a>
            </div>

            <div class="col-lg-8">
                <?php if (empty($itemDetails)): ?>
                    <div class="card">
                        <div class="card-body">
                            <p class="text-muted mb-0">Ebben a csomagban nincs megjeleníthető nyilatkozat.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($itemDetails as $detail): ?>
                        <?php
                        $item = $detail['item'] ?? null;
                        $submission = $detail['submission'] ?? null;
                        $displayRows = is_array($detail['display_rows'] ?? null) ? $detail['display_rows'] : [];
                        $displayTables = is_array($detail['display_tables'] ?? null) ? $detail['display_tables'] : [];
                        $itemStatus = (string) ($item->status ?? '');
                        [$itemStatusLabel, $itemStatusClass] = $itemStatusLabels[$itemStatus] ?? [$itemStatus ?: 'Ismeretlen állapot', 'secondary'];
                        ?>
                        <div class="card">
                            <div class="card-header">
                                <div class="d-flex flex-wrap justify-content-between align-items-start">
                                    <div>
                                        <h3 class="card-title mb-1">
                                            <?= esc($item->template_name ?? 'Nyilatkozat') ?>
                                        </h3>
                                        <div class="text-muted small">
                                            Verzió: <?= esc($item->template_version ?: '-') ?>
                                        </div>
                                    </div>
                                    <span class="badge badge-<?= esc($itemStatusClass) ?> mt-1"><?= esc($itemStatusLabel) ?></span>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (!$submission): ?>
                                    <p class="text-muted mb-0">Ehhez a nyilatkozathoz még nincs mentett adat.</p>
                                <?php else: ?>
                                    <div class="employee-declaration-summary mb-3">
                                        <div class="employee-declaration-field">
                                            <span>Beküldve</span>
                                            <strong><?= esc($submission->submitted_at ?: '-') ?></strong>
                                        </div>
                                        <div class="employee-declaration-field">
                                            <span>Beküldő</span>
                                            <strong><?= esc($submission->submitter_label ?: ($submission->submitter_email ?: '-')) ?></strong>
                                        </div>
                                        <div class="employee-declaration-field">
                                            <span>Beküldés azonosítója</span>
                                            <strong><?= esc($submission->submission_hash ?: '-') ?></strong>
                                        </div>
                                        <div class="employee-declaration-field">
                                            <span>Elfogadva</span>
                                            <strong><?= esc($submission->accepted_at ?: '-') ?></strong>
                                        </div>
                                    </div>

                                    <?php if (!empty($displayRows)): ?>
                                        <dl class="row mb-0">
                                            <?php foreach ($displayRows as $label => $value): ?>
                                                <dt class="col-sm-4 col-lg-3"><?= esc($label) ?></dt>
                                                <dd class="col-sm-8 col-lg-9"><?= esc($value !== '' ? $value : '-') ?></dd>
                                            <?php endforeach; ?>
                                        </dl>
                                    <?php endif; ?>

                                    <?php foreach ($displayTables as $table): ?>
                                        <?php
                                        $tableColumns = is_array($table['columns'] ?? null) ? $table['columns'] : [];
                                        $tableRows = is_array($table['rows'] ?? null) ? $table['rows'] : [];
                                        ?>
                                        <?php if (!empty($tableColumns) && !empty($tableRows)): ?>
                                            <div class="employee-declaration-table-wrap mt-3">
                                                <strong class="d-block mb-2"><?= esc($table['title'] ?? 'Táblázat') ?></strong>
                                                <table class="table table-bordered table-sm employee-declaration-table mb-0">
                                                    <thead>
                                                        <tr>
                                                            <?php foreach ($tableColumns as $column): ?>
                                                                <th><?= esc($column) ?></th>
                                                            <?php endforeach; ?>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($tableRows as $tableRow): ?>
                                                            <tr>
                                                                <?php foreach ($tableColumns as $columnIndex => $column): ?>
                                                                    <td><?= esc($tableRow[$columnIndex] ?? '-') ?></td>
                                                                <?php endforeach; ?>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>

                                    <?php if (empty($displayRows) && empty($displayTables)): ?>
                                        <p class="text-muted mb-0">A beküldött adatok nem jeleníthetők meg összesített nézetben.</p>
                                    <?php endif; ?>

                                    <?php if (!empty($item->review_note)): ?>
                                        <div class="alert alert-warning mt-3 mb-0">
                                            <strong>Munkaügyi megjegyzés:</strong><br>
                                            <?= nl2br(esc($item->review_note)) ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($submission): ?>
                                        <hr>
                                        <a href="<?= esc(url('declarations/my-declarations/' . (int) $packet->id . '/items/' . (int) $item->id . '/preview')) ?>" class="btn btn-outline-info btn-sm" target="_blank" rel="noopener">
                                            <i class="fas fa-eye pr-1"></i> PDF előnézet
                                        </a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?= $this->endSection() ?>
