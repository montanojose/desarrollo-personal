<?php
if (!isset($currentPage)) {
    $currentPage = '';
}
?>

<header class="main-header">
    <div class="header-container">

        <a href="<?= BASE_URL ?>/index.php" class="brand">
            <div class="brand-logo">
                <img src="<?= asset('assets/img/logo.jpg') ?>" alt="Logo Copa PER.EDU">
            </div>

            <div class="brand-text">
                <strong>COPA PER.EDU</strong>
                <span>EL TORNEO DE LOS PROFES</span>
            </div>
        </a>

        <button class="menu-toggle" id="menuToggle" aria-label="Abrir menú">
            <span></span>
            <span></span>
            <span></span>
        </button>

        <nav class="main-nav" id="mainNav">
            <a class="<?= activePage('fixture', $currentPage) ?>" href="<?= BASE_URL ?>/fixture.php">Fixture</a>
            <a class="<?= activePage('tablas', $currentPage) ?>" href="<?= BASE_URL ?>/tablas.php">Tabla</a>
            <a class="<?= activePage('equipos', $currentPage) ?>" href="<?= BASE_URL ?>/equipos.php">Equipos</a>
            <a class="<?= activePage('jugadores', $currentPage) ?>" href="<?= BASE_URL ?>/jugadores.php">Jugadores</a>
            <a class="<?= activePage('estadisticas', $currentPage) ?>" href="<?= BASE_URL ?>/estadisticas.php">Estadísticas</a>

            <a class="admin-button" href="<?= BASE_URL ?>/login.php">
                 ⚙ Admin
            </a>
        </nav>
    </div>
</header>