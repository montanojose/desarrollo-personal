<?php
require_once __DIR__ . '/includes/functions.php';

iniciarSesionSiNoExiste();

$pageTitle = "Login Admin - Copa PER.EDU";
$pageDescription = "Acceso al panel administrativo Copa PER.EDU";

$error = '';

if (usuarioEsAdmin()) {
    header('Location: ' . BASE_URL . '/admin/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    try {
        if ($email === '') {
            throw new Exception('Ingresá tu email.');
        }

        if ($password === '') {
            throw new Exception('Ingresá tu contraseña.');
        }

        $usuario = dbOne("
            SELECT
                id_usuario,
                id_rol,
                nombre,
                apellido,
                email,
                password_hash,
                activo
            FROM usuarios
            WHERE email = ?
            LIMIT 1
        ", [$email]);

        if (!$usuario) {
            throw new Exception('Email o contraseña incorrectos.');
        }

        if ((int)$usuario['activo'] !== 1) {
            throw new Exception('Este usuario está inactivo.');
        }

        if ((int)$usuario['id_rol'] !== 2) {
            throw new Exception('Este usuario no tiene permisos de administrador.');
        }

        if (!password_verify($password, $usuario['password_hash'])) {
            throw new Exception('Email o contraseña incorrectos.');
        }

        loginUsuario($usuario);

        $redirect = $_SESSION['redirect_after_login'] ?? BASE_URL . '/admin/index.php';
        unset($_SESSION['redirect_after_login']);

        header('Location: ' . $redirect);
        exit;

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <?php include __DIR__ . '/includes/head.php'; ?>
</head>
<body>

<main class="login-page">

    <section class="login-card">

        <div class="login-brand">
            <div class="login-logo">
        <img src="<?= asset('assets/img/logo.jpg') ?>" alt="Logo Copa PER.EDU">
            </div>

            <div>
                <span>Panel administrativo</span>
                <h1>COPA PER.EDU</h1>
                <p>Ingresá con tu usuario administrador.</p>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="login-alert">
                <?= h($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="login-form">

            <div class="login-group">
                <label for="email">Email</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="admin@correo.com"
                    value="<?= h($_POST['email'] ?? '') ?>"
                    required
                >
            </div>

            <div class="login-group">
                <label for="password">Contraseña</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Tu contraseña"
                    required
                >
            </div>

            <button type="submit" class="btn-main login-btn">
                Ingresar
            </button>

        </form>

        <div class="login-footer">
            <a href="<?= BASE_URL ?>/index.php">← Volver al sitio público</a>
        </div>

    </section>

</main>

</body>
</html>
