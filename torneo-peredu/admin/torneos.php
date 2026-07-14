<?php
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$pageTitle = "Torneos - Panel Admin";
$pageDescription = "Administración de torneos Copa PER.EDU";

/* =====================================================
   FUNCIONES LOCALES
===================================================== */

function setFlash($tipo, $mensaje)
{
    $_SESSION['flash'] = [
        'tipo' => $tipo,
        'mensaje' => $mensaje
    ];
}

function getFlash()
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    return $flash;
}

function torneoTieneDatos($idTorneo)
{
    $equipos = dbOne("
        SELECT COUNT(*) AS total
        FROM equipo_torneo
        WHERE id_torneo = ?
    ", [$idTorneo]);

    $fases = dbOne("
        SELECT COUNT(*) AS total
        FROM fases
        WHERE id_torneo = ?
    ", [$idTorneo]);

    $fechas = dbOne("
        SELECT COUNT(*) AS total
        FROM fechas
        WHERE id_torneo = ?
    ", [$idTorneo]);

    $partidos = dbOne("
        SELECT COUNT(*) AS total
        FROM partidos
        WHERE id_torneo = ?
    ", [$idTorneo]);

    return (
        (int)$equipos['total'] > 0 ||
        (int)$fases['total'] > 0 ||
        (int)$fechas['total'] > 0 ||
        (int)$partidos['total'] > 0
    );
}

function obtenerTorneoPorId($idTorneo)
{
    return dbOne("
        SELECT *
        FROM torneos
        WHERE id_torneo = ?
        LIMIT 1
    ", [$idTorneo]);
}

function obtenerResumenTorneo($idTorneo)
{
    return [
        'equipos' => (int) dbOne("
            SELECT COUNT(*) AS total
            FROM equipo_torneo
            WHERE id_torneo = ?
        ", [$idTorneo])['total'],

        'fases' => (int) dbOne("
            SELECT COUNT(*) AS total
            FROM fases
            WHERE id_torneo = ?
        ", [$idTorneo])['total'],

        'fechas' => (int) dbOne("
            SELECT COUNT(*) AS total
            FROM fechas
            WHERE id_torneo = ?
        ", [$idTorneo])['total'],

        'partidos' => (int) dbOne("
            SELECT COUNT(*) AS total
            FROM partidos
            WHERE id_torneo = ?
        ", [$idTorneo])['total'],
    ];
}

function textoEstadoTorneo($estado)
{
    return match ($estado) {
        'borrador' => 'Borrador',
        'en_curso' => 'En curso',
        'finalizado' => 'Finalizado',
        default => 'Sin estado',
    };
}

function claseEstadoTorneo($estado)
{
    return match ($estado) {
        'borrador' => 'badge-gray',
        'en_curso' => 'badge-green',
        'finalizado' => 'badge-blue',
        default => 'badge-gray',
    };
}

/* =====================================================
   PROCESAR FORMULARIOS
===================================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    try {
        if ($accion === 'crear') {
            $nombre = trim($_POST['nombre'] ?? '');
            $temporada = (int)($_POST['temporada'] ?? date('Y'));
            $estado = $_POST['estado'] ?? 'borrador';
            $descripcion = trim($_POST['descripcion'] ?? '');

            if ($nombre === '') {
                throw new Exception('El nombre del torneo es obligatorio.');
            }

            if ($temporada < 2020 || $temporada > 2100) {
                throw new Exception('La temporada no es válida.');
            }

            if (!in_array($estado, ['borrador', 'en_curso', 'finalizado'])) {
                throw new Exception('El estado seleccionado no es válido.');
            }

            dbQuery("
                INSERT INTO torneos (nombre, temporada, descripcion, estado)
                VALUES (?, ?, ?, ?)
            ", [$nombre, $temporada, $descripcion, $estado]);

            setFlash('success', 'Torneo creado correctamente.');
            header('Location: torneos.php');
            exit;
        }

        if ($accion === 'actualizar') {
            $idTorneo = (int)($_POST['id_torneo'] ?? 0);
            $nombre = trim($_POST['nombre'] ?? '');
            $temporada = (int)($_POST['temporada'] ?? date('Y'));
            $estado = $_POST['estado'] ?? 'borrador';
            $descripcion = trim($_POST['descripcion'] ?? '');

            if ($idTorneo <= 0) {
                throw new Exception('El torneo seleccionado no es válido.');
            }

            if ($nombre === '') {
                throw new Exception('El nombre del torneo es obligatorio.');
            }

            if ($temporada < 2020 || $temporada > 2100) {
                throw new Exception('La temporada no es válida.');
            }

            if (!in_array($estado, ['borrador', 'en_curso', 'finalizado'])) {
                throw new Exception('El estado seleccionado no es válido.');
            }

            dbQuery("
                UPDATE torneos
                SET nombre = ?, temporada = ?, descripcion = ?, estado = ?
                WHERE id_torneo = ?
            ", [$nombre, $temporada, $descripcion, $estado, $idTorneo]);

            setFlash('success', 'Torneo actualizado correctamente.');
            header('Location: torneos.php');
            exit;
        }

        if ($accion === 'eliminar') {
            $idTorneo = (int)($_POST['id_torneo'] ?? 0);

            if ($idTorneo <= 0) {
                throw new Exception('El torneo seleccionado no es válido.');
            }

            if (torneoTieneDatos($idTorneo)) {
                throw new Exception('No se puede eliminar este torneo porque ya tiene equipos, fases, fechas o partidos cargados.');
            }

            dbQuery("
                DELETE FROM torneos
                WHERE id_torneo = ?
            ", [$idTorneo]);

            setFlash('success', 'Torneo eliminado correctamente.');
            header('Location: torneos.php');
            exit;
        }

    } catch (Exception $e) {
        setFlash('error', $e->getMessage());
        header('Location: torneos.php');
        exit;
    }
}

/* =====================================================
   DATOS PARA MOSTRAR
===================================================== */

$torneos = obtenerTorneos();
$flash = getFlash();

$idEditar = isset($_GET['editar']) ? (int)$_GET['editar'] : 0;
$torneoEditar = null;

if ($idEditar > 0) {
    $torneoEditar = obtenerTorneoPorId($idEditar);

    if (!$torneoEditar) {
        setFlash('error', 'El torneo que querés editar no existe.');
        header('Location: torneos.php');
        exit;
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
            <a class="active" href="<?= BASE_URL ?>/admin/torneos.php">🏆 Torneos</a>
            <a href="<?= BASE_URL ?>/admin/equipos.php">👕 Equipos</a>
            <a href="<?= BASE_URL ?>/admin/jugadores.php">👤 Jugadores</a>
            <a href="<?= BASE_URL ?>/admin/planteles.php">📋 Planteles</a>
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
                <span class="admin-eyebrow">Gestión principal</span>
                <h1>Torneos</h1>
                <p>
                    Desde acá creás la Copa PER.EDU 2026 u otras temporadas.
                    El resto del sistema depende de que exista al menos un torneo.
                </p>
            </div>

            <div class="admin-top-actions">
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

        <section class="admin-dashboard-grid">

            <!-- FORMULARIO -->
            <article class="admin-panel">
                <div class="admin-panel-header">
                    <div>
                        <span><?= $torneoEditar ? 'Editar torneo' : 'Nuevo torneo' ?></span>
                        <h2><?= $torneoEditar ? 'Modificar datos' : 'Crear torneo' ?></h2>
                    </div>

                    <?php if ($torneoEditar): ?>
                        <a href="<?= BASE_URL ?>/admin/torneos.php">Cancelar edición</a>
                    <?php endif; ?>
                </div>

                <form method="POST" class="admin-form">

                    <?php if ($torneoEditar): ?>
                        <input type="hidden" name="accion" value="actualizar">
                        <input type="hidden" name="id_torneo" value="<?= h($torneoEditar['id_torneo']) ?>">
                    <?php else: ?>
                        <input type="hidden" name="accion" value="crear">
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="nombre">Nombre del torneo</label>
                        <input
                            type="text"
                            id="nombre"
                            name="nombre"
                            placeholder="Ej: Copa PER.EDU"
                            value="<?= h($torneoEditar['nombre'] ?? 'Copa PER.EDU') ?>"
                            required
                        >
                    </div>

                    <div class="form-grid-2">
                        <div class="form-group">
                            <label for="temporada">Temporada</label>
                            <input
                                type="number"
                                id="temporada"
                                name="temporada"
                                min="2020"
                                max="2100"
                                value="<?= h($torneoEditar['temporada'] ?? date('Y')) ?>"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="estado">Estado</label>
                            <select id="estado" name="estado" required>
                                <?php
                                $estadoActual = $torneoEditar['estado'] ?? 'borrador';
                                ?>
                                <option value="borrador" <?= $estadoActual === 'borrador' ? 'selected' : '' ?>>
                                    Borrador
                                </option>
                                <option value="en_curso" <?= $estadoActual === 'en_curso' ? 'selected' : '' ?>>
                                    En curso
                                </option>
                                <option value="finalizado" <?= $estadoActual === 'finalizado' ? 'selected' : '' ?>>
                                    Finalizado
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="descripcion">Descripción</label>
                        <textarea
                            id="descripcion"
                            name="descripcion"
                            rows="5"
                            placeholder="Ej: Torneo de los profes de escuelas técnicas de Mendoza."
                        ><?= h($torneoEditar['descripcion'] ?? 'El torneo de los profes — Escuelas técnicas de Mendoza compitiendo por la gloria.') ?></textarea>
                    </div>

                    <button type="submit" class="admin-btn admin-btn-primary">
                        <?= $torneoEditar ? 'Guardar cambios' : 'Crear torneo' ?>
                    </button>

                </form>
            </article>

            <!-- AYUDA -->
            <article class="admin-panel">
                <div class="admin-panel-header">
                    <div>
                        <span>Orden recomendado</span>
                        <h2>Cómo continuar</h2>
                    </div>
                </div>

                <div class="admin-help-list">
                    <div>
                        <strong>1</strong>
                        <p>Crear el torneo, por ejemplo: Copa PER.EDU 2026.</p>
                    </div>

                    <div>
                        <strong>2</strong>
                        <p>Cargar los equipos que van a participar.</p>
                    </div>

                    <div>
                        <strong>3</strong>
                        <p>Asociar los equipos al torneo actual.</p>
                    </div>

                    <div>
                        <strong>4</strong>
                        <p>Cargar jugadores y armar los planteles.</p>
                    </div>

                    <div>
                        <strong>5</strong>
                        <p>Crear fases, fechas y partidos manualmente.</p>
                    </div>
                </div>
            </article>

        </section>

        <!-- LISTADO -->
        <section class="admin-section">
            <div class="admin-section-header">
                <div>
                    <span>Listado general</span>
                    <h2>Torneos cargados</h2>
                </div>
            </div>

            <?php if (empty($torneos)): ?>

                <div class="admin-empty-box">
                    Todavía no hay torneos cargados.
                </div>

            <?php else: ?>

                <div class="admin-table-wrapper">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Torneo</th>
                                <th>Temporada</th>
                                <th>Estado</th>
                                <th>Equipos</th>
                                <th>Fases</th>
                                <th>Fechas</th>
                                <th>Partidos</th>
                                <th>Creado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($torneos as $torneo): ?>
                                <?php
                                $resumenTorneo = obtenerResumenTorneo($torneo['id_torneo']);
                                $tieneDatos = torneoTieneDatos($torneo['id_torneo']);
                                ?>

                                <tr>
                                    <td>
                                        <strong><?= h($torneo['nombre']) ?></strong>
                                        <?php if (!empty($torneo['descripcion'])): ?>
                                            <small><?= h($torneo['descripcion']) ?></small>
                                        <?php endif; ?>
                                    </td>

                                    <td><?= h($torneo['temporada']) ?></td>

                                    <td>
                                        <span class="status-badge <?= h(claseEstadoTorneo($torneo['estado'])) ?>">
                                            <?= h(textoEstadoTorneo($torneo['estado'])) ?>
                                        </span>
                                    </td>

                                    <td><?= h($resumenTorneo['equipos']) ?></td>
                                    <td><?= h($resumenTorneo['fases']) ?></td>
                                    <td><?= h($resumenTorneo['fechas']) ?></td>
                                    <td><?= h($resumenTorneo['partidos']) ?></td>

                                    <td><?= h(formatearFecha($torneo['creado_en'])) ?></td>

                                    <td>
                                        <div class="table-actions">
                                            <a
                                                href="<?= BASE_URL ?>/admin/torneos.php?editar=<?= h($torneo['id_torneo']) ?>"
                                                class="table-btn edit"
                                            >
                                                Editar
                                            </a>

                                            <?php if (!$tieneDatos): ?>
                                                <form
                                                    method="POST"
                                                    onsubmit="return confirm('¿Seguro que querés eliminar este torneo?');"
                                                >
                                                    <input type="hidden" name="accion" value="eliminar">
                                                    <input type="hidden" name="id_torneo" value="<?= h($torneo['id_torneo']) ?>">

                                                    <button type="submit" class="table-btn delete">
                                                        Eliminar
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="table-muted">
                                                    Con datos
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

    </main>

</div>

<script src="<?= asset('assets/js/app.js') ?>"></script>
</body>
</html>