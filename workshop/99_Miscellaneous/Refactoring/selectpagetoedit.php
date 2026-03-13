<?php
require_once 'config.php';
require_once SITE_PATH . '/includes/validation.php';
require_once SITE_PATH . '/includes/session.php';
require_once SITE_PATH . '/includes/connectDB.php';
require_once SITE_PATH . '/includes/repositories/PageRepository.php';

require_any_role($session, array('admin', 'editor'));
$pageRepository = new PageRepository($databaseConnection);
$pages = $pageRepository->getNavigationPages();
$errors = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_or_fail();

    $pageId = validate_required_int('pageId', 'Sayfa', 1, $errors);
    if (empty($errors)) {
        if ($pageRepository->pageExists($pageId)) {
            app_redirect_route('page.edit', array('id' => (int) $pageId));
        }
        $errors[] = 'Secilen sayfa bulunamadi.';
    }
}

include SITE_PATH . '/includes/header.php';
?>
<div id="main">
    <h2>Edit Page</h2>

    <?php render_error_summary($errors); ?>

    <form action="<?php echo e(app_route_url('page.edit.select')); ?>" method="post" novalidate>
        <?php echo csrf_field(); ?>
        <fieldset>
            <legend>Edit Page</legend>
            <ol>
                <li>
                    <label for="pageId">Title:</label>
                    <select id="pageId" name="pageId" required aria-required="true">
                        <option value="">--Select Page--</option>
                        <?php foreach ($pages as $page) { ?>
                            <option value="<?php echo (int) $page['id']; ?>"><?php echo e($page['menulabel']); ?></option>
                        <?php } ?>
                    </select>
                </li>
            </ol>
            <input type="submit" name="submit" value="Edit"/>
        </fieldset>
    </form>
    <br/>
    <a href="<?php echo e(app_route_url('home')); ?>">Cancel</a>
</div>
</div>
<?php include SITE_PATH . '/includes/footer.php'; ?>
