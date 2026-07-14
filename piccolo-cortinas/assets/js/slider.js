let current = 0;
const slides = document.querySelectorAll("#sliderTrack img");
const dots = document.querySelectorAll(".dot");

let interval;

function showSlide(index) {
    slides.forEach(s => s.classList.remove("active"));
    dots.forEach(d => d.classList.remove("active"));

    slides[index].classList.add("active");
    dots[index].classList.add("active");

    current = index;
}

function startSlider() {
    interval = setInterval(() => {
        let next = current + 1;
        if (next >= slides.length) next = 0;
        showSlide(next);
    }, 4000);
}

// CLICK DOTS
dots.forEach(dot => {
    dot.addEventListener("click", () => {
        clearInterval(interval);
        showSlide(parseInt(dot.dataset.index));
        startSlider();
    });
});

// INIT
showSlide(0);
startSlider();