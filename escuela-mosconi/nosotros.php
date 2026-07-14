<?php $pageTitle = "Nosotros - Escuela Mosconi"; ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php include("includes/head.php"); ?>
</head>
<body>

<?php include("includes/header.php"); ?>

<main class="nosotros-page">

    <!-- HERO NOSOTROS -->
    <section class="nosotros-hero">
        <div class="container nosotros-hero-content">
            <span class="section-tag">Nuestra institución</span>
            <h1>4-022 Escuela General Enrique Mosconi</h1>
            <p>
                Una escuela secundaria técnica y científica comprometida con la formación integral,
                la innovación educativa y el desarrollo de jóvenes preparados para los desafíos actuales.
            </p>
        </div>
    </section>

    <!-- HISTORIA -->
    <section class="nosotros-section historia-section">
        <div class="container historia-grid">

            <div class="historia-image-box reveal reveal-left">
                <div class="historia-placeholder">
                    <img src="includes/img/escuela_vieja.png" alt="Historia de la Escuela General Enrique Mosconi">
                </div>
            </div>

            <div class="historia-content reveal reveal-right reveal-delay-1">
                <span class="section-tag">Historia institucional</span>
                <h2>Una escuela con identidad técnica y compromiso social</h2>

                <p>
                    La Escuela General Enrique Mosconi fue creada el 1 de julio de 1966 en Chacras de Coria,
                    iniciando su camino como una institución orientada a la formación técnica en Electricidad General.
                    En sus primeros años funcionó en horario vespertino, impulsada por el esfuerzo de la comunidad
                    y de docentes comprometidos con brindar nuevas oportunidades educativas.
                </p>

                <p>
                    Con el paso del tiempo, la escuela fue consolidando una identidad propia, vinculada a la formación
                    técnica, al acompañamiento de trayectorias escolares y a la inclusión de jóvenes de la zona.
                    En el año 2000 se trasladó a calle Besares, comenzando una nueva etapa de crecimiento,
                    ampliación de matrícula y fortalecimiento de su propuesta educativa.
                </p>

                <div class="historia-data">
                    <div>
                        <strong>1966</strong>
                        <span>Año de creación</span>
                    </div>
                    <div>
                        <strong>Besares 237</strong>
                        <span>Chacras de Coria</span>
                    </div>
                    <div>
                        <strong>Formación</strong>
                        <span>Técnica y científica</span>
                    </div>
                </div>
            </div>

        </div>
    </section>

    <!-- MISIÓN Y VISIÓN -->
    <section class="nosotros-section mision-vision-section">
        <div class="container mision-vision-grid">

            <!-- MISIÓN -->
            <article class="mision-card reveal reveal-right">
                <span class="section-tag">Nuestra misión</span>
                <h2>Formar técnicos y ciudadanos preparados para los desafíos actuales</h2>

                <p>
                    Nuestra misión es brindar una formación secundaria técnica y científica de calidad,
                    orientada al desarrollo de saberes específicos en electricidad, programación y ciencias naturales,
                    integrando conocimientos teóricos, prácticos y tecnológicos.
                </p>

                <p>
                    Buscamos formar estudiantes capaces de analizar problemas, diseñar soluciones,
                    trabajar en equipo y aplicar criterios técnicos con responsabilidad, ética profesional
                    y compromiso social.
                </p>

                <p>
                    Como institución educativa, promovemos el aprendizaje de capacidades vinculadas
                    al pensamiento crítico, la innovación, el uso responsable de la tecnología,
                    la conciencia ecológica y la preparación para la continuidad de estudios superiores
                    o la inserción en el mundo laboral.
                </p>
            </article>

            <!-- VISIÓN -->
            <article class="vision-card reveal reveal-left reveal-delay-1">
                <span class="section-tag">Nuestra visión</span>
                <h2>Ser una institución de referencia en formación técnica y científica</h2>

                <p>
                    Aspiramos a consolidarnos como una escuela promotora de educación de calidad,
                    basada en la inclusión, la equidad, la innovación pedagógica y el respeto por la diversidad
                    de trayectorias escolares.
                </p>

                <p>
                    Nuestra visión es ser reconocidos como una institución educativa de referencia
                    en la formación técnica, científica y tecnológica, capaz de preparar estudiantes
                    para responder a los requerimientos del siglo XXI.
                </p>

                <p>
                    Proyectamos una escuela que forme jóvenes creativos, responsables y comprometidos,
                    preparados para convertirse en agentes de cambio en su comunidad y contribuir
                    al desarrollo social, económico, científico, tecnológico y ambiental.
                </p>
            </article>

        </div>
    </section>

    <!-- PILARES -->
    <section class="nosotros-section pilares-section">
        <div class="container">

            <div class="section-header-center reveal reveal-up">
                <span class="section-tag">Nuestros pilares</span>
                <h2>Valores que guían nuestra tarea educativa</h2>
            </div>

            <div class="pilares-grid">

                <article class="pilar-card reveal reveal-up">
                    <h3>Trabajo responsable</h3>
                    <p>
                        Promovemos la cultura del esfuerzo, la dedicación y la responsabilidad
                        como base para el crecimiento personal y laboral.
                    </p>
                </article>

                <article class="pilar-card reveal reveal-up reveal-delay-1">
                    <h3>Innovación educativa</h3>
                    <p>
                        Buscamos integrar nuevas tecnologías, metodologías activas y propuestas
                        que respondan a los desafíos actuales.
                    </p>
                </article>

                <article class="pilar-card reveal reveal-up reveal-delay-2">
                    <h3>Compromiso social</h3>
                    <p>
                        Formamos estudiantes preparados para participar activamente en su comunidad
                        con ética, solidaridad y pensamiento crítico.
                    </p>
                </article>

                <article class="pilar-card reveal reveal-up reveal-delay-3">
                    <h3>Conciencia ecológica</h3>
                    <p>
                        Impulsamos una mirada responsable sobre el ambiente, el uso de la energía
                        y el desarrollo sostenible.
                    </p>
                </article>

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