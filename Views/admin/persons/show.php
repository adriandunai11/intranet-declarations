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
    <?php
    $openRelationCount = 0;
    $linkedIntranetUser = $linkedIntranetUser ?? null;
    $intranetUserCandidates = $intranetUserCandidates ?? [];
    $openPacketRelationIds = $openPacketRelationIds ?? [];
    $openPacketCompanyIds = $openPacketCompanyIds ?? [];
    $openPacketsByRelationId = $openPacketsByRelationId ?? [];
    $openPacketsByCompanyId = $openPacketsByCompanyId ?? [];
    $draftPacketsByRelationId = $draftPacketsByRelationId ?? [];
    $personStatusLabels = [
        'active' => ['Aktív', 'success'],
        'inactive' => ['Inaktív', 'secondary'],
        'blocked' => ['Letiltva', 'danger'],
        'merged' => ['Összevonva', 'dark'],
    ];
    $packetStatusLabels = [
        'draft' => ['Előkészítés alatt', 'secondary'],
        'sent' => ['Kiküldve', 'info'],
        'in_progress' => ['Kitöltés alatt', 'warning'],
        'submitted' => ['Ellenőrzésre vár', 'primary'],
        'approved' => ['Elfogadva', 'success'],
        'completed' => ['Elfogadva', 'success'],
        'closed' => ['Lezárva', 'dark'],
        'cancelled' => ['Törölve', 'danger'],
    ];

    foreach ($relations as $relation) {
        $relationIsClosed = in_array((string) $relation->status, ['closed', 'cancelled'], true);

        if (!$relationIsClosed) {
            $openRelationCount++;
        }
    }
    ?>

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

                    <strong>Intranet felhasználó</strong>
                    <p class="text-muted">
                        <?= !empty($person->intranet_user_id) ? '#' . (int) $person->intranet_user_id : '-' ?>
                    </p>

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
                    <p>
                        <?php
                        [$personStatusLabel, $personStatusClass] = $personStatusLabels[(string) ($person->status ?? '')] ?? [$person->status ?: '-', 'secondary'];
                        ?>
                        <span class="badge badge-<?= esc($personStatusClass) ?>">
                            <?= esc($personStatusLabel) ?>
                        </span>
                    </p>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        Munkavállalói önkiszolgáló
                    </h3>
                </div>
                <div class="card-body">
                    <?php if (empty($person->intranet_user_id)): ?>
                        <div class="alert alert-warning">
                            A személy nincs intranet felhasználóhoz kötve, ezért saját nyilatkozatindítást még nem tud használni.
                        </div>

                        <?php if (!empty($intranetUserCandidates)): ?>
                            <p class="text-muted">
                                Találtunk aktív intranet felhasználót az Antra azonosító vagy e-mail cím alapján.
                                E-mail egyezésnél kézi ellenőrzés szükséges, mert a nyilatkozati e-mail eltérhet az intranet fiók e-mail címétől.
                            </p>

                            <div class="list-group mb-3">
                                <?php foreach ($intranetUserCandidates as $candidate): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <strong><?= esc($candidate['label']) ?></strong>
                                                <div class="text-muted small">
                                                    #<?= (int) $candidate['id'] ?>
                                                    <?php if (!empty($candidate['antra_id'])): ?>
                                                        · Antra: <?= esc($candidate['antra_id']) ?>
                                                    <?php endif; ?>
                                                    <?php if (!empty($candidate['email'])): ?>
                                                        · <?= esc($candidate['email']) ?>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="text-muted small"><?= esc($candidate['reason']) ?></div>
                                            </div>
                                            <?= form_open('declarations/persons/' . (int) $person->id . '/intranet/link') ?>
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="user_id" value="<?= (int) $candidate['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-primary">
                                                Kapcsolás
                                            </button>
                                            <?= form_close() ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">
                                Nincs egyértelmű aktív intranet felhasználó egyezés. Új felhasználó létrehozásakor a név, telefonszám és Antra azonosító kerül előtöltésre.
                            </p>
                        <?php endif; ?>

                        <?php if (hasPermissions('declarations_persons_edit')): ?>
                            <?= form_open('declarations/persons/' . (int) $person->id . '/intranet/user-add') ?>
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-default">
                                <i class="fas fa-user-plus pr-1"></i> Intranet felhasználó létrehozása
                            </button>
                            <?= form_close() ?>
                            <div class="text-muted small mt-2">
                                A felhasználó-létrehozó oldal nyílik meg, az ismert adatok szerveroldali előtöltéssel kerülnek át.
                                A nyilatkozati e-mail címet nem töltjük be intranet felhasználói e-mailként.
                                Létrehozás után az egyező aktív felhasználó itt kapcsolható a személyhez.
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <p>
                            <span class="badge badge-success">Intranethez kötve</span>
                        </p>

                        <dl class="row mb-3">
                            <dt class="col-sm-6">Felhasználó</dt>
                            <dd class="col-sm-6">
                                #<?= (int) $person->intranet_user_id ?>
                                <?php if ($linkedIntranetUser): ?>
                                    <span class="d-block text-muted small">
                                        <?= esc($linkedIntranetUser['label']) ?>
                                        <?php if (!empty($linkedIntranetUser['email'])): ?>
                                            · <?= esc($linkedIntranetUser['email']) ?>
                                        <?php endif; ?>
                                    </span>
                                <?php endif; ?>
                            </dd>

                            <dt class="col-sm-6">Nyitott jogviszony</dt>
                            <dd class="col-sm-6"><?= (int) $openRelationCount ?></dd>
                        </dl>

                        <p class="text-muted mb-0">
                            A dolgozó belépés után a Nyilatkozataim menüpontban tud új éves nyilatkozatot indítani.
                        </p>
                    <?php endif; ?>
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
                                        <th>Folyamat</th>
                                        <th>Telephely</th>
                                        <th>Elsődleges toborzó</th>
                                        <th>Kezdés</th>
                                        <th>Lezárás</th>
                                        <th>Művelet</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($relations as $relation): ?>
                                        <?php
                                        $relationId = (int) $relation->id;
                                        $hasOpenPacket = !empty($openPacketRelationIds[$relationId])
                                            || !empty($openPacketCompanyIds[(int) $relation->company_id]);
                                        $openPacket = $openPacketsByRelationId[$relationId]
                                            ?? ($openPacketsByCompanyId[(int) $relation->company_id] ?? null);
                                        $draftPacket = $draftPacketsByRelationId[$relationId] ?? null;
                                        $isClosedRelation = in_array((string) $relation->status, ['closed', 'cancelled'], true);
                                        ?>
                                        <tr>
                                            <td><?= esc($divisionNames[(int) $relation->company_id] ?? ('#' . $relation->company_id)) ?>
                                            </td>
                                            <td>
                                                <?php
                                                $relationStatusLabels = [
                                                    'draft' => ['Piszkozat', 'secondary'],
                                                    'onboarding' => ['Beléptetés alatt', 'primary'],
                                                    'invited' => ['Nyilatkozat kiküldve', 'info'],
                                                    'in_progress' => ['Nyilatkozat kitöltése alatt', 'warning'],
                                                    'declarations_submitted' => ['Nyilatkozatok ellenőrzésre várnak', 'primary'],
                                                    'completed' => ['Beléptetési nyilatkozatok elfogadva', 'success'],
                                                    'active' => ['Aktív dolgozó', 'success'],
                                                    'transferred' => ['Áthelyezve', 'warning'],
                                                    'closed' => ['Lezárva', 'secondary'],
                                                    'cancelled' => ['Törölve', 'danger'],
                                                ];
                                                $openPacketStatusLabels = [
                                                    'draft' => ['Csomag előkészítés alatt', 'secondary'],
                                                    'sent' => ['Nyilatkozat kiküldve', 'info'],
                                                    'in_progress' => ['Nyilatkozat kitöltése alatt', 'warning'],
                                                    'submitted' => ['Nyilatkozatok ellenőrzésre várnak', 'primary'],
                                                    'approved' => ['Nyilatkozatok elfogadva', 'success'],
                                                    'completed' => ['Nyilatkozatok elfogadva', 'success'],
                                                ];

                                                if ($openPacket && !$isClosedRelation) {
                                                    [$relationStatusLabel, $relationStatusClass] = $openPacketStatusLabels[(string) ($openPacket->status ?? '')]
                                                        ?? ['Nyitott nyilatkozatcsomag', 'info'];
                                                } else {
                                                    [$relationStatusLabel, $relationStatusClass] = $relationStatusLabels[$relation->status] ?? [$relation->status ?: '-', 'secondary'];
                                                }
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
                                            <td><?= esc($relation->end_date ?: '-') ?></td>
                                            <td>
                                                <?php if ($draftPacket): ?>
                                                    <a href="<?= url('declarations/packets/' . $draftPacket->id) ?>" class="btn btn-sm btn-default">
                                                        <i class="fas fa-file-signature pr-1"></i> Piszkozat megnyitása
                                                    </a>
                                                <?php elseif ($hasOpenPacket): ?>
                                                    <span class="badge badge-info d-block mb-1">Nyitott csomag van</span>
                                                <?php elseif (hasPermissions('declarations_packets_create') && !$isClosedRelation): ?>
                                                    <button type="button" class="btn btn-sm btn-default" data-toggle="modal"
                                                        data-target="#createPacketModal<?= $relationId ?>">
                                                        <i class="fas fa-file-signature pr-1"></i> Nyilatkozatcsomag
                                                    </button>
                                                <?php endif; ?>

                                                <?php if (
                                                    (hasPermissions('declarations_admin_override') || hasPermissions('declarations_review_payroll'))
                                                    && !$isClosedRelation
                                                ): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-danger mt-1" data-toggle="modal"
                                                        data-target="#closeRelationModal<?= $relationId ?>">
                                                        <i class="fas fa-lock pr-1"></i> Lezárás
                                                    </button>
                                                <?php endif; ?>

                                                <?php if (hasPermissions('declarations_admin_override') && (string) $relation->status === 'closed'): ?>
                                                    <?= form_open('declarations/persons/' . $person->id . '/relations/' . $relationId . '/reopen', ['class' => 'd-inline-block mt-1']) ?>
                                                    <?= csrf_field() ?>
                                                    <button type="submit" class="btn btn-sm btn-outline-warning"
                                                        onclick="return confirm('Biztosan visszanyitod ezt a jogviszonyt?')">
                                                        <i class="fas fa-unlock pr-1"></i> Visszanyitás
                                                    </button>
                                                    <?= form_close() ?>
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
                                        <th>Nyilatkozati év</th>
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
                                                <?php
                                                [$packetStatusLabel, $packetStatusClass] = $packetStatusLabels[(string) ($packet->status ?? '')] ?? [$packet->status ?: '-', 'secondary'];
                                                ?>
                                                <span class="badge badge-<?= esc($packetStatusClass) ?>">
                                                    <?= esc($packetStatusLabel) ?>
                                                </span>
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

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        Személy előzmények
                    </h3>
                </div>

                <div class="card-body">
                    <p class="text-muted">
                        A személyhez tartozó naplózott események külön oldalon, nagyobb nézetben érhetők el.
                    </p>

                    <a href="<?= url('declarations/persons/' . (int) $person->id . '/audit') ?>" class="btn btn-default">
                        <i class="fas fa-history pr-1"></i> Előzmények megnyitása
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php if (!empty($relations) && hasPermissions('declarations_packets_create')): ?>
    <?php foreach ($relations as $relation): ?>
        <?php
        $relationId = (int) $relation->id;
        $hasOpenPacket = !empty($openPacketRelationIds[$relationId])
            || !empty($openPacketCompanyIds[(int) $relation->company_id]);
        $draftPacket = $draftPacketsByRelationId[$relationId] ?? null;
        $isClosedRelation = in_array((string) $relation->status, ['closed', 'cancelled'], true);
        ?>
        <?php if ($hasOpenPacket || $draftPacket || $isClosedRelation): ?>
            <?php continue; ?>
        <?php endif; ?>
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
                                <label for="tax_year_<?= (int) $relation->id ?>">Nyilatkozati év</label>
                                <input type="number" name="tax_year" id="tax_year_<?= (int) $relation->id ?>" class="form-control"
                                    value="<?= (int) date('Y') ?>">
                            </div>
                        </div>

                        <div class="alert alert-info mb-3">
                            Az <strong>Alap beléptetési csomag</strong> gomb a kötelező beléptetési nyilatkozatokat hozza létre, a lenti jelölésektől függetlenül.
                            A <strong>Kijelölt nyilatkozatokból létrehozás</strong> gomb csak az itt külön bejelölt nyilatkozatokat teszi a csomagba.
                            Adóügyi nyilatkozatot csak akkor jelölj be, ha már most biztosan szükséges; egyébként a dolgozó később saját maga is indíthatja.
                        </div>

                        <label class="mb-2">Aktív nyilatkozatok</label>

                        <?php if (empty($templates)): ?>
                            <p class="text-muted">
                                Nincs aktív nyilatkozat.
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
                                        value="<?= (int) $template->id ?>">
                                    <label class="custom-control-label"
                                        for="template_<?= (int) $relation->id ?>_<?= (int) $template->id ?>">
                                        <?= esc($template->displayName()) ?>
                                        <small class="text-muted d-block">
                                            <?= esc($groupLabel) ?> · <?= esc($reviewLabel) ?>
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
                            Kijelölt nyilatkozatokból létrehozás
                        </button>
                    </div>

                    <?= form_close() ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php if (!empty($relations) && (hasPermissions('declarations_admin_override') || hasPermissions('declarations_review_payroll'))): ?>
    <?php foreach ($relations as $relation): ?>
        <?php if (!in_array((string) $relation->status, ['closed', 'cancelled'], true)): ?>
            <div class="modal fade" id="closeRelationModal<?= (int) $relation->id ?>" role="dialog" data-backdrop="static"
                aria-labelledby="closeRelationModalLabel<?= (int) $relation->id ?>" aria-hidden="true">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <?= form_open('declarations/persons/' . $person->id . '/relations/' . $relation->id . '/close', ['class' => 'form-validate']) ?>
                        <?= csrf_field() ?>

                        <div class="modal-header">
                            <h5 class="modal-title" id="closeRelationModalLabel<?= (int) $relation->id ?>">
                                Jogviszony lezárása
                            </h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Bezárás">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>

                        <div class="modal-body">
                            <p class="text-muted">
                                <?= esc($divisionNames[(int) $relation->company_id] ?? ('#' . $relation->company_id)) ?>
                                · kezdés: <?= esc($relation->start_date ?: '-') ?>
                            </p>

                            <div class="form-group">
                                <label for="end_date_<?= (int) $relation->id ?>" class="required">Lezárás dátuma</label>
                                <input type="date" name="end_date" id="end_date_<?= (int) $relation->id ?>"
                                    class="form-control" required value="<?= old('end_date', date('Y-m-d')) ?>">
                            </div>

                            <div class="alert alert-warning mb-0">
                                A lezárt jogviszony már nem számít nyitottnak a nyilatkozatcsomag-korlátozásnál.
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-lock pr-1"></i> Jogviszony lezárása
                            </button>
                            <button type="button" class="btn btn-default" data-dismiss="modal">Mégsem</button>
                        </div>

                        <?= form_close() ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
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

                    <h6 class="text-muted mb-3">Jogviszony adatok</h6>
                    <div class="row">
                        <div class="col-md-6 form-group">
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

                        <div class="col-md-6 form-group">
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
