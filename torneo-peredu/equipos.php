<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle = "Equipos - Copa PER.EDU";
$pageDescription = "Equipos participantes de la Copa PER.EDU";
$currentPage = "equipos";

/* =====================================================
   FUNCIONES LOCALES
===================================================== */

function obtenerEquiposPublicos($idTorneo)
{
    if (!$idTorneo) {
        return [];
    }

    return dbAll("
        SELECT
            et.id_equipo_torneo,
            e.id_equipo,
            e.nombre,
            e.escuela,
            e.escudo,

            (
                SELECT COUNT(*)
                FROM jugador_equipo_torneo jet
                INNER JOIN jugadores j
                    ON jet.id_jugador = j.id_jugador
                WHERE jet.id_equipo_torneo = et.id_equipo_torneo
                  AND jet.activo = 1
                  AND j.activo = 1
            ) AS total_jugadores

        FROM equipo_torneo et
        INNER JOIN equipos e
            ON et.id_equipo = e.id_equipo

        WHERE et.id_torneo = ?
          AND et.activo = 1
          AND e.activo = 1

        ORDER BY e.nombre ASC
    ", [$idTorneo]);
}

function obtenerJugadoresEquipoPublico($idEquipoTorneo)
{
    return dbAll("
        SELECT
            j.nombre,
            j.apellido,
            j.dni,
            jet.numero_camiseta,

            (
                SELECT COUNT(*)
                FROM jugador_partido jp
                WHERE jp.id_jugador_equipo_torneo = jet.id_jugador_equipo_torneo
                  AND jp.presente = 1
            ) AS partidos_jugados,

            (
                SELECT COALESCE(SUM(jp.goles), 0)
                FROM jugador_partido jp
                WHERE jp.id_jugador_equipo_torneo = jet.id_jugador_equipo_torneo
            ) AS goles,

            (
                SELECT COALESCE(SUM(jp.amarillas), 0)
                FROM jugador_partido jp
                WHERE jp.id_jugador_equipo_torneo = jet.id_jugador_equipo_torneo
            ) AS amarillas,

            (
                SELECT COALESCE(SUM(jp.rojas), 0)
                FROM jugador_partido jp
                WHERE jp.id_jugador_equipo_torneo = jet.id_jugador_equipo_torneo
            ) AS rojas

        FROM jugador_equipo_torneo jet
        INNER JOIN jugadores j
            ON jet.id_jugador = j.id_jugador

        WHERE jet.id_equipo_torneo = ?
          AND jet.activo = 1
          AND j.activo = 1

        ORDER BY
            CASE WHEN jet.numero_camiseta IS NULL THEN 1 ELSE 0 END,
            jet.numero_camiseta ASC,
            j.apellido ASC,
            j.nombre ASC
    ", [$idEquipoTorneo]);
}

function inicialesPublicas($nombre, $apellido)
{
    $inicialNombre = mb_substr(trim($nombre), 0, 1, 'UTF-8');
    $inicialApellido = mb_substr(trim($apellido), 0, 1, 'UTF-8');

    return mb_strtoupper($inicialNombre . $inicialApellido, 'UTF-8');
}

/* =====================================================
   DATOS
===================================================== */

$torneoActual = obtenerTorneoActual();
$idTorneoActual = $torneoActual['id_torneo'] ?? null;

$equipos = obtenerEquiposPublicos($idTorneoActual);

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <?php include("includes/head.php"); ?>
</head>
<body>

<?php include("includes/header.php"); ?>

<main class="public-page">

    <!-- HERO -->
    <section class="public-hero">
        <div class="hero-bg-lines"></div>

        <div class="public-hero-content">
            <span class="section-tag">Equipos participantes</span>

            <h1>Equipos</h1>

            <?php if ($torneoActual): ?>
                <p>
                    Estos son los equipos que forman parte de la
                    <strong><?= h($torneoActual['nombre']) ?></strong>
                    · Temporada <?= h($torneoActual['temporada']) ?>.
                </p>
            <?php else: ?>
                <p>Todavía no hay un torneo cargado.</p>
            <?php endif; ?>
        </div>
    </section>

    <!-- EQUIPOS -->
    <section class="public-section">
        <div class="public-container">

            <?php if (!$torneoActual): ?>

                <div class="public-empty">
                    <h2>No hay torneo disponible</h2>
                    <p>Cuando el administrador cree un torneo, los equipos aparecerán acá.</p>
                </div>

            <?php elseif (empty($equipos)): ?>

                <div class="public-empty">
                    <h2>No hay equipos cargados</h2>
                    <p>Todavía no se registraron equipos participantes en este torneo.</p>
                </div>

            <?php else: ?>

                <div class="teams-public-grid">

                    <?php foreach ($equipos as $equipo): ?>
                        <?php $jugadores = obtenerJugadoresEquipoPublico($equipo['id_equipo_torneo']); ?>

                        <article class="team-public-card">

                            <div class="team-public-header">

                                <div class="team-public-shield">
                                    <?php if (!empty($equipo['escudo'])): ?>
                                        <img src="<?= h(asset($equipo['escudo'])) ?>" alt="Escudo <?= h($equipo['nombre']) ?>">
                                    <?php else: ?>
                                        <span>👕</span>
                                    <?php endif; ?>
                                </div>

                                <div>
                                    <h2><?= h($equipo['nombre']) ?></h2>

                                    <?php if (!empty($equipo['escuela'])): ?>
                                        <p><?= h($equipo['escuela']) ?></p>
                                    <?php else: ?>
                                        <p>Escuela no especificada</p>
                                    <?php endif; ?>

                                    <small>
                                        <?= h($equipo['total_jugadores']) ?>
                                        jugadores cargados
                                    </small>
                                </div>

                            </div>

                            <?php if (empty($jugadores)): ?>

                                <div class="team-empty-players">
                                    Plantel todavía no cargado.
                                </div>

                            <?php else: ?>

                                <div class="team-players-list">

                                    <?php foreach ($jugadores as $jugador): ?>
                                        <div class="team-player-row">

                                            <div class="team-player-main">
                                                <div class="team-player-avatar">
                                                    <?= h(inicialesPublicas($jugador['nombre'], $jugador['apellido'])) ?>
                                                </div>

                                                <div>
                                                    <strong>
                                                        <?= h($jugador['apellido']) ?>, <?= h($jugador['nombre']) ?>
                                                    </strong>

                                                    <span>
                                                        <?php if (!empty($jugador['numero_camiseta'])): ?>
                                                            N° <?= h($jugador['numero_camiseta']) ?>
                                                        <?php else: ?>
                                                            Sin número
                                                        <?php endif; ?>
                                                    </span>
                                                </div>
                                            </div>

                                            <div class="team-player-stats">
                                                <span title="Partidos jugados">PJ <?= h($jugador['partidos_jugados']) ?></span>
                                                <span title="Goles">⚽ <?= h($jugador['goles']) ?></span>
                                                <span title="Amarillas">🟨 <?= h($jugador['amarillas']) ?></span>
                                                <span title="Rojas">🟥 <?= h($jugador['rojas']) ?></span>
                                            </div>

                                        </div>
                                    <?php endforeach; ?>

                                </div>

                            <?php endif; ?>

                        </article>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </div>
    </section>

</main>

<?php include("includes/footer.php"); ?>