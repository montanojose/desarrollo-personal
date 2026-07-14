<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle = "Fixture - Copa PER.EDU";
$pageDescription = "Fixture oficial de la Copa PER.EDU";
$currentPage = "fixture";

/* =====================================================
   FUNCIONES LOCALES
===================================================== */

function obtenerFechasFixturePublico($idTorneo)
{
    if (!$idTorneo) {
        return [];
    }

    return dbAll("
        SELECT
            fe.id_fecha,
            fe.numero_fecha,
            fe.nombre,
            fe.fecha_programada,
            f.nombre AS fase_nombre,
            f.tipo AS fase_tipo,

            (
                SELECT COUNT(*)
                FROM partidos p
                WHERE p.id_fecha = fe.id_fecha
            ) AS total_partidos,

            (
                SELECT COUNT(*)
                FROM partidos p
                WHERE p.id_fecha = fe.id_fecha
                  AND p.estado = 'finalizado'
            ) AS partidos_finalizados

        FROM fechas fe
        INNER JOIN fases f
            ON fe.id_fase = f.id_fase

        WHERE fe.id_torneo = ?

        ORDER BY fe.numero_fecha ASC, fe.id_fecha ASC
    ", [$idTorneo]);
}

function obtenerUltimaFechaCargadaFixture($idTorneo)
{
    if (!$idTorneo) {
        return null;
    }

    $fechaConPartidos = dbOne("
        SELECT fe.id_fecha
        FROM fechas fe
        INNER JOIN partidos p
            ON fe.id_fecha = p.id_fecha
        WHERE fe.id_torneo = ?
        GROUP BY fe.id_fecha, fe.numero_fecha
        ORDER BY fe.numero_fecha DESC, fe.id_fecha DESC
        LIMIT 1
    ", [$idTorneo]);

    if ($fechaConPartidos) {
        return (int)$fechaConPartidos['id_fecha'];
    }

    $ultimaFecha = dbOne("
        SELECT id_fecha
        FROM fechas
        WHERE id_torneo = ?
        ORDER BY numero_fecha DESC, id_fecha DESC
        LIMIT 1
    ", [$idTorneo]);

    return $ultimaFecha ? (int)$ultimaFecha['id_fecha'] : null;
}

function fechaPerteneceATorneoFixture($idFecha, $idTorneo)
{
    if (!$idFecha || !$idTorneo) {
        return false;
    }

    $resultado = dbOne("
        SELECT COUNT(*) AS total
        FROM fechas
        WHERE id_fecha = ?
          AND id_torneo = ?
    ", [$idFecha, $idTorneo]);

    return (int)$resultado['total'] > 0;
}

function obtenerPartidosFixturePublico($idTorneo, $idFechaFiltro = 0)
{
    if (!$idTorneo) {
        return [];
    }

    $sql = "
        SELECT
            p.*,

            f.nombre AS fase_nombre,
            f.tipo AS fase_tipo,

            fe.numero_fecha,
            fe.nombre AS fecha_nombre,
            fe.fecha_programada,

            el.nombre AS equipo_local,
            el.escudo AS escudo_local,

            ev.nombre AS equipo_visitante,
            ev.escudo AS escudo_visitante,

            pp.penales_local,
            pp.penales_visitante,
            pp.id_equipo_ganador

        FROM partidos p

        INNER JOIN fases f
            ON p.id_fase = f.id_fase

        INNER JOIN fechas fe
            ON p.id_fecha = fe.id_fecha

        INNER JOIN equipo_torneo etl
            ON p.id_equipo_local = etl.id_equipo_torneo
        INNER JOIN equipos el
            ON etl.id_equipo = el.id_equipo

        INNER JOIN equipo_torneo etv
            ON p.id_equipo_visitante = etv.id_equipo_torneo
        INNER JOIN equipos ev
            ON etv.id_equipo = ev.id_equipo

        LEFT JOIN penales_partido pp
            ON p.id_partido = pp.id_partido

        WHERE p.id_torneo = ?
    ";

    $params = [$idTorneo];

    if ($idFechaFiltro > 0) {
        $sql .= " AND p.id_fecha = ? ";
        $params[] = $idFechaFiltro;
    }

    $sql .= "
        ORDER BY
            fe.numero_fecha ASC,
            CASE WHEN p.fecha_hora IS NULL THEN 1 ELSE 0 END,
            p.fecha_hora ASC,
            p.id_partido ASC
    ";

    return dbAll($sql, $params);
}

function obtenerNombreFechaFixture($fecha)
{
    if (!empty($fecha['fecha_nombre'])) {
        return $fecha['fecha_nombre'];
    }

    if (!empty($fecha['nombre'])) {
        return $fecha['nombre'];
    }

    return 'Fecha ' . $fecha['numero_fecha'];
}

function escudoFixturePublico($escudo, $alt = 'Escudo')
{
    if (!empty($escudo)) {
        return '<img src="' . h(asset($escudo)) . '" alt="' . h($alt) . '">';
    }

    return '<span>👕</span>';
}

function textoTipoFasePublica($tipo)
{
    return $tipo === 'eliminatoria' ? 'Eliminatoria' : 'Fase regular';
}

function claseTipoFasePublica($tipo)
{
    return $tipo === 'eliminatoria' ? 'fixture-phase-knockout' : 'fixture-phase-regular';
}

/* =====================================================
   DATOS
===================================================== */

$torneoActual = obtenerTorneoActual();
$idTorneoActual = $torneoActual['id_torneo'] ?? null;

$fechas = obtenerFechasFixturePublico($idTorneoActual);

$fechaDefault = obtenerUltimaFechaCargadaFixture($idTorneoActual);

$fechaFiltro = $_GET['fecha'] ?? '';

if ($fechaFiltro === 'todas') {
    $idFechaFiltro = 0;
} elseif ($fechaFiltro !== '') {
    $idFechaFiltro = (int)$fechaFiltro;

    if (!fechaPerteneceATorneoFixture($idFechaFiltro, $idTorneoActual)) {
        $idFechaFiltro = $fechaDefault ?? 0;
    }
} else {
    $idFechaFiltro = $fechaDefault ?? 0;
}

$partidos = obtenerPartidosFixturePublico($idTorneoActual, $idFechaFiltro);

$partidosPorFecha = [];

foreach ($partidos as $partido) {
    $partidosPorFecha[$partido['id_fecha']][] = $partido;
}

$totalPartidos = count($partidos);
$totalFinalizados = 0;
$totalPendientes = 0;

foreach ($partidos as $partido) {
    if ($partido['estado'] === 'finalizado') {
        $totalFinalizados++;
    } else {
        $totalPendientes++;
    }
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
            <span class="section-tag">Calendario oficial</span>

            <h1>Fixture</h1>

            <?php if ($torneoActual): ?>
                <p>
                    Fechas, partidos, resultados y próximos encuentros de la
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
                    <p>Cuando el administrador cree un torneo, el fixture aparecerá acá.</p>
                </div>

            <?php elseif (empty($fechas)): ?>

                <div class="public-empty">
                    <h2>No hay fechas cargadas</h2>
                    <p>Todavía no se cargaron fechas para este torneo.</p>
                </div>

            <?php else: ?>

                <!-- RESUMEN -->
                <div class="fixture-summary-grid">

                    <article>
                        <strong><?= h($totalPartidos) ?></strong>
                        <span>Partidos mostrados</span>
                    </article>

                    <article>
                        <strong><?= h($totalFinalizados) ?></strong>
                        <span>Finalizados</span>
                    </article>

                    <article>
                        <strong><?= h($totalPendientes) ?></strong>
                        <span>Pendientes</span>
                    </article>

                    <article>
                        <strong><?= h(count($fechas)) ?></strong>
                        <span>Fechas cargadas</span>
                    </article>

                </div>

                <!-- FILTRO -->
                <form method="GET" class="fixture-filter-form">
                    <div class="public-form-group">
                        <label for="fecha">Seleccionar fecha</label>

                        <select id="fecha" name="fecha" onchange="this.form.submit()">
                            <option value="todas" <?= $idFechaFiltro === 0 ? 'selected' : '' ?>>
                                Todas las fechas
                            </option>

                            <?php foreach ($fechas as $fecha): ?>
                                <?php
                                $nombreFecha = $fecha['nombre'] ?: 'Fecha ' . $fecha['numero_fecha'];
                                ?>
                                <option
                                    value="<?= h($fecha['id_fecha']) ?>"
                                    <?= (int)$idFechaFiltro === (int)$fecha['id_fecha'] ? 'selected' : '' ?>
                                >
                                    #<?= h($fecha['numero_fecha']) ?>
                                    · <?= h($nombreFecha) ?>
                                    · <?= h($fecha['fase_nombre']) ?>
                                    · <?= h($fecha['total_partidos']) ?> partidos
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <noscript>
                        <button type="submit" class="btn-main">Ver fecha</button>
                    </noscript>
                </form>

                <?php if (empty($partidos)): ?>

                    <div class="public-empty">
                        <h2>No hay partidos para mostrar</h2>
                        <p>La fecha seleccionada todavía no tiene partidos cargados.</p>
                    </div>

                <?php else: ?>

                    <?php foreach ($partidosPorFecha as $idFecha => $listaPartidos): ?>
                        <?php
                        $primerPartido = $listaPartidos[0];
                        $nombreFechaGrupo = obtenerNombreFechaFixture($primerPartido);
                        ?>

                        <section class="fixture-date-block">

                            <div class="fixture-date-header">
                                <div>
                                    <span class="<?= h(claseTipoFasePublica($primerPartido['fase_tipo'])) ?>">
                                        <?= h(textoTipoFasePublica($primerPartido['fase_tipo'])) ?>
                                    </span>

                                    <h2>
                                        #<?= h($primerPartido['numero_fecha']) ?>
                                        · <?= h($nombreFechaGrupo) ?>
                                    </h2>

                                    <p>
                                        <?= h($primerPartido['fase_nombre']) ?>
                                        <?php if (!empty($primerPartido['fecha_programada'])): ?>
                                            · Programada: <?= h(formatearFecha($primerPartido['fecha_programada'])) ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>

                            <div class="fixture-matches-grid">

                                <?php foreach ($listaPartidos as $partido): ?>
                                    <article class="fixture-match-card">

                                        <div class="fixture-match-top">
                                            <span class="status-badge <?= h(claseEstadoPartido($partido['estado'])) ?>">
                                                <?= h(textoEstadoPartido($partido['estado'])) ?>
                                            </span>

                                            <small>
                                                <?= h(formatearFechaHora($partido['fecha_hora'])) ?>
                                                <?php if (!empty($partido['cancha'])): ?>
                                                    · <?= h($partido['cancha']) ?>
                                                <?php endif; ?>
                                            </small>
                                        </div>

                                        <div class="fixture-teams">

                                            <div class="fixture-team">
                                                <div class="fixture-team-shield">
                                                    <?= escudoFixturePublico($partido['escudo_local'], $partido['equipo_local']) ?>
                                                </div>

                                                <strong><?= h($partido['equipo_local']) ?></strong>
                                            </div>

                                            <div class="fixture-score">
                                                <?= h(marcadorPartido($partido)) ?>
                                            </div>

                                            <div class="fixture-team">
                                                <div class="fixture-team-shield">
                                                    <?= escudoFixturePublico($partido['escudo_visitante'], $partido['equipo_visitante']) ?>
                                                </div>

                                                <strong><?= h($partido['equipo_visitante']) ?></strong>
                                            </div>

                                        </div>

                                        <?php if ($partido['fase_tipo'] === 'eliminatoria' && $partido['estado'] === 'finalizado' && $partido['penales_local'] !== null): ?>
                                            <div class="fixture-penalties-note">
                                                Definido por penales:
                                                <?= h($partido['penales_local']) ?>
                                                -
                                                <?= h($partido['penales_visitante']) ?>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($partido['observaciones'])): ?>
                                            <div class="fixture-observation">
                                                <?= h($partido['observaciones']) ?>
                                            </div>
                                        <?php endif; ?>

                                    </article>
                                <?php endforeach; ?>

                            </div>

                        </section>

                    <?php endforeach; ?>

                <?php endif; ?>

            <?php endif; ?>

        </div>
    </section>

</main>

<?php include("includes/footer.php"); ?>