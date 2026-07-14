<?php
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = "Resultados - Panel Admin";
$pageDescription = "Carga de resultados Copa PER.EDU";

/* =====================================================
   FLASH
===================================================== */

if (!function_exists('adminSetFlash')) {
    function adminSetFlash($tipo, $mensaje)
    {
        $_SESSION['flash'] = [
            'tipo' => $tipo,
            'mensaje' => $mensaje
        ];
    }
}

if (!function_exists('adminGetFlash')) {
    function adminGetFlash()
    {
        if (!isset($_SESSION['flash'])) {
            return null;
        }

        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);

        return $flash;
    }
}

/* =====================================================
   FUNCIONES LOCALES
===================================================== */

function obtenerPartidosParaResultados($idTorneo)
{
    if (!$idTorneo) {
        return [];
    }

    return dbAll("
        SELECT
            p.*,

            f.nombre AS fase_nombre,
            f.tipo AS fase_tipo,

            fe.numero_fecha,
            fe.nombre AS fecha_nombre,

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

        ORDER BY
            fe.numero_fecha ASC,
            CASE WHEN p.fecha_hora IS NULL THEN 1 ELSE 0 END,
            p.fecha_hora ASC,
            p.id_partido ASC
    ", [$idTorneo]);
}

function obtenerPartidoResultadoPorId($idPartido, $idTorneo)
{
    return dbOne("
        SELECT
            p.*,

            f.nombre AS fase_nombre,
            f.tipo AS fase_tipo,

            fe.numero_fecha,
            fe.nombre AS fecha_nombre,

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

        WHERE p.id_partido = ?
          AND p.id_torneo = ?

        LIMIT 1
    ", [$idPartido, $idTorneo]);
}

function obtenerJugadoresParaResultado($partido)
{
    if (!$partido) {
        return [];
    }

    return dbAll("
        SELECT
            jet.id_jugador_equipo_torneo,
            jet.id_jugador,
            jet.id_equipo_torneo,
            jet.numero_camiseta,
            jet.activo AS activo_plantel,

            j.nombre,
            j.apellido,
            j.dni,
            j.activo AS activo_jugador,

            e.nombre AS equipo,

            jp.id_jugador_partido,
            COALESCE(jp.presente, 0) AS presente,
            COALESCE(jp.titular, 0) AS titular,
            COALESCE(jp.goles, 0) AS goles,
            COALESCE(jp.amarillas, 0) AS amarillas,
            COALESCE(jp.rojas, 0) AS rojas,

            CASE
                WHEN jet.id_equipo_torneo = ? THEN 'local'
                ELSE 'visitante'
            END AS lado

        FROM jugador_equipo_torneo jet

        INNER JOIN jugadores j
            ON jet.id_jugador = j.id_jugador

        INNER JOIN equipo_torneo et
            ON jet.id_equipo_torneo = et.id_equipo_torneo

        INNER JOIN equipos e
            ON et.id_equipo = e.id_equipo

        LEFT JOIN jugador_partido jp
            ON jp.id_jugador_equipo_torneo = jet.id_jugador_equipo_torneo
           AND jp.id_partido = ?

        WHERE jet.id_equipo_torneo IN (?, ?)
          AND (
                jet.activo = 1
                OR jp.id_jugador_partido IS NOT NULL
          )

        ORDER BY
            CASE WHEN jet.id_equipo_torneo = ? THEN 1 ELSE 2 END,
            jet.activo DESC,
            j.apellido ASC,
            j.nombre ASC
    ", [
        $partido['id_equipo_local'],
        $partido['id_partido'],
        $partido['id_equipo_local'],
        $partido['id_equipo_visitante'],
        $partido['id_equipo_local']
    ]);
}

function normalizarEnteroResultado($valor, $campo, $min = 0, $max = 999)
{
    $valor = trim((string)$valor);

    if ($valor === '') {
        return 0;
    }

    if (!ctype_digit($valor)) {
        throw new Exception($campo . ' debe ser un número entero positivo.');
    }

    $valor = (int)$valor;

    if ($valor < $min || $valor > $max) {
        throw new Exception($campo . ' debe estar entre ' . $min . ' y ' . $max . '.');
    }

    return $valor;
}

function normalizarPenales($valor, $campo)
{
    $valor = trim((string)$valor);

    if ($valor === '') {
        throw new Exception($campo . ' es obligatorio cuando hay empate en una fase eliminatoria.');
    }

    return normalizarEnteroResultado($valor, $campo, 0, 50);
}

function obtenerValorArray($array, $id, $default = 0)
{
    return isset($array[$id]) ? $array[$id] : $default;
}

function partidoTieneResultadoCargado($partido)
{
    if (!$partido) {
        return false;
    }

    return (
        $partido['goles_local'] !== null ||
        $partido['goles_visitante'] !== null ||
        $partido['estado'] === 'finalizado'
    );
}

function nombreFechaResultado($partido)
{
    if (!empty($partido['fecha_nombre'])) {
        return $partido['fecha_nombre'];
    }

    return 'Fecha ' . $partido['numero_fecha'];
}

function inicialesResultado($nombre, $apellido)
{
    $inicialNombre = mb_substr(trim($nombre), 0, 1, 'UTF-8');
    $inicialApellido = mb_substr(trim($apellido), 0, 1, 'UTF-8');

    return mb_strtoupper($inicialNombre . $inicialApellido, 'UTF-8');
}

/* =====================================================
   TORNEO ACTUAL
===================================================== */

$torneoActual = obtenerTorneoActual();
$idTorneoActual = $torneoActual['id_torneo'] ?? null;

/* =====================================================
   PROCESAR FORMULARIOS
===================================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    try {
        if (!$idTorneoActual) {
            throw new Exception('Primero tenés que crear un torneo.');
        }

        if ($accion === 'guardar_resultado') {
            $idPartido = (int)($_POST['id_partido'] ?? 0);

            if ($idPartido <= 0) {
                throw new Exception('El partido seleccionado no es válido.');
            }

            $partido = obtenerPartidoResultadoPorId($idPartido, $idTorneoActual);

            if (!$partido) {
                throw new Exception('El partido no pertenece al torneo actual.');
            }

            if (in_array($partido['estado'], ['cancelado'])) {
                throw new Exception('No se puede cargar resultado a un partido cancelado.');
            }

            $golesLocal = normalizarEnteroResultado($_POST['goles_local'] ?? '', 'Goles del equipo local', 0, 99);
            $golesVisitante = normalizarEnteroResultado($_POST['goles_visitante'] ?? '', 'Goles del equipo visitante', 0, 99);

            $jugadores = obtenerJugadoresParaResultado($partido);

            $presentes = $_POST['presente'] ?? [];
            $golesJugador = $_POST['goles_jugador'] ?? [];
            $amarillasJugador = $_POST['amarillas_jugador'] ?? [];
            $rojasJugador = $_POST['rojas_jugador'] ?? [];

            $esEliminatoria = $partido['fase_tipo'] === 'eliminatoria';
            $hayEmpate = $golesLocal === $golesVisitante;

            $penalesLocal = null;
            $penalesVisitante = null;
            $idEquipoGanador = null;

            if ($esEliminatoria && $hayEmpate) {
                $penalesLocal = normalizarPenales($_POST['penales_local'] ?? '', 'Penales del equipo local');
                $penalesVisitante = normalizarPenales($_POST['penales_visitante'] ?? '', 'Penales del equipo visitante');

                if ($penalesLocal === $penalesVisitante) {
                    throw new Exception('En penales debe haber un ganador. No pueden quedar empatados.');
                }

                $idEquipoGanador = $penalesLocal > $penalesVisitante
                    ? $partido['id_equipo_local']
                    : $partido['id_equipo_visitante'];
            }

            db()->beginTransaction();

            dbQuery("
                UPDATE partidos
                SET goles_local = ?,
                    goles_visitante = ?,
                    estado = 'finalizado'
                WHERE id_partido = ?
                  AND id_torneo = ?
            ", [
                $golesLocal,
                $golesVisitante,
                $idPartido,
                $idTorneoActual
            ]);

            if ($esEliminatoria && $hayEmpate) {
                dbQuery("
                    INSERT INTO penales_partido (
                        id_partido,
                        penales_local,
                        penales_visitante,
                        id_equipo_ganador
                    )
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        penales_local = VALUES(penales_local),
                        penales_visitante = VALUES(penales_visitante),
                        id_equipo_ganador = VALUES(id_equipo_ganador)
                ", [
                    $idPartido,
                    $penalesLocal,
                    $penalesVisitante,
                    $idEquipoGanador
                ]);
            } else {
                dbQuery("
                    DELETE FROM penales_partido
                    WHERE id_partido = ?
                ", [$idPartido]);
            }

            foreach ($jugadores as $jugador) {
                $idRelacion = (int)$jugador['id_jugador_equipo_torneo'];

                $presente = isset($presentes[$idRelacion]) ? 1 : 0;

                $goles = normalizarEnteroResultado(
                    obtenerValorArray($golesJugador, $idRelacion, 0),
                    'Goles del jugador ' . $jugador['apellido'] . ', ' . $jugador['nombre'],
                    0,
                    99
                );

                $amarillas = normalizarEnteroResultado(
                    obtenerValorArray($amarillasJugador, $idRelacion, 0),
                    'Amarillas del jugador ' . $jugador['apellido'] . ', ' . $jugador['nombre'],
                    0,
                    2
                );

                $rojas = normalizarEnteroResultado(
                    obtenerValorArray($rojasJugador, $idRelacion, 0),
                    'Rojas del jugador ' . $jugador['apellido'] . ', ' . $jugador['nombre'],
                    0,
                    1
                );

                if ($presente === 0 && ($goles > 0 || $amarillas > 0 || $rojas > 0)) {
                    throw new Exception('El jugador ' . $jugador['apellido'] . ', ' . $jugador['nombre'] . ' tiene estadísticas cargadas pero no está marcado como presente.');
                }

                if ($presente === 0) {
                    $goles = 0;
                    $amarillas = 0;
                    $rojas = 0;
                }

                dbQuery("
                    INSERT INTO jugador_partido (
                        id_partido,
                        id_jugador_equipo_torneo,
                        presente,
                        titular,
                        goles,
                        amarillas,
                        rojas,
                        observaciones
                    )
                    VALUES (?, ?, ?, 0, ?, ?, ?, NULL)
                    ON DUPLICATE KEY UPDATE
                        presente = VALUES(presente),
                        titular = VALUES(titular),
                        goles = VALUES(goles),
                        amarillas = VALUES(amarillas),
                        rojas = VALUES(rojas)
                ", [
                    $idPartido,
                    $idRelacion,
                    $presente,
                    $goles,
                    $amarillas,
                    $rojas
                ]);
            }

            db()->commit();

            adminSetFlash('success', 'Resultado cargado correctamente.');
            header('Location: resultados.php?partido=' . $idPartido);
            exit;
        }

        if ($accion === 'borrar_resultado') {
            $idPartido = (int)($_POST['id_partido'] ?? 0);

            if ($idPartido <= 0) {
                throw new Exception('El partido seleccionado no es válido.');
            }

            $partido = obtenerPartidoResultadoPorId($idPartido, $idTorneoActual);

            if (!$partido) {
                throw new Exception('El partido no pertenece al torneo actual.');
            }

            db()->beginTransaction();

            dbQuery("
                UPDATE partidos
                SET goles_local = NULL,
                    goles_visitante = NULL,
                    estado = 'programado'
                WHERE id_partido = ?
                  AND id_torneo = ?
            ", [$idPartido, $idTorneoActual]);

            dbQuery("
                DELETE FROM penales_partido
                WHERE id_partido = ?
            ", [$idPartido]);

            dbQuery("
                UPDATE jugador_partido
                SET goles = 0,
                    amarillas = 0,
                    rojas = 0
                WHERE id_partido = ?
            ", [$idPartido]);

            db()->commit();

            adminSetFlash('success', 'Resultado borrado correctamente. La asistencia quedó conservada.');
            header('Location: resultados.php?partido=' . $idPartido);
            exit;
        }

    } catch (Exception $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }

        adminSetFlash('error', $e->getMessage());

        $redirectPartido = isset($_POST['id_partido']) ? (int)$_POST['id_partido'] : 0;

        if ($redirectPartido > 0) {
            header('Location: resultados.php?partido=' . $redirectPartido);
        } else {
            header('Location: resultados.php');
        }

        exit;
    }
}

/* =====================================================
   DATOS PARA MOSTRAR
===================================================== */

$flash = adminGetFlash();

$partidos = obtenerPartidosParaResultados($idTorneoActual);

$idPartidoSeleccionado = isset($_GET['partido']) ? (int)$_GET['partido'] : 0;

if ($idPartidoSeleccionado <= 0 && !empty($partidos)) {
    $idPartidoSeleccionado = (int)$partidos[0]['id_partido'];
}

$partidoSeleccionado = null;

if ($idPartidoSeleccionado > 0 && $idTorneoActual) {
    $partidoSeleccionado = obtenerPartidoResultadoPorId($idPartidoSeleccionado, $idTorneoActual);
}

$jugadoresPartido = $partidoSeleccionado
    ? obtenerJugadoresParaResultado($partidoSeleccionado)
    : [];

$jugadoresLocal = [];
$jugadoresVisitante = [];

foreach ($jugadoresPartido as $jugador) {
    if ($jugador['lado'] === 'local') {
        $jugadoresLocal[] = $jugador;
    } else {
        $jugadoresVisitante[] = $jugador;
    }
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
    <?php include __DIR__ . '/../includes/head.php'; ?>
    <link rel="stylesheet" href="<?= asset('assets/css/admin.css') ?>">
</head>
<body class="admin-body">

<div class="admin-layout">

    <!-- SIDEBAR -->
    <aside class="admin-sidebar">

        <a href="<?= BASE_URL ?>/admin/index.php" class="admin-brand">
            <div class="admin-brand-logo">
                <img src="<?= asset('assets/img/logo.png') ?>" alt="Logo Copa PER.EDU">
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
            <a class="active" href="<?= BASE_URL ?>/admin/resultados.php">✍ Resultados</a>
            <a href="<?= BASE_URL ?>/admin/estadisticas.php">📊 Estadísticas</a>
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
                <span class="admin-eyebrow">Carga deportiva</span>
                <h1>Resultados</h1>

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
                <a href="<?= BASE_URL ?>/admin/partidos.php" class="admin-btn admin-btn-primary">
                    Ir a partidos
                </a>

                <a href="<?= BASE_URL ?>/admin/index.php" class="admin-btn admin-btn-secondary">
                    Volver al panel
                </a>
            </div>
        </header>

        <?php if ($flash): ?>
            <div class="admin-alert admin-alert-<?= h($flash['tipo']) ?>">
                <?= h($flash['mensaje']) ?>
            </div>
        <?php endif; ?>

        <?php if (!$torneoActual): ?>

            <section class="admin-empty-main">
                <h2>No hay torneo creado</h2>
                <p>Para cargar resultados primero tenés que crear un torneo.</p>
                <a href="<?= BASE_URL ?>/admin/torneos.php" class="admin-btn admin-btn-primary">
                    Crear torneo
                </a>
            </section>

        <?php elseif (empty($partidos)): ?>

            <section class="admin-empty-main">
                <h2>No hay partidos cargados</h2>
                <p>Para cargar resultados primero tenés que crear partidos.</p>
                <a href="<?= BASE_URL ?>/admin/partidos.php" class="admin-btn admin-btn-primary">
                    Crear partidos
                </a>
            </section>

        <?php else: ?>

            <!-- RESUMEN -->
            <section class="admin-stats-grid admin-stats-grid-compact">

                <article class="admin-stat-card">
                    <span>Total partidos</span>
                    <strong><?= h($totalPartidos) ?></strong>
                    <p>Cargados en el torneo</p>
                </article>

                <article class="admin-stat-card">
                    <span>Finalizados</span>
                    <strong><?= h($totalFinalizados) ?></strong>
                    <p>Con resultado cargado</p>
                </article>

                <article class="admin-stat-card warning">
                    <span>Pendientes</span>
                    <strong><?= h($totalPendientes) ?></strong>
                    <p>Sin resultado final</p>
                </article>

                <article class="admin-stat-card">
                    <span>Jugadores del partido</span>
                    <strong><?= h(count($jugadoresPartido)) ?></strong>
                    <p>Disponibles para asistencia</p>
                </article>

            </section>

            <!-- SELECTOR -->
            <section class="admin-section">
                <div class="admin-section-header">
                    <div>
                        <span>Partido seleccionado</span>
                        <h2>Elegir partido</h2>
                    </div>
                </div>

                <form method="GET" class="result-select-form">
                    <div class="form-group">
                        <label for="partido">Partido</label>
                        <select id="partido" name="partido" onchange="this.form.submit()">
                            <?php foreach ($partidos as $partido): ?>
                                <?php
                                $nombreFecha = nombreFechaResultado($partido);
                                ?>
                                <option
                                    value="<?= h($partido['id_partido']) ?>"
                                    <?= (int)$idPartidoSeleccionado === (int)$partido['id_partido'] ? 'selected' : '' ?>
                                >
                                    #<?= h($partido['numero_fecha']) ?>
                                    · <?= h($nombreFecha) ?>
                                    · <?= h($partido['equipo_local']) ?>
                                    vs
                                    <?= h($partido['equipo_visitante']) ?>
                                    · <?= h(textoEstadoPartido($partido['estado'])) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <noscript>
                        <button type="submit" class="admin-btn admin-btn-primary">Ver partido</button>
                    </noscript>
                </form>
            </section>

            <?php if (!$partidoSeleccionado): ?>

                <section class="admin-empty-main">
                    <h2>Partido no encontrado</h2>
                    <p>El partido seleccionado no existe en el torneo actual.</p>
                </section>

            <?php else: ?>

                <!-- FORMULARIO RESULTADO -->
                <form method="POST" class="admin-form result-main-form">

                    <input type="hidden" name="accion" value="guardar_resultado">
                    <input type="hidden" name="id_partido" value="<?= h($partidoSeleccionado['id_partido']) ?>">

                    <section class="result-match-card">

                        <div class="result-match-header">
                            <div>
                                <span>
                                    <?= h($partidoSeleccionado['fase_nombre']) ?>
                                    ·
                                    <?= h(nombreFechaResultado($partidoSeleccionado)) ?>
                                </span>

                                <h2>
                                    <?= h($partidoSeleccionado['equipo_local']) ?>
                                    <em>vs</em>
                                    <?= h($partidoSeleccionado['equipo_visitante']) ?>
                                </h2>

                                <p>
                                    <?= h(formatearFechaHora($partidoSeleccionado['fecha_hora'])) ?>
                                    <?php if (!empty($partidoSeleccionado['cancha'])): ?>
                                        · <?= h($partidoSeleccionado['cancha']) ?>
                                    <?php endif; ?>
                                </p>
                            </div>

                            <span class="status-badge <?= h(claseEstadoPartido($partidoSeleccionado['estado'])) ?>">
                                <?= h(textoEstadoPartido($partidoSeleccionado['estado'])) ?>
                            </span>
                        </div>

                        <div class="score-form-grid">

                            <div class="team-score-card">
                                <div class="team-shield-table">
                                    <?php if (!empty($partidoSeleccionado['escudo_local'])): ?>
                                        <img src="<?= h(asset($partidoSeleccionado['escudo_local'])) ?>" alt="Escudo local">
                                    <?php else: ?>
                                        <span>👕</span>
                                    <?php endif; ?>
                                </div>

                                <strong><?= h($partidoSeleccionado['equipo_local']) ?></strong>

                                <input
                                    type="number"
                                    name="goles_local"
                                    min="0"
                                    max="99"
                                    value="<?= h($partidoSeleccionado['goles_local'] ?? 0) ?>"
                                    required
                                >
                            </div>

                            <div class="score-center">-</div>

                            <div class="team-score-card">
                                <div class="team-shield-table">
                                    <?php if (!empty($partidoSeleccionado['escudo_visitante'])): ?>
                                        <img src="<?= h(asset($partidoSeleccionado['escudo_visitante'])) ?>" alt="Escudo visitante">
                                    <?php else: ?>
                                        <span>👕</span>
                                    <?php endif; ?>
                                </div>

                                <strong><?= h($partidoSeleccionado['equipo_visitante']) ?></strong>

                                <input
                                    type="number"
                                    name="goles_visitante"
                                    min="0"
                                    max="99"
                                    value="<?= h($partidoSeleccionado['goles_visitante'] ?? 0) ?>"
                                    required
                                >
                            </div>

                        </div>

                        <?php if ($partidoSeleccionado['fase_tipo'] === 'eliminatoria'): ?>
                            <div class="penalty-box">
                                <div>
                                    <span>Penales</span>
                                    <h3>Solo si hay empate en eliminatoria</h3>
                                    <p>
                                        Si el partido termina empatado, cargá los penales.
                                        El sistema define automáticamente el ganador por penales.
                                    </p>
                                </div>

                                <div class="penalty-inputs">
                                    <div class="form-group">
                                        <label><?= h($partidoSeleccionado['equipo_local']) ?></label>
                                        <input
                                            type="number"
                                            name="penales_local"
                                            min="0"
                                            max="50"
                                            value="<?= h($partidoSeleccionado['penales_local'] ?? '') ?>"
                                        >
                                    </div>

                                    <div class="form-group">
                                        <label><?= h($partidoSeleccionado['equipo_visitante']) ?></label>
                                        <input
                                            type="number"
                                            name="penales_visitante"
                                            min="0"
                                            max="50"
                                            value="<?= h($partidoSeleccionado['penales_visitante'] ?? '') ?>"
                                        >
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                    </section>

                    <?php if (empty($jugadoresPartido)): ?>

                        <section class="admin-empty-box">
                            Este partido no tiene jugadores disponibles para cargar asistencia.
                            Revisá que los equipos tengan plantel cargado.
                        </section>

                    <?php else: ?>

                        <section class="players-result-grid">

                            <!-- LOCAL -->
                            <article class="admin-panel">
                                <div class="admin-panel-header">
                                    <div>
                                        <span>Equipo local</span>
                                        <h2><?= h($partidoSeleccionado['equipo_local']) ?></h2>
                                    </div>
                                </div>

                                <?php if (empty($jugadoresLocal)): ?>

                                    <div class="admin-empty-box">
                                        Este equipo no tiene jugadores en el plantel.
                                    </div>

                                <?php else: ?>

                                    <div class="admin-table-wrapper">
                                        <table class="admin-table result-player-table">
                                            <thead>
                                                <tr>
                                                    <th>Presente</th>
                                                    <th>Jugador</th>
                                                    <th>N°</th>
                                                    <th>Goles</th>
                                                    <th>Amarillas</th>
                                                    <th>Rojas</th>
                                                </tr>
                                            </thead>

                                            <tbody>
                                                <?php foreach ($jugadoresLocal as $jugador): ?>
                                                    <tr>
                                                        <td>
                                                            <label class="check-cell">
                                                                <input
                                                                    type="checkbox"
                                                                    name="presente[<?= h($jugador['id_jugador_equipo_torneo']) ?>]"
                                                                    value="1"
                                                                    <?= (int)$jugador['presente'] === 1 ? 'checked' : '' ?>
                                                                >
                                                                <span></span>
                                                            </label>
                                                        </td>

                                                        <td>
                                                            <div class="player-cell">
                                                                <div class="player-avatar">
                                                                    <?= h(inicialesResultado($jugador['nombre'], $jugador['apellido'])) ?>
                                                                </div>

                                                                <div>
                                                                    <strong><?= h($jugador['apellido']) ?>, <?= h($jugador['nombre']) ?></strong>
                                                                    <small><?= h($jugador['dni'] ?: 'Sin DNI') ?></small>
                                                                </div>
                                                            </div>
                                                        </td>

                                                        <td><?= h($jugador['numero_camiseta'] ?: '-') ?></td>

                                                        <td>
                                                            <input class="stat-input" type="number" min="0" max="99"
                                                                name="goles_jugador[<?= h($jugador['id_jugador_equipo_torneo']) ?>]"
                                                                value="<?= h($jugador['goles']) ?>">
                                                        </td>

                                                        <td>
                                                            <input class="stat-input" type="number" min="0" max="2"
                                                                name="amarillas_jugador[<?= h($jugador['id_jugador_equipo_torneo']) ?>]"
                                                                value="<?= h($jugador['amarillas']) ?>">
                                                        </td>

                                                        <td>
                                                            <input class="stat-input" type="number" min="0" max="1"
                                                                name="rojas_jugador[<?= h($jugador['id_jugador_equipo_torneo']) ?>]"
                                                                value="<?= h($jugador['rojas']) ?>">
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>

                                <?php endif; ?>
                            </article>

                            <!-- VISITANTE -->
                            <article class="admin-panel">
                                <div class="admin-panel-header">
                                    <div>
                                        <span>Equipo visitante</span>
                                        <h2><?= h($partidoSeleccionado['equipo_visitante']) ?></h2>
                                    </div>
                                </div>

                                <?php if (empty($jugadoresVisitante)): ?>

                                    <div class="admin-empty-box">
                                        Este equipo no tiene jugadores en el plantel.
                                    </div>

                                <?php else: ?>

                                    <div class="admin-table-wrapper">
                                        <table class="admin-table result-player-table">
                                            <thead>
                                                <tr>
                                                    <th>Presente</th>
                                                    <th>Jugador</th>
                                                    <th>N°</th>
                                                    <th>Goles</th>
                                                    <th>Amarillas</th>
                                                    <th>Rojas</th>
                                                </tr>
                                            </thead>

                                            <tbody>
                                                <?php foreach ($jugadoresVisitante as $jugador): ?>
                                                    <tr>
                                                        <td>
                                                            <label class="check-cell">
                                                                <input
                                                                    type="checkbox"
                                                                    name="presente[<?= h($jugador['id_jugador_equipo_torneo']) ?>]"
                                                                    value="1"
                                                                    <?= (int)$jugador['presente'] === 1 ? 'checked' : '' ?>
                                                                >
                                                                <span></span>
                                                            </label>
                                                        </td>

                                                        <td>
                                                            <div class="player-cell">
                                                                <div class="player-avatar">
                                                                    <?= h(inicialesResultado($jugador['nombre'], $jugador['apellido'])) ?>
                                                                </div>

                                                                <div>
                                                                    <strong><?= h($jugador['apellido']) ?>, <?= h($jugador['nombre']) ?></strong>
                                                                    <small><?= h($jugador['dni'] ?: 'Sin DNI') ?></small>
                                                                </div>
                                                            </div>
                                                        </td>

                                                        <td><?= h($jugador['numero_camiseta'] ?: '-') ?></td>

                                                        <td>
                                                            <input class="stat-input" type="number" min="0" max="99"
                                                                name="goles_jugador[<?= h($jugador['id_jugador_equipo_torneo']) ?>]"
                                                                value="<?= h($jugador['goles']) ?>">
                                                        </td>

                                                        <td>
                                                            <input class="stat-input" type="number" min="0" max="2"
                                                                name="amarillas_jugador[<?= h($jugador['id_jugador_equipo_torneo']) ?>]"
                                                                value="<?= h($jugador['amarillas']) ?>">
                                                        </td>

                                                        <td>
                                                            <input class="stat-input" type="number" min="0" max="1"
                                                                name="rojas_jugador[<?= h($jugador['id_jugador_equipo_torneo']) ?>]"
                                                                value="<?= h($jugador['rojas']) ?>">
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>

                                <?php endif; ?>
                            </article>

                        </section>

                    <?php endif; ?>

                    <section class="result-actions">
                        <button type="submit" class="admin-btn admin-btn-primary">
                            Guardar resultado
                        </button>

                        <?php if (partidoTieneResultadoCargado($partidoSeleccionado)): ?>
                            <button
                                type="submit"
                                formaction=""
                                name="accion"
                                value="borrar_resultado"
                                class="admin-btn admin-btn-secondary"
                                onclick="return confirm('¿Seguro que querés borrar el resultado? Se conservará la asistencia, pero se limpiarán goles, tarjetas y penales.');"
                            >
                                Borrar resultado
                            </button>
                        <?php endif; ?>
                    </section>

                </form>

            <?php endif; ?>

        <?php endif; ?>

    </main>

</div>

<script src="<?= asset('assets/js/app.js') ?>"></script>
</body>
</html>