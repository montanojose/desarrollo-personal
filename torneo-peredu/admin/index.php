<?php
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();
$pageTitle = "Panel Admin - Copa PER.EDU";
$pageDescription = "Panel de administración de Copa PER.EDU";

$torneoActual = obtenerTorneoActual();
$idTorneoActual = $torneoActual['id_torneo'] ?? null;

$resumen = obtenerResumenAdmin($idTorneoActual);
$ultimosPartidos = obtenerUltimosPartidos($idTorneoActual, 6);
$proximosPartidos = obtenerProximosPartidos($idTorneoActual, 6);
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
            <a class="active" href="<?= BASE_URL ?>/admin/index.php">🏠 Inicio</a>
            <a href="<?= BASE_URL ?>/admin/torneos.php">🏆 Torneos</a>
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
                <span class="admin-eyebrow">Copa PER.EDU 2026</span>
                <h1>Panel de administración</h1>

                <?php if ($torneoActual): ?>
                    <p>
                        Torneo actual:
                        <strong><?= h($torneoActual['nombre']) ?></strong>
                        · Temporada <?= h($torneoActual['temporada']) ?>
                    </p>
                <?php else: ?>
                    <p>No hay torneos cargados todavía.</p>
                <?php endif; ?>
            </div>

            <div class="admin-top-actions">
                <a href="<?= BASE_URL ?>/admin/torneos.php" class="admin-btn admin-btn-secondary">
                    Crear torneo
                </a>

                <a href="<?= BASE_URL ?>/admin/partidos.php" class="admin-btn admin-btn-primary">
                    Nuevo partido
                </a>
            </div>
        </header>

        <?php if (!$torneoActual): ?>

            <section class="admin-empty-main">
                <h2>Primero tenés que crear un torneo</h2>
                <p>
                    Para empezar a usar el sistema, creá la Copa PER.EDU 2026.
                    Después vas a poder cargar equipos, jugadores, fases, fechas y partidos.
                </p>
                <a href="<?= BASE_URL ?>/admin/torneos.php" class="admin-btn admin-btn-primary">
                    Crear primer torneo
                </a>
            </section>

        <?php else: ?>

            <!-- TARJETAS RESUMEN -->
            <section class="admin-stats-grid">

                <article class="admin-stat-card">
                    <span>Torneos</span>
                    <strong><?= h($resumen['torneos']) ?></strong>
                    <p>Total creados</p>
                </article>

                <article class="admin-stat-card">
                    <span>Equipos</span>
                    <strong><?= h($resumen['equipos']) ?></strong>
                    <p>En el torneo actual</p>
                </article>

                <article class="admin-stat-card">
                    <span>Jugadores</span>
                    <strong><?= h($resumen['jugadores']) ?></strong>
                    <p>Asociados a planteles</p>
                </article>

                <article class="admin-stat-card">
                    <span>Partidos</span>
                    <strong><?= h($resumen['partidos']) ?></strong>
                    <p>Total programados</p>
                </article>

                <article class="admin-stat-card">
                    <span>Finalizados</span>
                    <strong><?= h($resumen['partidos_finalizados']) ?></strong>
                    <p>Con resultado cargado</p>
                </article>

                <article class="admin-stat-card">
                    <span>Pendientes</span>
                    <strong><?= h($resumen['partidos_pendientes']) ?></strong>
                    <p>Por jugar o en curso</p>
                </article>

                <article class="admin-stat-card">
                    <span>Goles</span>
                    <strong><?= h($resumen['goles']) ?></strong>
                    <p>En partidos finalizados</p>
                </article>

                <article class="admin-stat-card warning">
                    <span>Sanciones</span>
                    <strong><?= h($resumen['sanciones_pendientes']) ?></strong>
                    <p>Pendientes</p>
                </article>

            </section>

            <!-- ACCIONES RÁPIDAS -->
            <section class="admin-section">
                <div class="admin-section-header">
                    <div>
                        <span>Gestión rápida</span>
                        <h2>Acciones principales</h2>
                    </div>
                </div>

                <div class="admin-actions-grid">

                    <a href="<?= BASE_URL ?>/admin/equipos.php" class="admin-action-card">
                        <div>👕</div>
                        <h3>Cargar equipos</h3>
                        <p>Crear equipos y dejarlos disponibles para el torneo.</p>
                    </a>

                    <a href="<?= BASE_URL ?>/admin/jugadores.php" class="admin-action-card">
                        <div>👤</div>
                        <h3>Cargar jugadores</h3>
                        <p>Registrar jugadores con sus datos principales.</p>
                    </a>

                    <a href="<?= BASE_URL ?>/admin/planteles.php" class="admin-action-card">
                        <div>📋</div>
                        <h3>Armar planteles</h3>
                        <p>Asociar jugadores a equipos dentro del torneo.</p>
                    </a>

                    <a href="<?= BASE_URL ?>/admin/fases.php" class="admin-action-card">
                        <div>🧩</div>
                        <h3>Crear fases</h3>
                        <p>Fase regular, cuartos, semifinal, final, etc.</p>
                    </a>

                    <a href="<?= BASE_URL ?>/admin/fechas.php" class="admin-action-card">
                        <div>📅</div>
                        <h3>Crear fechas</h3>
                        <p>Fecha 1, Fecha 2, Cuartos, Semifinal o Final.</p>
                    </a>

                    <a href="<?= BASE_URL ?>/admin/partidos.php" class="admin-action-card">
                        <div>⚽</div>
                        <h3>Crear partidos</h3>
                        <p>Asignar fase, fecha, local, visitante, cancha y horario.</p>
                    </a>

                    <a href="<?= BASE_URL ?>/admin/resultados.php" class="admin-action-card">
                        <div>✍</div>
                        <h3>Cargar resultados</h3>
                        <p>Registrar goles, asistencia, tarjetas y penales.</p>
                    </a>

                    <a href="<?= BASE_URL ?>/admin/estadisticas.php" class="admin-action-card">
                        <div>📊</div>
                        <h3>Ver estadísticas</h3>
                        <p>Goleadores, tarjetas y rendimiento general.</p>
                    </a>

                </div>
            </section>

            <!-- PARTIDOS -->
            <section class="admin-dashboard-grid">

                <article class="admin-panel">
                    <div class="admin-panel-header">
                        <div>
                            <span>Control</span>
                            <h2>Últimos partidos</h2>
                        </div>
                        <a href="<?= BASE_URL ?>/admin/partidos.php">Ver todos</a>
                    </div>

                    <?php if (empty($ultimosPartidos)): ?>
                        <div class="admin-empty-box">
                            Todavía no hay partidos cargados.
                        </div>
                    <?php else: ?>
                        <div class="admin-match-list">
                            <?php foreach ($ultimosPartidos as $partido): ?>
                                <div class="admin-match-item">
                                    <div class="admin-match-info">
                                        <span>
                                            <?= h($partido['fase_nombre']) ?>
                                            ·
                                            <?= h($partido['fecha_nombre'] ?: 'Fecha ' . $partido['numero_fecha']) ?>
                                        </span>

                                        <strong>
                                            <?= h($partido['equipo_local']) ?>
                                            <em><?= h(marcadorPartido($partido)) ?></em>
                                            <?= h($partido['equipo_visitante']) ?>
                                        </strong>

                                        <small><?= h(formatearFechaHora($partido['fecha_hora'])) ?></small>
                                    </div>

                                    <span class="status-badge <?= h(claseEstadoPartido($partido['estado'])) ?>">
                                        <?= h(textoEstadoPartido($partido['estado'])) ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </article>

                <article class="admin-panel">
                    <div class="admin-panel-header">
                        <div>
                            <span>Fixture</span>
                            <h2>Próximos partidos</h2>
                        </div>
                        <a href="<?= BASE_URL ?>/admin/partidos.php">Programar</a>
                    </div>

                    <?php if (empty($proximosPartidos)): ?>
                        <div class="admin-empty-box">
                            No hay próximos partidos programados.
                        </div>
                    <?php else: ?>
                        <div class="admin-match-list">
                            <?php foreach ($proximosPartidos as $partido): ?>
                                <div class="admin-match-item">
                                    <div class="admin-match-info">
                                        <span>
                                            <?= h($partido['fase_nombre']) ?>
                                            ·
                                            <?= h($partido['fecha_nombre'] ?: 'Fecha ' . $partido['numero_fecha']) ?>
                                        </span>

                                        <strong>
                                            <?= h($partido['equipo_local']) ?>
                                            <em>vs</em>
                                            <?= h($partido['equipo_visitante']) ?>
                                        </strong>

                                        <small>
                                            <?= h(formatearFechaHora($partido['fecha_hora'])) ?>
                                            <?php if (!empty($partido['cancha'])): ?>
                                                · <?= h($partido['cancha']) ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>

                                    <span class="status-badge badge-blue">
                                        Programado
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </article>

            </section>

        <?php endif; ?>

    </main>

</div>

<script src="<?= asset('assets/js/app.js') ?>"></script>
</body>
</html>