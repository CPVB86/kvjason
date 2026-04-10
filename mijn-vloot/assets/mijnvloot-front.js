document.addEventListener('DOMContentLoaded', function () {
    const sliders = document.querySelectorAll('.mijnvloot-slider');

    sliders.forEach(function (slider) {
        const slidesContainer = slider.querySelector('.mijnvloot-slides');
        const slides = slidesContainer
            ? slidesContainer.querySelectorAll('.mijnvloot-slide')
            : [];
        const prevBtn = slider.querySelector('.mijnvloot-prev');
        const nextBtn = slider.querySelector('.mijnvloot-next');

        if (!slides.length) {
            return;
        }

        let currentIndex = 0;

        slides.forEach(function (slide, idx) {
            if (slide.classList.contains('active')) {
                currentIndex = idx;
            }
        });

        function showSlide(index) {
            if (!slides.length) return;

            if (index < 0) {
                index = slides.length - 1;
            }

            if (index >= slides.length) {
                index = 0;
            }

            slides.forEach(function (slide) {
                slide.classList.remove('active');
            });

            slides[index].classList.add('active');
            currentIndex = index;
        }

        if (prevBtn) {
            prevBtn.addEventListener('click', function () {
                showSlide(currentIndex - 1);
            });
        }

        if (nextBtn) {
            nextBtn.addEventListener('click', function () {
                showSlide(currentIndex + 1);
            });
        }
    });

    const items = document.querySelectorAll('.mijnvloot-item');

    items.forEach(function (item) {
        const toggles = item.querySelectorAll('.mijnvloot-toggle');
        const panels = item.querySelectorAll('.mijnvloot-panel');

        if (!toggles.length || !panels.length) {
            return;
        }

        toggles.forEach(function (toggle) {
            toggle.addEventListener('click', function () {
                const targetId = toggle.getAttribute('data-target');
                if (!targetId) return;

                const targetPanel = item.querySelector('#' + targetId);
                if (!targetPanel) return;

                toggles.forEach(function (btn) {
                    btn.classList.remove('is-open');
                });

                panels.forEach(function (panel) {
                    panel.classList.remove('is-open');
                });

                toggle.classList.add('is-open');
                targetPanel.classList.add('is-open');
            });
        });
    });
});
