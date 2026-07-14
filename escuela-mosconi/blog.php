<?php $pageTitle = "Blog - Escuela Mosconi"; ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php include("includes/head.php"); ?>
</head>
<body>

<?php include("includes/header.php"); ?>

<main class="blog-page">

    <!-- HERO BLOG -->
    <section class="blog-hero">
        <div class="container blog-hero-content reveal reveal-up">
            <span class="section-tag">Noticias institucionales</span>
            <h1>Blog de la Escuela</h1>
            <p>
                Un espacio pensado para compartir novedades, proyectos, actividades,
                comunicados y experiencias de nuestra comunidad educativa.
            </p>
        </div>
    </section>

    <!-- MENSAJE EN CONSTRUCCIÓN -->
    <section class="blog-working-section">
        <div class="container">
            <div class="blog-working-card reveal reveal-up">

                <div class="blog-working-icon">🛠️</div>

                <span class="section-tag">Estamos trabajando</span>

                <h2>Muy pronto vas a encontrar novedades en esta sección</h2>

                <p>
                    Estamos preparando este espacio para compartir noticias institucionales,
                    proyectos escolares, actividades de los estudiantes, comunicados importantes
                    y momentos destacados de la Escuela 4-022 General Enrique Mosconi.
                </p>

                <p>
                    Nuestro objetivo es que el blog sea un punto de encuentro entre la escuela,
                    las familias, los estudiantes y la comunidad.
                </p>

                <a href="index.php" class="blog-working-btn">Volver al inicio</a>

            </div>
        </div>
    </section>

</main>

<?php include("includes/footer.php"); ?>

<script>
document.addEventListener("DOMContentLoaded", function () {

    /* MENÚ HAMBURGUESA */
    const menuToggle = document.getElementById("menu-toggle");
    const mainNav = document.getElementById("main-nav");

    if (menuToggle && mainNav) {
        menuToggle.addEventListener("click", function () {
            mainNav.classList.toggle("active");
        });
    }

    /* ANIMACIONES AL HACER SCROLL */
    const elementosAnimados = document.querySelectorAll(".reveal");

    const observer = new IntersectionObserver((entradas) => {
        entradas.forEach((entrada) => {
            if (entrada.isIntersecting) {
                entrada.target.classList.add("is-visible");
            }
        });
    }, {
        threshold: 0.18,
        rootMargin: "0px 0px -60px 0px"
    });

    elementosAnimados.forEach((elemento) => {
        observer.observe(elemento);
    });

});
</script>

</body>
</html>