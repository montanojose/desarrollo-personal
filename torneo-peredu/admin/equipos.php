<?php
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = "Equipos - Panel Admin";
$pageDescription = "Administración de equipos Copa PER.EDU";

/* =====================================================
   FLASH
===================================================== */

function adminSetFlash($tipo, $mensaje)
{
    $_SESSION['flash'] = [
        'tipo' => $tipo,
        'mensaje' => $mensaje
    ];
}

function adminGetFlash()
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    return $flash;
}

/* =====================================================
   FUNCIONES LOCALES
===================================================== */

function obtenerEquipoTorneoPorId($idEquipoTorneo, $idTorneo)
{
    return dbOne("
        SELECT
            et.id_equipo_torneo,
            et.id_torneo,
            et.activo AS activo_en_torneo,
            e.id_equipo,
            e.nombre,
            e.escuela,
            e.escudo,
            e.activo AS activo_equipo,
            e.creado_en
        FROM equipo_torneo et
        INNER JOIN equipos e
            ON et.id_equipo = e.id_equipo
        WHERE et.id_equipo_torneo = ?
          AND et.id_torneo = ?
        LIMIT 1
    ", [$idEquipoTorneo, $idTorneo]);
}

function obtenerEquiposDelTorneo($idTorneo)
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
            e.activo AS activo_equipo,
            e.creado_en,

            (
                SELECT COUNT(*)
                FROM jugador_equipo_torneo jet
                WHERE jet.id_equipo_torneo = et.id_equipo_torneo
                  AND jet.activo = 1
            ) AS total_jugadores,

            (
                SELECT COUNT(*)
                FROM partidos p
                WHERE p.id_equipo_local = et.id_equipo_torneo
                   OR p.id_equipo_visitante = et.id_equipo_torneo
            ) AS total_partidos

        FROM equipo_torneo et
        INNER JOIN equipos e
            ON et.id_equipo = e.id_equipo
        WHERE et.id_torneo = ?
        ORDER BY e.nombre ASC
    ", [$idTorneo]);
}

function existeNombreEquipoEnTorneo($idTorneo, $nombre, $idEquipoExcluir = 0)
{
    $resultado = dbOne("
        SELECT COUNT(*) AS total
        FROM equipo_torneo et
        INNER JOIN equipos e
            ON et.id_equipo = e.id_equipo
        WHERE et.id_torneo = ?
          AND LOWER(e.nombre) = LOWER(?)
          AND e.id_equipo <> ?
    ", [$idTorneo, $nombre, $idEquipoExcluir]);

    return (int)$resultado['total'] > 0;
}

function equipoTieneUso($idEquipoTorneo)
{
    $jugadores = dbOne("
        SELECT COUNT(*) AS total
        FROM jugador_equipo_torneo
        WHERE id_equipo_torneo = ?
    ", [$idEquipoTorneo]);

    $partidos = dbOne("
        SELECT COUNT(*) AS total
        FROM partidos
        WHERE id_equipo_local = ?
           OR id_equipo_visitante = ?
    ", [$idEquipoTorneo, $idEquipoTorneo]);

    return (
        (int)$jugadores['total'] > 0 ||
        (int)$partidos['total'] > 0
    );
}

function equipoEstaEnOtrosTorneos($idEquipo, $idEquipoTorneoActual)
{
    $resultado = dbOne("
        SELECT COUNT(*) AS total
        FROM equipo_torneo
        WHERE id_equipo = ?
          AND id_equipo_torneo <> ?
    ", [$idEquipo, $idEquipoTorneoActual]);

    return (int)$resultado['total'] > 0;
}

function eliminarEscudoSiExiste($rutaRelativa)
{
    if (empty($rutaRelativa)) {
        return;
    }

    $rutaPermitida = 'assets/uploads/escudos/';

    if (!str_starts_with($rutaRelativa, $rutaPermitida)) {
        return;
    }

    $rutaAbsoluta = __DIR__ . '/../' . $rutaRelativa;

    if (file_exists($rutaAbsoluta)) {
        unlink($rutaAbsoluta);
    }
}

function subirEscudo($campoArchivo)
{
    if (!isset($_FILES[$campoArchivo])) {
        return null;
    }

    $archivo = $_FILES[$campoArchivo];

    if ($archivo['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Hubo un error al subir el escudo.');
    }

    $maxSize = 3 * 1024 * 1024;

    if ($archivo['size'] > $maxSize) {
        throw new Exception('El escudo no puede superar los 3 MB.');
    }

    $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));

    $extensionesPermitidas = ['jpg', 'jpeg', 'png', 'webp'];

    if (!in_array($extension, $extensionesPermitidas)) {
        throw new Exception('El escudo debe ser una imagen JPG, PNG o WEBP.');
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $archivo['tmp_name']);
    finfo_close($finfo);

    $mimesPermitidos = [
        'image/jpeg',
        'image/png',
        'image/webp'
    ];

    if (!in_array($mime, $mimesPermitidos)) {
        throw new Exception('El archivo subido no parece ser una imagen válida.');
    }

    $carpetaRelativa = 'assets/uploads/escudos/';
    $carpetaAbsoluta = __DIR__ . '/../' . $carpetaRelativa;

    if (!is_dir($carpetaAbsoluta)) {
        mkdir($carpetaAbsoluta, 0775, true);
    }

    $nombreArchivo = 'escudo_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $extension;
    $rutaFinalAbsoluta = $carpetaAbsoluta . $nombreArchivo;
    $rutaFinalRelativa = $carpetaRelativa . $nombreArchivo;

    if (!move_uploaded_file($archivo['tmp_name'], $rutaFinalAbsoluta)) {
        throw new Exception('No se pudo guardar el escudo en el servidor.');
    }

    return $rutaFinalRelativa;
}

function textoActivo($activo)
{
    return (int)$activo === 1 ? 'Activo' : 'Inactivo';
}

function claseActivo($activo)
{
    return (int)$activo === 1 ? 'badge-green' : 'badge-gray';
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
            $escuela = trim($_POST['escuela'] ?? '');

            if ($nombre === '') {
                throw new Exception('El nombre del equipo es obligatorio.');
            }

            if (existeNombreEquipoEnTorneo($idTorneoActual, $nombre)) {
                throw new Exception('Ya existe un equipo con ese nombre en este torneo.');
            }

            $escudo = subirEscudo('escudo');

            dbQuery("
                INSERT INTO equipos (nombre, escuela, escudo, activo)
                VALUES (?, ?, ?, 1)
            ", [$nombre, $escuela, $escudo]);

            $idEquipo = db()->lastInsertId();

            dbQuery("
                INSERT INTO equipo_torneo (id_torneo, id_equipo, activo)
                VALUES (?, ?, 1)
            ", [$idTorneoActual, $idEquipo]);

            adminSetFlash('success', 'Equipo creado y agregado al torneo correctamente.');
            header('Location: equipos.php');
            exit;
        }

        if ($accion === 'actualizar') {
            $idEquipoTorneo = (int)($_POST['id_equipo_torneo'] ?? 0);
            $idEquipo = (int)($_POST['id_equipo'] ?? 0);
            $nombre = trim($_POST['nombre'] ?? '');
            $escuela = trim($_POST['escuela'] ?? '');
            $activo = (int)($_POST['activo'] ?? 1);

            if ($idEquipoTorneo <= 0 || $idEquipo <= 0) {
                throw new Exception('El equipo seleccionado no es válido.');
            }

            if ($nombre === '') {
                throw new Exception('El nombre del equipo es obligatorio.');
            }

            $equipoActual = obtenerEquipoTorneoPorId($idEquipoTorneo, $idTorneoActual);

            if (!$equipoActual) {
                throw new Exception('El equipo que querés editar no existe en este torneo.');
            }

            if (existeNombreEquipoEnTorneo($idTorneoActual, $nombre, $idEquipo)) {
                throw new Exception('Ya existe otro equipo con ese nombre en este torneo.');
            }

            $nuevoEscudo = subirEscudo('escudo');
            $escudoFinal = $equipoActual['escudo'];

            if ($nuevoEscudo !== null) {
                eliminarEscudoSiExiste($equipoActual['escudo']);
                $escudoFinal = $nuevoEscudo;
            }

            dbQuery("
                UPDATE equipos
                SET nombre = ?, escuela = ?, escudo = ?, activo = ?
                WHERE id_equipo = ?
            ", [$nombre, $escuela, $escudoFinal, $activo, $idEquipo]);

            dbQuery("
                UPDATE equipo_torneo
                SET activo = ?
                WHERE id_equipo_torneo = ?
                  AND id_torneo = ?
            ", [$activo, $idEquipoTorneo, $idTorneoActual]);

            adminSetFlash('success', 'Equipo actualizado correctamente.');
            header('Location: equipos.php');
            exit;
        }

        if ($accion === 'eliminar') {
            $idEquipoTorneo = (int)($_POST['id_equipo_torneo'] ?? 0);

            if ($idEquipoTorneo <= 0) {
                throw new Exception('El equipo seleccionado no es válido.');
            }

            $equipo = obtenerEquipoTorneoPorId($idEquipoTorneo, $idTorneoActual);

            if (!$equipo) {
                throw new Exception('El equipo no existe en este torneo.');
            }

            if (equipoTieneUso($idEquipoTorneo)) {
                dbQuery("
                    UPDATE equipo_torneo
                    SET activo = 0
                    WHERE id_equipo_torneo = ?
                      AND id_torneo = ?
                ", [$idEquipoTorneo, $idTorneoActual]);

                dbQuery("
                    UPDATE equipos
                    SET activo = 0
                    WHERE id_equipo = ?
                ", [$equipo['id_equipo']]);

                adminSetFlash('success', 'El equipo tiene jugadores o partidos, por eso no se eliminó: quedó inactivo.');
                header('Location: equipos.php');
                exit;
            }

            dbQuery("
                DELETE FROM equipo_torneo
                WHERE id_equipo_torneo = ?
                  AND id_torneo = ?
            ", [$idEquipoTorneo, $idTorneoActual]);

            if (!equipoEstaEnOtrosTorneos($equipo['id_equipo'], $idEquipoTorneo)) {
                eliminarEscudoSiExiste($equipo['escudo']);

                dbQuery("
                    DELETE FROM equipos
                    WHERE id_equipo = ?
                ", [$equipo['id_equipo']]);
            }

            adminSetFlash('success', 'Equipo eliminado correctamente.');
            header('Location: equipos.php');
            exit;
        }

        if ($accion === 'activar') {
            $idEquipoTorneo = (int)($_POST['id_equipo_torneo'] ?? 0);

            $equipo = obtenerEquipoTorneoPorId($idEquipoTorneo, $idTorneoActual);

            if (!$equipo) {
                throw new Exception('El equipo no existe en este torneo.');
            }

            dbQuery("
                UPDATE equipo_torneo
                SET activo = 1
                WHERE id_equipo_torneo = ?
                  AND id_torneo = ?
            ", [$idEquipoTorneo, $idTorneoActual]);

            dbQuery("
                UPDATE equipos
                SET activo = 1
                WHERE id_equipo = ?
            ", [$equipo['id_equipo']]);

            adminSetFlash('success', 'Equipo activado correctamente.');
            header('Location: equipos.php');
            exit;
        }

    } catch (Exception $e) {
        adminSetFlash('error', $e->getMessage());
        header('Location: equipos.php');
        exit;
    }
}

/* =====================================================
   DATOS PARA MOSTRAR
===================================================== */

$flash = adminGetFlash();

$equipos = obtenerEquiposDelTorneo($idTorneoActual);

$idEditar = isset($_GET['editar']) ? (int)$_GET['editar'] : 0;
$equipoEditar = null;

if ($idEditar > 0 && $idTorneoActual) {
    $equipoEditar = obtenerEquipoTorneoPorId($idEditar, $idTorneoActual);

    if (!$equipoEditar) {
        adminSetFlash('error', 'El equipo que querés editar no existe.');
        header('Location: equipos.php');
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
            <a href="<?= BASE_URL ?>/admin/torneos.php">🏆 Torneos</a>
            <a class="active" href="<?= BASE_URL ?>/admin/equipos.php">👕 Equipos</a>
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
                <span class="admin-eyebrow">Gestión del torneo</span>
                <h1>Equipos</h1>

                <?php if ($torneoActual): ?>
                    <p>
                        Torneo actual:
                        <strong><?= h($torneoActual['nombre']) ?></strong>
                        · Temporada <?= h($torneoActual['temporada']) ?>
                    </p>
                <?php else: ?>
                    <p>Primero tenés que crear un torneo para poder cargar equipos.</p>
                <?php endif; ?>
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

        <?php if (!$torneoActual): ?>

            <section class="admin-empty-main">
                <h2>No hay torneo creado</h2>
                <p>
                    Para cargar equipos primero tenés que crear la Copa PER.EDU 2026
                    o seleccionar un torneo activo.
                </p>
                <a href="<?= BASE_URL ?>/admin/torneos.php" class="admin-btn admin-btn-primary">
                    Crear torneo
                </a>
            </section>

        <?php else: ?>

            <section class="admin-dashboard-grid">

                <!-- FORMULARIO -->
                <article class="admin-panel">
                    <div class="admin-panel-header">
                        <div>
                            <span><?= $equipoEditar ? 'Editar equipo' : 'Nuevo equipo' ?></span>
                            <h2><?= $equipoEditar ? 'Modificar equipo' : 'Crear equipo' ?></h2>
                        </div>

                        <?php if ($equipoEditar): ?>
                            <a href="<?= BASE_URL ?>/admin/equipos.php">Cancelar edición</a>
                        <?php endif; ?>
                    </div>

                    <form method="POST" enctype="multipart/form-data" class="admin-form">

                        <?php if ($equipoEditar): ?>
                            <input type="hidden" name="accion" value="actualizar">
                            <input type="hidden" name="id_equipo_torneo" value="<?= h($equipoEditar['id_equipo_torneo']) ?>">
                            <input type="hidden" name="id_equipo" value="<?= h($equipoEditar['id_equipo']) ?>">
                        <?php else: ?>
                            <input type="hidden" name="accion" value="crear">
                        <?php endif; ?>

                        <div class="form-group">
                            <label for="nombre">Nombre del equipo</label>
                            <input
                                type="text"
                                id="nombre"
                                name="nombre"
                                placeholder="Ej: Técnica 4"
                                value="<?= h($equipoEditar['nombre'] ?? '') ?>"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="escuela">Escuela o institución</label>
                            <input
                                type="text"
                                id="escuela"
                                name="escuela"
                                placeholder="Ej: Escuela técnica de Mendoza"
                                value="<?= h($equipoEditar['escuela'] ?? '') ?>"
                            >
                        </div>

                        <div class="form-group">
                            <label for="escudo">Escudo del equipo</label>

                            <?php if (!empty($equipoEditar['escudo'])): ?>
                                <div class="current-shield">
                                    <img src="<?= h(asset($equipoEditar['escudo'])) ?>" alt="Escudo actual">
                                    <span>Escudo actual. Si subís otro, se reemplaza.</span>
                                </div>
                            <?php endif; ?>

                            <input
                                type="file"
                                id="escudo"
                                name="escudo"
                                accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                            >

                            <small class="form-help">
                                Formatos permitidos: JPG, PNG o WEBP. Tamaño máximo: 3 MB.
                            </small>
                        </div>

                        <?php if ($equipoEditar): ?>
                            <div class="form-group">
                                <label for="activo">Estado</label>
                                <select id="activo" name="activo">
                                    <option value="1" <?= (int)$equipoEditar['activo_en_torneo'] === 1 ? 'selected' : '' ?>>
                                        Activo
                                    </option>
                                    <option value="0" <?= (int)$equipoEditar['activo_en_torneo'] === 0 ? 'selected' : '' ?>>
                                        Inactivo
                                    </option>
                                </select>
                            </div>
                        <?php endif; ?>

                        <button type="submit" class="admin-btn admin-btn-primary">
                            <?= $equipoEditar ? 'Guardar cambios' : 'Crear equipo' ?>
                        </button>

                    </form>
                </article>

                <!-- AYUDA -->
                <article class="admin-panel">
                    <div class="admin-panel-header">
                        <div>
                            <span>Importante</span>
                            <h2>Todos contra todos</h2>
                        </div>
                    </div>

                    <div class="admin-help-list">
                        <div>
                            <strong>1</strong>
                            <p>En este torneo no usamos zonas. Todos los equipos pertenecen al mismo torneo.</p>
                        </div>

                        <div>
                            <strong>2</strong>
                            <p>El escudo se carga desde esta pantalla y después se usa en fixture, tablas y equipos.</p>
                        </div>

                        <div>
                            <strong>3</strong>
                            <p>Más adelante el admin va a crear manualmente los partidos de fase regular y eliminatorias.</p>
                        </div>

                        <div>
                            <strong>4</strong>
                            <p>Si un equipo ya tiene jugadores o partidos, no se borra definitivamente: queda inactivo.</p>
                        </div>
                    </div>
                </article>

            </section>

            <!-- LISTADO -->
            <section class="admin-section">
                <div class="admin-section-header">
                    <div>
                        <span>Listado general</span>
                        <h2>Equipos del torneo</h2>
                    </div>
                </div>

                <?php if (empty($equipos)): ?>

                    <div class="admin-empty-box">
                        Todavía no hay equipos cargados en este torneo.
                    </div>

                <?php else: ?>

                    <div class="admin-table-wrapper">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Escudo</th>
                                    <th>Equipo</th>
                                    <th>Escuela</th>
                                    <th>Jugadores</th>
                                    <th>Partidos</th>
                                    <th>Estado</th>
                                    <th>Creado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php foreach ($equipos as $equipo): ?>
                                    <tr>
                                        <td>
                                            <div class="team-shield-table">
                                                <?php if (!empty($equipo['escudo'])): ?>
                                                    <img src="<?= h(asset($equipo['escudo'])) ?>" alt="Escudo <?= h($equipo['nombre']) ?>">
                                                <?php else: ?>
                                                    <span>👕</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>

                                        <td>
                                            <strong><?= h($equipo['nombre']) ?></strong>
                                            <small>ID equipo torneo: <?= h($equipo['id_equipo_torneo']) ?></small>
                                        </td>

                                        <td>
                                            <?= h($equipo['escuela'] ?: 'Sin escuela cargada') ?>
                                        </td>

                                        <td><?= h($equipo['total_jugadores']) ?></td>

                                        <td><?= h($equipo['total_partidos']) ?></td>

                                        <td>
                                            <span class="status-badge <?= h(claseActivo($equipo['activo_en_torneo'])) ?>">
                                                <?= h(textoActivo($equipo['activo_en_torneo'])) ?>
                                            </span>
                                        </td>

                                        <td><?= h(formatearFecha($equipo['creado_en'])) ?></td>

                                        <td>
                                            <div class="table-actions">
                                                <a
                                                    href="<?= BASE_URL ?>/admin/equipos.php?editar=<?= h($equipo['id_equipo_torneo']) ?>"
                                                    class="table-btn edit"
                                                >
                                                    Editar
                                                </a>

                                                <a
                                                    href="<?= BASE_URL ?>/admin/planteles.php?equipo=<?= h($equipo['id_equipo_torneo']) ?>"
                                                    class="table-btn info"
                                                >
                                                    Plantel
                                                </a>

                                                <?php if ((int)$equipo['activo_en_torneo'] === 1): ?>
                                                    <form
                                                        method="POST"
                                                        onsubmit="return confirm('¿Seguro que querés eliminar o desactivar este equipo?');"
                                                    >
                                                        <input type="hidden" name="accion" value="eliminar">
                                                        <input type="hidden" name="id_equipo_torneo" value="<?= h($equipo['id_equipo_torneo']) ?>">

                                                        <button type="submit" class="table-btn delete">
                                                            Eliminar
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <form method="POST">
                                                        <input type="hidden" name="accion" value="activar">
                                                        <input type="hidden" name="id_equipo_torneo" value="<?= h($equipo['id_equipo_torneo']) ?>">

                                                        <button type="submit" class="table-btn success">
                                                            Activar
                                                        </button>
                                                    </form>
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