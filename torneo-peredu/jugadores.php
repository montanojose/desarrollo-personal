<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle = "Jugadores - Copa PER.EDU";
$pageDescription = "Jugadores participantes de la Copa PER.EDU";
$currentPage = "jugadores";

/* =====================================================
   FUNCIONES LOCALES
===================================================== */

function obtenerEquiposFiltroJugadores($idTorneo)
{
    if (!$idTorneo) {
        return [];
    }

    return dbAll("
        SELECT
            et.id_equipo_torneo,
            e.nombre,
            e.escudo
        FROM equipo_torneo et
        INNER JOIN equipos e
            ON et.id_equipo = e.id_equipo
        WHERE et.id_torneo = ?
          AND et.activo = 1
          AND e.activo = 1
        ORDER BY e.nombre ASC
    ", [$idTorneo]);
}

function obtenerJugadoresPublicos($idTorneo, $idEquipoFiltro = 0, $busqueda = '')
{
    if (!$idTorneo) {
        return [];
    }

    $sql = "
        SELECT
            jet.id_jugador_equipo_torneo,
            jet.numero_camiseta,

            j.id_jugador,
            j.nombre,
            j.apellido,

            et.id_equipo_torneo,
            e.nombre AS equipo,
            e.escudo,

            COALESCE(SUM(CASE WHEN p.estado = 'finalizado' THEN jp.presente ELSE 0 END), 0) AS partidos_jugados,
            COALESCE(SUM(CASE WHEN p.estado = 'finalizado' THEN jp.goles ELSE 0 END), 0) AS goles,
            COALESCE(SUM(CASE WHEN p.estado = 'finalizado' THEN jp.amarillas ELSE 0 END), 0) AS amarillas,
            COALESCE(SUM(CASE WHEN p.estado = 'finalizado' THEN jp.rojas ELSE 0 END), 0) AS rojas

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

        WHERE et.id_torneo = ?
          AND jet.activo = 1
          AND j.activo = 1
          AND et.activo = 1
          AND e.activo = 1
    ";

    $params = [$idTorneo, $idTorneo];

    if ($idEquipoFiltro > 0) {
        $sql .= " AND et.id_equipo_torneo = ? ";
        $params[] = $idEquipoFiltro;
    }

    if ($busqueda !== '') {
        $sql .= "
            AND (
                j.nombre LIKE ?
                OR j.apellido LIKE ?
                OR CONCAT(j.nombre, ' ', j.apellido) LIKE ?
                OR CONCAT(j.apellido, ' ', j.nombre) LIKE ?
                OR e.nombre LIKE ?
            )
        ";

        $like = '%' . $busqueda . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $sql .= "
        GROUP BY
            jet.id_jugador_equipo_torneo,
            jet.numero_camiseta,
            j.id_jugador,
            j.nombre,
            j.apellido,
            et.id_equipo_torneo,
            e.nombre,
            e.escudo

        ORDER BY
            e.nombre ASC,
            CASE WHEN jet.numero_camiseta IS NULL THEN 1 ELSE 0 END,
            jet.numero_camiseta ASC,
            j.apellido ASC,
            j.nombre ASC
    ";

    return dbAll($sql, $params);
}

function inicialesJugadorPublico($nombre, $apellido)
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

$busqueda = trim($_GET['buscar'] ?? '');
$idEquipoFiltro = isset($_GET['equipo']) ? (int)$_GET['equipo'] : 0;

$equipos = obtenerEquiposFiltroJugadores($idTorneoActual);
$jugadores = obtenerJugadoresPublicos($idTorneoActual, $idEquipoFiltro, $busqueda);

$totalGoles = 0;
$totalAmarillas = 0;
$totalRojas = 0;

foreach ($jugadores as $jugador) {
    $totalGoles += (int)$jugador['goles'];
    $totalAmarillas += (int)$jugador['amarillas'];
    $totalRojas += (int)$jugador['rojas'];
}

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
            <span class="section-tag">Planteles del torneo</span>

            <h1>Jugadores</h1>

            <?php if ($torneoActual): ?>
                <p>
                    Listado de jugadores activos de la
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
                    <p>Cuando el administrador cree un torneo, los jugadores aparecerán acá.</p>
                </div>

            <?php else: ?>

                <!-- RESUMEN -->
                <div class="players-summary-grid">

                    <article>
                        <strong><?= h(count($jugadores)) ?></strong>
                        <span>Jugadores</span>
                    </article>

                    <article>
                        <strong><?= h($totalGoles) ?></strong>
                        <span>Goles</span>
                    </article>

                    <article>
                        <strong><?= h($totalAmarillas) ?></strong>
                        <span>Amarillas</span>
                    </article>

                    <article>
                        <strong><?= h($totalRojas) ?></strong>
                        <span>Rojas</span>
                    </article>

                </div>

                <!-- FILTROS -->
                <form method="GET" class="public-filter-form">

                    <div class="public-form-group">
                        <label for="buscar">Buscar jugador</label>
                        <input
                            type="text"
                            id="buscar"
                            name="buscar"
                            placeholder="Nombre, apellido o equipo"
                            value="<?= h($busqueda) ?>"
                        >
                    </div>

                    <div class="public-form-group">
                        <label for="equipo">Equipo</label>
                        <select id="equipo" name="equipo">
                            <option value="0">Todos los equipos</option>

                            <?php foreach ($equipos as $equipo): ?>
                                <option
                                    value="<?= h($equipo['id_equipo_torneo']) ?>"
                                    <?= (int)$idEquipoFiltro === (int)$equipo['id_equipo_torneo'] ? 'selected' : '' ?>
                                >
                                    <?= h($equipo['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="public-filter-actions">
                        <button type="submit" class="btn-main">Filtrar</button>
                        <a href="<?= BASE_URL ?>/jugadores.php" class="btn-secondary">Limpiar</a>
                    </div>

                </form>

                <?php if (empty($jugadores)): ?>

                    <div class="public-empty">
                        <h2>No hay jugadores para mostrar</h2>
                        <p>No se encontraron jugadores con los filtros aplicados.</p>
                    </div>

                <?php else: ?>

                    <!-- TABLA JUGADORES -->
                    <div class="public-table-wrapper">
                        <table class="public-table players-public-table">
                            <thead>
                                <tr>
                                    <th>Jugador</th>
                                    <th>Equipo</th>
                                    <th>N°</th>
                                    <th>PJ</th>
                                    <th>Goles</th>
                                    <th>Amarillas</th>
                                    <th>Rojas</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php foreach ($jugadores as $jugador): ?>
                                    <tr>
                                        <td>
                                            <div class="public-player-cell">
                                                <div class="public-player-avatar">
                                                    <?= h(inicialesJugadorPublico($jugador['nombre'], $jugador['apellido'])) ?>
                                                </div>

                                                <div>
                                                    <strong>
                                                        <?= h($jugador['apellido']) ?>, <?= h($jugador['nombre']) ?>
                                                    </strong>
                                                    <span>Jugador del torneo</span>
                                                </div>
                                            </div>
                                        </td>

                                        <td>
                                            <div class="public-team-cell">
                                                <div class="public-team-mini-shield">
                                                    <?php if (!empty($jugador['escudo'])): ?>
                                                        <img src="<?= h(asset($jugador['escudo'])) ?>" alt="Escudo <?= h($jugador['equipo']) ?>">
                                                    <?php else: ?>
                                                        <span>👕</span>
                                                    <?php endif; ?>
                                                </div>

                                                <strong><?= h($jugador['equipo']) ?></strong>
                                            </div>
                                        </td>

                                        <td>
                                            <?= h($jugador['numero_camiseta'] ?: '-') ?>
                                        </td>

                                        <td>
                                            <?= h($jugador['partidos_jugados']) ?>
                                        </td>

                                        <td>
                                            <strong class="public-stat-goal"><?= h($jugador['goles']) ?></strong>
                                        </td>

                                        <td>
                                            <span class="public-card-yellow"><?= h($jugador['amarillas']) ?></span>
                                        </td>

                                        <td>
                                            <span class="public-card-red"><?= h($jugador['rojas']) ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                <?php endif; ?>

            <?php endif; ?>

        </div>
    </section>

</main>

<?php include("includes/footer.php"); ?>