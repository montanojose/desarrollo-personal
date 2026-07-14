<?php
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = "Sanciones - Panel Admin";
$pageDescription = "Administración de sanciones Copa PER.EDU";

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

function obtenerJugadoresPlantelSanciones($idTorneo)
{
    if (!$idTorneo) {
        return [];
    }

    return dbAll("
        SELECT
            jet.id_jugador_equipo_torneo,
            jet.activo AS activo_plantel,
            j.id_jugador,
            j.nombre,
            j.apellido,
            j.dni,
            j.activo AS activo_jugador,
            e.nombre AS equipo,
            e.escudo
        FROM jugador_equipo_torneo jet
        INNER JOIN jugadores j
            ON jet.id_jugador = j.id_jugador
        INNER JOIN equipo_torneo et
            ON jet.id_equipo_torneo = et.id_equipo_torneo
        INNER JOIN equipos e
            ON et.id_equipo = e.id_equipo
        WHERE et.id_torneo = ?
        ORDER BY
            jet.activo DESC,
            e.nombre ASC,
            j.apellido ASC,
            j.nombre ASC
    ", [$idTorneo]);
}

function obtenerPartidosOrigenSanciones($idTorneo)
{
    if (!$idTorneo) {
        return [];
    }

    return dbAll("
        SELECT
            p.id_partido,
            p.fecha_hora,
            p.estado,
            fe.numero_fecha,
            fe.nombre AS fecha_nombre,
            f.nombre AS fase_nombre,
            el.nombre AS equipo_local,
            ev.nombre AS equipo_visitante,
            p.goles_local,
            p.goles_visitante
        FROM partidos p
        INNER JOIN fechas fe
            ON p.id_fecha = fe.id_fecha
        INNER JOIN fases f
            ON p.id_fase = f.id_fase
        INNER JOIN equipo_torneo etl
            ON p.id_equipo_local = etl.id_equipo_torneo
        INNER JOIN equipos el
            ON etl.id_equipo = el.id_equipo
        INNER JOIN equipo_torneo etv
            ON p.id_equipo_visitante = etv.id_equipo_torneo
        INNER JOIN equipos ev
            ON etv.id_equipo = ev.id_equipo
        WHERE p.id_torneo = ?
        ORDER BY
            fe.numero_fecha DESC,
            p.id_partido DESC
    ", [$idTorneo]);
}

function obtenerSancionesDelTorneo($idTorneo, $estadoFiltro = 'todas')
{
    if (!$idTorneo) {
        return [];
    }

    $sql = "
        SELECT
            sj.id_sancion,
            sj.id_jugador_equipo_torneo,
            sj.id_partido_origen,
            sj.motivo,
            sj.fechas_sancion,
            sj.fechas_cumplidas,
            sj.estado,
            sj.observaciones,
            sj.creado_en,

            j.nombre,
            j.apellido,
            j.dni,

            e.nombre AS equipo,
            e.escudo,

            p.goles_local,
            p.goles_visitante,
            fe.numero_fecha,
            fe.nombre AS fecha_nombre,
            f.nombre AS fase_nombre,
            el.nombre AS equipo_local,
            ev.nombre AS equipo_visitante

        FROM sanciones_jugador sj

        INNER JOIN jugador_equipo_torneo jet
            ON sj.id_jugador_equipo_torneo = jet.id_jugador_equipo_torneo

        INNER JOIN jugadores j
            ON jet.id_jugador = j.id_jugador

        INNER JOIN equipo_torneo et
            ON jet.id_equipo_torneo = et.id_equipo_torneo

        INNER JOIN equipos e
            ON et.id_equipo = e.id_equipo

        LEFT JOIN partidos p
            ON sj.id_partido_origen = p.id_partido

        LEFT JOIN fechas fe
            ON p.id_fecha = fe.id_fecha

        LEFT JOIN fases f
            ON p.id_fase = f.id_fase

        LEFT JOIN equipo_torneo etl
            ON p.id_equipo_local = etl.id_equipo_torneo
        LEFT JOIN equipos el
            ON etl.id_equipo = el.id_equipo

        LEFT JOIN equipo_torneo etv
            ON p.id_equipo_visitante = etv.id_equipo_torneo
        LEFT JOIN equipos ev
            ON etv.id_equipo = ev.id_equipo

        WHERE et.id_torneo = ?
    ";

    $params = [$idTorneo];

    if ($estadoFiltro !== 'todas') {
        $sql .= " AND sj.estado = ? ";
        $params[] = $estadoFiltro;
    }

    $sql .= "
        ORDER BY
            CASE sj.estado
                WHEN 'pendiente' THEN 1
                WHEN 'cumplida' THEN 2
                WHEN 'anulada' THEN 3
                ELSE 4
            END,
            sj.creado_en DESC,
            sj.id_sancion DESC
    ";

    return dbAll($sql, $params);
}

function obtenerSancionPorId($idSancion, $idTorneo)
{
    return dbOne("
        SELECT
            sj.*,
            et.id_torneo
        FROM sanciones_jugador sj
        INNER JOIN jugador_equipo_torneo jet
            ON sj.id_jugador_equipo_torneo = jet.id_jugador_equipo_torneo
        INNER JOIN equipo_torneo et
            ON jet.id_equipo_torneo = et.id_equipo_torneo
        WHERE sj.id_sancion = ?
          AND et.id_torneo = ?
        LIMIT 1
    ", [$idSancion, $idTorneo]);
}

function jugadorPlantelPerteneceAlTorneo($idJugadorEquipoTorneo, $idTorneo)
{
    $resultado = dbOne("
        SELECT COUNT(*) AS total
        FROM jugador_equipo_torneo jet
        INNER JOIN equipo_torneo et
            ON jet.id_equipo_torneo = et.id_equipo_torneo
        WHERE jet.id_jugador_equipo_torneo = ?
          AND et.id_torneo = ?
    ", [$idJugadorEquipoTorneo, $idTorneo]);

    return (int)$resultado['total'] > 0;
}

function partidoPerteneceAlTorneoSanciones($idPartido, $idTorneo)
{
    if (!$idPartido) {
        return true;
    }

    $resultado = dbOne("
        SELECT COUNT(*) AS total
        FROM partidos
        WHERE id_partido = ?
          AND id_torneo = ?
    ", [$idPartido, $idTorneo]);

    return (int)$resultado['total'] > 0;
}

function normalizarEnteroSancion($valor, $campo, $min = 0, $max = 99)
{
    $valor = trim((string)$valor);

    if ($valor === '') {
        return 0;
    }

    if (!ctype_digit($valor)) {
        throw new Exception($campo . ' debe ser un número entero.');
    }

    $valor = (int)$valor;

    if ($valor < $min || $valor > $max) {
        throw new Exception($campo . ' debe estar entre ' . $min . ' y ' . $max . '.');
    }

    return $valor;
}

function normalizarEstadoSancion($estado)
{
    if (!in_array($estado, ['pendiente', 'cumplida', 'anulada'])) {
        throw new Exception('El estado de la sanción no es válido.');
    }

    return $estado;
}

function textoEstadoSancion($estado)
{
    return match ($estado) {
        'pendiente' => 'Pendiente',
        'cumplida' => 'Cumplida',
        'anulada' => 'Anulada',
        default => 'Sin estado',
    };
}

function claseEstadoSancion($estado)
{
    return match ($estado) {
        'pendiente' => 'badge-yellow',
        'cumplida' => 'badge-green',
        'anulada' => 'badge-gray',
        default => 'badge-gray',
    };
}

function descripcionPartidoOrigen($sancion)
{
    if (empty($sancion['id_partido_origen'])) {
        return 'Sin partido asociado';
    }

    $nombreFecha = $sancion['fecha_nombre'] ?: 'Fecha ' . $sancion['numero_fecha'];

    $marcador = 'vs';

    if ($sancion['goles_local'] !== null && $sancion['goles_visitante'] !== null) {
        $marcador = $sancion['goles_local'] . ' - ' . $sancion['goles_visitante'];
    }

    return $nombreFecha . ' · ' .
        $sancion['equipo_local'] . ' ' .
        $marcador . ' ' .
        $sancion['equipo_visitante'];
}

function descripcionPartidoSelect($partido)
{
    $nombreFecha = $partido['fecha_nombre'] ?: 'Fecha ' . $partido['numero_fecha'];

    $marcador = 'vs';

    if ($partido['goles_local'] !== null && $partido['goles_visitante'] !== null) {
        $marcador = $partido['goles_local'] . ' - ' . $partido['goles_visitante'];
    }

    return $nombreFecha . ' · ' .
        $partido['equipo_local'] . ' ' .
        $marcador . ' ' .
        $partido['equipo_visitante'];
}

function escudoSancion($escudo, $alt = 'Escudo')
{
    if (!empty($escudo)) {
        return '<img src="' . h(asset($escudo)) . '" alt="' . h($alt) . '">';
    }

    return '<span>👕</span>';
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

        if ($accion === 'crear') {
            $idJugadorEquipoTorneo = (int)($_POST['id_jugador_equipo_torneo'] ?? 0);
            $idPartidoOrigen = (int)($_POST['id_partido_origen'] ?? 0);
            $motivo = trim($_POST['motivo'] ?? '');
            $fechasSancion = normalizarEnteroSancion($_POST['fechas_sancion'] ?? '', 'Fechas de sanción', 1, 99);
            $fechasCumplidas = normalizarEnteroSancion($_POST['fechas_cumplidas'] ?? '', 'Fechas cumplidas', 0, 99);
            $estado = normalizarEstadoSancion($_POST['estado'] ?? 'pendiente');
            $observaciones = trim($_POST['observaciones'] ?? '');

            if ($idJugadorEquipoTorneo <= 0) {
                throw new Exception('Tenés que seleccionar un jugador.');
            }

            if (!jugadorPlantelPerteneceAlTorneo($idJugadorEquipoTorneo, $idTorneoActual)) {
                throw new Exception('El jugador seleccionado no pertenece al torneo actual.');
            }

            if ($idPartidoOrigen <= 0) {
                $idPartidoOrigen = null;
            }

            if (!partidoPerteneceAlTorneoSanciones($idPartidoOrigen, $idTorneoActual)) {
                throw new Exception('El partido de origen no pertenece al torneo actual.');
            }

            if ($motivo === '') {
                throw new Exception('El motivo de la sanción es obligatorio.');
            }

            if ($fechasCumplidas > $fechasSancion) {
                throw new Exception('Las fechas cumplidas no pueden ser mayores a las fechas de sanción.');
            }

            if ($fechasCumplidas >= $fechasSancion && $estado === 'pendiente') {
                $estado = 'cumplida';
            }

            dbQuery("
                INSERT INTO sanciones_jugador (
                    id_jugador_equipo_torneo,
                    id_partido_origen,
                    motivo,
                    fechas_sancion,
                    fechas_cumplidas,
                    estado,
                    observaciones
                )
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ", [
                $idJugadorEquipoTorneo,
                $idPartidoOrigen,
                $motivo,
                $fechasSancion,
                $fechasCumplidas,
                $estado,
                $observaciones !== '' ? $observaciones : null
            ]);

            adminSetFlash('success', 'Sanción creada correctamente.');
            header('Location: sanciones.php');
            exit;
        }

        if ($accion === 'actualizar') {
            $idSancion = (int)($_POST['id_sancion'] ?? 0);
            $idJugadorEquipoTorneo = (int)($_POST['id_jugador_equipo_torneo'] ?? 0);
            $idPartidoOrigen = (int)($_POST['id_partido_origen'] ?? 0);
            $motivo = trim($_POST['motivo'] ?? '');
            $fechasSancion = normalizarEnteroSancion($_POST['fechas_sancion'] ?? '', 'Fechas de sanción', 1, 99);
            $fechasCumplidas = normalizarEnteroSancion($_POST['fechas_cumplidas'] ?? '', 'Fechas cumplidas', 0, 99);
            $estado = normalizarEstadoSancion($_POST['estado'] ?? 'pendiente');
            $observaciones = trim($_POST['observaciones'] ?? '');

            if ($idSancion <= 0) {
                throw new Exception('La sanción seleccionada no es válida.');
            }

            $sancion = obtenerSancionPorId($idSancion, $idTorneoActual);

            if (!$sancion) {
                throw new Exception('La sanción no pertenece al torneo actual.');
            }

            if ($idJugadorEquipoTorneo <= 0) {
                throw new Exception('Tenés que seleccionar un jugador.');
            }

            if (!jugadorPlantelPerteneceAlTorneo($idJugadorEquipoTorneo, $idTorneoActual)) {
                throw new Exception('El jugador seleccionado no pertenece al torneo actual.');
            }

            if ($idPartidoOrigen <= 0) {
                $idPartidoOrigen = null;
            }

            if (!partidoPerteneceAlTorneoSanciones($idPartidoOrigen, $idTorneoActual)) {
                throw new Exception('El partido de origen no pertenece al torneo actual.');
            }

            if ($motivo === '') {
                throw new Exception('El motivo de la sanción es obligatorio.');
            }

            if ($fechasCumplidas > $fechasSancion) {
                throw new Exception('Las fechas cumplidas no pueden ser mayores a las fechas de sanción.');
            }

            if ($fechasCumplidas >= $fechasSancion && $estado === 'pendiente') {
                $estado = 'cumplida';
            }

            dbQuery("
                UPDATE sanciones_jugador
                SET id_jugador_equipo_torneo = ?,
                    id_partido_origen = ?,
                    motivo = ?,
                    fechas_sancion = ?,
                    fechas_cumplidas = ?,
                    estado = ?,
                    observaciones = ?
                WHERE id_sancion = ?
            ", [
                $idJugadorEquipoTorneo,
                $idPartidoOrigen,
                $motivo,
                $fechasSancion,
                $fechasCumplidas,
                $estado,
                $observaciones !== '' ? $observaciones : null,
                $idSancion
            ]);

            adminSetFlash('success', 'Sanción actualizada correctamente.');
            header('Location: sanciones.php');
            exit;
        }

        if ($accion === 'cumplir_fecha') {
            $idSancion = (int)($_POST['id_sancion'] ?? 0);

            $sancion = obtenerSancionPorId($idSancion, $idTorneoActual);

            if (!$sancion) {
                throw new Exception('La sanción no pertenece al torneo actual.');
            }

            if ($sancion['estado'] !== 'pendiente') {
                throw new Exception('Solo se pueden cumplir fechas de sanciones pendientes.');
            }

            $nuevasCumplidas = (int)$sancion['fechas_cumplidas'] + 1;
            $nuevoEstado = $nuevasCumplidas >= (int)$sancion['fechas_sancion']
                ? 'cumplida'
                : 'pendiente';

            dbQuery("
                UPDATE sanciones_jugador
                SET fechas_cumplidas = ?,
                    estado = ?
                WHERE id_sancion = ?
            ", [$nuevasCumplidas, $nuevoEstado, $idSancion]);

            adminSetFlash('success', 'Se sumó una fecha cumplida.');
            header('Location: sanciones.php');
            exit;
        }

        if ($accion === 'marcar_cumplida') {
            $idSancion = (int)($_POST['id_sancion'] ?? 0);

            $sancion = obtenerSancionPorId($idSancion, $idTorneoActual);

            if (!$sancion) {
                throw new Exception('La sanción no pertenece al torneo actual.');
            }

            dbQuery("
                UPDATE sanciones_jugador
                SET fechas_cumplidas = fechas_sancion,
                    estado = 'cumplida'
                WHERE id_sancion = ?
            ", [$idSancion]);

            adminSetFlash('success', 'Sanción marcada como cumplida.');
            header('Location: sanciones.php');
            exit;
        }

        if ($accion === 'anular') {
            $idSancion = (int)($_POST['id_sancion'] ?? 0);

            $sancion = obtenerSancionPorId($idSancion, $idTorneoActual);

            if (!$sancion) {
                throw new Exception('La sanción no pertenece al torneo actual.');
            }

            dbQuery("
                UPDATE sanciones_jugador
                SET estado = 'anulada'
                WHERE id_sancion = ?
            ", [$idSancion]);

            adminSetFlash('success', 'Sanción anulada correctamente.');
            header('Location: sanciones.php');
            exit;
        }

    } catch (Exception $e) {
        adminSetFlash('error', $e->getMessage());
        header('Location: sanciones.php');
        exit;
    }
}

/* =====================================================
   DATOS PARA MOSTRAR
===================================================== */

$flash = adminGetFlash();

$estadoFiltro = $_GET['estado'] ?? 'todas';

if (!in_array($estadoFiltro, ['todas', 'pendiente', 'cumplida', 'anulada'])) {
    $estadoFiltro = 'todas';
}

$jugadores = obtenerJugadoresPlantelSanciones($idTorneoActual);
$partidosOrigen = obtenerPartidosOrigenSanciones($idTorneoActual);
$sanciones = obtenerSancionesDelTorneo($idTorneoActual, $estadoFiltro);

$idEditar = isset($_GET['editar']) ? (int)$_GET['editar'] : 0;
$sancionEditar = null;

if ($idEditar > 0 && $idTorneoActual) {
    $sancionEditar = obtenerSancionPorId($idEditar, $idTorneoActual);

    if (!$sancionEditar) {
        adminSetFlash('error', 'La sanción que querés editar no existe.');
        header('Location: sanciones.php');
        exit;
    }
}

$totalPendientes = (int) dbOne("
    SELECT COUNT(*) AS total
    FROM sanciones_jugador sj
    INNER JOIN jugador_equipo_torneo jet
        ON sj.id_jugador_equipo_torneo = jet.id_jugador_equipo_torneo
    INNER JOIN equipo_torneo et
        ON jet.id_equipo_torneo = et.id_equipo_torneo
    WHERE et.id_torneo = ?
      AND sj.estado = 'pendiente'
", [$idTorneoActual ?? 0])['total'];

$totalCumplidas = (int) dbOne("
    SELECT COUNT(*) AS total
    FROM sanciones_jugador sj
    INNER JOIN jugador_equipo_torneo jet
        ON sj.id_jugador_equipo_torneo = jet.id_jugador_equipo_torneo
    INNER JOIN equipo_torneo et
        ON jet.id_equipo_torneo = et.id_equipo_torneo
    WHERE et.id_torneo = ?
      AND sj.estado = 'cumplida'
", [$idTorneoActual ?? 0])['total'];

$totalAnuladas = (int) dbOne("
    SELECT COUNT(*) AS total
    FROM sanciones_jugador sj
    INNER JOIN jugador_equipo_torneo jet
        ON sj.id_jugador_equipo_torneo = jet.id_jugador_equipo_torneo
    INNER JOIN equipo_torneo et
        ON jet.id_equipo_torneo = et.id_equipo_torneo
    WHERE et.id_torneo = ?
      AND sj.estado = 'anulada'
", [$idTorneoActual ?? 0])['total'];

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <?php include __DIR__ . '/../includes/head.php'; ?>
    <link rel="stylesheet" href="<?= asset('assets/css/admin.css') ?>">
</head>
<body class="admin-body">

<div class="admin-layout">

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
            <a href="<?= BASE_URL ?>/admin/resultados.php">✍ Resultados</a>
            <a href="<?= BASE_URL ?>/admin/estadisticas.php">📊 Estadísticas</a>
            <a class="active" href="<?= BASE_URL ?>/admin/sanciones.php">🟥 Sanciones</a>
        </nav>

        <div class="admin-sidebar-footer">
            <a href="<?= BASE_URL ?>/index.php">← Ver sitio público</a>
        </div>

    </aside>

    <main class="admin-main">

        <header class="admin-topbar">
            <div>
                <span class="admin-eyebrow">Control disciplinario</span>
                <h1>Sanciones</h1>

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
                <a href="<?= BASE_URL ?>/admin/estadisticas.php" class="admin-btn admin-btn-primary">
                    Ver estadísticas
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
                <p>Para cargar sanciones primero tenés que crear un torneo.</p>
                <a href="<?= BASE_URL ?>/admin/torneos.php" class="admin-btn admin-btn-primary">
                    Crear torneo
                </a>
            </section>

        <?php elseif (empty($jugadores)): ?>

            <section class="admin-empty-main">
                <h2>No hay jugadores en planteles</h2>
                <p>Para cargar sanciones primero tenés que armar los planteles.</p>
                <a href="<?= BASE_URL ?>/admin/planteles.php" class="admin-btn admin-btn-primary">
                    Ir a planteles
                </a>
            </section>

        <?php else: ?>

            <section class="admin-stats-grid admin-stats-grid-compact">

                <article class="admin-stat-card warning">
                    <span>Pendientes</span>
                    <strong><?= h($totalPendientes) ?></strong>
                    <p>Sanciones por cumplir</p>
                </article>

                <article class="admin-stat-card">
                    <span>Cumplidas</span>
                    <strong><?= h($totalCumplidas) ?></strong>
                    <p>Ya completadas</p>
                </article>

                <article class="admin-stat-card">
                    <span>Anuladas</span>
                    <strong><?= h($totalAnuladas) ?></strong>
                    <p>No se aplican</p>
                </article>

                <article class="admin-stat-card">
                    <span>Mostradas</span>
                    <strong><?= h(count($sanciones)) ?></strong>
                    <p>Según filtro actual</p>
                </article>

            </section>

            <section class="admin-dashboard-grid">

                <article class="admin-panel">
                    <div class="admin-panel-header">
                        <div>
                            <span><?= $sancionEditar ? 'Editar sanción' : 'Nueva sanción' ?></span>
                            <h2><?= $sancionEditar ? 'Modificar sanción' : 'Crear sanción' ?></h2>
                        </div>

                        <?php if ($sancionEditar): ?>
                            <a href="<?= BASE_URL ?>/admin/sanciones.php">Cancelar edición</a>
                        <?php endif; ?>
                    </div>

                    <form method="POST" class="admin-form">

                        <?php if ($sancionEditar): ?>
                            <input type="hidden" name="accion" value="actualizar">
                            <input type="hidden" name="id_sancion" value="<?= h($sancionEditar['id_sancion']) ?>">
                        <?php else: ?>
                            <input type="hidden" name="accion" value="crear">
                        <?php endif; ?>

                        <div class="form-group">
                            <label for="id_jugador_equipo_torneo">Jugador</label>
                            <?php $jugadorActual = $sancionEditar['id_jugador_equipo_torneo'] ?? ''; ?>

                            <select id="id_jugador_equipo_torneo" name="id_jugador_equipo_torneo" required>
                                <option value="">Seleccionar jugador</option>
                                <?php foreach ($jugadores as $jugador): ?>
                                    <option
                                        value="<?= h($jugador['id_jugador_equipo_torneo']) ?>"
                                        <?= (int)$jugadorActual === (int)$jugador['id_jugador_equipo_torneo'] ? 'selected' : '' ?>
                                    >
                                        <?= h($jugador['apellido']) ?>, <?= h($jugador['nombre']) ?>
                                        · <?= h($jugador['equipo']) ?>
                                        <?= (int)$jugador['activo_plantel'] === 0 ? ' · Plantel inactivo' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="id_partido_origen">Partido de origen</label>
                            <?php $partidoActual = $sancionEditar['id_partido_origen'] ?? ''; ?>

                            <select id="id_partido_origen" name="id_partido_origen">
                                <option value="">Sin partido asociado</option>
                                <?php foreach ($partidosOrigen as $partido): ?>
                                    <option
                                        value="<?= h($partido['id_partido']) ?>"
                                        <?= (int)$partidoActual === (int)$partido['id_partido'] ? 'selected' : '' ?>
                                    >
                                        <?= h(descripcionPartidoSelect($partido)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-help">
                                Opcional. Sirve para saber en qué partido nació la sanción.
                            </small>
                        </div>

                        <div class="form-group">
                            <label for="motivo">Motivo</label>
                            <input
                                type="text"
                                id="motivo"
                                name="motivo"
                                placeholder="Ej: Roja directa, doble amarilla, conducta antideportiva"
                                value="<?= h($sancionEditar['motivo'] ?? '') ?>"
                                required
                            >
                        </div>

                        <div class="form-grid-2">
                            <div class="form-group">
                                <label for="fechas_sancion">Fechas de sanción</label>
                                <input
                                    type="number"
                                    id="fechas_sancion"
                                    name="fechas_sancion"
                                    min="1"
                                    max="99"
                                    value="<?= h($sancionEditar['fechas_sancion'] ?? 1) ?>"
                                    required
                                >
                            </div>

                            <div class="form-group">
                                <label for="fechas_cumplidas">Fechas cumplidas</label>
                                <input
                                    type="number"
                                    id="fechas_cumplidas"
                                    name="fechas_cumplidas"
                                    min="0"
                                    max="99"
                                    value="<?= h($sancionEditar['fechas_cumplidas'] ?? 0) ?>"
                                    required
                                >
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="estado">Estado</label>
                            <?php $estadoActual = $sancionEditar['estado'] ?? 'pendiente'; ?>

                            <select id="estado" name="estado" required>
                                <option value="pendiente" <?= $estadoActual === 'pendiente' ? 'selected' : '' ?>>
                                    Pendiente
                                </option>
                                <option value="cumplida" <?= $estadoActual === 'cumplida' ? 'selected' : '' ?>>
                                    Cumplida
                                </option>
                                <option value="anulada" <?= $estadoActual === 'anulada' ? 'selected' : '' ?>>
                                    Anulada
                                </option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="observaciones">Observaciones</label>
                            <textarea
                                id="observaciones"
                                name="observaciones"
                                rows="4"
                                placeholder="Opcional"
                            ><?= h($sancionEditar['observaciones'] ?? '') ?></textarea>
                        </div>

                        <button type="submit" class="admin-btn admin-btn-primary">
                            <?= $sancionEditar ? 'Guardar cambios' : 'Crear sanción' ?>
                        </button>

                    </form>
                </article>

                <article class="admin-panel">
                    <div class="admin-panel-header">
                        <div>
                            <span>Uso recomendado</span>
                            <h2>Control manual</h2>
                        </div>
                    </div>

                    <div class="admin-help-list">
                        <div>
                            <strong>1</strong>
                            <p>Creá la sanción cuando un jugador deba cumplir una o más fechas.</p>
                        </div>

                        <div>
                            <strong>2</strong>
                            <p>Cuando cumpla una fecha, usá “Sumar fecha” para avanzar el contador.</p>
                        </div>

                        <div>
                            <strong>3</strong>
                            <p>Cuando llegue al total de fechas, la sanción pasa automáticamente a cumplida.</p>
                        </div>

                        <div>
                            <strong>4</strong>
                            <p>Si hubo un error o se levanta la sanción, podés marcarla como anulada.</p>
                        </div>
                    </div>
                </article>

            </section>

            <section class="admin-section">
                <div class="admin-section-header">
                    <div>
                        <span>Buscar y filtrar</span>
                        <h2>Listado de sanciones</h2>
                    </div>
                </div>

                <form method="GET" class="admin-filter-form sanciones-filter-form">
                    <div class="form-group">
                        <label for="estado_filtro">Estado</label>
                        <select id="estado_filtro" name="estado">
                            <option value="todas" <?= $estadoFiltro === 'todas' ? 'selected' : '' ?>>
                                Todas
                            </option>
                            <option value="pendiente" <?= $estadoFiltro === 'pendiente' ? 'selected' : '' ?>>
                                Pendientes
                            </option>
                            <option value="cumplida" <?= $estadoFiltro === 'cumplida' ? 'selected' : '' ?>>
                                Cumplidas
                            </option>
                            <option value="anulada" <?= $estadoFiltro === 'anulada' ? 'selected' : '' ?>>
                                Anuladas
                            </option>
                        </select>
                    </div>

                    <div class="filter-actions">
                        <button type="submit" class="admin-btn admin-btn-primary">
                            Filtrar
                        </button>

                        <a href="<?= BASE_URL ?>/admin/sanciones.php" class="admin-btn admin-btn-secondary">
                            Limpiar
                        </a>
                    </div>
                </form>

                <?php if (empty($sanciones)): ?>

                    <div class="admin-empty-box">
                        No hay sanciones para mostrar con el filtro actual.
                    </div>

                <?php else: ?>

                    <div class="admin-table-wrapper">
                        <table class="admin-table sanciones-table">
                            <thead>
                                <tr>
                                    <th>Jugador</th>
                                    <th>Equipo</th>
                                    <th>Motivo</th>
                                    <th>Fechas</th>
                                    <th>Estado</th>
                                    <th>Partido origen</th>
                                    <th>Creada</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php foreach ($sanciones as $sancion): ?>
                                    <tr>
                                        <td>
                                            <strong><?= h($sancion['apellido']) ?>, <?= h($sancion['nombre']) ?></strong>
                                            <small><?= h($sancion['dni'] ?: 'Sin DNI') ?></small>
                                        </td>

                                        <td>
                                            <div class="stats-team-cell">
                                                <div class="team-shield-table small">
                                                    <?= escudoSancion($sancion['escudo'], $sancion['equipo']) ?>
                                                </div>
                                                <span><?= h($sancion['equipo']) ?></span>
                                            </div>
                                        </td>

                                        <td>
                                            <strong><?= h($sancion['motivo']) ?></strong>
                                            <?php if (!empty($sancion['observaciones'])): ?>
                                                <small><?= h($sancion['observaciones']) ?></small>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <strong>
                                                <?= h($sancion['fechas_cumplidas']) ?>
                                                /
                                                <?= h($sancion['fechas_sancion']) ?>
                                            </strong>
                                            <small>cumplidas</small>
                                        </td>

                                        <td>
                                            <span class="status-badge <?= h(claseEstadoSancion($sancion['estado'])) ?>">
                                                <?= h(textoEstadoSancion($sancion['estado'])) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <?= h(descripcionPartidoOrigen($sancion)) ?>
                                        </td>

                                        <td>
                                            <?= h(formatearFecha($sancion['creado_en'])) ?>
                                        </td>

                                        <td>
                                            <div class="table-actions">

                                                <a
                                                    href="<?= BASE_URL ?>/admin/sanciones.php?editar=<?= h($sancion['id_sancion']) ?>"
                                                    class="table-btn edit"
                                                >
                                                    Editar
                                                </a>

                                                <?php if ($sancion['estado'] === 'pendiente'): ?>

                                                    <form method="POST">
                                                        <input type="hidden" name="accion" value="cumplir_fecha">
                                                        <input type="hidden" name="id_sancion" value="<?= h($sancion['id_sancion']) ?>">

                                                        <button type="submit" class="table-btn success">
                                                            Sumar fecha
                                                        </button>
                                                    </form>

                                                    <form method="POST">
                                                        <input type="hidden" name="accion" value="marcar_cumplida">
                                                        <input type="hidden" name="id_sancion" value="<?= h($sancion['id_sancion']) ?>">

                                                        <button
                                                            type="submit"
                                                            class="table-btn info"
                                                            onclick="return confirm('¿Marcar esta sanción como cumplida?');"
                                                        >
                                                            Cumplida
                                                        </button>
                                                    </form>

                                                    <form method="POST">
                                                        <input type="hidden" name="accion" value="anular">
                                                        <input type="hidden" name="id_sancion" value="<?= h($sancion['id_sancion']) ?>">

                                                        <button
                                                            type="submit"
                                                            class="table-btn delete"
                                                            onclick="return confirm('¿Seguro que querés anular esta sanción?');"
                                                        >
                                                            Anular
                                                        </button>
                                                    </form>

                                                <?php else: ?>

                                                    <span class="table-muted">
                                                        Sin acción
                                                    </span>

                                                <?php endif; ?>

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