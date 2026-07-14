<header class="main-header">
    <div class="container header-content">

        <!-- LOGO + NOMBRE COMO BOTÓN A INICIO -->
        <a href="index.php" class="brand" aria-label="Ir al inicio">
            <img 
                src="includes/img/logo_mosconi.jpeg?v=<?php echo time(); ?>" 
                alt="Logo Escuela Mosconi" 
                class="brand-logo"
            >

            <div class="brand-text">
                <span class="brand-top">Escuela Técnica</span>
                <h1>4-022 Enrique Mosconi</h1>
            </div>
        </a>

        <!-- BOTÓN MENÚ MOBILE -->
        <button class="menu-toggle" id="menu-toggle" aria-label="Abrir menú">
            <span></span>
            <span></span>
            <span></span>
        </button>

        <!-- MENÚ -->
        <nav class="main-nav" id="main-nav">
            <ul class="nav-menu">
                <li><a href="index.php">Inicio</a></li>
                <li><a href="nosotros.php">Nosotros</a></li>
                <li><a href="instalaciones.php">Instalaciones</a></li>
                <li><a href="espacios-curriculares.php">Espacios Curriculares</a></li>
                <li><a href="blog.php">Blog</a></li>
            </ul>
        </nav>

    </div>
</header>