<?php

require_once "includes/inc_all_admin.php";

// Merge the built-in defaults with any admin-edited rows, so every known
// template key shows up in the list even before it has ever been saved.
$defaults = emailTemplateDefaults();

$overrides = [];
$sql_overrides = mysqli_query($mysqli, "SELECT email_template_key, email_template_updated_at FROM email_templates");
while ($row = mysqli_fetch_assoc($sql_overrides)) {
    $overrides[$row['email_template_key']] = $row['email_template_updated_at'];
}

$rows = [];
foreach ($defaults as $key => $default) {
    if ($q !== '' && stripos($default['name'], $q) === false && stripos($key, $q) === false) {
        continue;
    }
    $rows[] = [
        'key' => $key,
        'name' => $default['name'],
        'customized' => isset($overrides[$key]),
        'updated_at' => $overrides[$key] ?? null,
    ];
}

usort($rows, fn($a, $b) => strcmp($a['name'], $b['name']));

?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-envelope-open-text mr-2"></i>Email Templates</h3>
    </div>
    <div class="card-body">

        <p class="text-muted">Every automated email the app sends is listed below. Edit one to change its subject and body - anything left un-edited keeps sending the built-in default.</p>

        <form autocomplete="off">
            <div class="row">
                <div class="col-md-4">
                    <div class="input-group mb-3 mb-md-0">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search Email Templates">
                        <div class="input-group-append">
                            <button class="btn btn-dark"><i class="fa fa-search"></i></button>
                        </div>
                    </div>
                </div>
                <div class="col-md-8"></div>
            </div>
        </form>
        <hr>
        <div class="table-responsive-sm">
            <table class="table table-striped table-borderless table-hover">
                <thead class="text-dark">
                <tr>
                    <th>Template</th>
                    <th>Key</th>
                    <th>Status</th>
                    <th class="text-center">Action</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row) { ?>
                    <tr>
                        <td>
                            <a href="#" class="text-dark ajax-modal" data-modal-url="modals/email_template/email_template_edit.php?key=<?= urlencode($row['key']) ?>" data-modal-size="lg">
                                <i class="fa fa-fw fa-envelope-open-text mr-2"></i><?= escapeHtml($row['name']) ?>
                            </a>
                        </td>
                        <td><code><?= escapeHtml($row['key']) ?></code></td>
                        <td>
                            <?php if ($row['customized']) { ?>
                                <span class="text-success text-bold">Customized</span>
                                <span class="text-muted">- <?= escapeHtml($row['updated_at']) ?></span>
                            <?php } else { ?>
                                <span class="text-secondary">Default</span>
                            <?php } ?>
                        </td>
                        <td class="text-center">
                            <a href="#" class="ajax-modal" data-modal-url="modals/email_template/email_template_edit.php?key=<?= urlencode($row['key']) ?>" data-modal-size="lg">
                                <i class="fas fa-fw fa-edit"></i>
                            </a>
                            <?php if ($row['customized']) { ?>
                                <a class="text-danger confirm-link" href="post.php?reset_email_template=<?= urlencode($row['key']) ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" title="Reset to default">
                                    <i class="fas fa-fw fa-undo"></i>
                                </a>
                            <?php } ?>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
require_once "../includes/footer.php";
