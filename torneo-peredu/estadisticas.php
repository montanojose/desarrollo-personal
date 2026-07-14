<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle = "Estadísticas - Copa PER.EDU";
$pageDescription = "Estadísticas generales de la Copa PER.EDU";
$currentPage = "estadisticas";

/* =====================================================
   FUNCIONES LOCALES
===================================================== */

function obtenerResumenPublicoEstadisticas($idTorneo)
{
    if (!$idTorneo) {
        return [
            'equipos' => 0,
            'jugadores' => 0,
            'partidos' => 0,
            'finalizados' => 0,
            'goles' => 0,
            'amarillas' => 0,
            'rojas' => 0,
        ];
    }

    $equipos = dbOne("
        SELECT COUNT(*) AS total
        FROM equipo_torneo et
        INNER JOIN equipos e ON et.id_equipo = e.id_equipo
        WHERE et.id_torneo = ?
          AND et.activo = 1
          AND e.activo = 1
    ", [$idTorneo]);

    $jugadores = dbOne("
        SELECT COUNT(*) AS total
        FROM jugador_equipo_torneo jet
        INNER JOIN equipo_torneo et ON jet.id_equipo_torneo = et.id_equipo_torneo
        INNER JOIN jugadores j ON jet.id_jugador = j.id_jugador
        WHERE et.id_torneo = ?
          AND jet.activo = 1
          AND j.activo = 1
    ", [$idTorneo]);

    $partidos = dbOne("
        SELECT COUNT(*) AS total
        FROM partidos
        WHERE id_torneo = ?
    ", [$idTorneo]);

    $finalizados = dbOne("
        SELECT COUNT(*) AS total
        FROM partidos
        WHERE id_torneo = ?
          AND estado = 'finalizado'
    ", [$idTorneo]);

    $goles = dbOne("
        SELECT COALESCE(SUM(goles_local + goles_visitante), 0) AS total
        FROM partidos
        WHERE id_torneo = ?
          AND estado = 'finalizado'
          AND goles_local IS NOT NULL
          AND goles_visitante IS NOT NULL
    ", [$idTorneo]);

    $tarjetas = dbOne("
        SELECT
            COALESCE(SUM(jp.amarillas), 0) AS amarillas,
            COALESCE(SUM(jp.rojas), 0) AS rojas
        FROM jugador_partido jp
        INNER JOIN partidos p ON jp.id_partido = p.id_partido
        WHERE p.id_torneo = ?
          AND p.estado = 'finalizado'
    ", [$idTorneo]);

    return [
        'equipos' => (int)$equipos['total'],
        'jugadores' => (int)$jugadores['total'],
        'partidos' => (int)$partidos['total'],
        'finalizados' => (int)$finalizados['total'],
        'goles' => (int)$goles['total'],
        'amarillas' => (int)$tarjetas['amarillas'],
        'rojas' => (int)$tarjetas['rojas'],
    ];
}

function obtenerGoleadoresPublicos($idTorneo)
{
    if (!$idTorneo) {
        return [];
    }

    return dbAll("
        SELECT
            j.id_jugador,
            CONCAT(j.apellido, ', ', j.nombre) AS jugador,
            e.nombre AS equipo,
            e.escudo,
            SUM(jp.goles) AS goles
        FROM jugador_partido jp
        INNER JOIN partidos p
            ON jp.id_partido = p.id_partido
        INNER JOIN jugador_equipo_torneo jet
            ON jp.id_jugador_equipo_torneo = jet.id_jugador_equipo_torneo
        INNER JOIN jugadores j
            ON jet.id_jugador = j.id_jugador
        INNER JOIN equipo_torneo et
            ON jet.id_equipo_torneo = et.id_equipo_torneo
        INNER JOIN equipos e
            ON et.id_equipo = e.id_equipo
        WHERE p.id_torneo = ?
          AND p.estado = 'finalizado'
          AND jp.presente = 1
        GROUP BY
            j.id_jugador,
            jugador,
            e.nombre,
            e.escudo
        HAVING goles > 0
        ORDER BY goles DESC, jugador ASC
    ", [$idTorneo]);
}

function obtenerAmarillasPublicas($idTorneo)
{
    if (!$idTorneo) {
        return [];
    }

    return dbAll("
        SELECT
            j.id_jugador,
            CONCAT(j.apellido, ', ', j.nombre) AS jugador,
            e.nombre AS equipo,
            e.escudo,
            SUM(jp.amarillas) AS amarillas
        FROM jugador_partido jp
        INNER JOIN partidos p
            ON jp.id_partido = p.id_partido
        INNER JOIN jugador_equipo_torneo jet
            ON jp.id_jugador_equipo_torneo = jet.id_jugador_equipo_torneo
        INNER JOIN jugadores j
            ON jet.id_jugador = j.id_jugador
        INNER JOIN equipo_torneo et
            ON jet.id_equipo_torneo = et.id_equipo_torneo
        INNER JOIN equipos e
            ON et.id_equipo = e.id_equipo
        WHERE p.id_torneo = ?
          AND p.estado = 'finalizado'
          AND jp.presente = 1
        GROUP BY
            j.id_jugador,
            jugador,
            e.nombre,
            e.escudo
        HAVING amarillas > 0
        ORDER BY amarillas DESC, jugador ASC
    ", [$idTorneo]);
}

function obtenerRojasPublicas($idTorneo)
{
    if (!$idTorneo) {
        return [];
    }

    return dbAll("
        SELECT
            j.id_jugador,
            CONCAT(j.apellido, ', ', j.nombre) AS jugador,
            e.nombre AS equipo,
            e.escudo,
            SUM(jp.rojas) AS rojas
        FROM jugador_partido jp
        INNER JOIN partidos p
            ON jp.id_partido = p.id_partido
        INNER JOIN jugador_equipo_torneo jet
            ON jp.id_jugador_equipo_torneo = jet.id_jugador_equipo_torneo
        INNER JOIN jugadores j
            ON jet.id_jugador = j.id_jugador
        INNER JOIN equipo_torneo et
            ON jet.id_equipo_torneo = et.id_equipo_torneo
        INNER JOIN equipos e
            ON et.id_equipo = e.id_equipo
        WHERE p.id_torneo = ?
          AND p.estado = 'finalizado'
          AND jp.presente = 1
        GROUP BY
            j.id_jugador,
            jugador,
            e.nombre,
            e.escudo
        HAVING rojas > 0
        ORDER BY rojas DESC, jugador ASC
    ", [$idTorneo]);
}

function obtenerEstadisticasEquiposPublicas($idTorneo)
{
    if (!$idTorneo) {
        return [];
    }

    return dbAll("
        SELECT
            et.id_equipo_torneo,
            e.nombre AS equipo,
            e.escudo,

            COALESCE(partidos.partidos_jugados, 0) AS partidos_jugados,
            COALESCE(partidos.goles_favor, 0) AS goles_favor,
            COALESCE(partidos.goles_contra, 0) AS goles_contra,
            COALESCE(tarjetas.amarillas, 0) AS amarillas,
            COALESCE(tarjetas.rojas, 0) AS rojas

        FROM equipo_torneo et

        INNER JOIN equipos e
            ON et.id_equipo = e.id_equipo

        LEFT JOIN (
            SELECT
                datos.id_equipo_torneo,
                COUNT(*) AS partidos_jugados,
                SUM(datos.goles_favor) AS goles_favor,
                SUM(datos.goles_contra) AS goles_contra
            FROM (
                SELECT
                    p.id_equipo_local AS id_equipo_torneo,
                    p.goles_local AS goles_favor,
                    p.goles_visitante AS goles_contra
                FROM partidos p
                WHERE p.id_torneo = ?
                  AND p.estado = 'finalizado'
                  AND p.goles_local IS NOT NULL
                  AND p.goles_visitante IS NOT NULL

                UNION ALL

                SELECT
                    p.id_equipo_visitante AS id_equipo_torneo,
                    p.goles_visitante AS goles_favor,
                    p.goles_local AS goles_contra
                FROM partidos p
                WHERE p.id_torneo = ?
                  AND p.estado = 'finalizado'
                  AND p.goles_local IS NOT NULL
                  AND p.goles_visitante IS NOT NULL
            ) AS datos
            GROUP BY datos.id_equipo_torneo
        ) AS partidos
            ON partidos.id_equipo_torneo = et.id_equipo_torneo

        LEFT JOIN (
            SELECT
                jet.id_equipo_torneo,
                SUM(jp.amarillas) AS amarillas,
                SUM(jp.rojas) AS rojas
            FROM jugador_partido jp
            INNER JOIN partidos p
                ON jp.id_partido = p.id_partido
            INNER JOIN jugador_equipo_torneo jet
                ON jp.id_jugador_equipo_torneo = jet.id_jugador_equipo_torneo
            WHERE p.id_torneo = ?
              AND p.estado = 'finalizado'
            GROUP BY jet.id_equipo_torneo
        ) AS tarjetas
            ON tarjetas.id_equipo_torneo = et.id_equipo_torneo

        WHERE et.id_torneo = ?
          AND et.activo = 1
          AND e.activo = 1

        ORDER BY goles_favor DESC, equipo ASC
    ", [$idTorneo, $idTorneo, $idTorneo, $idTorneo]);
}

function escudoEstadisticaPublica($escudo, $alt = 'Escudo')
{
    if (!empty($escudo)) {
        return '<img src="' . h(asset($escudo)) . '" alt="' . h($alt) . '">';
    }

    return '<span>👕</span>';
}

/* =====================================================
   DATOS
===================================================== */

$torneoActual = obtenerTorneoActual();
$idTorneoActual = $torneoActual['id_torneo'] ?? null;

$resumen = obtenerResumenPublicoEstadisticas($idTorneoActual);
$goleadores = obtenerGoleadoresPublicos($idTorneoActual);
$amarillas = obtenerAmarillasPublicas($idTorneoActual);
$rojas = obtenerRojasPublicas($idTorneoActual);
$estadisticasEquipos = obtenerEstadisticasEquiposPublicas($idTorneoActual);

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
            <span class="section-tag">Números del torneo</span>

            <h1>Estadísticas</h1>

            <?php if ($torneoActual): ?>
                <p>
                    Goles, tarjetas y rendimiento general de la
                    <strong><?= h($torneoActual['nombre']) ?></strong>
                    · Temporada <?= h($torneoActual['temporada']) ?>.
                </p>
            <?php else: ?>
                <p>Todavía no hay un torneo cargado.</p>
            <?php endif; ?>
        </div>
    </section>

    <section class="public-section">
        <div class="public-container">

            <?php if (!$torneoActual): ?>

                <div class="public-empty">
                    <h2>No hay torneo disponible</h2>
                    <p>Cuando el administrador cree un torneo, las estadísticas aparecerán acá.</p>
                </div>

            <?php else: ?>

                <!-- RESUMEN -->
                <div class="public-stats-summary-grid">

                    <article>
                        <strong><?= h($resumen['equipos']) ?></strong>
                        <span>Equipos</span>
                    </article>

                    <article>
                        <strong><?= h($resumen['jugadores']) ?></strong>
                        <span>Jugadores</span>
                    </article>

                    <article>
                        <strong><?= h($resumen['partidos']) ?></strong>
                        <span>Partidos</span>
                    </article>

                    <article>
                        <strong><?= h($resumen['finalizados']) ?></strong>
                        <span>Finalizados</span>
                    </article>

                    <article>
                        <strong><?= h($resumen['goles']) ?></strong>
                        <span>Goles</span>
                    </article>

                    <article>
                        <strong><?= h($resumen['amarillas']) ?></strong>
                        <span>Amarillas</span>
                    </article>

                    <article>
                        <strong><?= h($resumen['rojas']) ?></strong>
                        <span>Rojas</span>
                    </article>

                </div>

                <!-- RANKINGS -->
                <section class="public-rankings-grid">

                    <!-- GOLEADORES -->
                    <article class="public-ranking-card">
                        <div class="public-ranking-header">
                            <span>⚽</span>
                            <div>
                                <h2>Goleadores</h2>
                                <p>Jugadores con más goles del torneo.</p>
                            </div>
                        </div>

                        <?php if (empty($goleadores)): ?>

                            <div class="public-empty-small">
                                Todavía no hay goles cargados.
                            </div>

                        <?php else: ?>

                            <div class="public-ranking-list">
                                <?php foreach ($goleadores as $index => $jugador): ?>
                                    <div class="public-ranking-row">
                                        <strong class="ranking-position"><?= h($index + 1) ?></strong>

                                        <div class="public-team-mini-shield">
                                            <?= escudoEstadisticaPublica($jugador['escudo'], $jugador['equipo']) ?>
                                        </div>

                                        <div class="ranking-info">
                                            <strong><?= h($jugador['jugador']) ?></strong>
                                            <span><?= h($jugador['equipo']) ?></span>
                                        </div>

                                        <strong class="ranking-number"><?= h($jugador['goles']) ?></strong>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                        <?php endif; ?>
                    </article>

                    <!-- AMARILLAS -->
                    <article class="public-ranking-card">
                        <div class="public-ranking-header">
                            <span>🟨</span>
                            <div>
                                <h2>Amarillas</h2>
                                <p>Jugadores con tarjetas amarillas.</p>
                            </div>
                        </div>

                        <?php if (empty($amarillas)): ?>

                            <div class="public-empty-small">
                                Todavía no hay amarillas cargadas.
                            </div>

                        <?php else: ?>

                            <div class="public-ranking-list">
                                <?php foreach ($amarillas as $index => $jugador): ?>
                                    <div class="public-ranking-row">
                                        <strong class="ranking-position"><?= h($index + 1) ?></strong>

                                        <div class="public-team-mini-shield">
                                            <?= escudoEstadisticaPublica($jugador['escudo'], $jugador['equipo']) ?>
                                        </div>

                                        <div class="ranking-info">
                                            <strong><?= h($jugador['jugador']) ?></strong>
                                            <span><?= h($jugador['equipo']) ?></span>
                                        </div>

                                        <strong class="ranking-number yellow"><?= h($jugador['amarillas']) ?></strong>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                        <?php endif; ?>
                    </article>

                    <!-- ROJAS -->
                    <article class="public-ranking-card">
                        <div class="public-ranking-header">
                            <span>🟥</span>
                            <div>
                                <h2>Rojas</h2>
                                <p>Jugadores con tarjetas rojas.</p>
                            </div>
                        </div>

                        <?php if (empty($rojas)): ?>

                            <div class="public-empty-small">
                                Todavía no hay rojas cargadas.
                            </div>

                        <?php else: ?>

                            <div class="public-ranking-list">
                                <?php foreach ($rojas as $index => $jugador): ?>
                                    <div class="public-ranking-row">
                                        <strong class="ranking-position"><?= h($index + 1) ?></strong>

                                        <div class="public-team-mini-shield">
                                            <?= escudoEstadisticaPublica($jugador['escudo'], $jugador['equipo']) ?>
                                        </div>

                                        <div class="ranking-info">
                                            <strong><?= h($jugador['jugador']) ?></strong>
                                            <span><?= h($jugador['equipo']) ?></span>
                                        </div>

                                        <strong class="ranking-number red"><?= h($jugador['rojas']) ?></strong>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                        <?php endif; ?>
                    </article>

                </section>

                <!-- ESTADÍSTICAS POR EQUIPO -->
                <section class="public-section-inner">
                    <div class="public-section-title">
                        <span>Por equipo</span>
                        <h2>Rendimiento general</h2>
                        <p>Resumen de goles y tarjetas por equipo participante.</p>
                    </div>

                    <?php if (empty($estadisticasEquipos)): ?>

                        <div class="public-empty">
                            <h2>No hay equipos cargados</h2>
                            <p>Cuando existan equipos, aparecerá el resumen por equipo.</p>
                        </div>

                    <?php else: ?>

                        <div class="public-table-wrapper">
                            <table class="public-table public-team-stats-table">
                                <thead>
                                    <tr>
                                        <th>Equipo</th>
                                        <th>PJ</th>
                                        <th>GF</th>
                                        <th>GC</th>
                                        <th>DG</th>
                                        <th>Amarillas</th>
                                        <th>Rojas</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <?php foreach ($estadisticasEquipos as $equipo): ?>
                                        <?php
                                        $dg = (int)$equipo['goles_favor'] - (int)$equipo['goles_contra'];
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="public-team-cell">
                                                    <div class="public-team-mini-shield">
                                                        <?= escudoEstadisticaPublica($equipo['escudo'], $equipo['equipo']) ?>
                                                    </div>

                                                    <strong><?= h($equipo['equipo']) ?></strong>
                                                </div>
                                            </td>

                                            <td><?= h($equipo['partidos_jugados']) ?></td>
                                            <td><?= h($equipo['goles_favor']) ?></td>
                                            <td><?= h($equipo['goles_contra']) ?></td>
                                            <td><?= h($dg) ?></td>
                                            <td><span class="public-card-yellow"><?= h($equipo['amarillas']) ?></span></td>
                                            <td><span class="public-card-red"><?= h($equipo['rojas']) ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                    <?php endif; ?>
                </section>

            <?php endif; ?>

        </div>
    </section>

</main>

<?php include("includes/footer.php"); ?>