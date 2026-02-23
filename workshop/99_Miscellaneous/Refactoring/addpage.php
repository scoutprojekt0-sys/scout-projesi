<?php
require_once 'config.php';
require_once SITE_PATH . '/includes/validation.php';
require_once SITE_PATH . '/includes/session.php';
require_once SITE_PATH . '/includes/connectDB.php';
require_once SITE_PATH . '/includes/repositories/PageRepository.php';

require_any_role($session, array('admin', 'editor'));
$pageRepository = new PageRepository($databaseConnection);

$errors = array();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_or_fail();

    $menuLabel = validate_required_string('menulabel', 'Menu basligi', 50, $errors);
    $content = validate_required_string('content', 'Icerik', 4000, $errors);

    if (empty($errors)) {
        $createdPageId = $pageRepository->createPageWithId($menuLabel, $content);
        if ($createdPageId !== null) {
            unset($_SESSION['menu_pages_cache']);
            audit_log('add', 'page', (int) $createdPageId, array('menulabel' => $menuLabel));
            app_redirect_route('home');
        }
        $errors[] = 'Yeni sayfa eklenemedi.';
    }
}

include SITE_PATH . '/includes/header.php';
?>
<div id="main">
    <h2>Add Page</h2>

    <?php render_error_summary($errors); ?>

    <form action="<?php echo e(app_route_url('page.add')); ?>" method="post" novalidate>
        <?php echo csrf_field(); ?>
        <fieldset>
            <legend>Add Page</legend>
            <ol>
                <li>
                    <label for="menulabel">Menu Label:</label>
                    <input type="text" name="menulabel" value="<?php echo e(post_value('menulabel')); ?>" id="menulabel" maxlength="50" required aria-required="true"/>
                </li>
                <li>
                    <label for="content">Page Content:</label>
                    <textarea name="content" id="content" maxlength="4000" required aria-required="true"><?php echo e(post_value('content')); ?></textarea>
                </li>
            </ol>
            <input type="submit" name="submit" value="Submit"/>

            <p>
                <a href="<?php echo e(app_route_url('home')); ?>">Cancel</a>
            </p>
        </fieldset>
    </form>
</div>
</div>
<?php include SITE_PATH . '/includes/footer.php'; ?>
