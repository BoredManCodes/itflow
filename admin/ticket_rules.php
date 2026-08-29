<?php

// Default Column Sortby Filter
$sort = "ticket_rule_order";
$order = "ASC";

require_once "includes/inc_all_admin.php";

$sql = mysqli_query(
    $mysqli,
    "SELECT SQL_CALC_FOUND_ROWS ticket_rules.*,
            COUNT(DISTINCT ticket_rule_conditions.ticket_rule_condition_id) AS condition_count,
            COUNT(DISTINCT ticket_rule_actions.ticket_rule_action_id) AS action_count
     FROM ticket_rules
     LEFT JOIN ticket_rule_conditions ON ticket_rule_conditions.ticket_rule_condition_rule_id = ticket_rule_id
     LEFT JOIN ticket_rule_actions ON ticket_rule_actions.ticket_rule_action_rule_id = ticket_rule_id
     WHERE ticket_rule_name LIKE '%$q%'
     AND ticket_rule_archived_at IS NULL
     GROUP BY ticket_rule_id
     ORDER BY $sort $order
     LIMIT $record_from, $record_to"
);

$num_rows = mysqli_fetch_row(mysqli_query($mysqli, "SELECT FOUND_ROWS()"));

?>

<div class="card card-dark">
    <div class="card-header py-2">
        <h3 class="card-title mt-2"><i class="fas fa-fw fa-random mr-2"></i>Ticket Rules</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary ajax-modal" data-modal-url="modals/ticket_rule/ticket_rule_add.php"><i class="fas fa-plus mr-2"></i>New Ticket Rule</button>
        </div>
    </div>
    <div class="card-body">

        <p class="text-muted">Rules run against every newly created ticket, in order. The first matching rule with <strong>Stop processing</strong> checked ends evaluation - later rules only run if it isn't checked, so several rules can layer actions onto the same ticket.</p>

        <form autocomplete="off">
            <div class="row">
                <div class="col-md-4">
                    <div class="input-group mb-3 mb-md-0">
                        <input type="search" class="form-control" name="q" value="<?php if (isset($q)) { echo stripslashes(escapeHtml($q)); } ?>" placeholder="Search Ticket Rules">
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
                <thead class="text-dark <?php if ($num_rows[0] == 0) { echo "d-none"; } ?>">
                <tr>
                    <th>
                        <a class="text-secondary" href="?<?= $url_query_strings_sort ?>&sort=ticket_rule_order&order=<?= $disp ?>">
                            Order <?php if ($sort == 'ticket_rule_order') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-secondary" href="?<?= $url_query_strings_sort ?>&sort=ticket_rule_name&order=<?= $disp ?>">
                            Name <?php if ($sort == 'ticket_rule_name') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th>Match</th>
                    <th>Stop processing</th>
                    <th>Conditions</th>
                    <th>Actions</th>
                    <th>
                        <a class="text-secondary" href="?<?= $url_query_strings_sort ?>&sort=ticket_rule_active&order=<?= $disp ?>">
                            Status <?php if ($sort == 'ticket_rule_active') { echo $order_icon; } ?>
                        </a>
                    </th>
                    <th class="text-center">Action</th>
                </tr>
                </thead>
                <tbody>
                <?php
                while ($row = mysqli_fetch_assoc($sql)) {
                    $ticket_rule_id = intval($row['ticket_rule_id']);
                    $ticket_rule_name = escapeHtml($row['ticket_rule_name']);
                    $ticket_rule_order = intval($row['ticket_rule_order']);
                    $ticket_rule_match_type = escapeHtml($row['ticket_rule_match_type']);
                    $ticket_rule_stop_processing = intval($row['ticket_rule_stop_processing']);
                    $ticket_rule_active = intval($row['ticket_rule_active']);
                    $condition_count = intval($row['condition_count']);
                    $action_count = intval($row['action_count']);
                    ?>
                    <tr>
                        <td><?= $ticket_rule_order ?></td>
                        <td>
                            <a href="ticket_rule.php?ticket_rule_id=<?= $ticket_rule_id ?>" class="text-dark">
                                <i class="fa fa-fw fa-random mr-2"></i><?= $ticket_rule_name ?>
                            </a>
                        </td>
                        <td><span class="badge badge-secondary"><?= strtoupper($ticket_rule_match_type) ?></span></td>
                        <td><?php if ($ticket_rule_stop_processing) { ?><i class="fas fa-check text-success"></i><?php } else { ?><i class="fas fa-times text-muted"></i><?php } ?></td>
                        <td><?= $condition_count ?></td>
                        <td><?= $action_count ?></td>
                        <td>
                            <?php if ($ticket_rule_active) { ?>
                                <span class="text-success text-bold">Active</span>
                            <?php } else { ?>
                                <span class="text-secondary">Inactive</span>
                            <?php } ?>
                        </td>
                        <td>
                            <div class="dropdown dropleft text-center">
                                <button class="btn btn-secondary btn-sm" type="button" data-toggle="dropdown">
                                    <i class="fas fa-ellipsis-h"></i>
                                </button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item" href="ticket_rule.php?ticket_rule_id=<?= $ticket_rule_id ?>">
                                        <i class="fas fa-fw fa-cogs mr-2"></i>Manage conditions/actions
                                    </a>
                                    <a class="dropdown-item ajax-modal" href="#" data-modal-url="modals/ticket_rule/ticket_rule_edit.php?id=<?= $ticket_rule_id ?>">
                                        <i class="fas fa-fw fa-edit mr-2"></i>Edit
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item text-danger text-bold confirm-link" href="post.php?archive_ticket_rule=<?= $ticket_rule_id ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>">
                                        <i class="fas fa-fw fa-trash mr-2"></i>Delete
                                    </a>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
        <?php require_once "../includes/filter_footer.php"; ?>
    </div>
</div>

<?php
require_once "../includes/footer.php";
