<?php
require_once SITE_PATH . '/includes/session.php';
require_once SITE_PATH . '/includes/repositories/PageRepository.php';

$pageRepository = new PageRepository($databaseConnection);
$menuCacheTtlSeconds = 60;
$menuPages = array();
$menuCache = isset($_SESSION['menu_pages_cache']) && is_array($_SESSION['menu_pages_cache']) ? $_SESSION['menu_pages_cache'] : null;
if ($menuCache !== null) {
    $menuCacheValidUntil = isset($menuCache['valid_until']) ? (int) $menuCache['valid_until'] : 0;
    if ($menuCacheValidUntil >= time() && isset($menuCache['pages']) && is_array($menuCache['pages'])) {
        $menuPages = $menuCache['pages'];
    }
}
if (empty($menuPages)) {
    $menuPages = $pageRepository->getNavigationPages();
    $_SESSION['menu_pages_cache'] = array(
        'pages' => $menuPages,
        'valid_until' => time() + $menuCacheTtlSeconds,
    );
}
$isLoggedOn = logged_on($session);
$canEditContent = $isLoggedOn ? has_any_role($session, array('admin', 'editor')) : false;
$isAdmin = $isLoggedOn ? is_admin($session) : false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <title><?php echo e(SITE_TITLE); ?></title>
    <link href="styles/site.css" rel="stylesheet" type="text/css"/>
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
<a class="skip-link" href="#main">Icerige gec</a>
<div class="outer-wrapper">
    <header>
        <div class="content-wrapper">
            <div class="float-left">
                <p class="site-title"><a href="<?php echo e(app_route_url('home')); ?>"><?php echo e(SITE_TITLE); ?></a></p>
            </div>
            <div class="float-right">
                <section id="login" aria-label="Kullanici islemleri">
                    <ul id="login">
                        <?php if ($isLoggedOn) { ?>
                            <?php if ($canEditContent) { ?>
                                <li><a href="<?php echo e(app_route_url('dashboard')); ?>">Dashboard</a></li>
                            <?php } ?>
                            <li><a href="<?php echo e(app_route_url('logout')); ?>">Sign out</a></li>
                            <li><a href="<?php echo e(app_route_url('password.change')); ?>">Password</a></li>
                            <?php if ($canEditContent) { ?>
                                <li><a href="<?php echo e(app_route_url('page.add')); ?>">Add</a></li>
                                <li><a href="<?php echo e(app_route_url('page.edit.select')); ?>">Edit</a></li>
                            <?php } ?>
                            <?php if ($isAdmin) { ?>
                                <li><a href="<?php echo e(app_route_url('page.delete')); ?>">Delete</a></li>
                            <?php } ?>
                        <?php } else { ?>
                            <li><a href="<?php echo e(app_route_url('login')); ?>">Login</a></li>
                            <li><a href="<?php echo e(app_route_url('register')); ?>">Register</a></li>
                        <?php } ?>
                    </ul>
                    <?php if ($isLoggedOn) { ?>
                        <div class="welcomeMessage">Welcome, <strong><?php echo e($session->get('username')); ?></strong></div>
                    <?php } ?>
                </section>
            </div>

            <div class="clear-fix"></div>
        </div>

        <section class="navigation" data-role="navbar">
            <nav aria-label="Site menu">
                <ul id="menu">
                    <?php foreach ($menuPages as $menuPage) { ?>
                        <li><a href="<?php echo e(app_route_url('home', array('pageid' => (int) $menuPage['id']))); ?>"><?php echo e($menuPage['menulabel']); ?></a></li>
                    <?php } ?>
                </ul>
            </nav>
        </section>
    </header>
