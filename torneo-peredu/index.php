<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle = "Inicio - Copa PER.EDU";
$pageDescription = "Sitio oficial de la Copa PER.EDU 2026";
$currentPage = "inicio";

$torneoActual = obtenerTorneoActual();

if ($torneoActual) {
    $idTorneoActual = $torneoActual['id_torneo'];
    $temporada = $torneoActual['temporada'];
    $estadoTemporada = match ($torneoActual['estado']) {
        'borrador' => 'Borrador',
        'en_curso' => 'En curso',
        'finalizado' => 'Finalizado',
        default => 'Sin estado',
    };

    $totalEquipos = (int) dbOne("
        SELECT COUNT(*) AS total
        FROM equipo_torneo et
        INNER JOIN equipos e ON et.id_equipo = e.id_equipo
        WHERE et.id_torneo = ?
          AND et.activo = 1
          AND e.activo = 1
    ", [$idTorneoActual])['total'];

    $totalPartidos = (int) dbOne("
        SELECT COUNT(*) AS total
        FROM partidos
        WHERE id_torneo = ?
    ", [$idTorneoActual])['total'];

    $totalGoles = (int) dbOne("
        SELECT COALESCE(SUM(goles_local + goles_visitante), 0) AS total
        FROM partidos
        WHERE id_torneo = ?
          AND estado = 'finalizado'
          AND goles_local IS NOT NULL
          AND goles_visitante IS NOT NULL
    ", [$idTorneoActual])['total'];

    $totalFechas = (int) dbOne("
        SELECT COUNT(*) AS total
        FROM fechas
        WHERE id_torneo = ?
    ", [$idTorneoActual])['total'];

} else {
    $idTorneoActual = null;
    $temporada = date('Y');
    $estadoTemporada = "Sin torneo";

    $totalEquipos = 0;
    $totalPartidos = 0;
    $totalGoles = 0;
    $totalFechas = 0;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <?php include("includes/head.php"); ?>
</head>
<body>

<?php include("includes/header.php"); ?>

<main class="home-page">

    <!-- HERO PRINCIPAL -->
    <section class="hero-home">

        <div class="hero-bg-lines"></div>

        <div class="hero-content">

            <div class="season-badge">
                <span></span>
                Temporada <?= h($temporada) ?> · <?= h($estadoTemporada) ?>
            </div>

            <h1 class="hero-title">
                <span>Copa</span>
                <strong>PER.EDU</strong>
            </h1>

            <div class="hero-year">
                <?= h($temporada) ?>
            </div>

            <p class="hero-subtitle">
                El torneo de los profes — Escuelas de Mendoza compitiendo por la gloria
            </p>

            <div class="hero-stats">

                <div class="hero-stat">
                    <strong><?= h($totalEquipos) ?></strong>
                    <span>Equipos</span>
                </div>

                <div class="hero-stat">
                    <strong><?= h($totalPartidos) ?></strong>
                    <span>Partidos</span>
                </div>

                <div class="hero-stat">
                    <strong><?= h($totalGoles) ?></strong>
                    <span>Goles</span>
                </div>

                <div class="hero-stat">
                    <strong><?= h($totalFechas) ?></strong>
                    <span>Fechas</span>
                </div>

            </div>

            <div class="hero-actions">
                <a href="<?= BASE_URL ?>/fixture.php" class="btn-main">Ver fixture</a>
                <a href="<?= BASE_URL ?>/tablas.php" class="btn-secondary">Ver tablas</a>
            </div>

        </div>

    </section>

    <!-- ACCESOS RÁPIDOS -->
    <section class="quick-section">
        <div class="quick-container">

            <div class="section-heading">
                <span>Información del torneo</span>
                <h2>Seguimiento de la Copa PER.EDU</h2>
                <p>
                    Consultá el fixture, la tabla de posiciones, los equipos participantes,
                    los jugadores y las estadísticas del torneo.
                </p>
            </div>

            <div class="quick-grid">

                <a href="<?= BASE_URL ?>/fixture.php" class="quick-card">
                    <div class="quick-icon">📅</div>
                    <h3>Fixture</h3>
                    <p>Partidos, fechas, resultados y próximos encuentros.</p>
                </a>

                <a href="<?= BASE_URL ?>/tablas.php" class="quick-card">
                    <div class="quick-icon">🏆</div>
                    <h3>Tabla de posiciones</h3>
                    <p>Clasificación general con puntos, goles y criterios de desempate.</p>
                </a>

                <a href="<?= BASE_URL ?>/equipos.php" class="quick-card">
                    <div class="quick-icon">👕</div>
                    <h3>Equipos</h3>
                    <p>Escudos, equipos participantes y planteles del torneo.</p>
                </a>

                <a href="<?= BASE_URL ?>/estadisticas.php" class="quick-card">
                    <div class="quick-icon">📊</div>
                    <h3>Estadísticas</h3>
                    <p>Goleadores, amarillas, rojas y rendimiento general.</p>
                </a>

            </div>

        </div>
    </section>

</main>

<?php include("includes/footer.php"); ?>