<?php

function validate_required_string($fieldName, $label, $maxLength, array &$errors)
{
    $value = isset($_POST[$fieldName]) ? trim((string) $_POST[$fieldName]) : '';

    if ($value === '') {
        $errors[] = $label . ' alani zorunludur.';
        return '';
    }

    if (mb_strlen($value) > $maxLength) {
        $errors[] = sprintf('%s en fazla %d karakter olabilir.', $label, $maxLength);
        return '';
    }

    return $value;
}

function validate_optional_string($fieldName, $label, $maxLength, array &$errors)
{
    $value = isset($_POST[$fieldName]) ? trim((string) $_POST[$fieldName]) : '';
    if ($value === '') {
        return '';
    }

    if (mb_strlen($value) > $maxLength) {
        $errors[] = sprintf('%s en fazla %d karakter olabilir.', $label, $maxLength);
        return '';
    }

    return $value;
}

function validate_required_int($fieldName, $label, $min, array &$errors)
{
    $raw = isset($_POST[$fieldName]) ? $_POST[$fieldName] : null;
    if ($raw === null || $raw === '') {
        $errors[] = $label . ' secimi zorunludur.';
        return null;
    }

    if (!is_numeric($raw)) {
        $errors[] = $label . ' gecersiz.';
        return null;
    }

    $value = (int) $raw;
    if ($value < $min) {
        $errors[] = $label . ' gecersiz.';
        return null;
    }

    return $value;
}

function get_query_int($fieldName, $default = null)
{
    if (!isset($_GET[$fieldName]) || $_GET[$fieldName] === '') {
        return $default;
    }

    if (!is_numeric($_GET[$fieldName])) {
        return $default;
    }

    return (int) $_GET[$fieldName];
}
