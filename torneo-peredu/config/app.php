<?php
// config/app.php

define('BASE_URL', '/desarrollo-personal/torneo-peredu');

function asset($path)
{
    $path = ltrim($path, '/');

    $filePath = __DIR__ . '/../' . $path;

    if (file_exists($filePath)) {
        return BASE_URL . '/' . $path . '?v=' . filemtime($filePath);
    }

    return BASE_URL . '/' . $path . '?v=' . time();
}

function activePage($page, $currentPage)
{
    return $page === $currentPage ? 'active' : '';
}
