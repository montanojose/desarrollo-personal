<?php $pageTitle = "Inicio - Escuela Mosconi"; ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php include("includes/head.php"); ?>
</head>
<body>

<?php include("includes/header.php"); ?>

<main>

    <section class="about about-with-carousel container animate-on-scroll">
        <div class="about-text">
            <div class="section-heading">
                <span class="section-tag">Institución</span>
                <h2>Sobre nuestra escuela</h2>
            </div>

            <p>
                La Escuela Mosconi trabaja día a día para brindar una formación técnica sólida,
                acompañando a los estudiantes en su crecimiento académico, humano y profesional.
                Esta plataforma busca fortalecer el vínculo entre la institución, las familias y la comunidad.
            </p>

            <a href="nosotros.php" class="btn-secondary">Conocé más</a>
        </div>

        <div class="about-carousel">
            <div class="carousel-track">
                <img src="includes/img/escuela-1.png" alt="Imagen de la Escuela Mosconi" class="carousel-img active">
                <img src="includes/img/escuela-2.png" alt="Instalaciones de la Escuela Mosconi" class="carousel-img">
                <img src="includes/img/escuela-3.png" alt="Actividades escolares" class="carousel-img">
            </div>

            <button class="carousel-btn carousel-prev" type="button">‹</button>
            <button class="carousel-btn carousel-next" type="button">›</button>

            <div class="carousel-dots">
                <button class="carousel-dot active" type="button"></button>
                <button class="carousel-dot" type="button"></button>
                <button class="carousel-dot" type="button"></button>
            </div>
        </div>
    </section>

    <section class="stats-section container animate-on-scroll">
        <div class="stats-grid">
            <div class="stat-card">
                <span class="stat-number" data-target="650">0</span>
                <p>Alumnos</p>
            </div>

            <div class="stat-card">
                <span class="stat-number" data-target="24">0</span>
                <p>Cursos</p>
            </div>

            <div class="stat-card">
                <span class="stat-number" data-target="3">0</span>
                <p>Espacios curriculares</p>
            </div>
        </div>
    </section>

    <section class="quick-links container">
        <article class="card animate-on-scroll">
            <h3>Espacios Curriculares</h3>
            <p>Conocé nuestras tecnicaturas y propuestas formativas.</p>
            <a href="espacios-curriculares.php">Ver más</a>
        </article>

        <article class="card animate-on-scroll">
            <h3>Instalaciones</h3>
            <p>Recorré los espacios donde se desarrollan las actividades escolares.</p>
            <a href="instalaciones.php">Ver más</a>
        </article>

        <article class="card animate-on-scroll">
            <h3>Blog</h3>
            <p>Descubrí noticias, eventos y novedades de la institución.</p>
            <a href="blog.php">Ver más</a>
        </article>
    </section>

    <section class="news container">
        <div class="section-heading animate-on-scroll">
            <span class="section-tag">Actualidad</span>
            <h2>Últimas novedades</h2>
        </div>

        <div class="news-grid">
            <article class="news-card animate-on-scroll">
                <span class="news-date">Abril 2026</span>
                <h3>Feria de proyectos institucionales</h3>
                <p>Los estudiantes presentaron trabajos y experiencias desarrolladas en los talleres y espacios técnicos.</p>
            </article>

            <article class="news-card animate-on-scroll">
                <span class="news-date">Abril 2026</span>
                <h3>Mejoras en laboratorios</h3>
                <p>La escuela continúa fortaleciendo sus espacios de aprendizaje con nuevas mejoras en equipamiento.</p>
            </article>

            <article class="news-card animate-on-scroll">
                <span class="news-date">Abril 2026</span>
                <h3>Actividades con la comunidad</h3>
                <p>Se realizaron propuestas institucionales que fortalecen el vínculo entre la escuela y su entorno.</p>
            </article>
        </div>
    </section>

</main>

<?php include("includes/footer.php"); ?>

</body>
</html>