<?php
require_once "includes/inc_all_user.php";

?>

<div class="card card-dark">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-cog me-2"></i>User Preferences</h3>
    </div>
    <div class="card-body">

        <form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <div class="row">
                <div class="col-md-3 text-center">
                    <?php if($session_avatar) { ?>
                    <img class="img-thumbnail" src="<?= "../../uploads/users/$session_user_id/" . escapeHtml($session_avatar) ?>">
                    <a href="post.php?clear_your_user_avatar&csrf_token=<?= $_SESSION['csrf_token'] ?>" class="btn btn-outline-danger w-100">Remove Avatar</a>
                    <hr>
                    <?php } ?>
                    <div class="mb-3">
                        <label>Upload Avatar</label>
                        <input type="file" class="form-control" accept="image/*" name="avatar">
                    </div>
                </div>
                <div class="col-md-9">

                    <div class="mb-3">
                        <label>Name <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                                <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                            <input type="text" class="form-control" name="name" placeholder="Full Name" maxlength="200" value="<?= stripslashes(escapeHtml($session_name)) ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label>Role</label>
                        <div class="input-group">
                                <span class="input-group-text"><i class="fa fa-fw fa-user-shield"></i></span>
                            <input type="text" class="form-control" value="<?= escapeHtml($session_user_role_display) ?>" disabled>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label>Email <strong class="text-danger">*</strong></label>
                        <div class="input-group">
                                <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                            <input type="email" class="form-control" name="email" placeholder="Email Address" maxlength="200" value="<?= escapeHtml($session_email) ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label>Signature</label>
                        <textarea class="form-control tinymceTicket" name="signature" rows="4" placeholder="Create a signature automatically appended to tickets, emails etc"><?= escapeHtml(getFieldById('user_settings',$session_user_id,'user_config_signature')) ?>
                        </textarea>
                    </div>
                    
                    <button type="submit" name="edit_your_user_details" class="btn btn-primary btn-responsive"><i class="fas fa-check me-2"></i>Save</button>

                </div>
            </div>

        </form>
                
    </div>

</div>

<div class="card card-dark">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-bell mr-2"></i>Browser Notifications</h3>
    </div>
    <div class="card-body">
        <p class="text-secondary">Send yourself a test notification to confirm your browser is set up to show ITFlow alerts for things like new ticket assignments and replies.</p>
        <button type="button" id="testBrowserNotificationBtn" class="btn btn-secondary"><i class="fas fa-paper-plane mr-2"></i>Send test notification</button>
    </div>
</div>

<script>
    document.getElementById('testBrowserNotificationBtn').addEventListener('click', function () {
        if (!window.ItflowNotify) {
            toastr.error('Browser notifications are not available on this page.');
            return;
        }

        window.ItflowNotify.test(function (status) {
            if (status === 'granted') {
                toastr.success('Test notification sent - it should pop up shortly.');
            } else if (status === 'denied') {
                toastr.error('Notifications are blocked for this site. Enable them in your browser\'s site settings and try again.');
            } else {
                toastr.error('This browser does not support notifications.');
            }
        });
    });
</script>

<?php
require_once "../../includes/footer.php";
