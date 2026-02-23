<?php
require_once 'config.php';
require_once SITE_PATH . '/includes/validation.php';
require_once SITE_PATH . '/includes/session.php';
require_once SITE_PATH . '/includes/connectDB.php';
require_once SITE_PATH . '/includes/repositories/PageRepository.php';

require_any_role($session, array('admin', 'editor'));
$pageRepository = new PageRepository($databaseConnection);

$errors = array();
$pageId = null;
$page = null;
$successMessage = '';
$previewHtml = '';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $pageId = get_query_int('id');
    if ($pageId === null || $pageId < 1) {
        app_redirect_route('home');
    }

    $page = $pageRepository->getPageById($pageId);
    if ($page === null) {
        app_redirect_route('home');
    }
    if (isset($_GET['saved']) && $_GET['saved'] === '1') {
        $successMessage = 'Sayfa basariyla guncellendi.';
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_or_fail();

    $pageId = validate_required_int('pageId', 'Sayfa', 1, $errors);
    $menuLabel = validate_required_string('menulabel', 'Menu basligi', 50, $errors);
    $content = validate_required_string('content', 'Icerik', 4000, $errors);
    $action = isset($_POST['form_action']) ? (string) $_POST['form_action'] : 'save';

    if ($action === 'preview') {
        $page = array(
            'id' => $pageId === null ? 0 : (int) $pageId,
            'menulabel' => post_value('menulabel'),
            'content' => post_value('content'),
        );
        if (empty($errors)) {
            $previewHtml = nl2br(e($content));
        }
    } elseif (empty($errors)) {
        $page = $pageRepository->getPageById($pageId);
        if ($page === null) {
            $errors[] = 'Duzenlenecek sayfa bulunamadi.';
        } else {
            if ($pageRepository->updatePage($pageId, $menuLabel, $content)) {
                unset($_SESSION['menu_pages_cache']);
                audit_log('edit', 'page', $pageId, array('menulabel' => $menuLabel));
                app_redirect_route('page.edit', array('id' => (int) $pageId, 'saved' => 1));
            }
            $errors[] = 'Sayfa guncellenemedi.';
        }
    } else {
        $page = array(
            'id' => $pageId,
            'menulabel' => post_value('menulabel'),
            'content' => post_value('content'),
        );
    }
} else {
    app_redirect_route('home');
}

include SITE_PATH . '/includes/header.php';
?>
<div id="main">
    <h1>Edit Page</h1>

    <?php render_error_summary($errors); ?>
    <?php if ($successMessage !== '') { ?>
        <p class="message-success"><?php echo e($successMessage); ?></p>
    <?php } ?>

    <form action="<?php echo e(app_route_url('page.edit')); ?>" method="post" novalidate>
        <?php echo csrf_field(); ?>
        <fieldset>
            <legend>Edit Page</legend>
            <ol>
                <li>
                    <input type="hidden" id="pageId" name="pageId" value="<?php echo (int) $page['id']; ?>"/>
                    <label for="menulabel">Menu Label:</label>
                    <input type="text" name="menulabel" value="<?php echo e($page['menulabel']); ?>" id="menulabel" maxlength="50" required aria-required="true"/>
                </li>
                <li>
                    <label for="content">Page Content:</label>
                    <textarea name="content" id="content" maxlength="4000" required aria-required="true"><?php echo e($page['content']); ?></textarea>
                </li>
            </ol>
            <button type="submit" name="form_action" value="save">Kaydet</button>
            <button type="submit" name="form_action" value="preview">Onizle</button>

            <p>
                <a href="<?php echo e(app_route_url('home')); ?>">Cancel</a>
            </p>
        </fieldset>
    </form>

    <?php if ($previewHtml !== '') { ?>
        <section class="preview-box" aria-live="polite">
            <h3>Onizleme</h3>
            <h4><?php echo e($page['menulabel']); ?></h4>
            <div><?php echo $previewHtml; ?></div>
        </section>
    <?php } ?>
</div>
</div>
<?php include SITE_PATH . '/includes/footer.php'; ?>
