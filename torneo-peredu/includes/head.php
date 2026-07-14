<?php
require_once __DIR__ . '/../config/app.php';

if (!isset($pageTitle)) {
    $pageTitle = 'Copa PER.EDU';
}

if (!isset($pageDescription)) {
    $pageDescription = 'El torneo de los profes - Copa PER.EDU 2026';
}
?>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title><?= htmlspecialchars($pageTitle) ?></title>
<meta name="description" content="<?= htmlspecialchars($pageDescription) ?>">

<link rel="icon" href="<?= asset('assets/img/logo.jpg') ?>" type="image/jpeg">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

<link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
<link rel="icon" type="image/jpeg" href="<?= asset('assets/img/logo.jpg') ?>">
<link rel="shortcut icon" href="<?= asset('assets/img/logo.jpg') ?>">
