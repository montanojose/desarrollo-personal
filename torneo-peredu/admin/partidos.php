<?php
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = "Partidos - Panel Admin";
$pageDescription = "Administración de partidos Copa PER.EDU";

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

function obtenerFechasParaPartidos($idTorneo)
{
    if (!$idTorneo) {
        return [];
    }

    return dbAll("
        SELECT
            fe.id_fecha,
            fe.id_torneo,
            fe.id_fase,
            fe.numero_fecha,
            fe.nombre,
            fe.fecha_programada,
            f.nombre AS fase_nombre,
            f.tipo AS fase_tipo,
            f.orden AS fase_orden
        FROM fechas fe
        INNER JOIN fases f
            ON fe.id_fase = f.id_fase
        WHERE fe.id_torneo = ?
        ORDER BY fe.numero_fecha ASC, f.orden ASC, fe.id_fecha ASC
    ", [$idTorneo]);
}

function obtenerFechaParaPartidoPorId($idFecha, $idTorneo)
{
    return dbOne("
        SELECT
            fe.id_fecha,
            fe.id_torneo,
            fe.id_fase,
            fe.numero_fecha,
            fe.nombre,
            fe.fecha_programada,
            f.nombre AS fase_nombre,
            f.tipo AS fase_tipo
        FROM fechas fe
        INNER JOIN fases f
            ON fe.id_fase = f.id_fase
        WHERE fe.id_fecha = ?
          AND fe.id_torneo = ?
        LIMIT 1
    ", [$idFecha, $idTorneo]);
}

function obtenerEquiposParaPartidos($idTorneo)
{
    if (!$idTorneo) {
        return [];
    }

    return dbAll("
        SELECT
            et.id_equipo_torneo,
            et.id_torneo,
            et.activo AS activo_en_torneo,
            e.id_equipo,
            e.nombre,
            e.escuela,
            e.escudo,
            e.activo AS activo_equipo
        FROM equipo_torneo et
        INNER JOIN equipos e
            ON et.id_equipo = e.id_equipo
        WHERE et.id_torneo = ?
          AND et.activo = 1
          AND e.activo = 1
        ORDER BY e.nombre ASC
    ", [$idTorneo]);
}

function equipoPerteneceAlTorneo($idEquipoTorneo, $idTorneo)
{
    $resultado = dbOne("
        SELECT COUNT(*) AS total
        FROM equipo_torneo et
        INNER JOIN equipos e
            ON et.id_equipo = e.id_equipo
        WHERE et.id_equipo_torneo = ?
          AND et.id_torneo = ?
          AND et.activo = 1
          AND e.activo = 1
    ", [$idEquipoTorneo, $idTorneo]);

    return (int)$resultado['total'] > 0;
}

function obtenerPartidoAdminPorId($idPartido, $idTorneo)
{
    return dbOne("
        SELECT *
        FROM partidos
        WHERE id_partido = ?
          AND id_torneo = ?
        LIMIT 1
    ", [$idPartido, $idTorneo]);
}

function obtenerPartidosAdmin($idTorneo, $idFechaFiltro = 0, $estadoFiltro = 'todos')
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
            pp.id_equipo_ganador,

            (
                SELECT COUNT(*)
                FROM jugador_partido jp
                WHERE jp.id_partido = p.id_partido
            ) AS total_jugadores_cargados

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

    if ($estadoFiltro !== 'todos') {
        $sql .= " AND p.estado = ? ";
        $params[] = $estadoFiltro;
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

function partidoTieneDatos($idPartido)
{
    $partido = dbOne("
        SELECT
            goles_local,
            goles_visitante,
            estado
        FROM partidos
        WHERE id_partido = ?
        LIMIT 1
    ", [$idPartido]);

    if (!$partido) {
        return false;
    }

    if ($partido['estado'] === 'finalizado') {
        return true;
    }

    if ($partido['goles_local'] !== null || $partido['goles_visitante'] !== null) {
        return true;
    }

    $jugadores = dbOne("
        SELECT COUNT(*) AS total
        FROM jugador_partido
        WHERE id_partido = ?
    ", [$idPartido]);

    $penales = dbOne("
        SELECT COUNT(*) AS total
        FROM penales_partido
        WHERE id_partido = ?
    ", [$idPartido]);

    return (
        (int)$jugadores['total'] > 0 ||
        (int)$penales['total'] > 0
    );
}

function existePartidoMismaFecha($idFecha, $idLocal, $idVisitante, $idPartidoExcluir = 0)
{
    $resultado = dbOne("
        SELECT COUNT(*) AS total
        FROM partidos
        WHERE id_fecha = ?
          AND id_partido <> ?
          AND (
                (id_equipo_local = ? AND id_equipo_visitante = ?)
                OR
                (id_equipo_local = ? AND id_equipo_visitante = ?)
          )
    ", [
        $idFecha,
        $idPartidoExcluir,
        $idLocal,
        $idVisitante,
        $idVisitante,
        $idLocal
    ]);

    return (int)$resultado['total'] > 0;
}

function normalizarFechaHoraPartido($fechaHora)
{
    $fechaHora = trim($fechaHora);

    if ($fechaHora === '') {
        return null;
    }

    $fechaHora = str_replace('T', ' ', $fechaHora);

    $dt = DateTime::createFromFormat('Y-m-d H:i', $fechaHora);

    if (!$dt) {
        throw new Exception('La fecha y hora del partido no es válida.');
    }

    return $dt->format('Y-m-d H:i:s');
}

function valorInputFechaHora($fechaHora)
{
    if (empty($fechaHora)) {
        return '';
    }

    return date('Y-m-d\TH:i', strtotime($fechaHora));
}

function nombreVisibleFechaPartido($fecha)
{
    if (!empty($fecha['fecha_nombre'])) {
        return $fecha['fecha_nombre'];
    }

    if (!empty($fecha['nombre'])) {
        return $fecha['nombre'];
    }

    return 'Fecha ' . $fecha['numero_fecha'];
}

function estadoValidoPartido($estado)
{
    return in_array($estado, [
        'programado',
        'en_juego',
        'finalizado',
        'suspendido',
        'cancelado'
    ]);
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
            $idFecha = (int)($_POST['id_fecha'] ?? 0);
            $idEquipoLocal = (int)($_POST['id_equipo_local'] ?? 0);
            $idEquipoVisitante = (int)($_POST['id_equipo_visitante'] ?? 0);
            $fechaHora = normalizarFechaHoraPartido($_POST['fecha_hora'] ?? '');
            $cancha = trim($_POST['cancha'] ?? '');
            $estado = $_POST['estado'] ?? 'programado';
            $observaciones = trim($_POST['observaciones'] ?? '');

            $fecha = obtenerFechaParaPartidoPorId($idFecha, $idTorneoActual);

            if (!$fecha) {
                throw new Exception('La fecha seleccionada no pertenece al torneo actual.');
            }

            if ($idEquipoLocal <= 0 || $idEquipoVisitante <= 0) {
                throw new Exception('Tenés que seleccionar equipo local y visitante.');
            }

            if ($idEquipoLocal === $idEquipoVisitante) {
                throw new Exception('El equipo local y visitante no pueden ser el mismo.');
            }

            if (!equipoPerteneceAlTorneo($idEquipoLocal, $idTorneoActual)) {
                throw new Exception('El equipo local no pertenece al torneo actual o está inactivo.');
            }

            if (!equipoPerteneceAlTorneo($idEquipoVisitante, $idTorneoActual)) {
                throw new Exception('El equipo visitante no pertenece al torneo actual o está inactivo.');
            }

            if (!estadoValidoPartido($estado)) {
                throw new Exception('El estado del partido no es válido.');
            }

            if ($estado === 'finalizado') {
                throw new Exception('El partido no se crea directamente como finalizado. El resultado se carga desde Resultados.');
            }

            if (existePartidoMismaFecha($idFecha, $idEquipoLocal, $idEquipoVisitante)) {
                throw new Exception('Ya existe un partido entre estos equipos en esta misma fecha.');
            }

            dbQuery("
                INSERT INTO partidos (
                    id_torneo,
                    id_fase,
                    id_fecha,
                    id_equipo_local,
                    id_equipo_visitante,
                    goles_local,
                    goles_visitante,
                    fecha_hora,
                    cancha,
                    estado,
                    observaciones
                )
                VALUES (?, ?, ?, ?, ?, NULL, NULL, ?, ?, ?, ?)
            ", [
                $idTorneoActual,
                $fecha['id_fase'],
                $idFecha,
                $idEquipoLocal,
                $idEquipoVisitante,
                $fechaHora,
                $cancha !== '' ? $cancha : null,
                $estado,
                $observaciones !== '' ? $observaciones : null
            ]);

            adminSetFlash('success', 'Partido creado correctamente.');
            header('Location: partidos.php?fecha=' . $idFecha);
            exit;
        }

        if ($accion === 'actualizar') {
            $idPartido = (int)($_POST['id_partido'] ?? 0);
            $idFecha = (int)($_POST['id_fecha'] ?? 0);
            $idEquipoLocal = (int)($_POST['id_equipo_local'] ?? 0);
            $idEquipoVisitante = (int)($_POST['id_equipo_visitante'] ?? 0);
            $fechaHora = normalizarFechaHoraPartido($_POST['fecha_hora'] ?? '');
            $cancha = trim($_POST['cancha'] ?? '');
            $estado = $_POST['estado'] ?? 'programado';
            $observaciones = trim($_POST['observaciones'] ?? '');

            if ($idPartido <= 0) {
                throw new Exception('El partido seleccionado no es válido.');
            }

            $partidoActual = obtenerPartidoAdminPorId($idPartido, $idTorneoActual);

            if (!$partidoActual) {
                throw new Exception('El partido que querés editar no existe en el torneo actual.');
            }

            $fecha = obtenerFechaParaPartidoPorId($idFecha, $idTorneoActual);

            if (!$fecha) {
                throw new Exception('La fecha seleccionada no pertenece al torneo actual.');
            }

            if ($idEquipoLocal <= 0 || $idEquipoVisitante <= 0) {
                throw new Exception('Tenés que seleccionar equipo local y visitante.');
            }

            if ($idEquipoLocal === $idEquipoVisitante) {
                throw new Exception('El equipo local y visitante no pueden ser el mismo.');
            }

            if (!equipoPerteneceAlTorneo($idEquipoLocal, $idTorneoActual)) {
                throw new Exception('El equipo local no pertenece al torneo actual o está inactivo.');
            }

            if (!equipoPerteneceAlTorneo($idEquipoVisitante, $idTorneoActual)) {
                throw new Exception('El equipo visitante no pertenece al torneo actual o está inactivo.');
            }

            if (!estadoValidoPartido($estado)) {
                throw new Exception('El estado del partido no es válido.');
            }

            $tieneDatos = partidoTieneDatos($idPartido);

            $cambioEquiposOFecha = (
                (int)$partidoActual['id_fecha'] !== $idFecha ||
                (int)$partidoActual['id_equipo_local'] !== $idEquipoLocal ||
                (int)$partidoActual['id_equipo_visitante'] !== $idEquipoVisitante
            );

            if ($tieneDatos && $cambioEquiposOFecha) {
                throw new Exception('Este partido ya tiene resultado, penales o estadísticas. No se puede cambiar fecha ni equipos.');
            }

            if ($estado === 'finalizado' && !$tieneDatos) {
                throw new Exception('Para finalizar el partido primero cargá el resultado desde Resultados.');
            }

            if (existePartidoMismaFecha($idFecha, $idEquipoLocal, $idEquipoVisitante, $idPartido)) {
                throw new Exception('Ya existe otro partido entre estos equipos en esta misma fecha.');
            }

            dbQuery("
                UPDATE partidos
                SET id_fase = ?,
                    id_fecha = ?,
                    id_equipo_local = ?,
                    id_equipo_visitante = ?,
                    fecha_hora = ?,
                    cancha = ?,
                    estado = ?,
                    observaciones = ?
                WHERE id_partido = ?
                  AND id_torneo = ?
            ", [
                $fecha['id_fase'],
                $idFecha,
                $idEquipoLocal,
                $idEquipoVisitante,
                $fechaHora,
                $cancha !== '' ? $cancha : null,
                $estado,
                $observaciones !== '' ? $observaciones : null,
                $idPartido,
                $idTorneoActual
            ]);

            adminSetFlash('success', 'Partido actualizado correctamente.');
            header('Location: partidos.php?fecha=' . $idFecha);
            exit;
        }

        if ($accion === 'eliminar') {
            $idPartido = (int)($_POST['id_partido'] ?? 0);

            if ($idPartido <= 0) {
                throw new Exception('El partido seleccionado no es válido.');
            }

            $partido = obtenerPartidoAdminPorId($idPartido, $idTorneoActual);

            if (!$partido) {
                throw new Exception('El partido no existe en el torneo actual.');
            }

            if (partidoTieneDatos($idPartido)) {
                throw new Exception('No se puede eliminar este partido porque ya tiene resultado, penales o estadísticas cargadas.');
            }

            dbQuery("
                DELETE FROM partidos
                WHERE id_partido = ?
                  AND id_torneo = ?
            ", [$idPartido, $idTorneoActual]);

            adminSetFlash('success', 'Partido eliminado correctamente.');
            header('Location: partidos.php?fecha=' . $partido['id_fecha']);
            exit;
        }

    } catch (Exception $e) {
        adminSetFlash('error', $e->getMessage());

        $redirectFecha = isset($_POST['id_fecha']) ? (int)$_POST['id_fecha'] : 0;

        if ($redirectFecha > 0) {
            header('Location: partidos.php?fecha=' . $redirectFecha);
        } else {
            header('Location: partidos.php');
        }

        exit;
    }
}

/* =====================================================
   DATOS PARA MOSTRAR
===================================================== */

$flash = adminGetFlash();

$fechas = obtenerFechasParaPartidos($idTorneoActual);
$equipos = obtenerEquiposParaPartidos($idTorneoActual);

$idFechaFiltro = isset($_GET['fecha']) ? (int)$_GET['fecha'] : 0;
$estadoFiltro = $_GET['estado'] ?? 'todos';

if (!in_array($estadoFiltro, ['todos', 'programado', 'en_juego', 'finalizado', 'suspendido', 'cancelado'])) {
    $estadoFiltro = 'todos';
}

$partidos = obtenerPartidosAdmin($idTorneoActual, $idFechaFiltro, $estadoFiltro);

$idEditar = isset($_GET['editar']) ? (int)$_GET['editar'] : 0;
$partidoEditar = null;

if ($idEditar > 0 && $idTorneoActual) {
    $partidoEditar = obtenerPartidoAdminPorId($idEditar, $idTorneoActual);

    if (!$partidoEditar) {
        adminSetFlash('error', 'El partido que querés editar no existe.');
        header('Location: partidos.php');
        exit;
    }
}

$totalPartidos = (int) dbOne("
    SELECT COUNT(*) AS total
    FROM partidos
    WHERE id_torneo = ?
", [$idTorneoActual ?? 0])['total'];

$totalProgramados = (int) dbOne("
    SELECT COUNT(*) AS total
    FROM partidos
    WHERE id_torneo = ?
      AND estado = 'programado'
", [$idTorneoActual ?? 0])['total'];

$totalFinalizados = (int) dbOne("
    SELECT COUNT(*) AS total
    FROM partidos
    WHERE id_torneo = ?
      AND estado = 'finalizado'
", [$idTorneoActual ?? 0])['total'];

$totalSuspendidosCancelados = (int) dbOne("
    SELECT COUNT(*) AS total
    FROM partidos
    WHERE id_torneo = ?
      AND estado IN ('suspendido', 'cancelado')
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
            <a class="active" href="<?= BASE_URL ?>/admin/partidos.php">⚽ Partidos</a>
            <a href="<?= BASE_URL ?>/admin/resultados.php">✍ Resultados</a>
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
                <span class="admin-eyebrow">Fixture del torneo</span>
                <h1>Partidos</h1>

                <?php if ($torneoActual): ?>
                    <p>
                        Torneo actual:
                        <strong><?= h($torneoActual['nombre']) ?></strong>
                        · Temporada <?= h($torneoActual['temporada']) ?>
                    </p>
                <?php else: ?>
                    <p>Primero tenés que crear un torneo para poder cargar partidos.</p>
                <?php endif; ?>
            </div>

            <div class="admin-top-actions">
                <a href="<?= BASE_URL ?>/admin/resultados.php" class="admin-btn admin-btn-primary">
                    Ir a resultados
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
                <p>Para crear partidos primero tenés que crear un torneo.</p>
                <a href="<?= BASE_URL ?>/admin/torneos.php" class="admin-btn admin-btn-primary">
                    Crear torneo
                </a>
            </section>

        <?php elseif (empty($fechas)): ?>

            <section class="admin-empty-main">
                <h2>No hay fechas cargadas</h2>
                <p>Para crear partidos primero tenés que cargar fases y fechas.</p>
                <a href="<?= BASE_URL ?>/admin/fechas.php" class="admin-btn admin-btn-primary">
                    Crear fechas
                </a>
            </section>

        <?php elseif (count($equipos) < 2): ?>

            <section class="admin-empty-main">
                <h2>Faltan equipos</h2>
                <p>Para crear partidos necesitás al menos dos equipos activos en el torneo actual.</p>
                <a href="<?= BASE_URL ?>/admin/equipos.php" class="admin-btn admin-btn-primary">
                    Cargar equipos
                </a>
            </section>

        <?php else: ?>

            <!-- RESUMEN -->
            <section class="admin-stats-grid admin-stats-grid-compact">

                <article class="admin-stat-card">
                    <span>Total partidos</span>
                    <strong><?= h($totalPartidos) ?></strong>
                    <p>Creados en el torneo</p>
                </article>

                <article class="admin-stat-card">
                    <span>Programados</span>
                    <strong><?= h($totalProgramados) ?></strong>
                    <p>Esperando jugarse</p>
                </article>

                <article class="admin-stat-card">
                    <span>Finalizados</span>
                    <strong><?= h($totalFinalizados) ?></strong>
                    <p>Con resultado cargado</p>
                </article>

                <article class="admin-stat-card warning">
                    <span>Suspendidos/cancelados</span>
                    <strong><?= h($totalSuspendidosCancelados) ?></strong>
                    <p>Sin actividad normal</p>
                </article>

            </section>

            <section class="admin-dashboard-grid">

                <!-- FORMULARIO -->
                <article class="admin-panel">
                    <div class="admin-panel-header">
                        <div>
                            <span><?= $partidoEditar ? 'Editar partido' : 'Nuevo partido' ?></span>
                            <h2><?= $partidoEditar ? 'Modificar partido' : 'Crear partido' ?></h2>
                        </div>

                        <?php if ($partidoEditar): ?>
                            <a href="<?= BASE_URL ?>/admin/partidos.php<?= $idFechaFiltro ? '?fecha=' . h($idFechaFiltro) : '' ?>">
                                Cancelar edición
                            </a>
                        <?php endif; ?>
                    </div>

                    <form method="POST" class="admin-form">

                        <?php if ($partidoEditar): ?>
                            <input type="hidden" name="accion" value="actualizar">
                            <input type="hidden" name="id_partido" value="<?= h($partidoEditar['id_partido']) ?>">
                        <?php else: ?>
                            <input type="hidden" name="accion" value="crear">
                        <?php endif; ?>

                        <div class="form-group">
                            <label for="id_fecha">Fecha / fase</label>
                            <?php
                            $fechaSeleccionadaForm = $partidoEditar['id_fecha'] ?? ($idFechaFiltro > 0 ? $idFechaFiltro : $fechas[0]['id_fecha']);
                            ?>

                            <select id="id_fecha" name="id_fecha" required>
                                <?php foreach ($fechas as $fecha): ?>
                                    <?php
                                    $nombreFecha = $fecha['nombre'] ?: 'Fecha ' . $fecha['numero_fecha'];
                                    ?>
                                    <option
                                        value="<?= h($fecha['id_fecha']) ?>"
                                        <?= (int)$fechaSeleccionadaForm === (int)$fecha['id_fecha'] ? 'selected' : '' ?>
                                    >
                                        #<?= h($fecha['numero_fecha']) ?>
                                        · <?= h($nombreFecha) ?>
                                        · <?= h($fecha['fase_nombre']) ?>
                                        <?= $fecha['fase_tipo'] === 'eliminatoria' ? '(Eliminatoria)' : '(Regular)' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <small class="form-help">
                                La fase se toma automáticamente de la fecha elegida.
                            </small>
                        </div>

                        <div class="form-grid-2">
                            <div class="form-group">
                                <label for="id_equipo_local">Equipo local</label>
                                <?php $localForm = $partidoEditar['id_equipo_local'] ?? ''; ?>

                                <select id="id_equipo_local" name="id_equipo_local" required>
                                    <option value="">Seleccionar local</option>
                                    <?php foreach ($equipos as $equipo): ?>
                                        <option
                                            value="<?= h($equipo['id_equipo_torneo']) ?>"
                                            <?= (int)$localForm === (int)$equipo['id_equipo_torneo'] ? 'selected' : '' ?>
                                        >
                                            <?= h($equipo['nombre']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="id_equipo_visitante">Equipo visitante</label>
                                <?php $visitanteForm = $partidoEditar['id_equipo_visitante'] ?? ''; ?>

                                <select id="id_equipo_visitante" name="id_equipo_visitante" required>
                                    <option value="">Seleccionar visitante</option>
                                    <?php foreach ($equipos as $equipo): ?>
                                        <option
                                            value="<?= h($equipo['id_equipo_torneo']) ?>"
                                            <?= (int)$visitanteForm === (int)$equipo['id_equipo_torneo'] ? 'selected' : '' ?>
                                        >
                                            <?= h($equipo['nombre']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-grid-2">
                            <div class="form-group">
                                <label for="fecha_hora">Día y hora</label>
                                <input
                                    type="datetime-local"
                                    id="fecha_hora"
                                    name="fecha_hora"
                                    value="<?= h(valorInputFechaHora($partidoEditar['fecha_hora'] ?? '')) ?>"
                                >
                            </div>

                            <div class="form-group">
                                <label for="cancha">Cancha</label>
                                <input
                                    type="text"
                                    id="cancha"
                                    name="cancha"
                                    placeholder="Ej: Cancha principal"
                                    value="<?= h($partidoEditar['cancha'] ?? '') ?>"
                                >
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="estado">Estado</label>
                            <?php $estadoForm = $partidoEditar['estado'] ?? 'programado'; ?>

                            <select id="estado" name="estado" required>
                                <option value="programado" <?= $estadoForm === 'programado' ? 'selected' : '' ?>>
                                    Programado
                                </option>
                                <option value="en_juego" <?= $estadoForm === 'en_juego' ? 'selected' : '' ?>>
                                    En juego
                                </option>
                                <option value="suspendido" <?= $estadoForm === 'suspendido' ? 'selected' : '' ?>>
                                    Suspendido
                                </option>
                                <option value="cancelado" <?= $estadoForm === 'cancelado' ? 'selected' : '' ?>>
                                    Cancelado
                                </option>

                                <?php if ($partidoEditar && partidoTieneDatos($partidoEditar['id_partido'])): ?>
                                    <option value="finalizado" <?= $estadoForm === 'finalizado' ? 'selected' : '' ?>>
                                        Finalizado
                                    </option>
                                <?php endif; ?>
                            </select>

                            <small class="form-help">
                                El estado finalizado se usa cuando el resultado ya fue cargado.
                            </small>
                        </div>

                        <div class="form-group">
                            <label for="observaciones">Observaciones</label>
                            <textarea
                                id="observaciones"
                                name="observaciones"
                                rows="4"
                                placeholder="Opcional"
                            ><?= h($partidoEditar['observaciones'] ?? '') ?></textarea>
                        </div>

                        <button type="submit" class="admin-btn admin-btn-primary">
                            <?= $partidoEditar ? 'Guardar cambios' : 'Crear partido' ?>
                        </button>

                    </form>
                </article>

                <!-- AYUDA -->
                <article class="admin-panel">
                    <div class="admin-panel-header">
                        <div>
                            <span>Importante</span>
                            <h2>Cómo se cargan</h2>
                        </div>
                    </div>

                    <div class="admin-help-list">
                        <div>
                            <strong>1</strong>
                            <p>Elegís una fecha. Esa fecha ya tiene asociada su fase: regular o eliminatoria.</p>
                        </div>

                        <div>
                            <strong>2</strong>
                            <p>El admin arma manualmente los cruces, tanto de fase regular como de eliminatorias.</p>
                        </div>

                        <div>
                            <strong>3</strong>
                            <p>Los goles, asistencia, tarjetas y penales se cargan después desde Resultados.</p>
                        </div>

                        <div>
                            <strong>4</strong>
                            <p>Si el partido ya tiene datos cargados, no se permite cambiar equipos ni fecha.</p>
                        </div>
                    </div>
                </article>

            </section>

            <!-- FILTROS -->
            <section class="admin-section">
                <div class="admin-section-header">
                    <div>
                        <span>Buscar y filtrar</span>
                        <h2>Listado de partidos</h2>
                    </div>
                </div>

                <form method="GET" class="admin-filter-form partidos-filter-form">
                    <div class="form-group">
                        <label for="fecha">Fecha</label>
                        <select id="fecha" name="fecha">
                            <option value="0">Todas las fechas</option>
                            <?php foreach ($fechas as $fecha): ?>
                                <?php $nombreFecha = $fecha['nombre'] ?: 'Fecha ' . $fecha['numero_fecha']; ?>
                                <option
                                    value="<?= h($fecha['id_fecha']) ?>"
                                    <?= (int)$idFechaFiltro === (int)$fecha['id_fecha'] ? 'selected' : '' ?>
                                >
                                    #<?= h($fecha['numero_fecha']) ?> · <?= h($nombreFecha) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="estado_filtro">Estado</label>
                        <select id="estado_filtro" name="estado">
                            <option value="todos" <?= $estadoFiltro === 'todos' ? 'selected' : '' ?>>Todos</option>
                            <option value="programado" <?= $estadoFiltro === 'programado' ? 'selected' : '' ?>>Programados</option>
                            <option value="en_juego" <?= $estadoFiltro === 'en_juego' ? 'selected' : '' ?>>En juego</option>
                            <option value="finalizado" <?= $estadoFiltro === 'finalizado' ? 'selected' : '' ?>>Finalizados</option>
                            <option value="suspendido" <?= $estadoFiltro === 'suspendido' ? 'selected' : '' ?>>Suspendidos</option>
                            <option value="cancelado" <?= $estadoFiltro === 'cancelado' ? 'selected' : '' ?>>Cancelados</option>
                        </select>
                    </div>

                    <div class="filter-actions">
                        <button type="submit" class="admin-btn admin-btn-primary">
                            Filtrar
                        </button>

                        <a href="<?= BASE_URL ?>/admin/partidos.php" class="admin-btn admin-btn-secondary">
                            Limpiar
                        </a>
                    </div>
                </form>

                <?php if (empty($partidos)): ?>

                    <div class="admin-empty-box">
                        No hay partidos para mostrar con los filtros actuales.
                    </div>

                <?php else: ?>

                    <div class="admin-table-wrapper">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Fase</th>
                                    <th>Partido</th>
                                    <th>Marcador</th>
                                    <th>Día y hora</th>
                                    <th>Cancha</th>
                                    <th>Estado</th>
                                    <th>Datos</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php foreach ($partidos as $partido): ?>
                                    <?php
                                    $tieneDatos = partidoTieneDatos($partido['id_partido']);
                                    $nombreFecha = $partido['fecha_nombre'] ?: 'Fecha ' . $partido['numero_fecha'];
                                    ?>

                                    <tr>
                                        <td>
                                            <strong>#<?= h($partido['numero_fecha']) ?></strong>
                                            <small><?= h($nombreFecha) ?></small>
                                        </td>

                                        <td>
                                            <strong><?= h($partido['fase_nombre']) ?></strong>
                                            <small><?= h($partido['fase_tipo'] === 'eliminatoria' ? 'Eliminatoria' : 'Regular') ?></small>
                                        </td>

                                        <td>
                                            <div class="match-teams-cell">
                                                <div class="match-team-mini">
                                                    <div class="team-shield-table small">
                                                        <?php if (!empty($partido['escudo_local'])): ?>
                                                            <img src="<?= h(asset($partido['escudo_local'])) ?>" alt="Escudo <?= h($partido['equipo_local']) ?>">
                                                        <?php else: ?>
                                                            <span>👕</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <strong><?= h($partido['equipo_local']) ?></strong>
                                                </div>

                                                <em>vs</em>

                                                <div class="match-team-mini">
                                                    <div class="team-shield-table small">
                                                        <?php if (!empty($partido['escudo_visitante'])): ?>
                                                            <img src="<?= h(asset($partido['escudo_visitante'])) ?>" alt="Escudo <?= h($partido['equipo_visitante']) ?>">
                                                        <?php else: ?>
                                                            <span>👕</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <strong><?= h($partido['equipo_visitante']) ?></strong>
                                                </div>
                                            </div>
                                        </td>

                                        <td>
                                            <strong><?= h(marcadorPartido($partido)) ?></strong>
                                        </td>

                                        <td>
                                            <?= h(formatearFechaHora($partido['fecha_hora'])) ?>
                                        </td>

                                        <td>
                                            <?= h($partido['cancha'] ?: 'Sin cancha') ?>
                                        </td>

                                        <td>
                                            <span class="status-badge <?= h(claseEstadoPartido($partido['estado'])) ?>">
                                                <?= h(textoEstadoPartido($partido['estado'])) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <strong><?= h($partido['total_jugadores_cargados']) ?></strong>
                                            <small>Jugadores cargados</small>
                                        </td>

                                        <td>
                                            <div class="table-actions">
                                                <a
                                                    href="<?= BASE_URL ?>/admin/partidos.php?editar=<?= h($partido['id_partido']) ?>&fecha=<?= h($partido['id_fecha']) ?>"
                                                    class="table-btn edit"
                                                >
                                                    Editar
                                                </a>

                                                <a
                                                    href="<?= BASE_URL ?>/admin/resultados.php?partido=<?= h($partido['id_partido']) ?>"
                                                    class="table-btn info"
                                                >
                                                    Resultado
                                                </a>

                                                <?php if (!$tieneDatos): ?>
                                                    <form method="POST">
                                                        <input type="hidden" name="accion" value="eliminar">
                                                        <input type="hidden" name="id_partido" value="<?= h($partido['id_partido']) ?>">
                                                        <input type="hidden" name="id_fecha" value="<?= h($partido['id_fecha']) ?>">

                                                        <button
                                                            type="submit"
                                                            class="table-btn delete"
                                                            onclick="return confirm('¿Seguro que querés eliminar este partido?');"
                                                        >
                                                            Eliminar
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="table-muted">
                                                        Tiene datos
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