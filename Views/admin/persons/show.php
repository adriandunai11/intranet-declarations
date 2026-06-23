<?= $this->extend('admin/layout/default') ?>
<?= $this->section('content') ?>

<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1><?= esc($person->fullName()) ?></h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="<?= url('/') ?>"><?= lang('App.home') ?></a></li>
                    <li class="breadcrumb-item"><a href="<?= url('declarations/persons') ?>">Nyilatkozat személyek</a>
                    </li>
                    <li class="breadcrumb-item active"><?= esc($person->fullName()) ?></li>
                </ol>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="row">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        Személy adatai
                    </h3>
                </div>
                <div class="card-body">
                    <strong>Név</strong>
                    <p class="text-muted"><?= esc($person->fullName()) ?></p>

                    <strong>Antra azonosító</strong>
                    <p class="text-muted"><?= esc($person->antra_id ?: '-') ?></p>

                    <strong>E-mail</strong>
                    <p class="text-muted"><?= esc($person->email ?: '-') ?></p>

                    <strong>Adóazonosító jel</strong>
                    <p class="text-muted"><?= esc($person->tax_number ?: '-') ?></p>

                    <strong>TAJ szám</strong>
                    <p class="text-muted"><?= esc($person->taj_number ?: '-') ?></p>

                    <strong>Születési név</strong>
                    <p class="text-muted"><?= esc($person->birth_name ?: '-') ?></p>

                    <strong>Anyja neve</strong>
                    <p class="text-muted"><?= esc($person->mother_name ?: '-') ?></p>

                    <strong>Születési hely, idő</strong>
                    <p class="text-muted">
                        <?= esc($person->birth_place ?: '-') ?>,
                        <?= esc($person->birth_date ?: '-') ?>
                    </p>

                    <strong>Telefonszám</strong>
                    <p class="text-muted"><?= esc($person->phone ?: '-') ?></p>

                    <strong>Állapot</strong>
                    <p class="text-muted"><?= esc($person->status ?: '-') ?></p>
                </div>
            </div>
        </div>
        <?php
        $divisionNames = [];
        foreach ($divisions as $division) {
            $divisionNames[(int) $division->id] = $division->name;
        }
        $locationNames = [];
        foreach ($locations as $location) {
            $locationNames[(int) $location->id] = $location->name;
        }
        ?>
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        Beléptetések és jogviszonyok
                    </h3>
                    <div class="card-tools">
                        <?php if (hasPermissions('declarations_relations_create')): ?>
                            <button type="button" class="btn btn-sm btn-default" data-toggle="modal"
                                data-target="#createRelationModal">
                                <i class="fas fa-plus pr-1"></i> Beléptetés indítása
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card-body">
                    <?php if (empty($relations)): ?>
                        <p class="text-muted mb-0">
                            Még nincs rögzített jogviszony.
                        </p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>Cég</th>
                                        <th>Státusz</th>
                                        <th>Telephely</th>
                                        <th>Elsődleges toborzó</th>
                                        <th>Kezdés</th>
                                        <th>Művelet</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($relations as $relation): ?>
                                        <tr>
                                            <td><?= esc($divisionNames[(int) $relation->company_id] ?? ('#' . $relation->company_id)) ?>
                                            </td>
                                            <td>
                                                <?php
                                                $relationStatusLabels = [
                                                    'draft' => ['Piszkozat', 'secondary'],
                                                    'onboarding' => ['Beléptetés alatt', 'primary'],
                                                    'invited' => ['Nyilatkozat kiküldve', 'info'],
                                                    'in_progress' => ['Kitöltés folyamatban', 'warning'],
                                                    'completed' => ['Nyilatkozatok elfogadva', 'success'],
                                                    'active' => ['Aktív dolgozó', 'success'],
                                                    'transferred' => ['Áthelyezve', 'warning'],
                                                    'closed' => ['Lezárva', 'secondary'],
                                                    'cancelled' => ['Törölve', 'danger'],
                                                ];

                                                [$relationStatusLabel, $relationStatusClass] = $relationStatusLabels[$relation->status] ?? [$relation->status ?: '-', 'secondary'];
                                                ?>
                                                <span class="badge badge-<?= esc($relationStatusClass) ?>">
                                                    <?= esc($relationStatusLabel) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if (!empty($relation->location_id)): ?>
                                                    <?= esc($locationNames[(int) $relation->location_id] ?? ($relation->location ?: ('#' . $relation->location_id))) ?>
                                                <?php else: ?>
                                                    <?= esc($relation->location ?: '-') ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?= esc($recruiterDisplayNames[(int) $relation->primary_recruiter_user_id] ?? '-') ?>
                                            </td>
                                            <td><?= esc($relation->start_date ?: '-') ?></td>
                                            <td>
                                                <?php if (hasPermissions('declarations_packets_create')): ?>
                                                    <button type="button" class="btn btn-sm btn-default" data-toggle="modal"
                                                        data-target="#createPacketModal<?= (int) $relation->id ?>">
                                                        <i class="fas fa-file-signature pr-1"></i> Nyilatkozatcsomag
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        Nyilatkozatcsomagok
                    </h3>
                </div>

                <div class="card-body">
                    <?php if (empty($packets)): ?>
                        <p class="text-muted mb-0">
                            Még nincs létrehozott nyilatkozatcsomag.
                        </p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Jogviszony ID</th>
                                        <th>Adóév</th>
                                        <th>Státusz</th>
                                        <th>Létrehozva</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($packets as $packet): ?>
                                        <tr>
                                            <td>
                                                <a href="<?= url('declarations/packets/' . $packet->id) ?>">
                                                    #<?= (int) $packet->id ?>
                                                </a>
                                            </td>
                                            <td>
                                                <?= (int) $packet->employment_relation_id ?>
                                            </td>
                                            <td>
                                                <?= esc($packet->tax_year ?: '-') ?>
                                            </td>
                                            <td>
                                                <?= esc($packet->status) ?>
                                            </td>
                                            <td>
                                                <?= esc($packet->created_at ?: '-') ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<?php if (!empty($relations) && hasPermissions('declarations_packets_create')): ?>
    <?php foreach ($relations as $relation): ?>
        <div class="modal fade" id="createPacketModal<?= (int) $relation->id ?>" role="dialog" data-backdrop="static"
            aria-labelledby="createPacketModalLabel<?= (int) $relation->id ?>" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <?= form_open('declarations/persons/' . $person->id . '/relations/' . $relation->id . '/packets/create', ['class' => 'form-validate']) ?>
                    <?= csrf_field() ?>

                    <div class="modal-header">
                        <h5 class="modal-title" id="createPacketModalLabel<?= (int) $relation->id ?>">
                            Nyilatkozatcsomag összeállítása
                        </h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Bezárás">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>

                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label for="tax_year_<?= (int) $relation->id ?>">Adóév</label>
                                <input type="number" name="tax_year" id="tax_year_<?= (int) $relation->id ?>" class="form-control"
                                    value="<?= (int) date('Y') ?>">
                            </div>
                        </div>

                        <div class="alert alert-info mb-3">
                            Az alap beléptetési csomag automatikusan a kötelező, nem adóügyi nyilatkozatokat hozza létre.
                            Az adóügyi nyilatkozatokat a beálló később opcionálisan választhatja, a munkaügy ellenőrzése mellett.
                        </div>

                        <label class="mb-2">Aktív nyilatkozat sablonok</label>

                        <?php if (empty($templates)): ?>
                            <p class="text-muted">
                                Nincs aktív nyilatkozat sablon.
                            </p>
                        <?php else: ?>
                            <?php foreach ($templates as $template): ?>
                                <?php
                                $groupLabel = match ($template->declaration_group ?? '') {
                                    'employment' => 'Nem adóügyi / toborzói felelősség',
                                    'tax' => 'Adóügyi / munkaügyi ellenőrzés',
                                    'personal_data' => 'Személyes adatok',
                                    default => $template->declaration_group ?: '-',
                                };

                                $reviewLabel = match ($template->review_role ?? '') {
                                    'recruiter' => 'Toborzó ellenőrzi',
                                    'payroll' => 'Munkaügy ellenőrzi',
                                    'none' => 'Nincs külön ellenőrzés',
                                    default => $template->review_role ?: '-',
                                };
                                ?>
                                <div class="custom-control custom-checkbox mb-2">
                                    <input type="checkbox" class="custom-control-input"
                                        id="template_<?= (int) $relation->id ?>_<?= (int) $template->id ?>" name="template_ids[]"
                                        value="<?= (int) $template->id ?>" <?= $template->required_policy === 'always' ? 'checked' : '' ?>>
                                    <label class="custom-control-label"
                                        for="template_<?= (int) $relation->id ?>_<?= (int) $template->id ?>">
                                        <?= esc($template->displayName()) ?>
                                        <small class="text-muted d-block">
                                            <?= esc($groupLabel) ?> · <?= esc($reviewLabel) ?>
                                            <?= !empty($template->needs_signature) ? ' · Aláírandó' : '' ?>
                                        </small>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div class="modal-footer justify-content-between">
                        <button type="submit" name="packet_mode" value="default_onboarding" class="btn btn-primary">
                            Alap beléptetési csomag létrehozása
                        </button>
                        <button type="submit" name="packet_mode" value="manual" class="btn btn-default">
                            Kijelölt sablonokból létrehozás
                        </button>
                    </div>

                    <?= form_close() ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php if (hasPermissions('declarations_relations_create')): ?>
    <div class="modal fade" id="createRelationModal" role="dialog" data-backdrop="static"
        aria-labelledby="createRelationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl" role="document">
            <div class="modal-content">
                <?= form_open('declarations/persons/' . $person->id . '/relations/create', ['class' => 'form-validate', 'id' => 'createRelationForm']) ?>
                <?= csrf_field() ?>

                <div class="modal-header">
                    <h5 class="modal-title" id="createRelationModalLabel">Beléptetési folyamat indítása</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Bezárás">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-12 form-group">
                            <label for="primary_recruiter_user_id" class="required">Elsődleges toborzó</label>
                            <select name="primary_recruiter_user_id" id="primary_recruiter_user_id" class="form-control select2" required>
                                <option value="">Válassz toborzót...</option>
                                <?php foreach ($recruiters as $recruiter): ?>
                                    <?php
                                    $recruiterId = (int) ($recruiter->id ?? 0);
                                    $recruiterLabel = $recruiterDisplayNames[$recruiterId] ?? ('Felhasználó #' . $recruiterId);
                                    ?>
                                    <option value="<?= $recruiterId ?>" <?= (string) old('primary_recruiter_user_id') === (string) $recruiterId ? 'selected' : '' ?>>
                                        <?= esc($recruiterLabel) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted">Csak Toborzó szerepkörű felhasználók választhatók.</small>
                        </div>
                    </div>

                    <h6 class="text-muted mb-3">Beléptetési adatok</h6>
                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label for="onboarding_type" class="required">Beléptetés típusa</label>
                            <select name="onboarding_type" id="onboarding_type" class="form-control" required>
                                <option value="candidate" <?= old('onboarding_type', 'candidate') === 'candidate' ? 'selected' : '' ?>>Új beálló / jelölt</option>
                                <option value="returning_parent" <?= old('onboarding_type') === 'returning_parent' ? 'selected' : '' ?>>Visszatérő kismama</option>
                                <option value="transfer" <?= old('onboarding_type') === 'transfer' ? 'selected' : '' ?>>Átlépő</option>
                            </select>
                        </div>
                        <div class="col-md-4 form-group">
                            <label for="company_id" class="required">Cég</label>
                            <select name="company_id" id="company_id" class="form-control" required>
                                <option value="">Válassz céget...</option>
                                <?php foreach ($divisions as $division): ?>
                                    <option value="<?= (int) $division->id ?>" <?= (string) old('company_id') === (string) $division->id ? 'selected' : '' ?>>
                                        <?= esc($division->name) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4 form-group">
                            <label for="start_date" class="required">Kezdés dátuma</label>
                            <input type="date" name="start_date" id="start_date" class="form-control" required
                                value="<?= old('start_date') ?>">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label for="location_id">Telephely</label>
                            <select name="location_id" id="location_id" class="form-control select2">
                                <option value="">Válassz telephelyet...</option>
                                <?php foreach ($locations as $location): ?>
                                    <option value="<?= (int) $location->id ?>" <?= (string) old('location_id') === (string) $location->id ? 'selected' : '' ?>>
                                        <?= esc($location->name) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="alert alert-info">
                        A munkakör, jogviszony típus és lezárás dátuma nem része a nyilatkozatcsomag indításának.
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-default">Beléptetés indítása</button>
                </div>

                <?= form_close() ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script>
    $(function () {
        if ($.fn.select2) {
            $('#primary_recruiter_user_id').select2({
                dropdownParent: $('#createRelationModal'),
                width: '100%',
                placeholder: 'Válassz toborzót...'
            });

            $('#location_id').select2({
                dropdownParent: $('#createRelationModal'),
                width: '100%',
                placeholder: 'Válassz telephelyet...',
                allowClear: true
            });
        }
    });
</script>
<?= $this->endSection() ?>
