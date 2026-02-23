<?php

require_once dirname(__DIR__) . '/config.php';
require_once SITE_PATH . '/includes/connectDB.php';
require_once SITE_PATH . '/includes/repositories/PageRepository.php';

function smoke_fail($message)
{
    fwrite(STDERR, '[FAIL] ' . $message . PHP_EOL);
    exit(1);
}

function smoke_ok($message)
{
    fwrite(STDOUT, '[OK] ' . $message . PHP_EOL);
}

$repository = new PageRepository($databaseConnection);
$seed = date('YmdHis') . '_' . bin2hex(random_bytes(3));
$menuLabel = 'smoke_' . $seed;
$content = 'smoke content ' . $seed;
$updatedMenuLabel = $menuLabel . '_u';
$updatedContent = $content . ' updated';
$pageId = null;

try {
    $created = $repository->createPage($menuLabel, $content);
    if (!$created) {
        smoke_fail('createPage false dondu.');
    }
    smoke_ok('createPage');

    $pages = $repository->getNavigationPages();
    foreach ($pages as $page) {
        if ($page['menulabel'] === $menuLabel) {
            $pageId = (int) $page['id'];
            break;
        }
    }
    if ($pageId === null) {
        smoke_fail('Eklenen sayfa nav listesinde bulunamadi.');
    }
    smoke_ok('getNavigationPages');

    $fetched = $repository->getPageById($pageId);
    if ($fetched === null || $fetched['menulabel'] !== $menuLabel || $fetched['content'] !== $content) {
        smoke_fail('getPageById beklenen degeri donmedi.');
    }
    smoke_ok('getPageById');

    $updated = $repository->updatePage($pageId, $updatedMenuLabel, $updatedContent);
    if (!$updated) {
        smoke_fail('updatePage false dondu.');
    }
    $updatedPage = $repository->getPageById($pageId);
    if ($updatedPage === null || $updatedPage['menulabel'] !== $updatedMenuLabel || $updatedPage['content'] !== $updatedContent) {
        smoke_fail('updatePage sonrasi veri beklenen gibi degil.');
    }
    smoke_ok('updatePage');

    $deleted = $repository->deletePage($pageId);
    if (!$deleted) {
        smoke_fail('deletePage false dondu.');
    }
    if ($repository->pageExists($pageId)) {
        smoke_fail('deletePage sonrasi pageExists true dondu.');
    }
    smoke_ok('deletePage/pageExists');
} finally {
    if ($pageId !== null && $repository->pageExists($pageId)) {
        $repository->deletePage($pageId);
    }
}

fwrite(STDOUT, PHP_EOL . 'PageRepository smoke: PASS' . PHP_EOL);