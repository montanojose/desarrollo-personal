<?php
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = "Jugadores - Panel Admin";
$pageDescription = "Administración de jugadores Copa PER.EDU";

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

function obtenerJugadorPorId($idJugador)
{
    return dbOne("
        SELECT *
        FROM jugadores
        WHERE id_jugador = ?
        LIMIT 1
    ", [$idJugador]);
}

function obtenerJugadores($busqueda = '', $estado = 'todos')
{
    $sql = "
        SELECT
            j.id_jugador,
            j.nombre,
            j.apellido,
            j.dni,
            j.fecha_nacimiento,
            j.activo,
            j.creado_en,

            (
                SELECT COUNT(*)
                FROM jugador_equipo_torneo jet
                WHERE jet.id_jugador = j.id_jugador
            ) AS total_planteles,

            (
                SELECT COUNT(*)
                FROM jugador_equipo_torneo jet
                WHERE jet.id_jugador = j.id_jugador
                  AND jet.activo = 1
            ) AS planteles_activos

        FROM jugadores j
        WHERE 1 = 1
    ";

    $params = [];

    if ($busqueda !== '') {
        $sql .= "
            AND (
                j.nombre LIKE ?
                OR j.apellido LIKE ?
                OR CONCAT(j.nombre, ' ', j.apellido) LIKE ?
                OR j.dni LIKE ?
            )
        ";

        $like = '%' . $busqueda . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    if ($estado === 'activos') {
        $sql .= " AND j.activo = 1 ";
    }

    if ($estado === 'inactivos') {
        $sql .= " AND j.activo = 0 ";
    }

    $sql .= "
        ORDER BY j.apellido ASC, j.nombre ASC, j.id_jugador DESC
    ";

    return dbAll($sql, $params);
}

function existeDniJugador($dni, $idJugadorExcluir = 0)
{
    if ($dni === '') {
        return false;
    }

    $resultado = dbOne("
        SELECT COUNT(*) AS total
        FROM jugadores
        WHERE dni = ?
          AND id_jugador <> ?
    ", [$dni, $idJugadorExcluir]);

    return (int)$resultado['total'] > 0;
}

function jugadorTienePlantel($idJugador)
{
    $resultado = dbOne("
        SELECT COUNT(*) AS total
        FROM jugador_equipo_torneo
        WHERE id_jugador = ?
    ", [$idJugador]);

    return (int)$resultado['total'] > 0;
}

function jugadorTienePartidos($idJugador)
{
    $resultado = dbOne("
        SELECT COUNT(*) AS total
        FROM jugador_partido jp
        INNER JOIN jugador_equipo_torneo jet
            ON jp.id_jugador_equipo_torneo = jet.id_jugador_equipo_torneo
        WHERE jet.id_jugador = ?
    ", [$idJugador]);

    return (int)$resultado['total'] > 0;
}

function normalizarFechaNacimiento($fecha)
{
    $fecha = trim($fecha);

    if ($fecha === '') {
        return null;
    }

    $partes = explode('-', $fecha);

    if (count($partes) !== 3) {
        throw new Exception('La fecha de nacimiento no es válida.');
    }

    $anio = (int)$partes[0];
    $mes = (int)$partes[1];
    $dia = (int)$partes[2];

    if (!checkdate($mes, $dia, $anio)) {
        throw new Exception('La fecha de nacimiento no es válida.');
    }

    return $fecha;
}

function calcularEdad($fechaNacimiento)
{
    if (empty($fechaNacimiento)) {
        return 'Sin dato';
    }

    $nacimiento = new DateTime($fechaNacimiento);
    $hoy = new DateTime();

    return $hoy->diff($nacimiento)->y . ' años';
}

function textoActivoJugador($activo)
{
    return (int)$activo === 1 ? 'Activo' : 'Inactivo';
}

function claseActivoJugador($activo)
{
    return (int)$activo === 1 ? 'badge-green' : 'badge-gray';
}

function inicialesJugador($nombre, $apellido)
{
    $inicialNombre = mb_substr(trim($nombre), 0, 1, 'UTF-8');
    $inicialApellido = mb_substr(trim($apellido), 0, 1, 'UTF-8');

    return mb_strtoupper($inicialNombre . $inicialApellido, 'UTF-8');
}

/* =====================================================
   PROCESAR FORMULARIOS
===================================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    try {
        if ($accion === 'crear') {
            $nombre = trim($_POST['nombre'] ?? '');
            $apellido = trim($_POST['apellido'] ?? '');
            $dni = trim($_POST['dni'] ?? '');
            $fechaNacimiento = normalizarFechaNacimiento($_POST['fecha_nacimiento'] ?? '');

            if ($nombre === '') {
                throw new Exception('El nombre del jugador es obligatorio.');
            }

            if ($apellido === '') {
                throw new Exception('El apellido del jugador es obligatorio.');
            }

            if (existeDniJugador($dni)) {
                throw new Exception('Ya existe un jugador cargado con ese DNI.');
            }

            dbQuery("
                INSERT INTO jugadores (
                    id_usuario,
                    nombre,
                    apellido,
                    dni,
                    fecha_nacimiento,
                    foto,
                    activo
                )
                VALUES (
                    NULL,
                    ?,
                    ?,
                    ?,
                    ?,
                    NULL,
                    1
                )
            ", [
                $nombre,
                $apellido,
                $dni !== '' ? $dni : null,
                $fechaNacimiento
            ]);

            adminSetFlash('success', 'Jugador creado correctamente.');
            header('Location: jugadores.php');
            exit;
        }

        if ($accion === 'actualizar') {
            $idJugador = (int)($_POST['id_jugador'] ?? 0);
            $nombre = trim($_POST['nombre'] ?? '');
            $apellido = trim($_POST['apellido'] ?? '');
            $dni = trim($_POST['dni'] ?? '');
            $fechaNacimiento = normalizarFechaNacimiento($_POST['fecha_nacimiento'] ?? '');
            $activo = (int)($_POST['activo'] ?? 1);

            if ($idJugador <= 0) {
                throw new Exception('El jugador seleccionado no es válido.');
            }

            if ($nombre === '') {
                throw new Exception('El nombre del jugador es obligatorio.');
            }

            if ($apellido === '') {
                throw new Exception('El apellido del jugador es obligatorio.');
            }

            $jugador = obtenerJugadorPorId($idJugador);

            if (!$jugador) {
                throw new Exception('El jugador que querés editar no existe.');
            }

            if (existeDniJugador($dni, $idJugador)) {
                throw new Exception('Ya existe otro jugador cargado con ese DNI.');
            }

            dbQuery("
                UPDATE jugadores
                SET nombre = ?,
                    apellido = ?,
                    dni = ?,
                    fecha_nacimiento = ?,
                    activo = ?
                WHERE id_jugador = ?
            ", [
                $nombre,
                $apellido,
                $dni !== '' ? $dni : null,
                $fechaNacimiento,
                $activo,
                $idJugador
            ]);

            if ($activo === 0) {
                dbQuery("
                    UPDATE jugador_equipo_torneo
                    SET activo = 0
                    WHERE id_jugador = ?
                ", [$idJugador]);
            }

            adminSetFlash('success', 'Jugador actualizado correctamente.');
            header('Location: jugadores.php');
            exit;
        }

        if ($accion === 'desactivar') {
            $idJugador = (int)($_POST['id_jugador'] ?? 0);

            if ($idJugador <= 0) {
                throw new Exception('El jugador seleccionado no es válido.');
            }

            $jugador = obtenerJugadorPorId($idJugador);

            if (!$jugador) {
                throw new Exception('El jugador no existe.');
            }

            dbQuery("
                UPDATE jugadores
                SET activo = 0
                WHERE id_jugador = ?
            ", [$idJugador]);

            dbQuery("
                UPDATE jugador_equipo_torneo
                SET activo = 0
                WHERE id_jugador = ?
            ", [$idJugador]);

            adminSetFlash('success', 'Jugador desactivado correctamente.');
            header('Location: jugadores.php');
            exit;
        }

        if ($accion === 'activar') {
            $idJugador = (int)($_POST['id_jugador'] ?? 0);

            if ($idJugador <= 0) {
                throw new Exception('El jugador seleccionado no es válido.');
            }

            $jugador = obtenerJugadorPorId($idJugador);

            if (!$jugador) {
                throw new Exception('El jugador no existe.');
            }

            dbQuery("
                UPDATE jugadores
                SET activo = 1
                WHERE id_jugador = ?
            ", [$idJugador]);

            adminSetFlash('success', 'Jugador activado correctamente. Si estaba en un plantel inactivo, podés reactivarlo desde Planteles.');
            header('Location: jugadores.php');
            exit;
        }

        if ($accion === 'eliminar') {
            $idJugador = (int)($_POST['id_jugador'] ?? 0);

            if ($idJugador <= 0) {
                throw new Exception('El jugador seleccionado no es válido.');
            }

            $jugador = obtenerJugadorPorId($idJugador);

            if (!$jugador) {
                throw new Exception('El jugador no existe.');
            }

            if (jugadorTienePlantel($idJugador) || jugadorTienePartidos($idJugador)) {
                dbQuery("
                    UPDATE jugadores
                    SET activo = 0
                    WHERE id_jugador = ?
                ", [$idJugador]);

                dbQuery("
                    UPDATE jugador_equipo_torneo
                    SET activo = 0
                    WHERE id_jugador = ?
                ", [$idJugador]);

                adminSetFlash('success', 'El jugador ya estaba asociado a un plantel o partido, por eso no se eliminó: quedó inactivo.');
                header('Location: jugadores.php');
                exit;
            }

            dbQuery("
                DELETE FROM jugadores
                WHERE id_jugador = ?
            ", [$idJugador]);

            adminSetFlash('success', 'Jugador eliminado correctamente.');
            header('Location: jugadores.php');
            exit;
        }

    } catch (Exception $e) {
        adminSetFlash('error', $e->getMessage());
        header('Location: jugadores.php');
        exit;
    }
}

/* =====================================================
   DATOS PARA MOSTRAR
===================================================== */

$flash = adminGetFlash();

$busqueda = trim($_GET['buscar'] ?? '');
$estadoFiltro = $_GET['estado'] ?? 'todos';

if (!in_array($estadoFiltro, ['todos', 'activos', 'inactivos'])) {
    $estadoFiltro = 'todos';
}

$jugadores = obtenerJugadores($busqueda, $estadoFiltro);

$idEditar = isset($_GET['editar']) ? (int)$_GET['editar'] : 0;
$jugadorEditar = null;

if ($idEditar > 0) {
    $jugadorEditar = obtenerJugadorPorId($idEditar);

    if (!$jugadorEditar) {
        adminSetFlash('error', 'El jugador que querés editar no existe.');
        header('Location: jugadores.php');
        exit;
    }
}

$totalJugadores = (int) dbOne("SELECT COUNT(*) AS total FROM jugadores")['total'];
$totalActivos = (int) dbOne("SELECT COUNT(*) AS total FROM jugadores WHERE activo = 1")['total'];
$totalInactivos = (int) dbOne("SELECT COUNT(*) AS total FROM jugadores WHERE activo = 0")['total'];

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
            <a class="active" href="<?= BASE_URL ?>/admin/jugadores.php">👤 Jugadores</a>
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
                <span class="admin-eyebrow">Gestión de personas</span>
                <h1>Jugadores</h1>
                <p>
                    Desde acá cargás los jugadores generales. Después, en Planteles,
                    los asociás a un equipo dentro del torneo.
                </p>
            </div>

            <div class="admin-top-actions">
                <a href="<?= BASE_URL ?>/admin/planteles.php" class="admin-btn admin-btn-primary">
                    Ir a planteles
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

        <!-- RESUMEN -->
        <section class="admin-stats-grid admin-stats-grid-compact">

            <article class="admin-stat-card">
                <span>Total jugadores</span>
                <strong><?= h($totalJugadores) ?></strong>
                <p>Cargados en el sistema</p>
            </article>

            <article class="admin-stat-card">
                <span>Activos</span>
                <strong><?= h($totalActivos) ?></strong>
                <p>Disponibles para planteles</p>
            </article>

            <article class="admin-stat-card warning">
                <span>Inactivos</span>
                <strong><?= h($totalInactivos) ?></strong>
                <p>No disponibles</p>
            </article>

            <article class="admin-stat-card">
                <span>Resultado actual</span>
                <strong><?= h(count($jugadores)) ?></strong>
                <p>Según el filtro aplicado</p>
            </article>

        </section>

        <section class="admin-dashboard-grid">

            <!-- FORMULARIO -->
            <article class="admin-panel">
                <div class="admin-panel-header">
                    <div>
                        <span><?= $jugadorEditar ? 'Editar jugador' : 'Nuevo jugador' ?></span>
                        <h2><?= $jugadorEditar ? 'Modificar datos' : 'Cargar jugador' ?></h2>
                    </div>

                    <?php if ($jugadorEditar): ?>
                        <a href="<?= BASE_URL ?>/admin/jugadores.php">Cancelar edición</a>
                    <?php endif; ?>
                </div>

                <form method="POST" class="admin-form">

                    <?php if ($jugadorEditar): ?>
                        <input type="hidden" name="accion" value="actualizar">
                        <input type="hidden" name="id_jugador" value="<?= h($jugadorEditar['id_jugador']) ?>">
                    <?php else: ?>
                        <input type="hidden" name="accion" value="crear">
                    <?php endif; ?>

                    <div class="form-grid-2">
                        <div class="form-group">
                            <label for="nombre">Nombre</label>
                            <input
                                type="text"
                                id="nombre"
                                name="nombre"
                                placeholder="Ej: Juan"
                                value="<?= h($jugadorEditar['nombre'] ?? '') ?>"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="apellido">Apellido</label>
                            <input
                                type="text"
                                id="apellido"
                                name="apellido"
                                placeholder="Ej: Pérez"
                                value="<?= h($jugadorEditar['apellido'] ?? '') ?>"
                                required
                            >
                        </div>
                    </div>

                    <div class="form-grid-2">
                        <div class="form-group">
                            <label for="dni">DNI</label>
                            <input
                                type="text"
                                id="dni"
                                name="dni"
                                placeholder="Ej: 30111222"
                                value="<?= h($jugadorEditar['dni'] ?? '') ?>"
                            >
                            <small class="form-help">
                                Opcional, pero recomendable para evitar jugadores duplicados.
                            </small>
                        </div>

                        <div class="form-group">
                            <label for="fecha_nacimiento">Fecha de nacimiento</label>
                            <input
                                type="date"
                                id="fecha_nacimiento"
                                name="fecha_nacimiento"
                                value="<?= h($jugadorEditar['fecha_nacimiento'] ?? '') ?>"
                            >
                        </div>
                    </div>

                    <?php if ($jugadorEditar): ?>
                        <div class="form-group">
                            <label for="activo">Estado</label>
                            <select id="activo" name="activo">
                                <option value="1" <?= (int)$jugadorEditar['activo'] === 1 ? 'selected' : '' ?>>
                                    Activo
                                </option>
                                <option value="0" <?= (int)$jugadorEditar['activo'] === 0 ? 'selected' : '' ?>>
                                    Inactivo
                                </option>
                            </select>
                            <small class="form-help">
                                Si lo pasás a inactivo, también se desactivan sus asociaciones activas a planteles.
                            </small>
                        </div>
                    <?php endif; ?>

                    <button type="submit" class="admin-btn admin-btn-primary">
                        <?= $jugadorEditar ? 'Guardar cambios' : 'Cargar jugador' ?>
                    </button>

                </form>
            </article>

            <!-- AYUDA -->
            <article class="admin-panel">
                <div class="admin-panel-header">
                    <div>
                        <span>Importante</span>
                        <h2>Cómo se usan</h2>
                    </div>
                </div>

                <div class="admin-help-list">
                    <div>
                        <strong>1</strong>
                        <p>Primero cargás al jugador en esta pantalla.</p>
                    </div>

                    <div>
                        <strong>2</strong>
                        <p>Después lo asociás a un equipo desde la sección Planteles.</p>
                    </div>

                    <div>
                        <strong>3</strong>
                        <p>No se carga foto del jugador para mantener el sistema más simple.</p>
                    </div>

                    <div>
                        <strong>4</strong>
                        <p>Si el jugador ya pertenece a un plantel, no se borra definitivamente: queda inactivo.</p>
                    </div>
                </div>
            </article>

        </section>

        <!-- FILTROS -->
        <section class="admin-section">
            <div class="admin-section-header">
                <div>
                    <span>Buscar y filtrar</span>
                    <h2>Listado de jugadores</h2>
                </div>
            </div>

            <form method="GET" class="admin-filter-form">
                <div class="form-group">
                    <label for="buscar">Buscar jugador</label>
                    <input
                        type="text"
                        id="buscar"
                        name="buscar"
                        placeholder="Nombre, apellido o DNI"
                        value="<?= h($busqueda) ?>"
                    >
                </div>

                <div class="form-group">
                    <label for="estado">Estado</label>
                    <select id="estado" name="estado">
                        <option value="todos" <?= $estadoFiltro === 'todos' ? 'selected' : '' ?>>
                            Todos
                        </option>
                        <option value="activos" <?= $estadoFiltro === 'activos' ? 'selected' : '' ?>>
                            Activos
                        </option>
                        <option value="inactivos" <?= $estadoFiltro === 'inactivos' ? 'selected' : '' ?>>
                            Inactivos
                        </option>
                    </select>
                </div>

                <div class="filter-actions">
                    <button type="submit" class="admin-btn admin-btn-primary">
                        Filtrar
                    </button>

                    <a href="<?= BASE_URL ?>/admin/jugadores.php" class="admin-btn admin-btn-secondary">
                        Limpiar
                    </a>
                </div>
            </form>

            <?php if (empty($jugadores)): ?>

                <div class="admin-empty-box">
                    No hay jugadores para mostrar con los filtros actuales.
                </div>

            <?php else: ?>

                <div class="admin-table-wrapper">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Jugador</th>
                                <th>DNI</th>
                                <th>Edad</th>
                                <th>Planteles</th>
                                <th>Estado</th>
                                <th>Creado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($jugadores as $jugador): ?>
                                <?php
                                $tienePlantel = (int)$jugador['total_planteles'] > 0;
                                ?>

                                <tr>
                                    <td>
                                        <div class="player-cell">
                                            <div class="player-avatar">
                                                <?= h(inicialesJugador($jugador['nombre'], $jugador['apellido'])) ?>
                                            </div>

                                            <div>
                                                <strong>
                                                    <?= h($jugador['apellido']) ?>, <?= h($jugador['nombre']) ?>
                                                </strong>
                                                <small>ID jugador: <?= h($jugador['id_jugador']) ?></small>
                                            </div>
                                        </div>
                                    </td>

                                    <td>
                                        <?= h($jugador['dni'] ?: 'Sin DNI') ?>
                                    </td>

                                    <td>
                                        <?= h(calcularEdad($jugador['fecha_nacimiento'])) ?>
                                    </td>

                                    <td>
                                        <strong><?= h($jugador['total_planteles']) ?></strong>
                                        <small>
                                            Activos: <?= h($jugador['planteles_activos']) ?>
                                        </small>
                                    </td>

                                    <td>
                                        <span class="status-badge <?= h(claseActivoJugador($jugador['activo'])) ?>">
                                            <?= h(textoActivoJugador($jugador['activo'])) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?= h(formatearFecha($jugador['creado_en'])) ?>
                                    </td>

                                    <td>
                                        <div class="table-actions">
                                            <a
                                                href="<?= BASE_URL ?>/admin/jugadores.php?editar=<?= h($jugador['id_jugador']) ?>"
                                                class="table-btn edit"
                                            >
                                                Editar
                                            </a>

                                            <?php if ((int)$jugador['activo'] === 1): ?>
                                                <form method="POST">
                                                    <input type="hidden" name="accion" value="desactivar">
                                                    <input type="hidden" name="id_jugador" value="<?= h($jugador['id_jugador']) ?>">

                                                    <button
                                                        type="submit"
                                                        class="table-btn warning"
                                                        onclick="return confirm('¿Seguro que querés desactivar este jugador?');"
                                                    >
                                                        Desactivar
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST">
                                                    <input type="hidden" name="accion" value="activar">
                                                    <input type="hidden" name="id_jugador" value="<?= h($jugador['id_jugador']) ?>">

                                                    <button type="submit" class="table-btn success">
                                                        Activar
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <form method="POST">
                                                <input type="hidden" name="accion" value="eliminar">
                                                <input type="hidden" name="id_jugador" value="<?= h($jugador['id_jugador']) ?>">

                                                <button
                                                    type="submit"
                                                    class="table-btn delete"
                                                    onclick="return confirm('¿Seguro? Si el jugador ya está asociado a un plantel, quedará inactivo. Si no tiene asociaciones, se eliminará definitivamente.');"
                                                >
                                                    Eliminar
                                                </button>
                                            </form>
                                        </div>

                                        <?php if ($tienePlantel): ?>
                                            <small class="table-muted">
                                                No se borra: tiene historial
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

    </main>

</div>

<script src="<?= asset('assets/js/app.js') ?>"></script>
</body>
</html>