<?php
// includes/functions.php

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

/* =====================================================
   FUNCIONES GENERALES
===================================================== */

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function dbQuery($sql, $params = [])
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function dbOne($sql, $params = [])
{
    return dbQuery($sql, $params)->fetch();
}

function dbAll($sql, $params = [])
{
    return dbQuery($sql, $params)->fetchAll();
}

function formatearFecha($fecha)
{
    if (empty($fecha)) {
        return 'Sin fecha';
    }

    return date('d/m/Y', strtotime($fecha));
}

function formatearFechaHora($fechaHora)
{
    if (empty($fechaHora)) {
        return 'Sin programar';
    }

    return date('d/m/Y H:i', strtotime($fechaHora));
}

/* =====================================================
   TORNEOS
===================================================== */

function obtenerTorneoActual()
{
    return dbOne("
        SELECT *
        FROM torneos
        ORDER BY 
            CASE estado
                WHEN 'en_curso' THEN 1
                WHEN 'borrador' THEN 2
                WHEN 'finalizado' THEN 3
                ELSE 4
            END,
            temporada DESC,
            id_torneo DESC
        LIMIT 1
    ");
}

function obtenerTorneos()
{
    return dbAll("
        SELECT *
        FROM torneos
        ORDER BY temporada DESC, id_torneo DESC
    ");
}

/* =====================================================
   RESUMEN ADMIN
===================================================== */

function obtenerResumenAdmin($idTorneo)
{
    $resumen = [
        'torneos' => 0,
        'equipos' => 0,
        'jugadores' => 0,
        'partidos' => 0,
        'partidos_finalizados' => 0,
        'partidos_pendientes' => 0,
        'goles' => 0,
        'sanciones_pendientes' => 0,
    ];

    $resumen['torneos'] = (int) dbOne("SELECT COUNT(*) AS total FROM torneos")['total'];

    if (!$idTorneo) {
        return $resumen;
    }

    $resumen['equipos'] = (int) dbOne("
        SELECT COUNT(*) AS total
        FROM equipo_torneo
        WHERE id_torneo = ? AND activo = 1
    ", [$idTorneo])['total'];

    $resumen['jugadores'] = (int) dbOne("
        SELECT COUNT(DISTINCT jet.id_jugador) AS total
        FROM jugador_equipo_torneo jet
        INNER JOIN equipo_torneo et 
            ON jet.id_equipo_torneo = et.id_equipo_torneo
        WHERE et.id_torneo = ?
          AND jet.activo = 1
    ", [$idTorneo])['total'];

    $resumen['partidos'] = (int) dbOne("
        SELECT COUNT(*) AS total
        FROM partidos
        WHERE id_torneo = ?
    ", [$idTorneo])['total'];

    $resumen['partidos_finalizados'] = (int) dbOne("
        SELECT COUNT(*) AS total
        FROM partidos
        WHERE id_torneo = ?
          AND estado = 'finalizado'
    ", [$idTorneo])['total'];

    $resumen['partidos_pendientes'] = (int) dbOne("
        SELECT COUNT(*) AS total
        FROM partidos
        WHERE id_torneo = ?
          AND estado IN ('programado', 'en_juego')
    ", [$idTorneo])['total'];

    $resumen['goles'] = (int) dbOne("
        SELECT COALESCE(SUM(goles_local + goles_visitante), 0) AS total
        FROM partidos
        WHERE id_torneo = ?
          AND estado = 'finalizado'
          AND goles_local IS NOT NULL
          AND goles_visitante IS NOT NULL
    ", [$idTorneo])['total'];

    $resumen['sanciones_pendientes'] = (int) dbOne("
        SELECT COUNT(*) AS total
        FROM sanciones_jugador sj
        INNER JOIN jugador_equipo_torneo jet
            ON sj.id_jugador_equipo_torneo = jet.id_jugador_equipo_torneo
        INNER JOIN equipo_torneo et
            ON jet.id_equipo_torneo = et.id_equipo_torneo
        WHERE et.id_torneo = ?
          AND sj.estado = 'pendiente'
    ", [$idTorneo])['total'];

    return $resumen;
}

/* =====================================================
   PARTIDOS
===================================================== */

function obtenerUltimosPartidos($idTorneo, $limite = 6)
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
            ev.nombre AS equipo_visitante,

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
            CASE WHEN p.fecha_hora IS NULL THEN 1 ELSE 0 END,
            p.fecha_hora DESC,
            p.id_partido DESC

        LIMIT $limite
    ", [$idTorneo]);
}

function obtenerProximosPartidos($idTorneo, $limite = 6)
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
            ev.nombre AS equipo_visitante

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

        WHERE p.id_torneo = ?
          AND p.estado = 'programado'

        ORDER BY 
            CASE WHEN p.fecha_hora IS NULL THEN 1 ELSE 0 END,
            p.fecha_hora ASC,
            p.id_partido ASC

        LIMIT $limite
    ", [$idTorneo]);
}

function textoEstadoPartido($estado)
{
    return match ($estado) {
        'programado' => 'Programado',
        'en_juego' => 'En juego',
        'finalizado' => 'Finalizado',
        'suspendido' => 'Suspendido',
        'cancelado' => 'Cancelado',
        default => 'Sin estado',
    };
}

function claseEstadoPartido($estado)
{
    return match ($estado) {
        'programado' => 'badge-blue',
        'en_juego' => 'badge-yellow',
        'finalizado' => 'badge-green',
        'suspendido' => 'badge-orange',
        'cancelado' => 'badge-red',
        default => 'badge-gray',
    };
}

function marcadorPartido($partido)
{
    if ($partido['estado'] !== 'finalizado') {
        return 'vs';
    }

    if ($partido['goles_local'] === null || $partido['goles_visitante'] === null) {
        return 'vs';
    }

    $marcador = $partido['goles_local'] . ' - ' . $partido['goles_visitante'];

    if (
        isset($partido['penales_local'], $partido['penales_visitante']) &&
        $partido['penales_local'] !== null &&
        $partido['penales_visitante'] !== null
    ) {
        $marcador .= ' (' . $partido['penales_local'] . ' - ' . $partido['penales_visitante'] . ')';
    }

    return $marcador;
}

/* =====================================================
   ESTADÍSTICAS PÚBLICAS
===================================================== */

function obtenerGoleadores($idTorneo, $limite = 10)
{
    if (!$idTorneo) {
        return [];
    }

    return dbAll("
        SELECT
            j.id_jugador,
            CONCAT(j.nombre, ' ', j.apellido) AS jugador,
            e.nombre AS equipo,
            SUM(jp.goles) AS goles
        FROM jugador_partido jp
        INNER JOIN jugador_equipo_torneo jet
            ON jp.id_jugador_equipo_torneo = jet.id_jugador_equipo_torneo
        INNER JOIN jugadores j
            ON jet.id_jugador = j.id_jugador
        INNER JOIN equipo_torneo et
            ON jet.id_equipo_torneo = et.id_equipo_torneo
        INNER JOIN equipos e
            ON et.id_equipo = e.id_equipo
        INNER JOIN partidos p
            ON jp.id_partido = p.id_partido
        WHERE p.id_torneo = ?
          AND p.estado = 'finalizado'
        GROUP BY j.id_jugador, jugador, equipo
        ORDER BY goles DESC, jugador ASC
        LIMIT $limite
    ", [$idTorneo]);
}

function obtenerTarjetas($idTorneo, $limite = 10)
{
    if (!$idTorneo) {
        return [];
    }

    return dbAll("
        SELECT
            j.id_jugador,
            CONCAT(j.nombre, ' ', j.apellido) AS jugador,
            e.nombre AS equipo,
            SUM(jp.amarillas) AS amarillas,
            SUM(jp.rojas) AS rojas
        FROM jugador_partido jp
        INNER JOIN jugador_equipo_torneo jet
            ON jp.id_jugador_equipo_torneo = jet.id_jugador_equipo_torneo
        INNER JOIN jugadores j
            ON jet.id_jugador = j.id_jugador
        INNER JOIN equipo_torneo et
            ON jet.id_equipo_torneo = et.id_equipo_torneo
        INNER JOIN equipos e
            ON et.id_equipo = e.id_equipo
        INNER JOIN partidos p
            ON jp.id_partido = p.id_partido
        WHERE p.id_torneo = ?
          AND p.estado = 'finalizado'
        GROUP BY j.id_jugador, jugador, equipo
        ORDER BY amarillas DESC, rojas DESC, jugador ASC
        LIMIT $limite
    ", [$idTorneo]);
}
/* =====================================================
   AUTENTICACIÓN ADMIN
===================================================== */

function iniciarSesionSiNoExiste()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function usuarioLogueado()
{
    iniciarSesionSiNoExiste();

    return isset($_SESSION['usuario']);
}

function usuarioEsAdmin()
{
    iniciarSesionSiNoExiste();

    return isset($_SESSION['usuario']) &&
           isset($_SESSION['usuario']['id_rol']) &&
           (int)$_SESSION['usuario']['id_rol'] === 2;
}

function obtenerUsuarioLogueado()
{
    iniciarSesionSiNoExiste();

    return $_SESSION['usuario'] ?? null;
}

function requireAdmin()
{
    iniciarSesionSiNoExiste();

    if (!usuarioEsAdmin()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? BASE_URL . '/admin/index.php';

        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

function loginUsuario($usuario)
{
    iniciarSesionSiNoExiste();

    session_regenerate_id(true);

    $_SESSION['usuario'] = [
        'id_usuario' => $usuario['id_usuario'],
        'id_rol' => $usuario['id_rol'],
        'nombre' => $usuario['nombre'],
        'apellido' => $usuario['apellido'],
        'email' => $usuario['email'],
    ];
}

function logoutUsuario()
{
    iniciarSesionSiNoExiste();

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}