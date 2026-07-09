<?= $this->extend('admin/layout/default') ?>
<?= $this->section('content') ?>
<?php
$person = $person ?? null;
$relations = $relations ?? [];
$templates = $templates ?? [];
$packets = $packets ?? [];
$defaultTaxYear = (int) ($defaultTaxYear ?? (int) date('Y'));
$pageError = $pageError ?? null;
$oldTemplateIds = array_map('intval', (array) old('template_ids', []));
$oldRelationId = (string) old('relation_id', '');
$oldTaxYear = old('tax_year', $defaultTaxYear);

$templatesByGroup = [];
foreach ($templates as $template) {
    $group = (string) ($template->declaration_group ?? 'other');
    $templatesByGroup[$group][] = $template;
}

$groupLabels = [
    'tax' => 'Adóügyi nyilatkozatok',
    'personal_data' => 'Személyes adatok / adatmódosítás',
    'employment' => 'Munkaviszonyhoz kapcsolódó nyilatkozatok',
    'other' => 'Egyéb nyilatkozatok',
];
?>
<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-8">
                <h1>Nyilatkozataim</h1>
                <p class="text-muted mb-0">Saját indítású adóügyi és adatváltozási nyilatkozatok.</p>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <?php if ($pageError): ?>
            <div class="alert alert-danger">
                <?= esc($pageError) ?>
            </div>
        <?php endif; ?>

        <?php if (session()->getFlashdata('sSuccess')): ?>
            <div class="alert alert-success">
                <?= esc(session()->getFlashdata('sSuccess')) ?>
            </div>
        <?php endif; ?>

        <?php if (session()->getFlashdata('sError')): ?>
            <div class="alert alert-danger">
                <?= esc(session()->getFlashdata('sError')) ?>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-5">
                <div class="card card-primary card-outline">
                    <div class="card-header">
                        <h3 class="card-title">Új nyilatkozat indítása</h3>
                    </div>
                    <div class="card-body">
                        <?php if (!$person): ?>
                            <div class="alert alert-warning mb-0">
                                A belépett felhasználóhoz nincs összekapcsolt nyilatkozati személy rekord.
                            </div>
                        <?php elseif (empty($relations)): ?>
                            <div class="alert alert-warning mb-0">
                                Nem található nyitott jogviszony a belépett felhasználóhoz.
                            </div>
                        <?php elseif (empty($templates)): ?>
                            <div class="alert alert-warning mb-0">
                                Jelenleg nincs munkavállaló által indítható online nyilatkozat.
                            </div>
                        <?php else: ?>
                            <form method="post" action="<?= esc(url('my-declarations/start')) ?>">
                                <?= csrf_field() ?>

                                <div class="form-group">
                                    <label for="relation_id">Jogviszony</label>
                                    <select name="relation_id" id="relation_id" class="form-control" required>
                                        <?php foreach ($relations as $relation): ?>
                                            <option value="<?= (int) $relation->id ?>" <?= $oldRelationId === (string) $relation->id ? 'selected' : '' ?>>
                                                #<?= (int) $relation->id ?> · <?= esc($relation->location ?: 'Jogviszony') ?>
                                                <?php if (!empty($relation->start_date)): ?> · <?= esc($relation->start_date) ?><?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="tax_year">Adóév</label>
                                    <input type="number" name="tax_year" id="tax_year" class="form-control" min="2020" max="<?= (int) date('Y') + 2 ?>" value="<?= esc($oldTaxYear ?: $defaultTaxYear) ?>" required>
                                </div>

                                <div class="form-group">
                                    <label>Indítandó nyilatkozatok</label>
                                    <?php foreach ($templatesByGroup as $group => $groupTemplates): ?>
                                        <div class="border rounded p-3 mb-3">
                                            <strong><?= esc($groupLabels[$group] ?? ucfirst($group)) ?></strong>
                                            <div class="mt-2">
                                                <?php foreach ($groupTemplates as $template): ?>
                                                    <div class="custom-control custom-checkbox mb-2">
                                                        <input type="checkbox" class="custom-control-input" name="template_ids[]" value="<?= (int) $template->id ?>" id="template_<?= (int) $template->id ?>" <?= in_array((int) $template->id, $oldTemplateIds, true) ? 'checked' : '' ?>>
                                                        <label class="custom-control-label" for="template_<?= (int) $template->id ?>">
                                                            <?= esc(method_exists($template, 'displayName') ? $template->displayName() : ($template->name ?? 'Nyilatkozat')) ?>
                                                            <?php if (!empty($template->description)): ?>
                                                                <span class="d-block text-muted small"><?= esc($template->description) ?></span>
                                                            <?php endif; ?>
                                                        </label>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <div class="alert alert-info small">
                                    A rendszer e-mailben küld egy kitöltési linket. A kitöltés ugyanazon a public felületen történik, mint a beléptetési nyilatkozatoknál.
                                </div>

                                <button type="submit" class="btn btn-primary">Kitöltési link küldése</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Korábbi és folyamatban lévő csomagok</h3>
                    </div>
                    <div class="card-body table-responsive p-0">
                        <table class="table table-striped table-hover mb-0">
                            <thead>
                            <tr>
                                <th>#</th>
                                <th>Adóév</th>
                                <th>Státusz</th>
                                <th>Létrehozva</th>
                                <th>Beküldve / zárva</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($packets)): ?>
                                <tr>
                                    <td colspan="5" class="text-muted">Még nincs nyilatkozatcsomag.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($packets as $packet): ?>
                                    <tr>
                                        <td>#<?= (int) $packet->id ?></td>
                                        <td><?= esc($packet->tax_year ?: '-') ?></td>
                                        <td><span class="badge badge-secondary"><?= esc($packet->status ?? '-') ?></span></td>
                                        <td><?= esc($packet->created_at ?? '-') ?></td>
                                        <td><?= esc($packet->completed_at ?? '-') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?= $this->endSection() ?>
