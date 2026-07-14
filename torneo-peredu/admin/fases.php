<?php
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = "Fases - Panel Admin";
$pageDescription = "Administración de fases Copa PER.EDU";

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

function obtenerFasesDelTorneo($idTorneo)
{
    if (!$idTorneo) {
        return [];
    }

    return dbAll("
        SELECT
            f.id_fase,
            f.id_torneo,
            f.nombre,
            f.tipo,
            f.orden,

            (
                SELECT COUNT(*)
                FROM fechas fe
                WHERE fe.id_fase = f.id_fase
            ) AS total_fechas,

            (
                SELECT COUNT(*)
                FROM partidos p
                WHERE p.id_fase = f.id_fase
            ) AS total_partidos

        FROM fases f
        WHERE f.id_torneo = ?
        ORDER BY f.orden ASC, f.id_fase ASC
    ", [$idTorneo]);
}

function obtenerFasePorId($idFase, $idTorneo)
{
    return dbOne("
        SELECT *
        FROM fases
        WHERE id_fase = ?
          AND id_torneo = ?
        LIMIT 1
    ", [$idFase, $idTorneo]);
}

function existeNombreFase($idTorneo, $nombre, $idFaseExcluir = 0)
{
    $resultado = dbOne("
        SELECT COUNT(*) AS total
        FROM fases
        WHERE id_torneo = ?
          AND LOWER(nombre) = LOWER(?)
          AND id_fase <> ?
    ", [$idTorneo, $nombre, $idFaseExcluir]);

    return (int)$resultado['total'] > 0;
}

function existeOrdenFase($idTorneo, $orden, $idFaseExcluir = 0)
{
    $resultado = dbOne("
        SELECT COUNT(*) AS total
        FROM fases
        WHERE id_torneo = ?
          AND orden = ?
          AND id_fase <> ?
    ", [$idTorneo, $orden, $idFaseExcluir]);

    return (int)$resultado['total'] > 0;
}

function faseTieneDatos($idFase)
{
    $fechas = dbOne("
        SELECT COUNT(*) AS total
        FROM fechas
        WHERE id_fase = ?
    ", [$idFase]);

    $partidos = dbOne("
        SELECT COUNT(*) AS total
        FROM partidos
        WHERE id_fase = ?
    ", [$idFase]);

    return (
        (int)$fechas['total'] > 0 ||
        (int)$partidos['total'] > 0
    );
}

function obtenerSiguienteOrdenFase($idTorneo)
{
    if (!$idTorneo) {
        return 1;
    }

    $resultado = dbOne("
        SELECT COALESCE(MAX(orden), 0) + 1 AS siguiente
        FROM fases
        WHERE id_torneo = ?
    ", [$idTorneo]);

    return (int)$resultado['siguiente'];
}

function textoTipoFase($tipo)
{
    return match ($tipo) {
        'regular' => 'Fase regular',
        'eliminatoria' => 'Eliminatoria',
        default => 'Sin tipo',
    };
}

function claseTipoFase($tipo)
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
            $nombre = trim($_POST['nombre'] ?? '');
            $tipo = $_POST['tipo'] ?? 'regular';
            $orden = (int)($_POST['orden'] ?? 1);

            if ($nombre === '') {
                throw new Exception('El nombre de la fase es obligatorio.');
            }

            if (!in_array($tipo, ['regular', 'eliminatoria'])) {
                throw new Exception('El tipo de fase no es válido.');
            }

            if ($orden <= 0) {
                throw new Exception('El orden debe ser mayor a cero.');
            }

            if (existeNombreFase($idTorneoActual, $nombre)) {
                throw new Exception('Ya existe una fase con ese nombre en este torneo.');
            }

            if (existeOrdenFase($idTorneoActual, $orden)) {
                throw new Exception('Ya existe una fase con ese número de orden.');
            }

            dbQuery("
                INSERT INTO fases (id_torneo, nombre, tipo, orden)
                VALUES (?, ?, ?, ?)
            ", [$idTorneoActual, $nombre, $tipo, $orden]);

            adminSetFlash('success', 'Fase creada correctamente.');
            header('Location: fases.php');
            exit;
        }

        if ($accion === 'actualizar') {
            $idFase = (int)($_POST['id_fase'] ?? 0);
            $nombre = trim($_POST['nombre'] ?? '');
            $tipo = $_POST['tipo'] ?? 'regular';
            $orden = (int)($_POST['orden'] ?? 1);

            if ($idFase <= 0) {
                throw new Exception('La fase seleccionada no es válida.');
            }

            $fase = obtenerFasePorId($idFase, $idTorneoActual);

            if (!$fase) {
                throw new Exception('La fase que querés editar no existe en el torneo actual.');
            }

            if ($nombre === '') {
                throw new Exception('El nombre de la fase es obligatorio.');
            }

            if (!in_array($tipo, ['regular', 'eliminatoria'])) {
                throw new Exception('El tipo de fase no es válido.');
            }

            if ($orden <= 0) {
                throw new Exception('El orden debe ser mayor a cero.');
            }

            if (existeNombreFase($idTorneoActual, $nombre, $idFase)) {
                throw new Exception('Ya existe otra fase con ese nombre en este torneo.');
            }

            if (existeOrdenFase($idTorneoActual, $orden, $idFase)) {
                throw new Exception('Ya existe otra fase con ese número de orden.');
            }

            dbQuery("
                UPDATE fases
                SET nombre = ?,
                    tipo = ?,
                    orden = ?
                WHERE id_fase = ?
                  AND id_torneo = ?
            ", [$nombre, $tipo, $orden, $idFase, $idTorneoActual]);

            adminSetFlash('success', 'Fase actualizada correctamente.');
            header('Location: fases.php');
            exit;
        }

        if ($accion === 'eliminar') {
            $idFase = (int)($_POST['id_fase'] ?? 0);

            if ($idFase <= 0) {
                throw new Exception('La fase seleccionada no es válida.');
            }

            $fase = obtenerFasePorId($idFase, $idTorneoActual);

            if (!$fase) {
                throw new Exception('La fase no existe en el torneo actual.');
            }

            if (faseTieneDatos($idFase)) {
                throw new Exception('No se puede eliminar esta fase porque ya tiene fechas o partidos asociados.');
            }

            dbQuery("
                DELETE FROM fases
                WHERE id_fase = ?
                  AND id_torneo = ?
            ", [$idFase, $idTorneoActual]);

            adminSetFlash('success', 'Fase eliminada correctamente.');
            header('Location: fases.php');
            exit;
        }

    } catch (Exception $e) {
        adminSetFlash('error', $e->getMessage());
        header('Location: fases.php');
        exit;
    }
}

/* =====================================================
   DATOS PARA MOSTRAR
===================================================== */

$flash = adminGetFlash();

$fases = obtenerFasesDelTorneo($idTorneoActual);

$idEditar = isset($_GET['editar']) ? (int)$_GET['editar'] : 0;
$faseEditar = null;

if ($idEditar > 0 && $idTorneoActual) {
    $faseEditar = obtenerFasePorId($idEditar, $idTorneoActual);

    if (!$faseEditar) {
        adminSetFlash('error', 'La fase que querés editar no existe.');
        header('Location: fases.php');
        exit;
    }
}

$siguienteOrden = obtenerSiguienteOrdenFase($idTorneoActual);

$totalRegulares = 0;
$totalEliminatorias = 0;

foreach ($fases as $fase) {
    if ($fase['tipo'] === 'regular') {
        $totalRegulares++;
    }

    if ($fase['tipo'] === 'eliminatoria') {
        $totalEliminatorias++;
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
            <a href="<?= BASE_URL ?>/admin/planteles.php">📋 Planteles</a>
            <a class="active" href="<?= BASE_URL ?>/admin/fases.php">🧩 Fases</a>
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
                <span class="admin-eyebrow">Estructura del torneo</span>
                <h1>Fases</h1>

                <?php if ($torneoActual): ?>
                    <p>
                        Torneo actual:
                        <strong><?= h($torneoActual['nombre']) ?></strong>
                        · Temporada <?= h($torneoActual['temporada']) ?>
                    </p>
                <?php else: ?>
                    <p>Primero tenés que crear un torneo para poder cargar fases.</p>
                <?php endif; ?>
            </div>

            <div class="admin-top-actions">
                <a href="<?= BASE_URL ?>/admin/fechas.php" class="admin-btn admin-btn-primary">
                    Ir a fechas
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
                    Para crear fases primero tenés que crear un torneo.
                </p>
                <a href="<?= BASE_URL ?>/admin/torneos.php" class="admin-btn admin-btn-primary">
                    Crear torneo
                </a>
            </section>

        <?php else: ?>

            <!-- RESUMEN -->
            <section class="admin-stats-grid admin-stats-grid-compact">

                <article class="admin-stat-card">
                    <span>Total fases</span>
                    <strong><?= h(count($fases)) ?></strong>
                    <p>Creadas en el torneo</p>
                </article>

                <article class="admin-stat-card">
                    <span>Regulares</span>
                    <strong><?= h($totalRegulares) ?></strong>
                    <p>Suman para la tabla</p>
                </article>

                <article class="admin-stat-card warning">
                    <span>Eliminatorias</span>
                    <strong><?= h($totalEliminatorias) ?></strong>
                    <p>Definen cruces y finales</p>
                </article>

                <article class="admin-stat-card">
                    <span>Siguiente orden</span>
                    <strong><?= h($siguienteOrden) ?></strong>
                    <p>Orden sugerido</p>
                </article>

            </section>

            <section class="admin-dashboard-grid">

                <!-- FORMULARIO -->
                <article class="admin-panel">
                    <div class="admin-panel-header">
                        <div>
                            <span><?= $faseEditar ? 'Editar fase' : 'Nueva fase' ?></span>
                            <h2><?= $faseEditar ? 'Modificar fase' : 'Crear fase' ?></h2>
                        </div>

                        <?php if ($faseEditar): ?>
                            <a href="<?= BASE_URL ?>/admin/fases.php">Cancelar edición</a>
                        <?php endif; ?>
                    </div>

                    <form method="POST" class="admin-form">

                        <?php if ($faseEditar): ?>
                            <input type="hidden" name="accion" value="actualizar">
                            <input type="hidden" name="id_fase" value="<?= h($faseEditar['id_fase']) ?>">
                        <?php else: ?>
                            <input type="hidden" name="accion" value="crear">
                        <?php endif; ?>

                        <div class="form-group">
                            <label for="nombre">Nombre de la fase</label>
                            <input
                                type="text"
                                id="nombre"
                                name="nombre"
                                placeholder="Ej: Fase Regular, Cuartos de final, Semifinal, Final"
                                value="<?= h($faseEditar['nombre'] ?? '') ?>"
                                required
                            >
                        </div>

                        <div class="form-grid-2">
                            <div class="form-group">
                                <label for="tipo">Tipo de fase</label>
                                <?php $tipoActual = $faseEditar['tipo'] ?? 'regular'; ?>
                                <select id="tipo" name="tipo" required>
                                    <option value="regular" <?= $tipoActual === 'regular' ? 'selected' : '' ?>>
                                        Regular
                                    </option>
                                    <option value="eliminatoria" <?= $tipoActual === 'eliminatoria' ? 'selected' : '' ?>>
                                        Eliminatoria
                                    </option>
                                </select>
                                <small class="form-help">
                                    La fase regular suma para la tabla. La eliminatoria sirve para cruces y penales.
                                </small>
                            </div>

                            <div class="form-group">
                                <label for="orden">Orden</label>
                                <input
                                    type="number"
                                    id="orden"
                                    name="orden"
                                    min="1"
                                    value="<?= h($faseEditar['orden'] ?? $siguienteOrden) ?>"
                                    required
                                >
                                <small class="form-help">
                                    Ejemplo: 1 fase regular, 2 cuartos, 3 semifinal, 4 final.
                                </small>
                            </div>
                        </div>

                        <button type="submit" class="admin-btn admin-btn-primary">
                            <?= $faseEditar ? 'Guardar cambios' : 'Crear fase' ?>
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
                            <p>Crear primero la fase regular. Esta es la única que suma para la tabla de posiciones.</p>
                        </div>

                        <div>
                            <strong>2</strong>
                            <p>Crear después las fases eliminatorias: cuartos, semifinal y final.</p>
                        </div>

                        <div>
                            <strong>3</strong>
                            <p>El admin va a crear manualmente los partidos de cada fase.</p>
                        </div>

                        <div>
                            <strong>4</strong>
                            <p>Si una fase ya tiene fechas o partidos, no se elimina para evitar romper el torneo.</p>
                        </div>
                    </div>
                </article>

            </section>

            <!-- LISTADO -->
            <section class="admin-section">
                <div class="admin-section-header">
                    <div>
                        <span>Listado general</span>
                        <h2>Fases del torneo</h2>
                    </div>
                </div>

                <?php if (empty($fases)): ?>

                    <div class="admin-empty-box">
                        Todavía no hay fases cargadas. Creá al menos una fase regular.
                    </div>

                <?php else: ?>

                    <div class="admin-table-wrapper">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Orden</th>
                                    <th>Fase</th>
                                    <th>Tipo</th>
                                    <th>Fechas</th>
                                    <th>Partidos</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php foreach ($fases as $fase): ?>
                                    <?php
                                    $tieneDatos = ((int)$fase['total_fechas'] > 0 || (int)$fase['total_partidos'] > 0);
                                    ?>

                                    <tr>
                                        <td>
                                            <strong>#<?= h($fase['orden']) ?></strong>
                                        </td>

                                        <td>
                                            <strong><?= h($fase['nombre']) ?></strong>
                                            <small>ID fase: <?= h($fase['id_fase']) ?></small>
                                        </td>

                                        <td>
                                            <span class="status-badge <?= h(claseTipoFase($fase['tipo'])) ?>">
                                                <?= h(textoTipoFase($fase['tipo'])) ?>
                                            </span>
                                        </td>

                                        <td><?= h($fase['total_fechas']) ?></td>

                                        <td><?= h($fase['total_partidos']) ?></td>

                                        <td>
                                            <div class="table-actions">
                                                <a
                                                    href="<?= BASE_URL ?>/admin/fases.php?editar=<?= h($fase['id_fase']) ?>"
                                                    class="table-btn edit"
                                                >
                                                    Editar
                                                </a>

                                                <?php if (!$tieneDatos): ?>
                                                    <form method="POST">
                                                        <input type="hidden" name="accion" value="eliminar">
                                                        <input type="hidden" name="id_fase" value="<?= h($fase['id_fase']) ?>">

                                                        <button
                                                            type="submit"
                                                            class="table-btn delete"
                                                            onclick="return confirm('¿Seguro que querés eliminar esta fase?');"
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
