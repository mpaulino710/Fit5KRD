// Fit5K - Main JavaScript File

document.addEventListener('DOMContentLoaded', function () {

    // Inicializar tooltips de Bootstrap
    initTooltips();

    // Validación de formularios
    initFormValidation();

    // Manejo de fechas en formularios
    initDatePickers();

    // Contador regresivo para eventos
    initEventCountdowns();

    // Animaciones de carga
    initLoadingAnimations();

    // Galería de imágenes
    initImageGallery();

    // Funcionalidad de búsqueda
    initSearchFunctionality();
});

// Tooltips
function initTooltips() {
    const tooltips = document.querySelectorAll('[data-toggle="tooltip"]');
    tooltips.forEach(tooltip => {
        tooltip.addEventListener('mouseenter', function (e) {
            const title = this.getAttribute('title');
            const tooltipEl = document.createElement('div');
            tooltipEl.className = 'custom-tooltip';
            tooltipEl.textContent = title;
            document.body.appendChild(tooltipEl);

            const rect = this.getBoundingClientRect();
            tooltipEl.style.left = rect.left + (rect.width / 2) - (tooltipEl.offsetWidth / 2) + 'px';
            tooltipEl.style.top = rect.top - tooltipEl.offsetHeight - 10 + 'px';

            this.setAttribute('title', '');
        });

        tooltip.addEventListener('mouseleave', function () {
            const tooltipEl = document.querySelector('.custom-tooltip');
            if (tooltipEl) {
                this.setAttribute('title', tooltipEl.textContent);
                tooltipEl.remove();
            }
        });
    });
}

// Validación de formularios
function initFormValidation() {
    const forms = document.querySelectorAll('.needs-validation');

    forms.forEach(form => {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }

            // Validación personalizada de contraseñas
            const password = form.querySelector('#password');
            const confirmPassword = form.querySelector('#confirm_password');

            if (password && confirmPassword) {
                if (password.value !== confirmPassword.value) {
                    showError(confirmPassword, 'Las contraseñas no coinciden');
                    event.preventDefault();
                    event.stopPropagation();
                }
            }

            form.classList.add('was-validated');
        }, false);
    });

    // Validación en tiempo real
    const inputs = document.querySelectorAll('.form-control');
    inputs.forEach(input => {
        input.addEventListener('blur', function () {
            validateField(this);
        });
    });
}

function validateField(field) {
    if (field.validity.valid) {
        field.classList.remove('is-invalid');
        field.classList.add('is-valid');
    } else {
        field.classList.remove('is-valid');
        field.classList.add('is-invalid');
    }
}

function showError(element, message) {
    let errorDiv = element.nextElementSibling;
    if (!errorDiv || !errorDiv.classList.contains('invalid-feedback')) {
        errorDiv = document.createElement('div');
        errorDiv.className = 'invalid-feedback';
        element.parentNode.appendChild(errorDiv);
    }
    errorDiv.textContent = message;
    element.classList.add('is-invalid');
}

// Selectores de fecha
function initDatePickers() {
    const dateInputs = document.querySelectorAll('input[type="date"]');
    dateInputs.forEach(input => {
        // Establecer fecha mínima (hoy) para eventos futuros
        if (input.id === 'fecha_evento') {
            const today = new Date().toISOString().split('T')[0];
            input.setAttribute('min', today);
        }
    });
}

// Contadores regresivos
function initEventCountdowns() {
    const countdownElements = document.querySelectorAll('.event-countdown');

    countdownElements.forEach(element => {
        const eventDate = new Date(element.dataset.date).getTime();

        function updateCountdown() {
            const now = new Date().getTime();
            const distance = eventDate - now;

            if (distance < 0) {
                element.innerHTML = '<span class="badge bg-secondary">Evento finalizado</span>';
                return;
            }

            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));

            element.innerHTML = `
                <div class="countdown-item">
                    <span class="countdown-number">${days}</span>
                    <span class="countdown-label">días</span>
                </div>
                <div class="countdown-item">
                    <span class="countdown-number">${hours}</span>
                    <span class="countdown-label">horas</span>
                </div>
                <div class="countdown-item">
                    <span class="countdown-number">${minutes}</span>
                    <span class="countdown-label">min</span>
                </div>
            `;
        }

        updateCountdown();
        setInterval(updateCountdown, 60000); // Actualizar cada minuto
    });
}

// Animaciones de carga
function initLoadingAnimations() {
    // Mostrar spinner durante cargas
    document.addEventListener('submit', function (e) {
        if (e.target.tagName === 'FORM') {
            const submitBtn = e.target.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
                submitBtn.disabled = true;
            }
        }
    });
}

// Galería de imágenes
function initImageGallery() {
    const galleryImages = document.querySelectorAll('.gallery-image');
    const lightbox = document.createElement('div');
    lightbox.className = 'lightbox';
    lightbox.innerHTML = `
        <div class="lightbox-content">
            <img src="" alt="">
            <button class="lightbox-close">&times;</button>
            <button class="lightbox-prev">&larr;</button>
            <button class="lightbox-next">&rarr;</button>
        </div>
    `;
    document.body.appendChild(lightbox);

    let currentIndex = 0;

    galleryImages.forEach((img, index) => {
        img.addEventListener('click', () => {
            currentIndex = index;
            openLightbox(img.src);
        });
    });

    function openLightbox(src) {
        lightbox.querySelector('img').src = src;
        lightbox.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    lightbox.querySelector('.lightbox-close').addEventListener('click', () => {
        lightbox.classList.remove('active');
        document.body.style.overflow = 'auto';
    });

    lightbox.querySelector('.lightbox-prev').addEventListener('click', () => {
        currentIndex = (currentIndex - 1 + galleryImages.length) % galleryImages.length;
        openLightbox(galleryImages[currentIndex].src);
    });

    lightbox.querySelector('.lightbox-next').addEventListener('click', () => {
        currentIndex = (currentIndex + 1) % galleryImages.length;
        openLightbox(galleryImages[currentIndex].src);
    });

    lightbox.addEventListener('click', (e) => {
        if (e.target === lightbox) {
            lightbox.classList.remove('active');
            document.body.style.overflow = 'auto';
        }
    });
}

// Funcionalidad de búsqueda
function initSearchFunctionality() {
    const searchInput = document.getElementById('busqueda');
    const searchForm = document.querySelector('.search-form');

    if (searchInput && searchForm) {
        // Búsqueda con debounce
        let timeoutId;
        searchInput.addEventListener('input', function () {
            clearTimeout(timeoutId);
            timeoutId = setTimeout(() => {
                if (this.value.length >= 2 || this.value.length === 0) {
                    searchForm.submit();
                }
            }, 500);
        });

        // Autocompletado
        searchInput.addEventListener('keyup', function () {
            if (this.value.length >= 2) {
                fetchAutocompleteSuggestions(this.value);
            }
        });
    }
}

async function fetchAutocompleteSuggestions(query) {
    try {
        const response = await fetch(`/api/search/suggest?q=${encodeURIComponent(query)}`);
        const suggestions = await response.json();

        // Mostrar sugerencias
        showAutocompleteSuggestions(suggestions);
    } catch (error) {
        console.error('Error fetching suggestions:', error);
    }
}

function showAutocompleteSuggestions(suggestions) {
    // Implementar lógica para mostrar sugerencias
}

// Funciones utilitarias
function formatCurrency(amount) {
    return new Intl.NumberFormat('es-ES', {
        style: 'currency',
        currency: 'USD'
    }).format(amount);
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('es-ES', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
}

// Confirmación de acciones importantes
function confirmAction(message) {
    return confirm(message || '¿Estás seguro de realizar esta acción?');
}

// Mostrar notificaciones
function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;

    document.body.appendChild(notification);

    setTimeout(() => {
        notification.classList.add('show');
    }, 100);

    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 3000);
}

// Manejo de errores de formulario
document.addEventListener('invalid', (function () {
    return function (e) {
        e.preventDefault();
        showError(e.target, 'Por favor, completa este campo correctamente');
    };
})(), true);

// Estilos para componentes dinámicos
const style = document.createElement('style');
style.textContent = `
    .custom-tooltip {
        position: fixed;
        background: rgba(0,0,0,0.8);
        color: white;
        padding: 5px 10px;
        border-radius: 4px;
        font-size: 0.9rem;
        z-index: 10000;
        pointer-events: none;
    }
    
    .countdown-item {
        display: inline-block;
        text-align: center;
        margin: 0 5px;
        background: var(--primary-color);
        color: white;
        padding: 5px 10px;
        border-radius: 4px;
        min-width: 60px;
    }
    
    .countdown-number {
        font-size: 1.5rem;
        font-weight: bold;
        display: block;
    }
    
    .countdown-label {
        font-size: 0.8rem;
        display: block;
    }
    
    .lightbox {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.9);
        z-index: 10000;
    }
    
    .lightbox.active {
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .lightbox-content {
        position: relative;
        max-width: 90%;
        max-height: 90%;
    }
    
    .lightbox-content img {
        max-width: 100%;
        max-height: 80vh;
    }
    
    .lightbox-close,
    .lightbox-prev,
    .lightbox-next {
        position: absolute;
        background: rgba(0,0,0,0.5);
        color: white;
        border: none;
        padding: 10px;
        cursor: pointer;
        font-size: 1.5rem;
    }
    
    .lightbox-close {
        top: 10px;
        right: 10px;
    }
    
    .lightbox-prev {
        left: 10px;
        top: 50%;
        transform: translateY(-50%);
    }
    
    .lightbox-next {
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
    }
    
    .notification {
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 25px;
        background: var(--success);
        color: white;
        border-radius: 4px;
        transform: translateX(100%);
        transition: transform 0.3s;
        z-index: 10000;
    }
    
    .notification.show {
        transform: translateX(0);
    }
    
    .notification-error {
        background: var(--danger);
    }
    
    .notification-warning {
        background: var(--warning);
    }
    
    .notification-info {
        background: var(--info);
    }
`;
document.head.appendChild(style);