// assets/js/main.js

document.addEventListener('DOMContentLoaded', function() {
        // MENÚ MÓVIL
        const menuToggle = document.getElementById('menuToggle');
        const navMenu = document.getElementById('navMenu');
    
        if (menuToggle && navMenu) {
            menuToggle.addEventListener('click', function() {
                this.classList.toggle('active');
                navMenu.classList.toggle('active');
            });
        }
    
        // Cerrar menú al hacer clic en un enlace
        const navLinks = document.querySelectorAll('.nav-link');
        navLinks.forEach(link => {
            link.addEventListener('click', function() {
                navMenu.classList.remove('active');
                if (menuToggle) menuToggle.classList.remove('active');
            });
        });

        // Toggle panel de filtros
        const filterToggle = document.getElementById('filterToggle');
        const filterPanel = document.getElementById('filterPanel');
        if (filterToggle && filterPanel) {
            filterToggle.addEventListener('click', function() {
                filterPanel.classList.toggle('active');
                const icon = this.querySelector('i');
                if (filterPanel.classList.contains('active')) {
                    icon.classList.remove('fa-filter');
                    icon.classList.add('fa-times');
                } else {
                    icon.classList.remove('fa-times');
                    icon.classList.add('fa-filter');
                }
            });
        }

        contarFiltrosActivos();

        // Inicializar badge del carrito desde servidor
        updateCartBadge();

        // Botones de añadir
        const botonesAñadir = document.querySelectorAll('.btn-añadir');
        botonesAñadir.forEach(boton => {
            boton.addEventListener('click', function() {
                const id = this.dataset.id;
                const tipo = this.dataset.tipo;
                añadirAlCarrito(id, tipo, this);
            });
        });

        // Scroll suave
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        });
    });

    function contarFiltrosActivos() {
        const params = new URLSearchParams(window.location.search);
        let contador = 0;
        const filtros = ['categoria', 'marca', 'tipo', 'precio_min', 'precio_max', 'mostrar'];
        filtros.forEach(filtro => {
            if (params.get(filtro) && params.get(filtro) !== '' && params.get(filtro) !== 'todos') contador++;
        });
        const filterCount = document.getElementById('filterCount');
        if (!filterCount) return;
        if (contador > 0) { filterCount.textContent = contador; filterCount.classList.add('active'); }
        else filterCount.classList.remove('active');
    }

    // -----------------------
    // Funciones del carrito
    // -----------------------
    function updateCartBadge() {
        const cartBadge = document.getElementById('cartBadge');
        if (!cartBadge) return;
        fetch('cart_actions.php?action=count')
            .then(res => res.json())
            .then(data => {
                if (data && typeof data.cart_count !== 'undefined') {
                    cartBadge.textContent = data.cart_count;
                    cartBadge.style.display = data.cart_count > 0 ? 'flex' : '';
                }
            })
            .catch(err => console.error('Error al obtener contador de carrito:', err));
    }

    function añadirAlCarrito(id, tipo, boton) {
        if (tipo === 'servicio') {
            mostrarNotificacion('Servicio agregado a solicitudes', 'success');
            // Para servicios podrías tener otro flujo
            return;
        }

        // Para artículos o servicios: llamar endpoint server-side
        const form = new FormData();
        form.append('action', 'add');
        form.append('id', id);
        form.append('cantidad', 1);
        form.append('tipo', tipo || 'articulo');

        fetch('cart_actions.php', { method: 'POST', body: form })
            .then(res => res.json())
            .then(resp => {
                if (resp.success) {
                    // Mensajes distintos para servicio/artículo
                    if (tipo === 'servicio') {
                        mostrarNotificacion(resp.message || 'Servicio solicitado (precio pendiente)', 'success');
                    } else {
                        mostrarNotificacion(resp.message || 'Producto añadido al carrito', 'success');
                    }
                    animarBoton(boton);
                    updateCartBadge();
                } else {
                    mostrarNotificacion(resp.message || 'No se pudo añadir al carrito', 'info');
                }
            })
            .catch(err => {
                console.error(err);
                mostrarNotificacion('Error de red al añadir al carrito', 'info');
            });
    }

    function removeFromCart(id, tipo) {
        const form = new FormData();
        form.append('action', 'remove');
        form.append('id', id);
        if (tipo) form.append('tipo', tipo);
        return fetch('cart_actions.php', { method: 'POST', body: form })
            .then(res => res.json())
            .then(resp => { updateCartBadge(); return resp; });
    }

    function updateCartItem(id, cantidad, tipo) {
        const form = new FormData();
        form.append('action', 'update');
        form.append('id', id);
        form.append('cantidad', cantidad);
        if (tipo) form.append('tipo', tipo);
        return fetch('cart_actions.php', { method: 'POST', body: form })
            .then(res => res.json())
            .then(resp => { updateCartBadge(); return resp; });
    }

    function clearCart() {
        if (!confirm('¿Vaciar el carrito?')) return;
        const form = new FormData();
        form.append('action', 'clear');
        return fetch('cart_actions.php', { method: 'POST', body: form })
            .then(res => res.json())
            .then(resp => { updateCartBadge(); return resp; });
    }

    // Notificaciones y animaciones (reutilizadas)
    function mostrarNotificacion(mensaje, tipo = 'info') {
        const notificacion = document.createElement('div');
        notificacion.className = `notificacion notificacion-${tipo}`;
        notificacion.innerHTML = `<i class="fas fa-${tipo === 'success' ? 'check-circle' : 'info-circle'}"></i><span>${mensaje}</span>`;
        if (!document.getElementById('notificacion-styles')) {
            const styles = document.createElement('style');
            styles.id = 'notificacion-styles';
            styles.textContent = `.notificacion{position:fixed;top:100px;right:20px;background:#fff;padding:1rem 1.5rem;border-radius:10px;box-shadow:0 5px 20px rgba(0,0,0,0.2);display:flex;align-items:center;gap:.8rem;z-index:9999;animation:slideIn .3s}@keyframes slideIn{from{transform:translateX(400px);opacity:0}to{transform:translateX(0);opacity:1}}@keyframes slideOut{from{transform:translateX(0);opacity:1}to{transform:translateX(400px);opacity:0}}.notificacion-success{border-left:4px solid #27ae60;color:#27ae60}.notificacion-info{border-left:4px solid #3498db;color:#3498db}.notificacion i{font-size:1.5rem}`;
            document.head.appendChild(styles);
        }
        document.body.appendChild(notificacion);
        setTimeout(() => { notificacion.style.animation = 'slideOut .3s'; setTimeout(()=>notificacion.remove(),300); }, 3000);
    }

    function animarBoton(boton) {
        if (!boton) return;
        boton.style.transform = 'scale(0.9)';
        setTimeout(()=>boton.style.transform='scale(1)',200);
        const textoOriginal = boton.innerHTML;
        boton.innerHTML = '<i class="fas fa-check"></i> Agregado';
        boton.style.backgroundColor = '#27ae60';
        setTimeout(()=>{ boton.innerHTML = textoOriginal; boton.style.backgroundColor=''; }, 2000);
    }

    // Lazy loading optional
    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries, obs) => { entries.forEach(e => { if (e.isIntersecting) { const img = e.target; if (img.dataset && img.dataset.src) img.src = img.dataset.src; img.classList.add('loaded'); obs.unobserve(img); } }); });
        document.querySelectorAll('img[data-src]').forEach(img => observer.observe(img));
    }



    