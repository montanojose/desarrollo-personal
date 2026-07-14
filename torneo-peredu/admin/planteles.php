<?php
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = "Planteles - Panel Admin";
$pageDescription = "Administración de planteles Copa PER.EDU";

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

function obtenerEquiposActivosDelTorneo($idTorneo)
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
            e.escudo
        FROM equipo_torneo et
        INNER JOIN equipos e
            ON et.id_equipo = e.id_equipo
        WHERE et.id_torneo = ?
        ORDER BY et.activo DESC, e.nombre ASC
    ", [$idTorneo]);
}

function obtenerEquipoTorneoPlantel($idEquipoTorneo, $idTorneo)
{
    return dbOne("
        SELECT
            et.id_equipo_torneo,
            et.id_torneo,
            et.activo AS activo_en_torneo,
            e.id_equipo,
            e.nombre,
            e.escuela,
            e.escudo
        FROM equipo_torneo et
        INNER JOIN equipos e
            ON et.id_equipo = e.id_equipo
        WHERE et.id_equipo_torneo = ?
          AND et.id_torneo = ?
        LIMIT 1
    ", [$idEquipoTorneo, $idTorneo]);
}

function obtenerPlantelEquipo($idEquipoTorneo)
{
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
            j.fecha_nacimiento,
            j.activo AS activo_jugador,

            (
                SELECT COUNT(*)
                FROM jugador_partido jp
                WHERE jp.id_jugador_equipo_torneo = jet.id_jugador_equipo_torneo
            ) AS total_partidos,

            (
                SELECT COALESCE(SUM(jp.goles), 0)
                FROM jugador_partido jp
                WHERE jp.id_jugador_equipo_torneo = jet.id_jugador_equipo_torneo
            ) AS total_goles,

            (
                SELECT COALESCE(SUM(jp.amarillas), 0)
                FROM jugador_partido jp
                WHERE jp.id_jugador_equipo_torneo = jet.id_jugador_equipo_torneo
            ) AS total_amarillas,

            (
                SELECT COALESCE(SUM(jp.rojas), 0)
                FROM jugador_partido jp
                WHERE jp.id_jugador_equipo_torneo = jet.id_jugador_equipo_torneo
            ) AS total_rojas

        FROM jugador_equipo_torneo jet
        INNER JOIN jugadores j
            ON jet.id_jugador = j.id_jugador
        WHERE jet.id_equipo_torneo = ?
        ORDER BY jet.activo DESC, j.apellido ASC, j.nombre ASC
    ", [$idEquipoTorneo]);
}

function obtenerJugadoresDisponiblesParaTorneo($idTorneo, $idEquipoTorneo)
{
    return dbAll("
        SELECT
            j.id_jugador,
            j.nombre,
            j.apellido,
            j.dni,
            j.activo,

            jet_actual.id_jugador_equipo_torneo AS ya_en_este_equipo,
            jet_actual.numero_camiseta AS camiseta_actual,
            jet_actual.activo AS activo_en_este_equipo,

            e_otro.nombre AS equipo_activo_actual

        FROM jugadores j

        LEFT JOIN jugador_equipo_torneo jet_actual
            ON jet_actual.id_jugador = j.id_jugador
           AND jet_actual.id_equipo_torneo = ?

        LEFT JOIN jugador_equipo_torneo jet_otro
            ON jet_otro.id_jugador = j.id_jugador
           AND jet_otro.activo = 1
           AND jet_otro.id_equipo_torneo <> ?

        LEFT JOIN equipo_torneo et_otro
            ON jet_otro.id_equipo_torneo = et_otro.id_equipo_torneo
           AND et_otro.id_torneo = ?

        LEFT JOIN equipos e_otro
            ON et_otro.id_equipo = e_otro.id_equipo

        WHERE j.activo = 1
          AND (
                et_otro.id_equipo_torneo IS NULL
                OR jet_actual.id_jugador_equipo_torneo IS NOT NULL
          )

        ORDER BY j.apellido ASC, j.nombre ASC
    ", [$idEquipoTorneo, $idEquipoTorneo, $idTorneo]);
}

function obtenerRelacionPlantelPorId($idJugadorEquipoTorneo, $idTorneo)
{
    return dbOne("
        SELECT
            jet.*,
            j.nombre,
            j.apellido,
            j.dni,
            j.activo AS activo_jugador,
            et.id_torneo,
            e.nombre AS equipo
        FROM jugador_equipo_torneo jet
        INNER JOIN jugadores j
            ON jet.id_jugador = j.id_jugador
        INNER JOIN equipo_torneo et
            ON jet.id_equipo_torneo = et.id_equipo_torneo
        INNER JOIN equipos e
            ON et.id_equipo = e.id_equipo
        WHERE jet.id_jugador_equipo_torneo = ?
          AND et.id_torneo = ?
        LIMIT 1
    ", [$idJugadorEquipoTorneo, $idTorneo]);
}

function jugadorActivoEnOtroEquipoDelTorneo($idJugador, $idTorneo, $idEquipoTorneoActual)
{
    $resultado = dbOne("
        SELECT
            e.nombre AS equipo
        FROM jugador_equipo_torneo jet
        INNER JOIN equipo_torneo et
            ON jet.id_equipo_torneo = et.id_equipo_torneo
        INNER JOIN equipos e
            ON et.id_equipo = e.id_equipo
        WHERE jet.id_jugador = ?
          AND et.id_torneo = ?
          AND jet.id_equipo_torneo <> ?
          AND jet.activo = 1
        LIMIT 1
    ", [$idJugador, $idTorneo, $idEquipoTorneoActual]);

    return $resultado ?: null;
}

function obtenerRelacionJugadorEquipo($idJugador, $idEquipoTorneo)
{
    return dbOne("
        SELECT *
        FROM jugador_equipo_torneo
        WHERE id_jugador = ?
          AND id_equipo_torneo = ?
        LIMIT 1
    ", [$idJugador, $idEquipoTorneo]);
}

function relacionPlantelTieneHistorial($idJugadorEquipoTorneo)
{
    $resultado = dbOne("
        SELECT COUNT(*) AS total
        FROM jugador_partido
        WHERE id_jugador_equipo_torneo = ?
    ", [$idJugadorEquipoTorneo]);

    return (int)$resultado['total'] > 0;
}

function existeCamisetaActivaEnEquipo($idEquipoTorneo, $numeroCamiseta, $idRelacionExcluir = 0)
{
    if ($numeroCamiseta === null || $numeroCamiseta === '') {
        return false;
    }

    $resultado = dbOne("
        SELECT COUNT(*) AS total
        FROM jugador_equipo_torneo
        WHERE id_equipo_torneo = ?
          AND numero_camiseta = ?
          AND activo = 1
          AND id_jugador_equipo_torneo <> ?
    ", [$idEquipoTorneo, $numeroCamiseta, $idRelacionExcluir]);

    return (int)$resultado['total'] > 0;
}

function normalizarCamiseta($numero)
{
    $numero = trim((string)$numero);

    if ($numero === '') {
        return null;
    }

    if (!ctype_digit($numero)) {
        throw new Exception('El número de camiseta debe ser un número entero positivo.');
    }

    $numero = (int)$numero;

    if ($numero < 1 || $numero > 999) {
        throw new Exception('El número de camiseta debe estar entre 1 y 999.');
    }

    return $numero;
}

function textoActivoPlantel($activo)
{
    return (int)$activo === 1 ? 'Activo' : 'Inactivo';
}

function claseActivoPlantel($activo)
{
    return (int)$activo === 1 ? 'badge-green' : 'badge-gray';
}

function inicialesPlantel($nombre, $apellido)
{
    $inicialNombre = mb_substr(trim($nombre), 0, 1, 'UTF-8');
    $inicialApellido = mb_substr(trim($apellido), 0, 1, 'UTF-8');

    return mb_strtoupper($inicialNombre . $inicialApellido, 'UTF-8');
}

/* =====================================================
   TORNEO ACTUAL Y EQUIPO SELECCIONADO
===================================================== */

$torneoActual = obtenerTorneoActual();
$idTorneoActual = $torneoActual['id_torneo'] ?? null;

$equipos = obtenerEquiposActivosDelTorneo($idTorneoActual);

$idEquipoSeleccionado = isset($_GET['equipo']) ? (int)$_GET['equipo'] : 0;

if ($idEquipoSeleccionado <= 0 && !empty($equipos)) {
    $idEquipoSeleccionado = (int)$equipos[0]['id_equipo_torneo'];
}

$equipoSeleccionado = null;

if ($idTorneoActual && $idEquipoSeleccionado > 0) {
    $equipoSeleccionado = obtenerEquipoTorneoPlantel($idEquipoSeleccionado, $idTorneoActual);

    if (!$equipoSeleccionado) {
        $idEquipoSeleccionado = !empty($equipos) ? (int)$equipos[0]['id_equipo_torneo'] : 0;
        $equipoSeleccionado = $idEquipoSeleccionado > 0
            ? obtenerEquipoTorneoPlantel($idEquipoSeleccionado, $idTorneoActual)
            : null;
    }
}

/* =====================================================
   PROCESAR FORMULARIOS
===================================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    try {
        if (!$idTorneoActual) {
            throw new Exception('Primero tenés que crear un torneo.');
        }

        $idEquipoPost = (int)($_POST['id_equipo_torneo'] ?? $idEquipoSeleccionado);

        if ($idEquipoPost <= 0) {
            throw new Exception('Primero tenés que elegir un equipo.');
        }

        $equipoPost = obtenerEquipoTorneoPlantel($idEquipoPost, $idTorneoActual);

        if (!$equipoPost) {
            throw new Exception('El equipo seleccionado no pertenece al torneo actual.');
        }

        if ($accion === 'agregar') {
            $idJugador = (int)($_POST['id_jugador'] ?? 0);
            $numeroCamiseta = normalizarCamiseta($_POST['numero_camiseta'] ?? '');

            if ($idJugador <= 0) {
                throw new Exception('Tenés que seleccionar un jugador.');
            }

            $jugador = dbOne("
                SELECT *
                FROM jugadores
                WHERE id_jugador = ?
                  AND activo = 1
                LIMIT 1
            ", [$idJugador]);

            if (!$jugador) {
                throw new Exception('El jugador seleccionado no existe o está inactivo.');
            }

            $equipoActivo = jugadorActivoEnOtroEquipoDelTorneo($idJugador, $idTorneoActual, $idEquipoPost);

            if ($equipoActivo) {
                throw new Exception('Este jugador ya está activo en otro equipo del torneo: ' . $equipoActivo['equipo'] . '.');
            }

            if (existeCamisetaActivaEnEquipo($idEquipoPost, $numeroCamiseta)) {
                throw new Exception('Ya hay un jugador activo con ese número de camiseta en este equipo.');
            }

            $relacionExistente = obtenerRelacionJugadorEquipo($idJugador, $idEquipoPost);

            if ($relacionExistente) {
                dbQuery("
                    UPDATE jugador_equipo_torneo
                    SET numero_camiseta = ?,
                        activo = 1
                    WHERE id_jugador_equipo_torneo = ?
                ", [$numeroCamiseta, $relacionExistente['id_jugador_equipo_torneo']]);

                adminSetFlash('success', 'El jugador ya estaba en este plantel: se reactivó y se actualizaron sus datos.');
                header('Location: planteles.php?equipo=' . $idEquipoPost);
                exit;
            }

            dbQuery("
                INSERT INTO jugador_equipo_torneo (
                    id_jugador,
                    id_equipo_torneo,
                    numero_camiseta,
                    activo
                )
                VALUES (?, ?, ?, 1)
            ", [$idJugador, $idEquipoPost, $numeroCamiseta]);

            adminSetFlash('success', 'Jugador agregado al plantel correctamente.');
            header('Location: planteles.php?equipo=' . $idEquipoPost);
            exit;
        }

        if ($accion === 'actualizar') {
            $idRelacion = (int)($_POST['id_jugador_equipo_torneo'] ?? 0);
            $numeroCamiseta = normalizarCamiseta($_POST['numero_camiseta'] ?? '');
            $activo = (int)($_POST['activo'] ?? 1);

            if ($idRelacion <= 0) {
                throw new Exception('La relación de plantel seleccionada no es válida.');
            }

            $relacion = obtenerRelacionPlantelPorId($idRelacion, $idTorneoActual);

            if (!$relacion) {
                throw new Exception('El jugador no pertenece al torneo actual.');
            }

            $idEquipoRelacion = (int)$relacion['id_equipo_torneo'];

            if ($activo === 1) {
                $equipoActivo = jugadorActivoEnOtroEquipoDelTorneo(
                    (int)$relacion['id_jugador'],
                    $idTorneoActual,
                    $idEquipoRelacion
                );

                if ($equipoActivo) {
                    throw new Exception('No se puede activar: el jugador ya está activo en otro equipo del torneo: ' . $equipoActivo['equipo'] . '.');
                }

                if (existeCamisetaActivaEnEquipo($idEquipoRelacion, $numeroCamiseta, $idRelacion)) {
                    throw new Exception('Ya hay otro jugador activo con ese número de camiseta en este equipo.');
                }
            }

            dbQuery("
                UPDATE jugador_equipo_torneo
                SET numero_camiseta = ?,
                    activo = ?
                WHERE id_jugador_equipo_torneo = ?
            ", [$numeroCamiseta, $activo, $idRelacion]);

            adminSetFlash('success', 'Datos del jugador en el plantel actualizados correctamente.');
            header('Location: planteles.php?equipo=' . $idEquipoRelacion);
            exit;
        }

        if ($accion === 'desactivar') {
            $idRelacion = (int)($_POST['id_jugador_equipo_torneo'] ?? 0);

            $relacion = obtenerRelacionPlantelPorId($idRelacion, $idTorneoActual);

            if (!$relacion) {
                throw new Exception('La relación de plantel seleccionada no existe.');
            }

            dbQuery("
                UPDATE jugador_equipo_torneo
                SET activo = 0
                WHERE id_jugador_equipo_torneo = ?
            ", [$idRelacion]);

            adminSetFlash('success', 'Jugador desactivado del plantel correctamente.');
            header('Location: planteles.php?equipo=' . $relacion['id_equipo_torneo']);
            exit;
        }

        if ($accion === 'activar') {
            $idRelacion = (int)($_POST['id_jugador_equipo_torneo'] ?? 0);

            $relacion = obtenerRelacionPlantelPorId($idRelacion, $idTorneoActual);

            if (!$relacion) {
                throw new Exception('La relación de plantel seleccionada no existe.');
            }

            if ((int)$relacion['activo_jugador'] !== 1) {
                throw new Exception('No se puede activar en el plantel porque el jugador está inactivo en el sistema.');
            }

            $equipoActivo = jugadorActivoEnOtroEquipoDelTorneo(
                (int)$relacion['id_jugador'],
                $idTorneoActual,
                (int)$relacion['id_equipo_torneo']
            );

            if ($equipoActivo) {
                throw new Exception('No se puede activar: el jugador ya está activo en otro equipo del torneo: ' . $equipoActivo['equipo'] . '.');
            }

            if (existeCamisetaActivaEnEquipo(
                (int)$relacion['id_equipo_torneo'],
                $relacion['numero_camiseta'],
                $idRelacion
            )) {
                throw new Exception('No se puede activar: ya hay otro jugador activo con ese número de camiseta.');
            }

            dbQuery("
                UPDATE jugador_equipo_torneo
                SET activo = 1
                WHERE id_jugador_equipo_torneo = ?
            ", [$idRelacion]);

            adminSetFlash('success', 'Jugador activado en el plantel correctamente.');
            header('Location: planteles.php?equipo=' . $relacion['id_equipo_torneo']);
            exit;
        }

        if ($accion === 'quitar') {
            $idRelacion = (int)($_POST['id_jugador_equipo_torneo'] ?? 0);

            $relacion = obtenerRelacionPlantelPorId($idRelacion, $idTorneoActual);

            if (!$relacion) {
                throw new Exception('La relación de plantel seleccionada no existe.');
            }

            if (relacionPlantelTieneHistorial($idRelacion)) {
                dbQuery("
                    UPDATE jugador_equipo_torneo
                    SET activo = 0
                    WHERE id_jugador_equipo_torneo = ?
                ", [$idRelacion]);

                adminSetFlash('success', 'El jugador ya tiene historial en partidos, por eso no se eliminó: quedó inactivo en este plantel.');
                header('Location: planteles.php?equipo=' . $relacion['id_equipo_torneo']);
                exit;
            }

            dbQuery("
                DELETE FROM jugador_equipo_torneo
                WHERE id_jugador_equipo_torneo = ?
            ", [$idRelacion]);

            adminSetFlash('success', 'Jugador quitado del plantel correctamente.');
            header('Location: planteles.php?equipo=' . $relacion['id_equipo_torneo']);
            exit;
        }

    } catch (Exception $e) {
        adminSetFlash('error', $e->getMessage());
        $redirectEquipo = isset($_POST['id_equipo_torneo']) ? (int)$_POST['id_equipo_torneo'] : $idEquipoSeleccionado;
        header('Location: planteles.php?equipo=' . $redirectEquipo);
        exit;
    }
}

/* =====================================================
   DATOS PARA MOSTRAR
===================================================== */

$flash = adminGetFlash();

$plantel = $equipoSeleccionado
    ? obtenerPlantelEquipo($equipoSeleccionado['id_equipo_torneo'])
    : [];

$jugadoresDisponibles = $equipoSeleccionado
    ? obtenerJugadoresDisponiblesParaTorneo($idTorneoActual, $equipoSeleccionado['id_equipo_torneo'])
    : [];

$idEditar = isset($_GET['editar']) ? (int)$_GET['editar'] : 0;
$relacionEditar = null;

if ($idEditar > 0 && $idTorneoActual) {
    $relacionEditar = obtenerRelacionPlantelPorId($idEditar, $idTorneoActual);

    if (!$relacionEditar) {
        adminSetFlash('error', 'El jugador del plantel que querés editar no existe.');
        header('Location: planteles.php?equipo=' . $idEquipoSeleccionado);
        exit;
    }
}

$totalActivosPlantel = 0;
$totalInactivosPlantel = 0;

foreach ($plantel as $item) {
    if ((int)$item['activo_plantel'] === 1) {
        $totalActivosPlantel++;
    } else {
        $totalInactivosPlantel++;
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
            <a class="active" href="<?= BASE_URL ?>/admin/planteles.php">📋 Planteles</a>
            <a href="<?= BASE_URL ?>/admin/fases.php">🧩 Fases</a>
            <a href="<?= BASE_URL ?>/admin/fechas.php">📅 Fechas</a>
            <a href="<?= BASE_URL ?>/admin/partidos.php">⚽ Partidos</a>
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
                <span class="admin-eyebrow">Gestión del torneo</span>
                <h1>Planteles</h1>

                <?php if ($torneoActual): ?>
                    <p>
                        Torneo actual:
                        <strong><?= h($torneoActual['nombre']) ?></strong>
                        · Temporada <?= h($torneoActual['temporada']) ?>
                    </p>
                <?php else: ?>
                    <p>Primero tenés que crear un torneo para poder armar planteles.</p>
                <?php endif; ?>
            </div>

            <div class="admin-top-actions">
                <a href="<?= BASE_URL ?>/admin/jugadores.php" class="admin-btn admin-btn-primary">
                    Cargar jugadores
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
                <p>
                    Para armar planteles primero tenés que crear un torneo.
                </p>
                <a href="<?= BASE_URL ?>/admin/torneos.php" class="admin-btn admin-btn-primary">
                    Crear torneo
                </a>
            </section>

        <?php elseif (empty($equipos)): ?>

            <section class="admin-empty-main">
                <h2>No hay equipos cargados</h2>
                <p>
                    Para armar planteles primero tenés que cargar equipos en el torneo actual.
                </p>
                <a href="<?= BASE_URL ?>/admin/equipos.php" class="admin-btn admin-btn-primary">
                    Cargar equipos
                </a>
            </section>

        <?php else: ?>

            <!-- SELECTOR DE EQUIPO -->
            <section class="admin-section">
                <div class="admin-section-header">
                    <div>
                        <span>Equipo seleccionado</span>
                        <h2>Elegir equipo del torneo</h2>
                    </div>
                </div>

                <form method="GET" class="plantel-team-selector">
                    <div class="form-group">
                        <label for="equipo">Equipo</label>
                        <select id="equipo" name="equipo" onchange="this.form.submit()">
                            <?php foreach ($equipos as $equipo): ?>
                                <option
                                    value="<?= h($equipo['id_equipo_torneo']) ?>"
                                    <?= (int)$equipo['id_equipo_torneo'] === (int)$idEquipoSeleccionado ? 'selected' : '' ?>
                                >
                                    <?= h($equipo['nombre']) ?>
                                    <?= (int)$equipo['activo_en_torneo'] === 0 ? ' - Inactivo' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <noscript>
                        <button type="submit" class="admin-btn admin-btn-primary">Ver plantel</button>
                    </noscript>
                </form>

                <?php if ($equipoSeleccionado): ?>
                    <div class="plantel-selected-team">
                        <div class="team-shield-table">
                            <?php if (!empty($equipoSeleccionado['escudo'])): ?>
                                <img src="<?= h(asset($equipoSeleccionado['escudo'])) ?>" alt="Escudo <?= h($equipoSeleccionado['nombre']) ?>">
                            <?php else: ?>
                                <span>👕</span>
                            <?php endif; ?>
                        </div>

                        <div>
                            <strong><?= h($equipoSeleccionado['nombre']) ?></strong>
                            <small><?= h($equipoSeleccionado['escuela'] ?: 'Sin escuela cargada') ?></small>
                        </div>

                        <span class="status-badge <?= h(claseActivoPlantel($equipoSeleccionado['activo_en_torneo'])) ?>">
                            <?= h(textoActivoPlantel($equipoSeleccionado['activo_en_torneo'])) ?>
                        </span>
                    </div>
                <?php endif; ?>
            </section>

            <!-- RESUMEN -->
            <section class="admin-stats-grid admin-stats-grid-compact">

                <article class="admin-stat-card">
                    <span>Total plantel</span>
                    <strong><?= h(count($plantel)) ?></strong>
                    <p>Jugadores asociados</p>
                </article>

                <article class="admin-stat-card">
                    <span>Activos</span>
                    <strong><?= h($totalActivosPlantel) ?></strong>
                    <p>Disponibles para partidos</p>
                </article>

                <article class="admin-stat-card warning">
                    <span>Inactivos</span>
                    <strong><?= h($totalInactivosPlantel) ?></strong>
                    <p>Conservan historial</p>
                </article>

                <article class="admin-stat-card">
                    <span>Disponibles</span>
                    <strong><?= h(count($jugadoresDisponibles)) ?></strong>
                    <p>Para agregar al equipo</p>
                </article>

            </section>

            <section class="admin-dashboard-grid">

                <!-- FORMULARIO AGREGAR / EDITAR -->
                <article class="admin-panel">
                    <div class="admin-panel-header">
                        <div>
                            <span><?= $relacionEditar ? 'Editar jugador del plantel' : 'Agregar jugador' ?></span>
                            <h2><?= $relacionEditar ? 'Modificar datos' : 'Sumar al plantel' ?></h2>
                        </div>

                        <?php if ($relacionEditar): ?>
                            <a href="<?= BASE_URL ?>/admin/planteles.php?equipo=<?= h($relacionEditar['id_equipo_torneo']) ?>">
                                Cancelar edición
                            </a>
                        <?php endif; ?>
                    </div>

                    <form method="POST" class="admin-form">

                        <?php if ($relacionEditar): ?>
                            <input type="hidden" name="accion" value="actualizar">
                            <input type="hidden" name="id_equipo_torneo" value="<?= h($relacionEditar['id_equipo_torneo']) ?>">
                            <input type="hidden" name="id_jugador_equipo_torneo" value="<?= h($relacionEditar['id_jugador_equipo_torneo']) ?>">
                        <?php else: ?>
                            <input type="hidden" name="accion" value="agregar">
                            <input type="hidden" name="id_equipo_torneo" value="<?= h($idEquipoSeleccionado) ?>">
                        <?php endif; ?>

                        <?php if ($relacionEditar): ?>

                            <div class="form-group">
                                <label>Jugador</label>
                                <div class="plantel-edit-player">
                                    <div class="player-avatar">
                                        <?= h(inicialesPlantel($relacionEditar['nombre'], $relacionEditar['apellido'])) ?>
                                    </div>

                                    <div>
                                        <strong><?= h($relacionEditar['apellido']) ?>, <?= h($relacionEditar['nombre']) ?></strong>
                                        <small>Equipo: <?= h($relacionEditar['equipo']) ?></small>
                                    </div>
                                </div>
                            </div>

                        <?php else: ?>

                            <div class="form-group">
                                <label for="id_jugador">Jugador cargado previamente</label>
                                <select id="id_jugador" name="id_jugador" required>
                                    <option value="">Seleccionar jugador</option>

                                    <?php foreach ($jugadoresDisponibles as $jugador): ?>
                                        <option value="<?= h($jugador['id_jugador']) ?>">
                                            <?= h($jugador['apellido']) ?>, <?= h($jugador['nombre']) ?>
                                            <?= !empty($jugador['dni']) ? ' - DNI ' . h($jugador['dni']) : '' ?>
                                            <?= $jugador['ya_en_este_equipo'] ? ' - Ya estuvo en este equipo' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                                <small class="form-help">
                                    Solo aparecen jugadores activos que no estén activos en otro equipo del mismo torneo.
                                </small>
                            </div>

                        <?php endif; ?>

                        <div class="form-group">
                            <label for="numero_camiseta">Número de camiseta</label>
                            <input
                                type="number"
                                id="numero_camiseta"
                                name="numero_camiseta"
                                min="1"
                                max="999"
                                placeholder="Ej: 10"
                                value="<?= h($relacionEditar['numero_camiseta'] ?? '') ?>"
                            >
                            <small class="form-help">
                                Opcional. Si se carga, no puede repetirse entre jugadores activos del mismo equipo.
                            </small>
                        </div>

                        <?php if ($relacionEditar): ?>
                            <div class="form-group">
                                <label for="activo">Estado en el plantel</label>
                                <select id="activo" name="activo">
                                    <option value="1" <?= (int)$relacionEditar['activo'] === 1 ? 'selected' : '' ?>>
                                        Activo
                                    </option>
                                    <option value="0" <?= (int)$relacionEditar['activo'] === 0 ? 'selected' : '' ?>>
                                        Inactivo
                                    </option>
                                </select>
                            </div>
                        <?php endif; ?>

                        <button type="submit" class="admin-btn admin-btn-primary">
                            <?= $relacionEditar ? 'Guardar cambios' : 'Agregar al plantel' ?>
                        </button>

                    </form>
                </article>

                <!-- AYUDA -->
                <article class="admin-panel">
                    <div class="admin-panel-header">
                        <div>
                            <span>Reglas del plantel</span>
                            <h2>Control automático</h2>
                        </div>
                    </div>

                    <div class="admin-help-list">
                        <div>
                            <strong>1</strong>
                            <p>Un jugador puede estar activo en un solo equipo dentro del mismo torneo.</p>
                        </div>

                        <div>
                            <strong>2</strong>
                            <p>El número de camiseta no es obligatorio, pero si se carga no se puede repetir en activos del mismo equipo.</p>
                        </div>

                        <div>
                            <strong>3</strong>
                            <p>Si el jugador no tiene historial, se puede quitar definitivamente del plantel.</p>
                        </div>

                        <div>
                            <strong>4</strong>
                            <p>Si ya jugó partidos, no se elimina: queda inactivo para conservar sus estadísticas.</p>
                        </div>
                    </div>
                </article>

            </section>

            <!-- LISTADO PLANTEL -->
            <section class="admin-section">
                <div class="admin-section-header">
                    <div>
                        <span>Listado</span>
                        <h2>Plantel de <?= h($equipoSeleccionado['nombre']) ?></h2>
                    </div>
                </div>

                <?php if (empty($plantel)): ?>

                    <div class="admin-empty-box">
                        Este equipo todavía no tiene jugadores asociados.
                    </div>

                <?php else: ?>

                    <div class="admin-table-wrapper">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Jugador</th>
                                    <th>DNI</th>
                                    <th>Camiseta</th>
                                    <th>Partidos</th>
                                    <th>Goles</th>
                                    <th>Tarjetas</th>
                                    <th>Estado jugador</th>
                                    <th>Estado plantel</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php foreach ($plantel as $jugador): ?>
                                    <?php
                                    $tieneHistorial = (int)$jugador['total_partidos'] > 0;
                                    ?>

                                    <tr>
                                        <td>
                                            <div class="player-cell">
                                                <div class="player-avatar">
                                                    <?= h(inicialesPlantel($jugador['nombre'], $jugador['apellido'])) ?>
                                                </div>

                                                <div>
                                                    <strong>
                                                        <?= h($jugador['apellido']) ?>, <?= h($jugador['nombre']) ?>
                                                    </strong>
                                                    <small>ID plantel: <?= h($jugador['id_jugador_equipo_torneo']) ?></small>
                                                </div>
                                            </div>
                                        </td>

                                        <td>
                                            <?= h($jugador['dni'] ?: 'Sin DNI') ?>
                                        </td>

                                        <td>
                                            <?= h($jugador['numero_camiseta'] ?: 'Sin número') ?>
                                        </td>

                                        <td>
                                            <?= h($jugador['total_partidos']) ?>
                                        </td>

                                        <td>
                                            <?= h($jugador['total_goles']) ?>
                                        </td>

                                        <td>
                                            <small>
                                                🟨 <?= h($jugador['total_amarillas']) ?>
                                                ·
                                                🟥 <?= h($jugador['total_rojas']) ?>
                                            </small>
                                        </td>

                                        <td>
                                            <span class="status-badge <?= h(claseActivoPlantel($jugador['activo_jugador'])) ?>">
                                                <?= h(textoActivoPlantel($jugador['activo_jugador'])) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <span class="status-badge <?= h(claseActivoPlantel($jugador['activo_plantel'])) ?>">
                                                <?= h(textoActivoPlantel($jugador['activo_plantel'])) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <div class="table-actions">
                                                <a
                                                    href="<?= BASE_URL ?>/admin/planteles.php?equipo=<?= h($idEquipoSeleccionado) ?>&editar=<?= h($jugador['id_jugador_equipo_torneo']) ?>"
                                                    class="table-btn edit"
                                                >
                                                    Editar
                                                </a>

                                                <?php if ((int)$jugador['activo_plantel'] === 1): ?>
                                                    <form method="POST">
                                                        <input type="hidden" name="accion" value="desactivar">
                                                        <input type="hidden" name="id_equipo_torneo" value="<?= h($idEquipoSeleccionado) ?>">
                                                        <input type="hidden" name="id_jugador_equipo_torneo" value="<?= h($jugador['id_jugador_equipo_torneo']) ?>">

                                                        <button
                                                            type="submit"
                                                            class="table-btn warning"
                                                            onclick="return confirm('¿Seguro que querés desactivar este jugador del plantel?');"
                                                        >
                                                            Desactivar
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <form method="POST">
                                                        <input type="hidden" name="accion" value="activar">
                                                        <input type="hidden" name="id_equipo_torneo" value="<?= h($idEquipoSeleccionado) ?>">
                                                        <input type="hidden" name="id_jugador_equipo_torneo" value="<?= h($jugador['id_jugador_equipo_torneo']) ?>">

                                                        <button type="submit" class="table-btn success">
                                                            Activar
                                                        </button>
                                                    </form>
                                                <?php endif; ?>

                                                <form method="POST">
                                                    <input type="hidden" name="accion" value="quitar">
                                                    <input type="hidden" name="id_equipo_torneo" value="<?= h($idEquipoSeleccionado) ?>">
                                                    <input type="hidden" name="id_jugador_equipo_torneo" value="<?= h($jugador['id_jugador_equipo_torneo']) ?>">

                                                    <button
                                                        type="submit"
                                                        class="table-btn delete"
                                                        onclick="return confirm('¿Seguro? Si el jugador ya tiene historial, quedará inactivo. Si no tiene historial, se quitará definitivamente del plantel.');"
                                                    >
                                                        Quitar
                                                    </button>
                                                </form>
                                            </div>

                                            <?php if ($tieneHistorial): ?>
                                                <small class="table-muted">
                                                    Tiene historial
                                                </small>
                                            <?php endif; ?>
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
