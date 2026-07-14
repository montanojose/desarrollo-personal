<?php
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = "Estadísticas - Panel Admin";
$pageDescription = "Estadísticas Copa PER.EDU";

/* =====================================================
   FUNCIONES LOCALES
===================================================== */

function obtenerResumenEstadisticas($idTorneo)
{
    if (!$idTorneo) {
        return [
            'equipos' => 0,
            'jugadores' => 0,
            'partidos' => 0,
            'finalizados' => 0,
            'goles' => 0,
            'promedio_goles' => 0,
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

    $totalFinalizados = (int)$finalizados['total'];
    $totalGoles = (int)$goles['total'];

    return [
        'equipos' => (int)$equipos['total'],
        'jugadores' => (int)$jugadores['total'],
        'partidos' => (int)$partidos['total'],
        'finalizados' => $totalFinalizados,
        'goles' => $totalGoles,
        'promedio_goles' => $totalFinalizados > 0 ? round($totalGoles / $totalFinalizados, 2) : 0,
        'amarillas' => (int)$tarjetas['amarillas'],
        'rojas' => (int)$tarjetas['rojas'],
    ];
}

function obtenerTablaPosicionesAdmin($idTorneo)
{
    if (!$idTorneo) {
        return [];
    }

    return dbAll("
        SELECT
            et.id_equipo_torneo,
            e.nombre AS equipo,
            e.escudo,

            COALESCE(COUNT(datos.id_equipo_torneo), 0) AS partidos_jugados,
            COALESCE(SUM(datos.ganado), 0) AS ganados,
            COALESCE(SUM(datos.empatado), 0) AS empatados,
            COALESCE(SUM(datos.perdido), 0) AS perdidos,
            COALESCE(SUM(datos.goles_favor), 0) AS goles_favor,
            COALESCE(SUM(datos.goles_contra), 0) AS goles_contra,
            COALESCE(SUM(datos.goles_favor - datos.goles_contra), 0) AS diferencia_goles,
            COALESCE(SUM(datos.puntos), 0) AS puntos

        FROM equipo_torneo et

        INNER JOIN equipos e
            ON et.id_equipo = e.id_equipo

        LEFT JOIN (
            SELECT
                p.id_equipo_local AS id_equipo_torneo,
                p.goles_local AS goles_favor,
                p.goles_visitante AS goles_contra,

                CASE WHEN p.goles_local > p.goles_visitante THEN 1 ELSE 0 END AS ganado,
                CASE WHEN p.goles_local = p.goles_visitante THEN 1 ELSE 0 END AS empatado,
                CASE WHEN p.goles_local < p.goles_visitante THEN 1 ELSE 0 END AS perdido,

                CASE
                    WHEN p.goles_local > p.goles_visitante THEN 3
                    WHEN p.goles_local = p.goles_visitante THEN 1
                    ELSE 0
                END AS puntos

            FROM partidos p
            INNER JOIN fases f ON p.id_fase = f.id_fase
            WHERE p.id_torneo = ?
              AND f.tipo = 'regular'
              AND p.estado = 'finalizado'
              AND p.goles_local IS NOT NULL
              AND p.goles_visitante IS NOT NULL

            UNION ALL

            SELECT
                p.id_equipo_visitante AS id_equipo_torneo,
                p.goles_visitante AS goles_favor,
                p.goles_local AS goles_contra,

                CASE WHEN p.goles_visitante > p.goles_local THEN 1 ELSE 0 END AS ganado,
                CASE WHEN p.goles_visitante = p.goles_local THEN 1 ELSE 0 END AS empatado,
                CASE WHEN p.goles_visitante < p.goles_local THEN 1 ELSE 0 END AS perdido,

                CASE
                    WHEN p.goles_visitante > p.goles_local THEN 3
                    WHEN p.goles_visitante = p.goles_local THEN 1
                    ELSE 0
                END AS puntos

            FROM partidos p
            INNER JOIN fases f ON p.id_fase = f.id_fase
            WHERE p.id_torneo = ?
              AND f.tipo = 'regular'
              AND p.estado = 'finalizado'
              AND p.goles_local IS NOT NULL
              AND p.goles_visitante IS NOT NULL

        ) AS datos
            ON datos.id_equipo_torneo = et.id_equipo_torneo

        WHERE et.id_torneo = ?
          AND et.activo = 1
          AND e.activo = 1

        GROUP BY
            et.id_equipo_torneo,
            e.nombre,
            e.escudo

        ORDER BY
            puntos DESC,
            diferencia_goles DESC,
            goles_favor DESC,
            equipo ASC
    ", [$idTorneo, $idTorneo, $idTorneo]);
}

function obtenerGoleadoresAdmin($idTorneo)
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
        GROUP BY j.id_jugador, jugador, e.nombre, e.escudo
        HAVING goles > 0
        ORDER BY goles DESC, jugador ASC
    ", [$idTorneo]);
}

function obtenerTarjetasAdmin($idTorneo)
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
            SUM(jp.amarillas) AS amarillas,
            SUM(jp.rojas) AS rojas,
            SUM(jp.amarillas + jp.rojas) AS total_tarjetas
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
        GROUP BY j.id_jugador, jugador, e.nombre, e.escudo
        HAVING total_tarjetas > 0
        ORDER BY rojas DESC, amarillas DESC, jugador ASC
    ", [$idTorneo]);
}

function obtenerAsistenciasAdmin($idTorneo)
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

            COUNT(jp.id_jugador_partido) AS partidos_cargados,
            COALESCE(SUM(jp.presente), 0) AS presentes,

            CASE
                WHEN COUNT(jp.id_jugador_partido) > 0
                THEN ROUND((SUM(jp.presente) / COUNT(jp.id_jugador_partido)) * 100, 1)
                ELSE 0
            END AS porcentaje_asistencia

        FROM jugador_equipo_torneo jet

        INNER JOIN jugadores j
            ON jet.id_jugador = j.id_jugador

        INNER JOIN equipo_torneo et
            ON jet.id_equipo_torneo = et.id_equipo_torneo

        INNER JOIN equipos e
            ON et.id_equipo = e.id_equipo

        LEFT JOIN jugador_partido jp
            ON jp.id_jugador_equipo_torneo = jet.id_jugador_equipo_torneo

        LEFT JOIN partidos p
            ON jp.id_partido = p.id_partido
           AND p.id_torneo = ?
           AND p.estado = 'finalizado'

        WHERE et.id_torneo = ?

        GROUP BY
            j.id_jugador,
            jugador,
            e.nombre,
            e.escudo

        HAVING partidos_cargados > 0

        ORDER BY porcentaje_asistencia DESC, presentes DESC, jugador ASC
    ", [$idTorneo, $idTorneo]);
}

function escudoMini($escudo, $alt = 'Escudo')
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

$resumen = obtenerResumenEstadisticas($idTorneoActual);
$tabla = obtenerTablaPosicionesAdmin($idTorneoActual);
$goleadores = obtenerGoleadoresAdmin($idTorneoActual);
$tarjetas = obtenerTarjetasAdmin($idTorneoActual);
$asistencias = obtenerAsistenciasAdmin($idTorneoActual);

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <?php include __DIR__ . '/../includes/head.php'; ?>
    <link rel="stylesheet" href="<?= asset('assets/css/admin.css') ?>">
</head>
<body class="admin-body">

<div class="admin-layout">

    <!-- SIDEBAR -->
    <aside class="admin-sidebar">

        <a href="<?= BASE_URL ?>/admin/index.php" class="admin-brand">
            <div class="admin-brand-logo">
            <img src="<?= asset('assets/img/logo.jpg') ?>" alt="Logo Copa PER.EDU">
            </div>
            <div>
                <strong>PER.EDU</strong>
                <span>Panel Admin</span>
            </div>
        </a>

        <nav class="admin-nav">
            <a href="<?= BASE_URL ?>/admin/index.php">🏠 Inicio</a>
            <a href="<?= BASE_URL ?>/admin/torneos.php">🏆 Torneos</a>
            <a href="<?= BASE_URL ?>/admin/equipos.php">👕 Equipos</a>
            <a href="<?= BASE_URL ?>/admin/jugadores.php">👤 Jugadores</a>
            <a href="<?= BASE_URL ?>/admin/planteles.php">📋 Planteles</a>
            <a href="<?= BASE_URL ?>/admin/fases.php">🧩 Fases</a>
            <a href="<?= BASE_URL ?>/admin/fechas.php">📅 Fechas</a>
            <a href="<?= BASE_URL ?>/admin/partidos.php">⚽ Partidos</a>
            <a href="<?= BASE_URL ?>/admin/resultados.php">✍ Resultados</a>
            <a class="active" href="<?= BASE_URL ?>/admin/estadisticas.php">📊 Estadísticas</a>
            <a href="<?= BASE_URL ?>/admin/sanciones.php">🟥 Sanciones</a>
        </nav>

        <div class="admin-sidebar-footer">
            <a href="<?= BASE_URL ?>/index.php">← Ver sitio público</a>
        </div>

    </aside>

    <!-- CONTENIDO -->
    <main class="admin-main">

        <header class="admin-topbar">
            <div>
                <span class="admin-eyebrow">Resumen deportivo</span>
                <h1>Estadísticas</h1>

                <?php if ($torneoActual): ?>
                    <p>
                        Torneo actual:
                        <strong><?= h($torneoActual['nombre']) ?></strong>
                        · Temporada <?= h($torneoActual['temporada']) ?>
                    </p>
                <?php else: ?>
                    <p>Primero tenés que crear un torneo.</p>
                <?php endif; ?>
            </div>

            <div class="admin-top-actions">
                <a href="<?= BASE_URL ?>/admin/resultados.php" class="admin-btn admin-btn-primary">
                    Cargar resultados
                </a>

                <a href="<?= BASE_URL ?>/admin/index.php" class="admin-btn admin-btn-secondary">
                    Volver al panel
                </a>
            </div>
        </header>

        <?php if (!$torneoActual): ?>

            <section class="admin-empty-main">
                <h2>No hay torneo creado</h2>
                <p>Para ver estadísticas primero tenés que crear un torneo y cargar partidos.</p>
                <a href="<?= BASE_URL ?>/admin/torneos.php" class="admin-btn admin-btn-primary">
                    Crear torneo
                </a>
            </section>

        <?php else: ?>

            <!-- RESUMEN -->
            <section class="admin-stats-grid estadisticas-grid">

                <article class="admin-stat-card">
                    <span>Equipos</span>
                    <strong><?= h($resumen['equipos']) ?></strong>
                    <p>Activos en el torneo</p>
                </article>

                <article class="admin-stat-card">
                    <span>Jugadores</span>
                    <strong><?= h($resumen['jugadores']) ?></strong>
                    <p>Activos en planteles</p>
                </article>

                <article class="admin-stat-card">
                    <span>Partidos</span>
                    <strong><?= h($resumen['partidos']) ?></strong>
                    <p>Total cargados</p>
                </article>

                <article class="admin-stat-card">
                    <span>Finalizados</span>
                    <strong><?= h($resumen['finalizados']) ?></strong>
                    <p>Con resultado</p>
                </article>

                <article class="admin-stat-card">
                    <span>Goles</span>
                    <strong><?= h($resumen['goles']) ?></strong>
                    <p>Total del torneo</p>
                </article>

                <article class="admin-stat-card">
                    <span>Promedio</span>
                    <strong><?= h($resumen['promedio_goles']) ?></strong>
                    <p>Goles por partido</p>
                </article>

                <article class="admin-stat-card warning">
                    <span>Amarillas</span>
                    <strong><?= h($resumen['amarillas']) ?></strong>
                    <p>Tarjetas amarillas</p>
                </article>

                <article class="admin-stat-card warning">
                    <span>Rojas</span>
                    <strong><?= h($resumen['rojas']) ?></strong>
                    <p>Tarjetas rojas</p>
                </article>

            </section>

            <!-- TABLA DE POSICIONES -->
            <section class="admin-section">
                <div class="admin-section-header">
                    <div>
                        <span>Fase regular</span>
                        <h2>Tabla de posiciones</h2>
                    </div>
                </div>

                <?php if (empty($tabla)): ?>

                    <div class="admin-empty-box">
                        Todavía no hay equipos o partidos finalizados para calcular la tabla.
                    </div>

                <?php else: ?>

                    <div class="admin-table-wrapper">
                        <table class="admin-table stats-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Equipo</th>
                                    <th>Pts</th>
                                    <th>PJ</th>
                                    <th>PG</th>
                                    <th>PE</th>
                                    <th>PP</th>
                                    <th>GF</th>
                                    <th>GC</th>
                                    <th>DG</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php foreach ($tabla as $index => $equipo): ?>
                                    <tr>
                                        <td>
                                            <strong><?= h($index + 1) ?></strong>
                                        </td>

                                        <td>
                                            <div class="stats-team-cell">
                                                <div class="team-shield-table small">
                                                    <?= escudoMini($equipo['escudo'], $equipo['equipo']) ?>
                                                </div>

                                                <strong><?= h($equipo['equipo']) ?></strong>
                                            </div>
                                        </td>

                                        <td><strong class="stats-points"><?= h($equipo['puntos']) ?></strong></td>
                                        <td><?= h($equipo['partidos_jugados']) ?></td>
                                        <td><?= h($equipo['ganados']) ?></td>
                                        <td><?= h($equipo['empatados']) ?></td>
                                        <td><?= h($equipo['perdidos']) ?></td>
                                        <td><?= h($equipo['goles_favor']) ?></td>
                                        <td><?= h($equipo['goles_contra']) ?></td>
                                        <td><?= h($equipo['diferencia_goles']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                <?php endif; ?>
            </section>

            <section class="admin-dashboard-grid">

                <!-- GOLEADORES -->
                <article class="admin-panel">
                    <div class="admin-panel-header">
                        <div>
                            <span>Jugadores</span>
                            <h2>Goleadores</h2>
                        </div>
                    </div>

                    <?php if (empty($goleadores)): ?>

                        <div class="admin-empty-box">
                            Todavía no hay goles cargados.
                        </div>

                    <?php else: ?>

                        <div class="admin-table-wrapper">
                            <table class="admin-table mini-stats-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Jugador</th>
                                        <th>Equipo</th>
                                        <th>Goles</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <?php foreach ($goleadores as $index => $jugador): ?>
                                        <tr>
                                            <td><?= h($index + 1) ?></td>

                                            <td>
                                                <strong><?= h($jugador['jugador']) ?></strong>
                                            </td>

                                            <td>
                                                <div class="stats-team-cell">
                                                    <div class="team-shield-table small">
                                                        <?= escudoMini($jugador['escudo'], $jugador['equipo']) ?>
                                                    </div>
                                                    <span><?= h($jugador['equipo']) ?></span>
                                                </div>
                                            </td>

                                            <td>
                                                <strong class="stats-points"><?= h($jugador['goles']) ?></strong>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                    <?php endif; ?>
                </article>

                <!-- TARJETAS -->
                <article class="admin-panel">
                    <div class="admin-panel-header">
                        <div>
                            <span>Disciplina</span>
                            <h2>Tarjetas</h2>
                        </div>
                    </div>

                    <?php if (empty($tarjetas)): ?>

                        <div class="admin-empty-box">
                            Todavía no hay tarjetas cargadas.
                        </div>

                    <?php else: ?>

                        <div class="admin-table-wrapper">
                            <table class="admin-table mini-stats-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Jugador</th>
                                        <th>Equipo</th>
                                        <th>🟨</th>
                                        <th>🟥</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <?php foreach ($tarjetas as $index => $jugador): ?>
                                        <tr>
                                            <td><?= h($index + 1) ?></td>

                                            <td>
                                                <strong><?= h($jugador['jugador']) ?></strong>
                                            </td>

                                            <td>
                                                <div class="stats-team-cell">
                                                    <div class="team-shield-table small">
                                                        <?= escudoMini($jugador['escudo'], $jugador['equipo']) ?>
                                                    </div>
                                                    <span><?= h($jugador['equipo']) ?></span>
                                                </div>
                                            </td>

                                            <td><?= h($jugador['amarillas']) ?></td>
                                            <td><?= h($jugador['rojas']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                    <?php endif; ?>
                </article>

            </section>

            <!-- ASISTENCIA -->
            <section class="admin-section">
                <div class="admin-section-header">
                    <div>
                        <span>Presencias</span>
                        <h2>Asistencia de jugadores</h2>
                    </div>
                </div>

                <?php if (empty($asistencias)): ?>

                    <div class="admin-empty-box">
                        Todavía no hay asistencia cargada en partidos finalizados.
                    </div>

                <?php else: ?>

                    <div class="admin-table-wrapper">
                        <table class="admin-table stats-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Jugador</th>
                                    <th>Equipo</th>
                                    <th>Presentes</th>
                                    <th>Partidos cargados</th>
                                    <th>Asistencia</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php foreach ($asistencias as $index => $jugador): ?>
                                    <tr>
                                        <td><?= h($index + 1) ?></td>

                                        <td>
                                            <strong><?= h($jugador['jugador']) ?></strong>
                                        </td>

                                        <td>
                                            <div class="stats-team-cell">
                                                <div class="team-shield-table small">
                                                    <?= escudoMini($jugador['escudo'], $jugador['equipo']) ?>
                                                </div>
                                                <span><?= h($jugador['equipo']) ?></span>
                                            </div>
                                        </td>

                                        <td><?= h($jugador['presentes']) ?></td>

                                        <td><?= h($jugador['partidos_cargados']) ?></td>

                                        <td>
                                            <div class="attendance-bar">
                                                <div style="width: <?= h($jugador['porcentaje_asistencia']) ?>%;"></div>
                                                <span><?= h($jugador['porcentaje_asistencia']) ?>%</span>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                <?php endif; ?>

            </section>

        <?php endif; ?>

    </main>

</div>

<script src="<?= asset('assets/js/app.js') ?>"></script>
</body>
</html>
