// ===============================
// MENÚ HAMBURGUESA
// ===============================

const menuToggle = document.getElementById("menu-toggle");
const mainNav = document.getElementById("main-nav");

if (menuToggle && mainNav) {
    menuToggle.addEventListener("click", () => {
        mainNav.classList.toggle("active");
        menuToggle.classList.toggle("active");
    });
}


// ===============================
// MENÚ ACTIVO AUTOMÁTICO
// ===============================

const currentPage = window.location.pathname.split("/").pop() || "index.php";
const navLinks = document.querySelectorAll(".nav-menu a");

navLinks.forEach(link => {
    const linkPage = link.getAttribute("href");

    if (linkPage === currentPage) {
        link.classList.add("active");
    }
});


// ===============================
// ANIMACIONES AL HACER SCROLL
// ===============================

const animatedElements = document.querySelectorAll(".animate-on-scroll");

const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.classList.add("show");
        }
    });
}, {
    threshold: 0.15
});

animatedElements.forEach(element => {
    observer.observe(element);
});
// ===============================
// CARRUSEL SOBRE LA ESCUELA
// ===============================

const carouselImages = document.querySelectorAll(".carousel-img");
const carouselDots = document.querySelectorAll(".carousel-dot");
const prevBtn = document.querySelector(".carousel-prev");
const nextBtn = document.querySelector(".carousel-next");

let currentSlide = 0;

function showSlide(index) {
    carouselImages.forEach(img => img.classList.remove("active"));
    carouselDots.forEach(dot => dot.classList.remove("active"));

    carouselImages[index].classList.add("active");
    carouselDots[index].classList.add("active");
}

function nextSlide() {
    currentSlide++;

    if (currentSlide >= carouselImages.length) {
        currentSlide = 0;
    }

    showSlide(currentSlide);
}

function prevSlide() {
    currentSlide--;

    if (currentSlide < 0) {
        currentSlide = carouselImages.length - 1;
    }

    showSlide(currentSlide);
}

if (carouselImages.length > 0) {
    nextBtn.addEventListener("click", nextSlide);
    prevBtn.addEventListener("click", prevSlide);

    carouselDots.forEach((dot, index) => {
        dot.addEventListener("click", () => {
            currentSlide = index;
            showSlide(currentSlide);
        });
    });

    setInterval(nextSlide, 4000);
}
// ===============================
// CONTADORES ANIMADOS
// ===============================

const counters = document.querySelectorAll(".stat-number");

const startCounter = (counter) => {
    const target = Number(counter.dataset.target);
    let current = 0;
    const increment = Math.ceil(target / 80);

    const updateCounter = () => {
        current += increment;

        if (current >= target) {
            counter.textContent = target;
        } else {
            counter.textContent = current;
            requestAnimationFrame(updateCounter);
        }
    };

    updateCounter();
};

const counterObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting && !entry.target.classList.contains("counted")) {
            entry.target.classList.add("counted");
            startCounter(entry.target);
        }
    });
}, {
    threshold: 0.5
});

counters.forEach(counter => {
    counterObserver.observe(counter);
});

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
