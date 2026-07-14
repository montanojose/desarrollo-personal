<?php
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = "Fechas - Panel Admin";
$pageDescription = "Administración de fechas Copa PER.EDU";

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

function obtenerFasesParaFechas($idTorneo)
{
    if (!$idTorneo) {
        return [];
    }

    return dbAll("
        SELECT
            id_fase,
            nombre,
            tipo,
            orden
        FROM fases
        WHERE id_torneo = ?
        ORDER BY orden ASC, id_fase ASC
    ", [$idTorneo]);
}

function obtenerFechasDelTorneo($idTorneo)
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
            f.orden AS fase_orden,

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
        ORDER BY fe.numero_fecha ASC, f.orden ASC, fe.id_fecha ASC
    ", [$idTorneo]);
}

function obtenerFechaPorId($idFecha, $idTorneo)
{
    return dbOne("
        SELECT *
        FROM fechas
        WHERE id_fecha = ?
          AND id_torneo = ?
        LIMIT 1
    ", [$idFecha, $idTorneo]);
}

function fasePerteneceAlTorneo($idFase, $idTorneo)
{
    $resultado = dbOne("
        SELECT COUNT(*) AS total
        FROM fases
        WHERE id_fase = ?
          AND id_torneo = ?
    ", [$idFase, $idTorneo]);

    return (int)$resultado['total'] > 0;
}

function existeNumeroFecha($idTorneo, $numeroFecha, $idFechaExcluir = 0)
{
    $resultado = dbOne("
        SELECT COUNT(*) AS total
        FROM fechas
        WHERE id_torneo = ?
          AND numero_fecha = ?
          AND id_fecha <> ?
    ", [$idTorneo, $numeroFecha, $idFechaExcluir]);

    return (int)$resultado['total'] > 0;
}

function fechaTienePartidos($idFecha)
{
    $resultado = dbOne("
        SELECT COUNT(*) AS total
        FROM partidos
        WHERE id_fecha = ?
    ", [$idFecha]);

    return (int)$resultado['total'] > 0;
}

function obtenerSiguienteNumeroFecha($idTorneo)
{
    if (!$idTorneo) {
        return 1;
    }

    $resultado = dbOne("
        SELECT COALESCE(MAX(numero_fecha), 0) + 1 AS siguiente
        FROM fechas
        WHERE id_torneo = ?
    ", [$idTorneo]);

    return (int)$resultado['siguiente'];
}

function normalizarFechaProgramada($fecha)
{
    $fecha = trim($fecha);

    if ($fecha === '') {
        return null;
    }

    $partes = explode('-', $fecha);

    if (count($partes) !== 3) {
        throw new Exception('La fecha programada no es válida.');
    }

    $anio = (int)$partes[0];
    $mes = (int)$partes[1];
    $dia = (int)$partes[2];

    if (!checkdate($mes, $dia, $anio)) {
        throw new Exception('La fecha programada no es válida.');
    }

    return $fecha;
}

function textoTipoFaseFecha($tipo)
{
    return match ($tipo) {
        'regular' => 'Regular',
        'eliminatoria' => 'Eliminatoria',
        default => 'Sin tipo',
    };
}

function claseTipoFaseFecha($tipo)
{
    return match ($tipo) {
        'regular' => 'badge-green',
        'eliminatoria' => 'badge-yellow',
        default => 'badge-gray',
    };
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
            $idFase = (int)($_POST['id_fase'] ?? 0);
            $numeroFecha = (int)($_POST['numero_fecha'] ?? 0);
            $nombre = trim($_POST['nombre'] ?? '');
            $fechaProgramada = normalizarFechaProgramada($_POST['fecha_programada'] ?? '');

            if ($idFase <= 0) {
                throw new Exception('Tenés que seleccionar una fase.');
            }

            if (!fasePerteneceAlTorneo($idFase, $idTorneoActual)) {
                throw new Exception('La fase seleccionada no pertenece al torneo actual.');
            }

            if ($numeroFecha <= 0) {
                throw new Exception('El número de fecha debe ser mayor a cero.');
            }

            if (existeNumeroFecha($idTorneoActual, $numeroFecha)) {
                throw new Exception('Ya existe una fecha con ese número en este torneo.');
            }

            dbQuery("
                INSERT INTO fechas (
                    id_torneo,
                    id_fase,
                    numero_fecha,
                    nombre,
                    fecha_programada
                )
                VALUES (?, ?, ?, ?, ?)
            ", [
                $idTorneoActual,
                $idFase,
                $numeroFecha,
                $nombre !== '' ? $nombre : null,
                $fechaProgramada
            ]);

            adminSetFlash('success', 'Fecha creada correctamente.');
            header('Location: fechas.php');
            exit;
        }

        if ($accion === 'actualizar') {
            $idFecha = (int)($_POST['id_fecha'] ?? 0);
            $idFase = (int)($_POST['id_fase'] ?? 0);
            $numeroFecha = (int)($_POST['numero_fecha'] ?? 0);
            $nombre = trim($_POST['nombre'] ?? '');
            $fechaProgramada = normalizarFechaProgramada($_POST['fecha_programada'] ?? '');

            if ($idFecha <= 0) {
                throw new Exception('La fecha seleccionada no es válida.');
            }

            $fecha = obtenerFechaPorId($idFecha, $idTorneoActual);

            if (!$fecha) {
                throw new Exception('La fecha que querés editar no existe en el torneo actual.');
            }

            if ($idFase <= 0) {
                throw new Exception('Tenés que seleccionar una fase.');
            }

            if (!fasePerteneceAlTorneo($idFase, $idTorneoActual)) {
                throw new Exception('La fase seleccionada no pertenece al torneo actual.');
            }

            if ($numeroFecha <= 0) {
                throw new Exception('El número de fecha debe ser mayor a cero.');
            }

            if (existeNumeroFecha($idTorneoActual, $numeroFecha, $idFecha)) {
                throw new Exception('Ya existe otra fecha con ese número en este torneo.');
            }

            dbQuery("
                UPDATE fechas
                SET id_fase = ?,
                    numero_fecha = ?,
                    nombre = ?,
                    fecha_programada = ?
                WHERE id_fecha = ?
                  AND id_torneo = ?
            ", [
                $idFase,
                $numeroFecha,
                $nombre !== '' ? $nombre : null,
                $fechaProgramada,
                $idFecha,
                $idTorneoActual
            ]);

            adminSetFlash('success', 'Fecha actualizada correctamente.');
            header('Location: fechas.php');
            exit;
        }

        if ($accion === 'eliminar') {
            $idFecha = (int)($_POST['id_fecha'] ?? 0);

            if ($idFecha <= 0) {
                throw new Exception('La fecha seleccionada no es válida.');
            }

            $fecha = obtenerFechaPorId($idFecha, $idTorneoActual);

            if (!$fecha) {
                throw new Exception('La fecha no existe en el torneo actual.');
            }

            if (fechaTienePartidos($idFecha)) {
                throw new Exception('No se puede eliminar esta fecha porque ya tiene partidos asociados.');
            }

            dbQuery("
                DELETE FROM fechas
                WHERE id_fecha = ?
                  AND id_torneo = ?
            ", [$idFecha, $idTorneoActual]);

            adminSetFlash('success', 'Fecha eliminada correctamente.');
            header('Location: fechas.php');
            exit;
        }

    } catch (Exception $e) {
        adminSetFlash('error', $e->getMessage());
        header('Location: fechas.php');
        exit;
    }
}

/* =====================================================
   DATOS PARA MOSTRAR
===================================================== */

$flash = adminGetFlash();

$fases = obtenerFasesParaFechas($idTorneoActual);
$fechas = obtenerFechasDelTorneo($idTorneoActual);

$idEditar = isset($_GET['editar']) ? (int)$_GET['editar'] : 0;
$fechaEditar = null;

if ($idEditar > 0 && $idTorneoActual) {
    $fechaEditar = obtenerFechaPorId($idEditar, $idTorneoActual);

    if (!$fechaEditar) {
        adminSetFlash('error', 'La fecha que querés editar no existe.');
        header('Location: fechas.php');
        exit;
    }
}

$siguienteNumeroFecha = obtenerSiguienteNumeroFecha($idTorneoActual);

$totalRegulares = 0;
$totalEliminatorias = 0;
$totalPartidos = 0;
$totalFinalizados = 0;

foreach ($fechas as $fecha) {
    if ($fecha['fase_tipo'] === 'regular') {
        $totalRegulares++;
    }

    if ($fecha['fase_tipo'] === 'eliminatoria') {
        $totalEliminatorias++;
    }

    $totalPartidos += (int)$fecha['total_partidos'];
    $totalFinalizados += (int)$fecha['partidos_finalizados'];
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
            <a href="<?= BASE_URL ?>/admin/planteles.php">📋 Planteles</a>
            <a href="<?= BASE_URL ?>/admin/fases.php">🧩 Fases</a>
            <a class="active" href="<?= BASE_URL ?>/admin/fechas.php">📅 Fechas</a>
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
                <span class="admin-eyebrow">Calendario del torneo</span>
                <h1>Fechas</h1>

                <?php if ($torneoActual): ?>
                    <p>
                        Torneo actual:
                        <strong><?= h($torneoActual['nombre']) ?></strong>
                        · Temporada <?= h($torneoActual['temporada']) ?>
                    </p>
                <?php else: ?>
                    <p>Primero tenés que crear un torneo para poder cargar fechas.</p>
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
                <p>
                    Para crear fechas primero tenés que crear un torneo.
                </p>
                <a href="<?= BASE_URL ?>/admin/torneos.php" class="admin-btn admin-btn-primary">
                    Crear torneo
                </a>
            </section>

        <?php elseif (empty($fases)): ?>

            <section class="admin-empty-main">
                <h2>No hay fases cargadas</h2>
                <p>
                    Para crear fechas primero tenés que cargar al menos una fase.
                    Por ejemplo: Fase Regular.
                </p>
                <a href="<?= BASE_URL ?>/admin/fases.php" class="admin-btn admin-btn-primary">
                    Crear fases
                </a>
            </section>

        <?php else: ?>

            <!-- RESUMEN -->
            <section class="admin-stats-grid admin-stats-grid-compact">

                <article class="admin-stat-card">
                    <span>Total fechas</span>
                    <strong><?= h(count($fechas)) ?></strong>
                    <p>Creadas en el torneo</p>
                </article>

                <article class="admin-stat-card">
                    <span>Fase regular</span>
                    <strong><?= h($totalRegulares) ?></strong>
                    <p>Fechas de todos contra todos</p>
                </article>

                <article class="admin-stat-card warning">
                    <span>Eliminatorias</span>
                    <strong><?= h($totalEliminatorias) ?></strong>
                    <p>Cuartos, semi o final</p>
                </article>

                <article class="admin-stat-card">
                    <span>Partidos</span>
                    <strong><?= h($totalPartidos) ?></strong>
                    <p>Asociados a fechas</p>
                </article>

            </section>

            <section class="admin-dashboard-grid">

                <!-- FORMULARIO -->
                <article class="admin-panel">
                    <div class="admin-panel-header">
                        <div>
                            <span><?= $fechaEditar ? 'Editar fecha' : 'Nueva fecha' ?></span>
                            <h2><?= $fechaEditar ? 'Modificar fecha' : 'Crear fecha' ?></h2>
                        </div>

                        <?php if ($fechaEditar): ?>
                            <a href="<?= BASE_URL ?>/admin/fechas.php">Cancelar edición</a>
                        <?php endif; ?>
                    </div>

                    <form method="POST" class="admin-form">

                        <?php if ($fechaEditar): ?>
                            <input type="hidden" name="accion" value="actualizar">
                            <input type="hidden" name="id_fecha" value="<?= h($fechaEditar['id_fecha']) ?>">
                        <?php else: ?>
                            <input type="hidden" name="accion" value="crear">
                        <?php endif; ?>

                        <div class="form-group">
                            <label for="id_fase">Fase</label>
                            <?php $faseActual = $fechaEditar['id_fase'] ?? $fases[0]['id_fase']; ?>

                            <select id="id_fase" name="id_fase" required>
                                <?php foreach ($fases as $fase): ?>
                                    <option
                                        value="<?= h($fase['id_fase']) ?>"
                                        <?= (int)$faseActual === (int)$fase['id_fase'] ? 'selected' : '' ?>
                                    >
                                        <?= h($fase['nombre']) ?> - <?= h(textoTipoFaseFecha($fase['tipo'])) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <small class="form-help">
                                Elegí si esta fecha pertenece a fase regular o eliminatoria.
                            </small>
                        </div>

                        <div class="form-grid-2">
                            <div class="form-group">
                                <label for="numero_fecha">Número de fecha</label>
                                <input
                                    type="number"
                                    id="numero_fecha"
                                    name="numero_fecha"
                                    min="1"
                                    value="<?= h($fechaEditar['numero_fecha'] ?? $siguienteNumeroFecha) ?>"
                                    required
                                >
                                <small class="form-help">
                                    No se puede repetir dentro del mismo torneo.
                                </small>
                            </div>

                            <div class="form-group">
                                <label for="fecha_programada">Fecha programada</label>
                                <input
                                    type="date"
                                    id="fecha_programada"
                                    name="fecha_programada"
                                    value="<?= h($fechaEditar['fecha_programada'] ?? '') ?>"
                                >
                                <small class="form-help">
                                    Opcional. Sirve para ordenar o mostrar calendario.
                                </small>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="nombre">Nombre visible</label>
                            <input
                                type="text"
                                id="nombre"
                                name="nombre"
                                placeholder="Ej: Fecha 1, Fecha 2, Cuartos de final, Semifinal, Final"
                                value="<?= h($fechaEditar['nombre'] ?? '') ?>"
                            >
                            <small class="form-help">
                                Si lo dejás vacío, después se puede mostrar como “Fecha N”.
                            </small>
                        </div>

                        <button type="submit" class="admin-btn admin-btn-primary">
                            <?= $fechaEditar ? 'Guardar cambios' : 'Crear fecha' ?>
                        </button>

                    </form>
                </article>

                <!-- AYUDA -->
                <article class="admin-panel">
                    <div class="admin-panel-header">
                        <div>
                            <span>Cómo conviene cargar</span>
                            <h2>Orden recomendado</h2>
                        </div>
                    </div>

                    <div class="admin-help-list">
                        <div>
                            <strong>1</strong>
                            <p>Primero cargá las fechas de fase regular: Fecha 1, Fecha 2, Fecha 3, etc.</p>
                        </div>

                        <div>
                            <strong>2</strong>
                            <p>Después cargá las fechas eliminatorias: Cuartos, Semifinal y Final.</p>
                        </div>

                        <div>
                            <strong>3</strong>
                            <p>Los partidos se van a crear manualmente dentro de cada fecha.</p>
                        </div>

                        <div>
                            <strong>4</strong>
                            <p>Si una fecha ya tiene partidos, no se puede eliminar para no romper el fixture.</p>
                        </div>
                    </div>
                </article>

            </section>

            <!-- LISTADO -->
            <section class="admin-section">
                <div class="admin-section-header">
                    <div>
                        <span>Listado general</span>
                        <h2>Fechas del torneo</h2>
                    </div>
                </div>

                <?php if (empty($fechas)): ?>

                    <div class="admin-empty-box">
                        Todavía no hay fechas cargadas.
                    </div>

                <?php else: ?>

                    <div class="admin-table-wrapper">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Número</th>
                                    <th>Nombre</th>
                                    <th>Fase</th>
                                    <th>Tipo</th>
                                    <th>Programada</th>
                                    <th>Partidos</th>
                                    <th>Finalizados</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php foreach ($fechas as $fecha): ?>
                                    <?php
                                    $tienePartidos = (int)$fecha['total_partidos'] > 0;
                                    $nombreVisible = $fecha['nombre'] ?: 'Fecha ' . $fecha['numero_fecha'];
                                    ?>

                                    <tr>
                                        <td>
                                            <strong>#<?= h($fecha['numero_fecha']) ?></strong>
                                        </td>

                                        <td>
                                            <strong><?= h($nombreVisible) ?></strong>
                                            <small>ID fecha: <?= h($fecha['id_fecha']) ?></small>
                                        </td>

                                        <td>
                                            <?= h($fecha['fase_nombre']) ?>
                                        </td>

                                        <td>
                                            <span class="status-badge <?= h(claseTipoFaseFecha($fecha['fase_tipo'])) ?>">
                                                <?= h(textoTipoFaseFecha($fecha['fase_tipo'])) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <?= h(formatearFecha($fecha['fecha_programada'])) ?>
                                        </td>

                                        <td>
                                            <?= h($fecha['total_partidos']) ?>
                                        </td>

                                        <td>
                                            <?= h($fecha['partidos_finalizados']) ?>
                                        </td>

                                        <td>
                                            <div class="table-actions">
                                                <a
                                                    href="<?= BASE_URL ?>/admin/fechas.php?editar=<?= h($fecha['id_fecha']) ?>"
                                                    class="table-btn edit"
                                                >
                                                    Editar
                                                </a>

                                                <a
                                                    href="<?= BASE_URL ?>/admin/partidos.php?fecha=<?= h($fecha['id_fecha']) ?>"
                                                    class="table-btn info"
                                                >
                                                    Partidos
                                                </a>

                                                <?php if (!$tienePartidos): ?>
                                                    <form method="POST">
                                                        <input type="hidden" name="accion" value="eliminar">
                                                        <input type="hidden" name="id_fecha" value="<?= h($fecha['id_fecha']) ?>">

                                                        <button
                                                            type="submit"
                                                            class="table-btn delete"
                                                            onclick="return confirm('¿Seguro que querés eliminar esta fecha?');"
                                                        >
                                                            Eliminar
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="table-muted">
                                                        Tiene partidos
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
