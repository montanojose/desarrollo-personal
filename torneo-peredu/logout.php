<?php
require_once __DIR__ . '/includes/functions.php';

logoutUsuario();

header('Location: ' . BASE_URL . '/login.php');
exit;