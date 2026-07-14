// =========================
// ANIMACIONES AL HACER SCROLL
// =========================
const elements = document.querySelectorAll(".fade-in");

function showOnScroll() {
    elements.forEach(el => {
        const rect = el.getBoundingClientRect();

        if (rect.top < window.innerHeight - 100) {
            el.classList.add("visible");
        }
    });
}

window.addEventListener("scroll", showOnScroll);
window.addEventListener("load", showOnScroll);

// =========================
// EFECTO HEADER AL HACER SCROLL
// =========================
const header = document.querySelector(".site-header");

window.addEventListener("scroll", () => {
    if (header) {
        if (window.scrollY > 20) {
            header.classList.add("scrolled");
        } else {
            header.classList.remove("scrolled");
        }
    }
});

// =========================
// ANIMACION ESCALONADA DE BENEFICIOS
// =========================
const benefitCards = document.querySelectorAll(".benefit-card");

function animateBenefits() {
    if (benefitCards.length > 0) {
        benefitCards.forEach((card, i) => {
            setTimeout(() => {
                card.classList.add("show");
            }, i * 180);
        });
    }
}

window.addEventListener("load", animateBenefits);

// =========================
// FORMULARIO CONTACTO -> WHATSAPP
// =========================
const contactForm = document.getElementById("contactForm");

if (contactForm) {
    contactForm.addEventListener("submit", function (e) {
        e.preventDefault();

        const nombre = document.getElementById("nombre").value.trim();
        const franja = document.getElementById("franja").value.trim();
        const email = document.getElementById("email").value.trim();
        const asunto = document.getElementById("asunto").value.trim();
        const mensaje = document.getElementById("mensaje").value.trim();

        const texto =
            `Hola, me gustaría hacer una consulta.%0A%0A` +
            `Nombre: ${encodeURIComponent(nombre)}%0A` +
            `Franja horaria para la visita: ${encodeURIComponent(franja)}%0A` +
            `Email: ${encodeURIComponent(email)}%0A` +
            `Asunto: ${encodeURIComponent(asunto)}%0A` +
            `Mensaje: ${encodeURIComponent(mensaje)}`;

        const numero = "5492616575318";
        const url = `https://wa.me/${numero}?text=${texto}`;

        window.open(url, "_blank");
    });
}