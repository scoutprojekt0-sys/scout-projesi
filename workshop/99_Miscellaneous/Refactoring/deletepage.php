<?php
require_once 'config.php';
require_once SITE_PATH . '/includes/validation.php';
require_once SITE_PATH . '/includes/session.php';
require_once SITE_PATH . '/includes/connectDB.php';
require_once SITE_PATH . '/includes/repositories/PageRepository.php';

require_admin($session);
$pageRepository = new PageRepository($databaseConnection);
$pages = $pageRepository->getNavigationPages();
$errors = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_or_fail();

    $pageId = validate_required_int('pageId', 'Sayfa', 1, $errors);
    if ($pageId === 1) {
        $errors[] = 'Ana sayfa silinemez.';
    }

    if (empty($errors)) {
        if ($pageRepository->deletePage($pageId)) {
            unset($_SESSION['menu_pages_cache']);
            audit_log('delete', 'page', $pageId);
            app_redirect_route('home');
        }

        $errors[] = 'Sayfa silinemedi.';
    }
}

include SITE_PATH . '/includes/header.php';
?>
<div id="main">
    <h2>Delete Page</h2>

    <?php render_error_summary($errors); ?>

    <form action="<?php echo e(app_route_url('page.delete')); ?>" method="post" novalidate>
        <?php echo csrf_field(); ?>
        <fieldset>
            <legend>Delete Page</legend>
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
            <input type="submit" name="submit" value="Delete"/>

            <p>
                <a href="<?php echo e(app_route_url('home')); ?>">Cancel</a>
            </p>
        </fieldset>
    </form>
</div>
</div>
<?php include SITE_PATH . '/includes/footer.php'; ?>
