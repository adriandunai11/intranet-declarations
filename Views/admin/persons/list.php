<?= $this->extend('admin/layout/default') ?>
<?= $this->section('content') ?>

<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1>Nyilatkozat személyek</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="<?= url('/') ?>"><?= lang('App.home') ?></a></li>
                    <li class="breadcrumb-item">Nyilatkozatok</li>
                    <li class="breadcrumb-item active">Személyek</li>
                </ol>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header with-border">
                    <h3 class="card-title">Személy törzs</h3>
                    <div class="card-tools pull-right">
                        <?php if (hasPermissions('declarations_persons_add')): ?>
                            <a href="#createModal" data-toggle="modal" data-target="#createModal"
                                class="btn btn-default btn-sm">
                                <i class="fa fa-plus pr-1"></i> Új személy
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <table id="dataTable1" class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>AZ</th>
                                <th>Antra</th>
                                <th>Név</th>
                                <th>E-mail</th>
                                <th>Adóazonosító</th>
                                <th>TAJ</th>
                                <th>Állapot</th>
                                <th>Intranet</th>
                                <th width="180"><?= lang('App.action') ?></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="modal fade" id="createModal" role="dialog" data-backdrop="static" aria-labelledby="createModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createModalLabel">Új személy létrehozása</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                        aria-hidden="true">&times;</span></button>
            </div>
            <?= form_open('declarations/persons/create', ['class' => 'form-validate', 'id' => 'createPersonForm']) ?>
            <?= csrf_field() ?>
            <?php if (!empty($matches)): ?>
                <input type="hidden" name="force_create" value="1">
            <?php endif; ?>
            <div class="modal-body">
                <?php $validation = session()->getFlashdata('validation'); ?>
                <?php if ($validation): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($validation as $error): ?>
                                <li><?= esc($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php $matches = session()->getFlashdata('possible_matches'); ?>
                <?php if (!empty($matches)): ?>
                    <div class="alert alert-warning">
                        <h5><i class="fas fa-exclamation-triangle"></i> Lehetséges visszatérő dolgozó</h5>

                        <p>
                            A megadott adatok alapján már lehet ilyen személy a rendszerben.
                            Ellenőrizd a találatokat, mielőtt új személyt hozol létre.
                        </p>

                        <ul class="mb-3">
                            <?php foreach ($matches as $match): ?>
                                <li>
                                    <strong><?= esc($match['person']->fullName()) ?></strong>
                                    -
                                    <?= esc($match['reason']) ?>
                                    -
                                    <?= (int) $match['score'] ?>%

                                    <a href="<?= url('declarations/persons/' . $match['person']->id) ?>"
                                        class="btn btn-xs btn-default ml-2">
                                        Megnyitás
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>

                        <button type="button" class="btn btn-warning btn-sm" data-toggle="modal"
                            data-target="#createPersonModal">
                            Ennek ellenére új személy létrehozása
                        </button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-6 form-group">
                        <label for="antra_id">Antra azonosító</label>
                        <input type="text" name="antra_id" id="antra_id" class="form-control"
                            value="<?= old('antra_id') ?>">
                    </div>
                    <div class="col-md-6 form-group">
                        <label for="email" class="required">E-mail</label>
                        <input type="email" name="email" id="email" required class="form-control"
                            value="<?= old('email') ?>">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 form-group">
                        <label for="lastname" class="required">Vezetéknév</label>
                        <input type="text" name="lastname" id="lastname" required class="form-control"
                            value="<?= old('lastname') ?>">
                    </div>
                    <div class="col-md-6 form-group">
                        <label for="firstname" class="required">Keresztnév</label>
                        <input type="text" name="firstname" id="firstname" required class="form-control"
                            value="<?= old('firstname') ?>">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 form-group">
                        <label for="birth_name">Születési név</label>
                        <input type="text" name="birth_name" id="birth_name" class="form-control"
                            value="<?= old('birth_name') ?>">
                    </div>
                    <div class="col-md-6 form-group">
                        <label for="mother_name">Anyja neve</label>
                        <input type="text" name="mother_name" id="mother_name" class="form-control"
                            value="<?= old('mother_name') ?>">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 form-group">
                        <label for="birth_place">Születési hely</label>
                        <input type="text" name="birth_place" id="birth_place" class="form-control"
                            value="<?= old('birth_place') ?>">
                    </div>
                    <div class="col-md-4 form-group">
                        <label for="birth_date">Születési dátum</label>
                        <input type="date" name="birth_date" id="birth_date" class="form-control"
                            value="<?= old('birth_date') ?>">
                    </div>
                    <div class="col-md-4 form-group">
                        <label for="phone">Telefonszám</label>
                        <input type="text" name="phone" id="phone" class="form-control" value="<?= old('phone') ?>">
                    </div>
                </div>

                <div class="alert alert-info">
                    A TAJ számot és az adóazonosító jelet a beálló adja meg a nyilatkozatkitöltő felületen.
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-default">Mentés</button>
            </div>
            <?= form_close() ?>
        </div>
    </div>
</div>


<?php if (hasPermissions('declarations_persons_edit')): ?>
    <div class="modal fade" id="editPersonModal" role="dialog" data-backdrop="static" aria-labelledby="editPersonModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editPersonModalLabel">Személy szerkesztése</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Bezárás">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <?= form_open('', ['class' => 'form-validate', 'id' => 'editPersonForm']) ?>
                <?= csrf_field() ?>

                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label for="edit_antra_id">Antra azonosító</label>
                            <input type="text" name="antra_id" id="edit_antra_id" class="form-control">
                        </div>

                        <div class="col-md-4 form-group">
                            <label for="edit_lastname" class="required">Vezetéknév</label>
                            <input type="text" name="lastname" id="edit_lastname" class="form-control" required>
                        </div>

                        <div class="col-md-4 form-group">
                            <label for="edit_firstname" class="required">Keresztnév</label>
                            <input type="text" name="firstname" id="edit_firstname" class="form-control" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label for="edit_birth_name">Születési név</label>
                            <input type="text" name="birth_name" id="edit_birth_name" class="form-control">
                        </div>

                        <div class="col-md-4 form-group">
                            <label for="edit_mother_name">Anyja neve</label>
                            <input type="text" name="mother_name" id="edit_mother_name" class="form-control">
                        </div>

                        <div class="col-md-4 form-group">
                            <label for="edit_birth_place">Születési hely</label>
                            <input type="text" name="birth_place" id="edit_birth_place" class="form-control">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label for="edit_birth_date">Születési dátum</label>
                            <input type="date" name="birth_date" id="edit_birth_date" class="form-control">
                        </div>
                    </div>

                    <div class="alert alert-info">
                        A TAJ számot és az adóazonosító jelet a beálló adja meg a public nyilatkozatkitöltő felületen.
                        Itt nem módosítjuk ezeket az adatokat, hogy véletlenül se írjuk felül.
                    </div>

                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label for="edit_email">E-mail</label>
                            <input type="email" name="email" id="edit_email" class="form-control">
                        </div>

                        <div class="col-md-4 form-group">
                            <label for="edit_phone">Telefonszám</label>
                            <input type="text" name="phone" id="edit_phone" class="form-control">
                        </div>

                        <div class="col-md-4 form-group">
                            <label for="edit_status">Állapot</label>
                            <select name="status" id="edit_status" class="form-control">
                                <option value="active">Aktív</option>
                                <option value="inactive">Inaktív</option>
                                <option value="blocked">Letiltva</option>
                                <option value="merged">Összevonva</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-default">
                        <i class="fas fa-save pr-1"></i> Mentés
                    </button>
                    <button type="button" class="btn btn-default" data-dismiss="modal">Mégsem</button>
                </div>

                <?= form_close() ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<?= $this->endSection() ?>

<?= $this->section('js') ?>
<?php if (session()->getFlashdata('validation')): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            $('#createModal').modal('show');
        });
    </script>
<?php endif; ?>

<script>
    $(document).ready(function () {
        var table = $('#dataTable1').DataTable({
            processing: true,
            serverSide: true,
            autoWidth: false,
            language: { url: '//cdn.datatables.net/plug-ins/1.11.4/i18n/hu.json' },
            dom: 'Bfrtip',
            stateSave: true,
            ajax: {
                url: '<?= url('declarations/persons/datatable') ?>',
                type: 'POST',
                data: function (d) {
                    d['<?= csrf_token() ?>'] = '<?= csrf_hash() ?>';
                    return d;
                }
            },
            columns: [
                { data: 'id' },
                { data: 'antra_id' },
                { data: 'name', searchable: false },
                { data: 'email' },
                { data: 'tax_number' },
                { data: 'taj_number' },
                { data: 'status' },
                { data: 'intranet_link', orderable: false, searchable: false },
                { data: 'actions', orderable: false, searchable: false }
            ],
            buttons: [
                { extend: 'pageLength', className: 'rounded btn btn-default' },
                { text: '<i class="fas fa-rotate"></i>', className: 'ml-2 rounded btn btn-default', action: function () { table.ajax.reload(); } }
            ],
            initComplete: function () {
                $('#dataTable1').wrap('<div style="overflow:auto; width:100%;position:relative;"></div>');
            }
        });


        $(document).on('click', '.js-edit-person', function () {
            var button = $(this);

            $('#editPersonForm').attr(
                'action',
                '<?= url('declarations/persons') ?>/' + button.data('id') + '/update'
            );

            $('#edit_antra_id').val(button.data('antra-id') || '');
            $('#edit_lastname').val(button.data('lastname') || '');
            $('#edit_firstname').val(button.data('firstname') || '');
            $('#edit_birth_name').val(button.data('birth-name') || '');
            $('#edit_mother_name').val(button.data('mother-name') || '');
            $('#edit_birth_place').val(button.data('birth-place') || '');
            $('#edit_birth_date').val(button.data('birth-date') || '');
            $('#edit_email').val(button.data('email') || '');
            $('#edit_phone').val(button.data('phone') || '');
            $('#edit_status').val(button.data('status') || 'active');

            $('#editPersonModal').modal('show');
        });

        $('#editPersonForm').validate();
        $('#createPersonForm').validate();
    });
</script>
<?= $this->endSection() ?>