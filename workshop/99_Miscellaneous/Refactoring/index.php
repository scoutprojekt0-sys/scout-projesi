<?php
require_once 'config.php';
require_once SITE_PATH . '/includes/validation.php';
require_once SITE_PATH . '/includes/session.php';
require_once SITE_PATH . '/includes/connectDB.php';
require_once SITE_PATH . '/includes/repositories/PageRepository.php';
include SITE_PATH . '/includes/header.php';

$pageRepository = new PageRepository($databaseConnection);
$pageId = get_query_int('pageid', 1);
if ($pageId === null || $pageId < 1) {
    $pageId = 1;
}

$page = $pageRepository->getPageById($pageId);
?>

<div id="main">
    <?php if ($page !== null) { ?>
        <h2><?php echo e($page['menulabel']); ?></h2>
        <div><?php echo nl2br(e($page['content'])); ?></div>
    <?php } else { ?>
        <p class="message-error" role="alert">Page not found.</p>
    <?php } ?>
</div>
</div>
<?php include SITE_PATH . '/includes/footer.php'; ?>
