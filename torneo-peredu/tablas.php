<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle = "Tabla de posiciones - Copa PER.EDU";
$pageDescription = "Tabla de posiciones oficial de la Copa PER.EDU";
$currentPage = "tablas";

/* =====================================================
   FUNCIONES LOCALES
===================================================== */

function obtenerTablaPublica($idTorneo)
{
    if (!$idTorneo) {
        return [];
    }

    return dbAll("
        SELECT
            et.id_equipo_torneo,
            e.nombre AS equipo,
            e.escuela,
            e.escudo,

            COALESCE(pos.partidos_jugados, 0) AS partidos_jugados,
            COALESCE(pos.ganados, 0) AS ganados,
            COALESCE(pos.empatados, 0) AS empatados,
            COALESCE(pos.perdidos, 0) AS perdidos,
            COALESCE(pos.goles_favor, 0) AS goles_favor,
            COALESCE(pos.goles_contra, 0) AS goles_contra,
            COALESCE(pos.diferencia_goles, 0) AS diferencia_goles,
            COALESCE(pos.puntos, 0) AS puntos,

            COALESCE(disciplina.amarillas, 0) AS amarillas,
            COALESCE(disciplina.rojas, 0) AS rojas

        FROM equipo_torneo et

        INNER JOIN equipos e
            ON et.id_equipo = e.id_equipo

        LEFT JOIN (
            SELECT
                datos.id_equipo_torneo,

                COUNT(*) AS partidos_jugados,
                SUM(datos.ganado) AS ganados,
                SUM(datos.empatado) AS empatados,
                SUM(datos.perdido) AS perdidos,
                SUM(datos.goles_favor) AS goles_favor,
                SUM(datos.goles_contra) AS goles_contra,
                SUM(datos.goles_favor - datos.goles_contra) AS diferencia_goles,
                SUM(datos.puntos) AS puntos

            FROM (
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
                INNER JOIN fases f
                    ON p.id_fase = f.id_fase

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
                INNER JOIN fases f
                    ON p.id_fase = f.id_fase

                WHERE p.id_torneo = ?
                  AND f.tipo = 'regular'
                  AND p.estado = 'finalizado'
                  AND p.goles_local IS NOT NULL
                  AND p.goles_visitante IS NOT NULL
            ) AS datos

            GROUP BY datos.id_equipo_torneo
        ) AS pos
            ON pos.id_equipo_torneo = et.id_equipo_torneo

        LEFT JOIN (
            SELECT
                jet.id_equipo_torneo,
                SUM(jp.amarillas) AS amarillas,
                SUM(jp.rojas) AS rojas

            FROM jugador_partido jp

            INNER JOIN partidos p
                ON jp.id_partido = p.id_partido

            INNER JOIN fases f
                ON p.id_fase = f.id_fase

            INNER JOIN jugador_equipo_torneo jet
                ON jp.id_jugador_equipo_torneo = jet.id_jugador_equipo_torneo

            WHERE p.id_torneo = ?
              AND f.tipo = 'regular'
              AND p.estado = 'finalizado'

            GROUP BY jet.id_equipo_torneo
        ) AS disciplina
            ON disciplina.id_equipo_torneo = et.id_equipo_torneo

        WHERE et.id_torneo = ?
          AND et.activo = 1
          AND e.activo = 1

        ORDER BY
            puntos DESC,
            diferencia_goles DESC,
            goles_favor DESC,
            ganados DESC,
            rojas ASC,
            amarillas ASC,
            equipo ASC
    ", [$idTorneo, $idTorneo, $idTorneo, $idTorneo]);
}

function obtenerResumenTablaPublica($idTorneo)
{
    if (!$idTorneo) {
        return [
            'equipos' => 0,
            'partidos_regular' => 0,
            'goles_regular' => 0,
            'fechas_regular' => 0,
        ];
    }

    $equipos = dbOne("
        SELECT COUNT(*) AS total
        FROM equipo_torneo et
        INNER JOIN equipos e
            ON et.id_equipo = e.id_equipo
        WHERE et.id_torneo = ?
          AND et.activo = 1
          AND e.activo = 1
    ", [$idTorneo]);

    $partidosRegular = dbOne("
        SELECT COUNT(*) AS total
        FROM partidos p
        INNER JOIN fases f
            ON p.id_fase = f.id_fase
        WHERE p.id_torneo = ?
          AND f.tipo = 'regular'
          AND p.estado = 'finalizado'
    ", [$idTorneo]);

    $golesRegular = dbOne("
        SELECT COALESCE(SUM(p.goles_local + p.goles_visitante), 0) AS total
        FROM partidos p
        INNER JOIN fases f
            ON p.id_fase = f.id_fase
        WHERE p.id_torneo = ?
          AND f.tipo = 'regular'
          AND p.estado = 'finalizado'
          AND p.goles_local IS NOT NULL
          AND p.goles_visitante IS NOT NULL
    ", [$idTorneo]);

    $fechasRegular = dbOne("
        SELECT COUNT(*) AS total
        FROM fechas fe
        INNER JOIN fases f
            ON fe.id_fase = f.id_fase
        WHERE fe.id_torneo = ?
          AND f.tipo = 'regular'
    ", [$idTorneo]);

    return [
        'equipos' => (int)$equipos['total'],
        'partidos_regular' => (int)$partidosRegular['total'],
        'goles_regular' => (int)$golesRegular['total'],
        'fechas_regular' => (int)$fechasRegular['total'],
    ];
}

function escudoTablaPublica($escudo, $alt = 'Escudo')
{
    if (!empty($escudo)) {
        return '<img src="' . h(asset($escudo)) . '" alt="' . h($alt) . '">';
    }

    return '<span>👕</span>';
}

function clasePosicionTabla($posicion)
{
    if ($posicion === 1) {
        return 'position-first';
    }

    if ($posicion === 2) {
        return 'position-second';
    }

    if ($posicion === 3) {
        return 'position-third';
    }

    if ($posicion <= 4) {
        return 'position-classified';
    }

    return '';
}

/* =====================================================
   DATOS
===================================================== */

$torneoActual = obtenerTorneoActual();
$idTorneoActual = $torneoActual['id_torneo'] ?? null;

$tabla = obtenerTablaPublica($idTorneoActual);
$resumen = obtenerResumenTablaPublica($idTorneoActual);

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
            <span class="section-tag">Clasificación general</span>

            <h1>Tabla de posiciones</h1>

            <?php if ($torneoActual): ?>
                <p>
                    Posiciones oficiales de la fase regular de la
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
                    <p>Cuando el administrador cree un torneo, la tabla aparecerá acá.</p>
                </div>

            <?php else: ?>

                <!-- RESUMEN -->
                <div class="table-summary-grid">

                    <article>
                        <strong><?= h($resumen['equipos']) ?></strong>
                        <span>Equipos</span>
                    </article>

                    <article>
                        <strong><?= h($resumen['partidos_regular']) ?></strong>
                        <span>Partidos jugados</span>
                    </article>

                    <article>
                        <strong><?= h($resumen['goles_regular']) ?></strong>
                        <span>Goles</span>
                    </article>

                    <article>
                        <strong><?= h($resumen['fechas_regular']) ?></strong>
                        <span>Fechas regulares</span>
                    </article>

                </div>

                <?php if (empty($tabla)): ?>

                    <div class="public-empty">
                        <h2>No hay equipos cargados</h2>
                        <p>Todavía no hay equipos activos para mostrar en la tabla.</p>
                    </div>

                <?php else: ?>

                    <!-- TABLA -->
                    <div class="public-table-wrapper">
                        <table class="public-table standings-table">
                            <thead>
                                <tr>
                                    <th>Pos</th>
                                    <th>Equipo</th>
                                    <th>Pts</th>
                                    <th>PJ</th>
                                    <th>PG</th>
                                    <th>PE</th>
                                    <th>PP</th>
                                    <th>GF</th>
                                    <th>GC</th>
                                    <th>DG</th>
                                    <th>🟨</th>
                                    <th>🟥</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php foreach ($tabla as $index => $equipo): ?>
                                    <?php
                                    $posicion = $index + 1;
                                    $clasePosicion = clasePosicionTabla($posicion);
                                    ?>

                                    <tr class="<?= h($clasePosicion) ?>">
                                        <td>
                                            <span class="position-badge">
                                                <?= h($posicion) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <div class="standings-team-cell">
                                                <div class="public-team-mini-shield">
                                                    <?= escudoTablaPublica($equipo['escudo'], $equipo['equipo']) ?>
                                                </div>

                                                <div>
                                                    <strong><?= h($equipo['equipo']) ?></strong>

                                                    <?php if (!empty($equipo['escuela'])): ?>
                                                        <span><?= h($equipo['escuela']) ?></span>
                                                    <?php else: ?>
                                                        <span>Equipo participante</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>

                                        <td>
                                            <strong class="standings-points">
                                                <?= h($equipo['puntos']) ?>
                                            </strong>
                                        </td>

                                        <td><?= h($equipo['partidos_jugados']) ?></td>
                                        <td><?= h($equipo['ganados']) ?></td>
                                        <td><?= h($equipo['empatados']) ?></td>
                                        <td><?= h($equipo['perdidos']) ?></td>
                                        <td><?= h($equipo['goles_favor']) ?></td>
                                        <td><?= h($equipo['goles_contra']) ?></td>

                                        <td>
                                            <strong class="<?= (int)$equipo['diferencia_goles'] >= 0 ? 'dg-positive' : 'dg-negative' ?>">
                                                <?= h($equipo['diferencia_goles']) ?>
                                            </strong>
                                        </td>

                                        <td>
                                            <span class="public-card-yellow">
                                                <?= h($equipo['amarillas']) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <span class="public-card-red">
                                                <?= h($equipo['rojas']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- CRITERIOS -->
                    <section class="tie-rules-box">
                        <div>
                            <span>Reglamento</span>
                            <h2>Criterios de desempate</h2>
                            <p>
                                Si dos o más equipos terminan igualados en puntos, la tabla se ordena
                                aplicando estos criterios en el siguiente orden.
                            </p>
                        </div>

                        <ol>
                            <li>Puntos obtenidos.</li>
                            <li>Diferencia de goles.</li>
                            <li>Goles a favor.</li>
                            <li>Partidos ganados.</li>
                            <li>Menor cantidad de tarjetas rojas.</li>
                            <li>Menor cantidad de tarjetas amarillas.</li>
                            <li>Orden alfabético del equipo.</li>
                        </ol>
                    </section>

                    <!-- REFERENCIAS -->
                    <section class="standings-legend">
                        <div>
                            <span class="legend-color legend-green"></span>
                            <p>Primeros puestos de referencia para clasificación.</p>
                        </div>

                        <div>
                            <strong>Pts</strong>
                            <p>Puntos</p>
                        </div>

                        <div>
                            <strong>PJ</strong>
                            <p>Partidos jugados</p>
                        </div>

                        <div>
                            <strong>PG</strong>
                            <p>Partidos ganados</p>
                        </div>

                        <div>
                            <strong>PE</strong>
                            <p>Partidos empatados</p>
                        </div>

                        <div>
                            <strong>PP</strong>
                            <p>Partidos perdidos</p>
                        </div>

                        <div>
                            <strong>GF</strong>
                            <p>Goles a favor</p>
                        </div>

                        <div>
                            <strong>GC</strong>
                            <p>Goles en contra</p>
                        </div>

                        <div>
                            <strong>DG</strong>
                            <p>Diferencia de goles</p>
                        </div>
                    </section>

                <?php endif; ?>

            <?php endif; ?>

        </div>
    </section>

</main>

<?php include("includes/footer.php"); ?>