<?php include('includes/header.php'); ?>
<?php include('includes/navbar.php'); ?>

<main class="page-content">
    <section class="page-banner">
        <div class="container">
            <h1>Contacto</h1>
            <p>Escribinos y te ayudamos a encontrar la mejor opción para tu espacio.</p>
        </div>
    </section>

    <section class="contact-section section fade-in">
        <div class="container">
            <div class="contact-grid">

                <div class="contact-info">
                    <span class="contact-tag">Atención personalizada</span>
                    <h2>Hablemos sobre tu consulta</h2>

                    <p>
                        Podés escribirnos para consultar por instalación, reparación, motorización
                        y venta de cortinas. Completá el formulario y te responderemos por WhatsApp
                        para brindarte una atención más directa y coordinar una posible visita.
                    </p>
                </div>

                <div class="contact-form-box">
                    <form id="contactForm" class="contact-form">
                        <input type="text" id="nombre" name="nombre" placeholder="Tu nombre" required>
                        <input type="text" id="franja" name="franja" placeholder="Franja horaria para la visita" required>
                        <input type="email" id="email" name="email" placeholder="Tu correo electrónico">
                        <input type="text" id="asunto" name="asunto" placeholder="Asunto de la consulta">
                        <textarea id="mensaje" name="mensaje" rows="6" placeholder="Escribí tu consulta" required></textarea>

                        <button type="submit" class="btn btn-primary">Enviar por WhatsApp</button>
                    </form>
                </div>

            </div>
        </div>
    </section>
</main>

<?php include('includes/footer.php'); ?>