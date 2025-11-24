// script-catalogo.js - Funcionalidad del catálogo con filtros laterales

document.addEventListener('DOMContentLoaded', function() {
    
    // ============================================
    // TOGGLE SIDEBAR DE FILTROS (MÓVIL)
    // ============================================
    const toggleFiltersBtn = document.getElementById('toggleFilters');
    const sidebarFilters = document.getElementById('sidebarFilters');
    const closeSidebarBtn = document.getElementById('closeSidebar');
    
    // Crear overlay si no existe
    let overlay = document.querySelector('.sidebar-overlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';
        document.body.appendChild(overlay);
    }
    
    // Función para abrir sidebar
    function openSidebar() {
        if (sidebarFilters) {
            sidebarFilters.classList.add('active');
            overlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    }
    
    // Función para cerrar sidebar
    function closeSidebar() {
        if (sidebarFilters) {
            sidebarFilters.classList.remove('active');
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        }
    }
    
    // Event listeners
    if (toggleFiltersBtn) {
        toggleFiltersBtn.addEventListener('click', openSidebar);
    }
    
    if (closeSidebarBtn) {
        closeSidebarBtn.addEventListener('click', closeSidebar);
    }
    
    if (overlay) {
        overlay.addEventListener('click', closeSidebar);
    }
    
    // Cerrar sidebar al presionar ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && sidebarFilters && sidebarFilters.classList.contains('active')) {
            closeSidebar();
        }
    });
    
    // Cerrar sidebar al cambiar de tamaño de ventana (si pasa de móvil a desktop)
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            if (window.innerWidth > 1024 && sidebarFilters && sidebarFilters.classList.contains('active')) {
                closeSidebar();
            }
        }, 250);
    });
    
    // ============================================
    // SMOOTH SCROLL AL CAMBIAR PÁGINA
    // ============================================
    const paginationLinks = document.querySelectorAll('.page-link');
    paginationLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            // Scroll suave hacia arriba al cambiar de página
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    });
    
    // ============================================
    // BOTONES DE AÑADIR AL CARRITO
    // ============================================
    const addToCartButtons = document.querySelectorAll('.btn-add-cart, .btn-añadir');
    
    addToCartButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            const itemId = this.getAttribute('data-id');
            const itemType = this.getAttribute('data-tipo');
            
            // Validar que existan los datos necesarios
            if (!itemId || !itemType) {
                console.error('Faltan datos del producto:', { itemId, itemType });
                alert('Error: No se pueden obtener los datos del producto');
                return;
            }
            
            // Crear el botón original para restaurarlo
            const originalContent = this.innerHTML;
            const originalBackground = this.style.background;
            
            // Cambiar el contenido del botón
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Agregando...';
            this.disabled = true;
            
            // Petición AJAX
            fetch('cart_actions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=add&id=${encodeURIComponent(itemId)}&tipo=${encodeURIComponent(itemType)}&cantidad=1`
            })
            .then(response => {
                // Verificar si la respuesta es OK
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Actualizar badge del carrito
                    const cartBadge = document.getElementById('cartBadge');
                    if (cartBadge) {
                        cartBadge.textContent = data.cart_count || parseInt(cartBadge.textContent) + 1;
                        
                        // Animación del badge
                        cartBadge.style.animation = 'none';
                        setTimeout(() => {
                            cartBadge.style.animation = 'pulse 0.6s ease';
                        }, 10);
                    }
                    
                    // Cambiar botón a éxito
                    this.innerHTML = '<i class="fas fa-check"></i> ¡Agregado!';
                    this.style.background = 'linear-gradient(135deg, #27ae60 0%, #229954 100%)';
                    
                    // Restaurar botón después de 2 segundos
                    setTimeout(() => {
                        this.innerHTML = originalContent;
                        this.style.background = originalBackground;
                        this.disabled = false;
                    }, 2000);
                } else {
                    // Mostrar error con mensaje
                    console.error('Error del servidor:', data.message || data.error);
                    this.innerHTML = '<i class="fas fa-exclamation-triangle"></i> ' + (data.message || 'Error');
                    this.style.background = 'linear-gradient(135deg, #e74c3c 0%, #c0392b 100%)';
                    
                    // Restaurar botón después de 3 segundos
                    setTimeout(() => {
                        this.innerHTML = originalContent;
                        this.style.background = originalBackground;
                        this.disabled = false;
                    }, 3000);
                }
            })
            .catch(error => {
                console.error('Error de red o servidor:', error);
                this.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Error de conexión';
                this.style.background = 'linear-gradient(135deg, #e74c3c 0%, #c0392b 100%)';
                
                setTimeout(() => {
                    this.innerHTML = originalContent;
                    this.style.background = originalBackground;
                    this.disabled = false;
                }, 3000);
            });
        });
    });
    
    // ============================================
    // ANIMACIONES DE ENTRADA
    // ============================================
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);
    
    // Observar todas las tarjetas de producto
    const productCards = document.querySelectorAll('.product-card');
    productCards.forEach(card => {
        observer.observe(card);
    });
    
    // ============================================
    // CAMBIO AUTOMÁTICO DE FILTROS
    // ============================================
    const filterRadios = document.querySelectorAll('.filter-option input[type="radio"]');
    const filterSelects = document.querySelectorAll('.filter-select-sidebar');
    
    // Auto-submit al cambiar radio buttons (opcional)
    filterRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            // Descomenta si quieres que se aplique automáticamente
            // this.closest('form').submit();
        });
    });
    
    // ============================================
    // CONTADOR DE FILTROS ACTIVOS
    // ============================================
    function updateActiveFiltersCount() {
        const form = document.querySelector('.filter-form-sidebar');
        if (!form) return;
        
        const badge = document.querySelector('.active-filters-badge');
        if (!badge) return;
        
        let count = 0;
        
        // Contar selects con valores
        const selects = form.querySelectorAll('select');
        selects.forEach(select => {
            if (select.value !== '') count++;
        });
        
        // Contar inputs de precio
        const priceInputs = form.querySelectorAll('.price-input');
        priceInputs.forEach(input => {
            if (input.value !== '') count++;
        });
        
        // Contar radio buttons (excepto "todos")
        const radioChecked = form.querySelector('input[type="radio"]:checked');
        if (radioChecked && radioChecked.value !== '' && radioChecked.value !== 'todos') {
            count++;
        }
        
        // Actualizar badge
        if (count > 0) {
            badge.textContent = count;
            badge.style.display = 'flex';
        } else {
            badge.style.display = 'none';
        }
    }
    
    // Escuchar cambios en los filtros
    const filterForm = document.querySelector('.filter-form-sidebar');
    if (filterForm) {
        filterForm.addEventListener('change', updateActiveFiltersCount);
        filterForm.addEventListener('input', updateActiveFiltersCount);
        
        // Actualizar al cargar
        updateActiveFiltersCount();
    }
    
    // ============================================
    // BOTÓN LIMPIAR FILTROS
    // ============================================
    const clearFiltersBtn = document.querySelector('.btn-clear');
    if (clearFiltersBtn) {
        clearFiltersBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            const form = document.querySelector('.filter-form-sidebar');
            if (form) {
                // Resetear todos los campos
                form.reset();
                
                // Actualizar contador
                updateActiveFiltersCount();
                
                // Opcional: enviar formulario automáticamente
                // form.submit();
            }
        });
    }
    
    // ============================================
    // VALIDACIÓN DE RANGO DE PRECIOS
    // ============================================
    const precioMin = document.getElementById('precioMin');
    const precioMax = document.getElementById('precioMax');
    
    if (precioMin && precioMax) {
        function validatePriceRange() {
            const min = parseFloat(precioMin.value) || 0;
            const max = parseFloat(precioMax.value) || Infinity;
            
            if (min > max && max > 0) {
                precioMax.setCustomValidity('El precio máximo debe ser mayor al mínimo');
            } else {
                precioMax.setCustomValidity('');
            }
        }
        
        precioMin.addEventListener('input', validatePriceRange);
        precioMax.addEventListener('input', validatePriceRange);
    }
    
    // ============================================
    // BÚSQUEDA CON ENTER
    // ============================================
    const searchInput = document.querySelector('.search-input-wrapper input');
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const searchForm = this.closest('form');
                if (searchForm) {
                    searchForm.submit();
                }
            }
        });
    }
    
    // ============================================
    // ANIMACIÓN DE CARGA
    // ============================================
    window.addEventListener('load', function() {
        document.body.classList.add('loaded');
    });
    
    // ============================================
    // TOOLTIP SIMPLE PARA BOTONES
    // ============================================
    const buttonsWithTooltip = document.querySelectorAll('[data-tooltip]');
    buttonsWithTooltip.forEach(button => {
        button.addEventListener('mouseenter', function() {
            const tooltipText = this.getAttribute('data-tooltip');
            const tooltip = document.createElement('div');
            tooltip.className = 'custom-tooltip';
            tooltip.textContent = tooltipText;
            tooltip.style.cssText = `
                position: absolute;
                background: var(--cetacean-blue);
                color: white;
                padding: 0.5rem 1rem;
                border-radius: 8px;
                font-size: 0.85rem;
                white-space: nowrap;
                z-index: 10000;
                pointer-events: none;
                box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            `;
            
            document.body.appendChild(tooltip);
            
            const rect = this.getBoundingClientRect();
            tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
            tooltip.style.top = rect.top - tooltip.offsetHeight - 10 + 'px';
            
            this.tooltipElement = tooltip;
        });
        
        button.addEventListener('mouseleave', function() {
            if (this.tooltipElement) {
                this.tooltipElement.remove();
                this.tooltipElement = null;
            }
        });
    });
    
    // ============================================
    // LAZY LOADING DE IMÁGENES
    // ============================================
    const lazyImages = document.querySelectorAll('img[data-src]');
    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.dataset.src;
                img.removeAttribute('data-src');
                observer.unobserve(img);
            }
        });
    });
    
    lazyImages.forEach(img => imageObserver.observe(img));
    
    // ============================================
    // PREVENIR DOBLE SUBMIT
    // ============================================
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                setTimeout(() => {
                    submitBtn.disabled = false;
                }, 3000);
            }
        });
    });
    
    // ============================================
    // SCROLL TO TOP BUTTON (OPCIONAL)
    // ============================================
    const scrollToTopBtn = document.createElement('button');
    scrollToTopBtn.innerHTML = '<i class="fas fa-arrow-up"></i>';
    scrollToTopBtn.className = 'scroll-to-top';
    scrollToTopBtn.style.cssText = `
        position: fixed;
        bottom: 30px;
        right: 30px;
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--absolute-zero) 0%, #0158e8 100%);
        color: white;
        border: none;
        cursor: pointer;
        display: none;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        box-shadow: 0 4px 15px rgba(1, 70, 199, 0.4);
        transition: all 0.3s ease;
        z-index: 1000;
    `;
    
    document.body.appendChild(scrollToTopBtn);
    
    window.addEventListener('scroll', function() {
        if (window.pageYOffset > 300) {
            scrollToTopBtn.style.display = 'flex';
        } else {
            scrollToTopBtn.style.display = 'none';
        }
    });
    
    scrollToTopBtn.addEventListener('click', function() {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    });
    
    scrollToTopBtn.addEventListener('mouseenter', function() {
        this.style.transform = 'scale(1.1)';
    });
    
    scrollToTopBtn.addEventListener('mouseleave', function() {
        this.style.transform = 'scale(1)';
    });
    
    console.log('Script de catálogo cargado correctamente ✓');
});


// ============================================
// CARRUSEL DE IMÁGENES MEJORADO - VERSIÓN PREMIUM
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    
    // Configuración global del carrusel
    const carouselConfig = {
        autoPlayInterval: 4000,
        autoPlay: false, // Cambiar a true para auto-play
        swipeThreshold: 50,
        keyboardNavigation: true,
        touchEnabled: true,
        transitionSpeed: 500
    };
    
    // Seleccionar todos los carruseles
    const carousels = document.querySelectorAll('.image-carousel');
    
    carousels.forEach((carousel, carouselIndex) => {
        initCarousel(carousel, carouselIndex);
    });
    
    // ============================================
    // FUNCIÓN PRINCIPAL DE INICIALIZACIÓN
    // ============================================
    function initCarousel(carousel, carouselIndex) {
        const images = carousel.querySelectorAll('.carousel-image');
        const thumbnails = carousel.querySelectorAll('.thumbnail-wrapper');
        const prevBtn = carousel.querySelector('.carousel-control.prev');
        const nextBtn = carousel.querySelector('.carousel-control.next');
        const counter = carousel.querySelector('.current-image');
        const thumbnailsContainer = carousel.querySelector('.carousel-thumbnails');
        
        // Si solo hay una imagen, no inicializar carrusel
        if (images.length <= 1) {
            if (prevBtn) prevBtn.style.display = 'none';
            if (nextBtn) nextBtn.style.display = 'none';
            if (counter) counter.parentElement.style.display = 'none';
            if (thumbnailsContainer) thumbnailsContainer.style.display = 'none';
            return;
        }
        
        let currentIndex = 0;
        let autoPlayTimer = null;
        let isTransitioning = false;
        
        // ============================================
        // FUNCIÓN PARA MOSTRAR IMAGEN
        // ============================================
        function showImage(index, direction = 'none') {
            if (isTransitioning) return;
            
            // Validar índice
            if (index < 0) index = images.length - 1;
            if (index >= images.length) index = 0;
            
            // Si es el mismo índice, no hacer nada
            if (index === currentIndex) return;
            
            isTransitioning = true;
            const previousIndex = currentIndex;
            currentIndex = index;
            
            // Actualizar imágenes con transición
            images.forEach((img, i) => {
                img.classList.remove('active', 'slide-out-left', 'slide-out-right', 'slide-in-left', 'slide-in-right');
                
                if (i === currentIndex) {
                    img.classList.add('active');
                    // Efecto de brillo opcional
                    img.classList.add('shine');
                    setTimeout(() => img.classList.remove('shine'), 800);
                } else if (i === previousIndex) {
                    if (direction === 'next') {
                        img.classList.add('slide-out-left');
                    } else if (direction === 'prev') {
                        img.classList.add('slide-out-right');
                    }
                }
            });
            
            // Actualizar miniaturas
            thumbnails.forEach((thumb, i) => {
                thumb.classList.toggle('active', i === currentIndex);
                
                // Hacer scroll suave para mantener visible la miniatura activa
                if (i === currentIndex && thumbnailsContainer) {
                    const thumbRect = thumb.getBoundingClientRect();
                    const containerRect = thumbnailsContainer.getBoundingClientRect();
                    
                    if (thumbRect.left < containerRect.left || thumbRect.right > containerRect.right) {
                        thumb.scrollIntoView({
                            behavior: 'smooth',
                            block: 'nearest',
                            inline: 'center'
                        });
                    }
                }
            });
            
            // Actualizar contador con animación
            if (counter) {
                counter.parentElement.classList.add('updating');
                setTimeout(() => {
                    counter.textContent = currentIndex + 1;
                    counter.parentElement.classList.remove('updating');
                }, 150);
            }
            
            // Reiniciar autoplay si está activo
            if (carouselConfig.autoPlay) {
                resetAutoPlay();
            }
            
            // Permitir nueva transición después de un tiempo
            setTimeout(() => {
                isTransitioning = false;
            }, carouselConfig.transitionSpeed);
        }
        
        // ============================================
        // NAVEGACIÓN CON BOTONES
        // ============================================
        if (prevBtn) {
            prevBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                showImage(currentIndex - 1, 'prev');
            });
        }
        
        if (nextBtn) {
            nextBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                showImage(currentIndex + 1, 'next');
            });
        }
        
        // ============================================
        // NAVEGACIÓN CON MINIATURAS
        // ============================================
        thumbnails.forEach((thumbnail, index) => {
            thumbnail.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const direction = index > currentIndex ? 'next' : 'prev';
                showImage(index, direction);
            });
            
            // Hacer thumbnails navegables con teclado
            thumbnail.setAttribute('tabindex', '0');
            thumbnail.setAttribute('role', 'button');
            thumbnail.setAttribute('aria-label', `Ver imagen ${index + 1}`);
            
            thumbnail.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    const direction = index > currentIndex ? 'next' : 'prev';
                    showImage(index, direction);
                }
            });
        });
        
        // ============================================
        // NAVEGACIÓN TÁCTIL (SWIPE)
        // ============================================
        if (carouselConfig.touchEnabled) {
            let touchStartX = 0;
            let touchEndX = 0;
            let touchStartY = 0;
            let touchEndY = 0;
            let isSwiping = false;
            
            carousel.addEventListener('touchstart', (e) => {
                touchStartX = e.changedTouches[0].screenX;
                touchStartY = e.changedTouches[0].screenY;
                isSwiping = true;
            }, { passive: true });
            
            carousel.addEventListener('touchmove', (e) => {
                if (!isSwiping) return;
                touchEndX = e.changedTouches[0].screenX;
                touchEndY = e.changedTouches[0].screenY;
            }, { passive: true });
            
            carousel.addEventListener('touchend', (e) => {
                if (!isSwiping) return;
                isSwiping = false;
                handleSwipe();
            }, { passive: true });
            
            function handleSwipe() {
                const diffX = touchStartX - touchEndX;
                const diffY = Math.abs(touchStartY - touchEndY);
                
                // Solo procesar si es un swipe horizontal significativo
                if (Math.abs(diffX) > carouselConfig.swipeThreshold && diffY < 100) {
                    if (diffX > 0) {
                        // Swipe left - siguiente imagen
                        showImage(currentIndex + 1, 'next');
                    } else {
                        // Swipe right - imagen anterior
                        showImage(currentIndex - 1, 'prev');
                    }
                }
            }
        }
        
        // ============================================
        // NAVEGACIÓN CON TECLADO
        // ============================================
        if (carouselConfig.keyboardNavigation) {
            carousel.setAttribute('tabindex', '0');
            carousel.setAttribute('role', 'region');
            carousel.setAttribute('aria-label', 'Carrusel de imágenes del producto');
            
            carousel.addEventListener('keydown', (e) => {
                // Solo procesar si el carrusel tiene foco
                if (!carousel.matches(':focus-within')) return;
                
                switch(e.key) {
                    case 'ArrowLeft':
                        e.preventDefault();
                        showImage(currentIndex - 1, 'prev');
                        break;
                    case 'ArrowRight':
                        e.preventDefault();
                        showImage(currentIndex + 1, 'next');
                        break;
                    case 'Home':
                        e.preventDefault();
                        showImage(0, 'prev');
                        break;
                    case 'End':
                        e.preventDefault();
                        showImage(images.length - 1, 'next');
                        break;
                }
            });
        }
        
        // ============================================
        // AUTO-PLAY
        // ============================================
        function startAutoPlay() {
            if (!carouselConfig.autoPlay) return;
            
            autoPlayTimer = setInterval(() => {
                showImage(currentIndex + 1, 'next');
            }, carouselConfig.autoPlayInterval);
        }
        
        function stopAutoPlay() {
            if (autoPlayTimer) {
                clearInterval(autoPlayTimer);
                autoPlayTimer = null;
            }
        }
        
        function resetAutoPlay() {
            stopAutoPlay();
            startAutoPlay();
        }
        
        // Pausar autoplay al hacer hover
        carousel.addEventListener('mouseenter', stopAutoPlay);
        carousel.addEventListener('mouseleave', () => {
            if (carouselConfig.autoPlay) startAutoPlay();
        });
        
        // Iniciar autoplay si está configurado
        if (carouselConfig.autoPlay) {
            startAutoPlay();
        }
        
        // ============================================
        // LAZY LOADING DE IMÁGENES
        // ============================================
        images.forEach((img, index) => {
            // Cargar solo la primera imagen inmediatamente
            if (index === 0) {
                img.classList.remove('loading');
            } else {
                // Precargar las siguientes imágenes
                const tempImg = new Image();
                tempImg.onload = () => {
                    img.classList.remove('loading');
                };
                tempImg.src = img.src;
            }
        });
        
        // ============================================
        // ZOOM EN CLICK (MODAL)
        // ============================================
        const imageContainer = carousel.closest('.product-image-container');
        if (imageContainer) {
            images.forEach(img => {
                img.style.cursor = 'zoom-in';
                
                img.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    openImageModal(img.src, currentIndex);
                });
            });
        }
        
        // ============================================
        // PRECARGA DE IMÁGENES ADYACENTES
        // ============================================
        function preloadAdjacentImages() {
            const nextIndex = (currentIndex + 1) % images.length;
            const prevIndex = (currentIndex - 1 + images.length) % images.length;
            
            [nextIndex, prevIndex].forEach(index => {
                const img = images[index];
                if (img && !img.complete) {
                    const tempImg = new Image();
                    tempImg.src = img.src;
                }
            });
        }
        
        // Precargar al iniciar y al cambiar de imagen
        preloadAdjacentImages();
        
        // ============================================
        // INDICADOR DE SWIPE (PRIMERA VEZ)
        // ============================================
        if (window.innerWidth <= 768 && !localStorage.getItem('swipe-hint-shown-' + carouselIndex)) {
            showSwipeIndicator();
            localStorage.setItem('swipe-hint-shown-' + carouselIndex, 'true');
        }
        
        function showSwipeIndicator() {
            const indicator = document.createElement('div');
            indicator.className = 'swipe-indicator';
            indicator.innerHTML = '<i class="fas fa-hand-point-left"></i> Desliza para ver más';
            carousel.appendChild(indicator);
            
            setTimeout(() => {
                indicator.remove();
            }, 3000);
        }
    }
    
    // ============================================
    // MODAL DE ZOOM PARA IMÁGENES
    // ============================================
    function openImageModal(imageSrc, currentIndex) {
        // Crear modal si no existe
        let modal = document.getElementById('imageModal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'imageModal';
            modal.className = 'image-modal';
            modal.innerHTML = `
                <div class="image-modal-content">
                    <button class="modal-close" aria-label="Cerrar">
                        <i class="fas fa-times"></i>
                    </button>
                    <img src="" alt="Imagen ampliada">
                </div>
            `;
            document.body.appendChild(modal);
        }
        
        const modalImg = modal.querySelector('img');
        const closeBtn = modal.querySelector('.modal-close');
        
        // Establecer imagen
        modalImg.src = imageSrc;
        
        // Mostrar modal
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
        
        // Cerrar con botón
        closeBtn.onclick = closeImageModal;
        
        // Cerrar con click fuera de la imagen
        modal.onclick = (e) => {
            if (e.target === modal) {
                closeImageModal();
            }
        };
        
        // Cerrar con ESC
        document.addEventListener('keydown', handleEscKey);
    }
    
    function closeImageModal() {
        const modal = document.getElementById('imageModal');
        if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
            document.removeEventListener('keydown', handleEscKey);
        }
    }
    
    function handleEscKey(e) {
        if (e.key === 'Escape') {
            closeImageModal();
        }
    }
    
    // ============================================
    // INTERSECTION OBSERVER PARA PERFORMANCE
    // ============================================
    const carouselObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
            }
        });
    }, {
        threshold: 0.1
    });
    
    carousels.forEach(carousel => {
        carouselObserver.observe(carousel);
    });
    
    console.log(`✅ ${carousels.length} carruseles inicializados correctamente`);
});

// ============================================
// UTILIDADES GLOBALES
// ============================================

// Prevenir comportamiento por defecto en drag de imágenes
document.addEventListener('dragstart', (e) => {
    if (e.target.classList.contains('carousel-image') || 
        e.target.closest('.carousel-thumbnails')) {
        e.preventDefault();
    }
});

// Mejorar rendimiento en resize
let resizeTimer;
window.addEventListener('resize', () => {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(() => {
        // Recalcular posiciones si es necesario
        console.log('Carrusel ajustado al nuevo tamaño de ventana');
    }, 250);
});


document.addEventListener('DOMContentLoaded', function() {
    
    // ============================================
    // SINCRONIZACIÓN DE BADGES DE CARRITO
    // ============================================
    function syncCartBadges() {
        const mainBadge = document.getElementById('cartBadge');
        const floatingBadge = document.getElementById('floatingCartBadge');
        
        if (mainBadge && floatingBadge) {
            const count = parseInt(mainBadge.textContent) || 0;
            floatingBadge.textContent = count;
            
            if (count > 0) {
                floatingBadge.style.display = 'flex';
            } else {
                floatingBadge.style.display = 'none';
            }
        }
    }
    
    // Sincronizar al cargar
    syncCartBadges();
    
    // Observar cambios en el badge principal
    const mainBadge = document.getElementById('cartBadge');
    if (mainBadge) {
        const badgeObserver = new MutationObserver(syncCartBadges);
        badgeObserver.observe(mainBadge, { 
            childList: true, 
            characterData: true, 
            subtree: true 
        });
    }
    
    // ============================================
    // INDICADORES DE STOCK BAJO
    // ============================================
    function updateStockIndicators() {
        const stockBadges = document.querySelectorAll('.stock-badge');
        
        stockBadges.forEach(badge => {
            const stockText = badge.textContent;
            const stockMatch = stockText.match(/\d+/);
            
            if (stockMatch) {
                const stock = parseInt(stockMatch[0]);
                
                // Remover clases anteriores
                badge.classList.remove('low-stock', 'very-low-stock');
                
                if (stock <= 5 && stock > 2) {
                    badge.classList.add('low-stock');
                    badge.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Stock: ' + stock;
                } else if (stock <= 2) {
                    badge.classList.add('very-low-stock');
                    badge.innerHTML = '<i class="fas fa-exclamation-circle"></i> ¡Últimas ' + stock + '!';
                }
            }
        });
    }
    
    updateStockIndicators();
    
    // ============================================
    // OCULTAR/MOSTRAR FILTROS SEGÚN TIPO
    // ============================================
    const radioButtons = document.querySelectorAll('input[name="mostrar"]');
    const articulosFilters = document.getElementById('articulos-filters');
    
    if (articulosFilters) {
        radioButtons.forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.value === 'servicios') {
                    articulosFilters.style.display = 'none';
                    // Limpiar filtros de artículos al cambiar a servicios
                    const selects = articulosFilters.querySelectorAll('select');
                    const inputs = articulosFilters.querySelectorAll('input');
                    selects.forEach(s => s.value = '');
                    inputs.forEach(i => i.value = '');
                } else {
                    articulosFilters.style.display = 'block';
                }
            });
        });
    }
    
    // ============================================
    // ANIMACIÓN DEL BOTÓN FLOTANTE AL AGREGAR
    // ============================================
    const addToCartButtons = document.querySelectorAll('.btn-add-cart');
    const floatingCartBtn = document.getElementById('floatingCartBtn');
    
    addToCartButtons.forEach(button => {
        button.addEventListener('click', function() {
            if (floatingCartBtn) {
                // Animación de "salto"
                floatingCartBtn.style.animation = 'none';
                setTimeout(() => {
                    floatingCartBtn.style.animation = 'cartBounce 0.6s ease';
                    setTimeout(() => {
                        floatingCartBtn.style.animation = 'floatAnimation 3s ease-in-out infinite';
                    }, 600);
                }, 10);
            }
        });
    });
    
    // Definir animación de rebote
    const style = document.createElement('style');
    style.textContent = `
        @keyframes cartBounce {
            0%, 100% { transform: scale(1) translateY(0); }
            10% { transform: scale(1.2) translateY(-10px); }
            30% { transform: scale(0.9) translateY(0); }
            50% { transform: scale(1.1) translateY(-5px); }
            70% { transform: scale(0.95) translateY(0); }
        }
    `;
    document.head.appendChild(style);
    
    // ============================================
    // TOOLTIP PARA BOTÓN FLOTANTE
    // ============================================
    if (floatingCartBtn) {
        floatingCartBtn.addEventListener('mouseenter', function() {
            const badge = document.getElementById('floatingCartBadge');
            const count = badge ? parseInt(badge.textContent) || 0 : 0;
            
            const tooltip = document.createElement('div');
            tooltip.className = 'floating-cart-tooltip';
            tooltip.textContent = count > 0 ? `${count} producto${count > 1 ? 's' : ''} en el carrito` : 'Carrito vacío';
            tooltip.style.cssText = `
                position: absolute;
                right: 70px;
                top: 50%;
                transform: translateY(-50%);
                background: var(--cetacean-blue);
                color: white;
                padding: 0.6rem 1.2rem;
                border-radius: 8px;
                font-size: 0.9rem;
                white-space: nowrap;
                box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
                z-index: 10000;
                pointer-events: none;
                animation: slideInTooltip 0.3s ease;
            `;
            
            this.appendChild(tooltip);
            this.tooltipElement = tooltip;
        });
        
        floatingCartBtn.addEventListener('mouseleave', function() {
            if (this.tooltipElement) {
                this.tooltipElement.remove();
                this.tooltipElement = null;
            }
        });
    }
    
    // Animación del tooltip
    const tooltipStyle = document.createElement('style');
    tooltipStyle.textContent = `
        @keyframes slideInTooltip {
            from {
                opacity: 0;
                transform: translateY(-50%) translateX(10px);
            }
            to {
                opacity: 1;
                transform: translateY(-50%) translateX(0);
            }
        }
    `;
    document.head.appendChild(tooltipStyle);
    
    // ============================================
    // MEJORA EN LA NAVEGACIÓN CON TECLADO
    // ============================================
    document.addEventListener('keydown', function(e) {
        // Alt + C para abrir carrito
        if (e.altKey && e.key === 'c') {
            e.preventDefault();
            if (floatingCartBtn) {
                window.location.href = floatingCartBtn.href;
            }
        }
        
        // Alt + F para abrir filtros en móvil
        if (e.altKey && e.key === 'f') {
            e.preventDefault();
            const toggleBtn = document.getElementById('toggleFilters');
            if (toggleBtn && window.innerWidth <= 1024) {
                toggleBtn.click();
            }
        }
    });
    
    // ============================================
    // CONTADOR DE PRODUCTOS VISIBLES
    // ============================================
    function updateVisibleProductCount() {
        const productsGrid = document.querySelector('.products-grid');
        if (!productsGrid) return;
        
        const products = productsGrid.querySelectorAll('.product-card');
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        });
        
        products.forEach(product => {
            product.style.opacity = '0';
            product.style.transform = 'translateY(30px)';
            product.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            observer.observe(product);
        });
    }
    
    updateVisibleProductCount();
    
    // ============================================
    // PRECARGAR IMÁGENES VISIBLES
    // ============================================
    function preloadVisibleImages() {
        const images = document.querySelectorAll('.carousel-image[loading="lazy"]');
        
        const imageObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    if (img.dataset.src) {
                        img.src = img.dataset.src;
                        img.removeAttribute('data-src');
                    }
                    imageObserver.unobserve(img);
                }
            });
        }, {
            rootMargin: '50px'
        });
        
        images.forEach(img => imageObserver.observe(img));
    }
    
    preloadVisibleImages();
    
    // ============================================
    // FEEDBACK VISUAL AL HACER SCROLL
    // ============================================
    let lastScrollTop = 0;
    const floatingBtn = document.getElementById('floatingCartBtn');
    
    window.addEventListener('scroll', function() {
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        
        if (floatingBtn) {
            if (scrollTop > lastScrollTop && scrollTop > 300) {
                // Scrolling down
                floatingBtn.style.transform = 'scale(0.8)';
                floatingBtn.style.opacity = '0.7';
            } else {
                // Scrolling up
                floatingBtn.style.transform = 'scale(1)';
                floatingBtn.style.opacity = '1';
            }
        }
        
        lastScrollTop = scrollTop;
    }, { passive: true });
    
    // ============================================
    // NOTIFICACIÓN AL AGREGAR PRODUCTO
    // ============================================
    function showAddToCartNotification(productName, success = true) {
        const notification = document.createElement('div');
        notification.className = 'cart-notification';
        notification.innerHTML = `
            <div class="notification-content">
                <i class="fas fa-${success ? 'check-circle' : 'times-circle'}"></i>
                <span>${success ? 'Producto agregado' : 'Error al agregar'}</span>
            </div>
        `;
        
        notification.style.cssText = `
            position: fixed;
            top: 100px;
            right: 30px;
            background: ${success ? 'linear-gradient(135deg, #27ae60 0%, #229954 100%)' : 'linear-gradient(135deg, #e74c3c 0%, #c0392b 100%)'};
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
            z-index: 10001;
            animation: slideInNotification 0.4s ease, slideOutNotification 0.4s ease 2.6s;
            display: flex;
            align-items: center;
            gap: 1rem;
            max-width: 300px;
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.remove();
        }, 3000);
    }
    
    // Animaciones para notificaciones
    const notificationStyle = document.createElement('style');
    notificationStyle.textContent = `
        @keyframes slideInNotification {
            from {
                opacity: 0;
                transform: translateX(100px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        @keyframes slideOutNotification {
            from {
                opacity: 1;
                transform: translateX(0);
            }
            to {
                opacity: 0;
                transform: translateX(100px);
            }
        }
        
        .notification-content {
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }
        
        .notification-content i {
            font-size: 1.5rem;
        }
    `;
    document.head.appendChild(notificationStyle);
    
    // Interceptar clicks en botones de agregar para mostrar notificación
    addToCartButtons.forEach(button => {
        const originalClick = button.onclick;
        button.addEventListener('click', function(e) {
            const productCard = this.closest('.product-card');
            const productName = productCard ? productCard.querySelector('h3').textContent : 'Producto';
            
            // Esperar un momento para verificar si fue exitoso
            setTimeout(() => {
                const wasSuccessful = !this.classList.contains('error');
                showAddToCartNotification(productName, wasSuccessful);
            }, 500);
        });
    });
    
    console.log('✅ Script de mejoras cargado correctamente');
});