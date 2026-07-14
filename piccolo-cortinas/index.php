<?php include('includes/header.php'); ?>
<?php include('includes/navbar.php'); ?>

<main>

<section class="hero">
    <div class="container hero-content">

        <div class="hero-text">
            <span class="hero-tag">Calidad, diseño y atención personalizada</span>

            <h1>Cortinas a medida en Mendoza</h1>

            <p>
                Instalación, venta y reparación de cortinas para hogares, oficinas y comercios.
                Soluciones funcionales, modernas y adaptadas a cada ambiente.
            </p>

            <div class="hero-buttons">
                <a href="productos.php" class="btn btn-primary">Ver productos</a>
                <a href="contacto.php" class="btn btn-secondary">Solicitar asesoramiento</a>
            </div>
        </div>

        <div class="hero-image">
    <div class="hero-slider">

        <div class="slider-track" id="sliderTrack">
            <img src="assets/img/portada1.jpg">
            <img src="assets/img/portada2.jpg">
            <img src="assets/img/portada3.jpg">
        </div>

        <!-- PUNTITOS -->
            <div class="slider-dots" id="sliderDots">
                <span class="dot active" data-index="0"></span>
                <span class="dot" data-index="1"></span>
                <span class="dot" data-index="2"></span>
            </div>

        </div>
    </div>

    </div>
<section class="benefits section fade-in">
    <div class="container">
        <div class="section-header">
            <h2>¿Por qué elegirnos?</h2>
            <p>Brindamos un servicio pensado para acompañarte desde la elección hasta la instalación final.</p>
        </div>

        <div class="benefits-grid" id="benefitsList">
            <article class="benefit-card">
                <div class="benefit-icon">🤝</div>
                <h3>Atención personalizada</h3>
                <p>Te asesoramos según el ambiente, la funcionalidad y el estilo que buscás para tu espacio.</p>
            </article>

            <article class="benefit-card">
                <div class="benefit-icon">🏠</div>
                <h3>Servicio a domicilio</h3>
                <p>Coordinamos visitas para tomar medidas, evaluar opciones y brindarte una atención más cómoda.</p>
            </article>

            <article class="benefit-card">
                <div class="benefit-icon">🛠️</div>
                <h3>Instalación profesional</h3>
                <p>Realizamos colocación cuidada y prolija para que el resultado final sea funcional y estético.</p>
            </article>

            <article class="benefit-card">
                <div class="benefit-icon">🕒</div>
                <h3>Horarios flexibles</h3>
                <p>Buscamos adaptarnos a la disponibilidad del cliente para facilitar la atención y la instalación.</p>
            </article>
        </div>
    </div>
</section>
<section class="featured-products section fade-in">
    <div class="container">

        <div class="section-header">
            <h2>Productos destacados</h2>
            <p>Conocé algunas de las opciones más elegidas por nuestros clientes.</p>
        </div>

        <div class="cards-grid">

            <article class="product-card">
                <img src="assets/img/productos/roller.jpg">
                <h3>Cortinas Roller</h3>
                <p>Opción moderna y versátil.</p>
                <a href="productos.php" class="btn btn-secondary">Ver más</a>
            </article>

            <article class="product-card">
                <img src="assets/img/productos/blackout.jpg">
                <h3>Blackout</h3>
                <p>Máxima privacidad y control de luz.</p>
                <a href="productos.php" class="btn btn-secondary">Ver más</a>
            </article>

            <article class="product-card">
                <img src="assets/img/productos/sunscreen.jpg">
                <h3>Sunscreen</h3>
                <p>Equilibrio entre luz y protección.</p>
                <a href="productos.php" class="btn btn-secondary">Ver más</a>
            </article>

        </div>
    </div>
</section>

<section class="about-preview section fade-in">
    <div class="container about-preview-content">
        <h2>Sobre nosotros</h2>

        <p>
            En Piccolo Cortinas brindamos soluciones a medida con instalación,
            reparación y asesoramiento personalizado.
        </p>

        <a href="nosotros.php" class="btn btn-primary">Conocé más</a>
    </div>
</section>

<section class="contact-preview section fade-in">
    <div class="container contact-preview-content">
        <h2>¿Necesitás asesoramiento?</h2>

        <p>Contáctanos y te ayudamos a elegir la mejor opción.</p>

        <a href="contacto.php" class="btn btn-primary">Ir a contacto</a>
    </div>
</section>

</main>

<?php include('includes/footer.php'); ?>