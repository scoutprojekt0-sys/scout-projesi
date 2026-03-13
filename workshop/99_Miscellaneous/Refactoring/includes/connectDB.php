<?php
require_once SITE_PATH . '/functions/database.php';

// Create database connection
$databaseConnection = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME, DB_PORT);
if ($databaseConnection->connect_error) {
    app_fail('Veritabani baglantisi su an kurulamadi.', 'Database selection failed: ' . $databaseConnection->connect_error);
}

$databaseConnection->set_charset(DB_CHARSET);

// Create tables if needed.
if (!defined('IN_TEST') || IN_TEST == false) {
    prep_DB_content();
}




