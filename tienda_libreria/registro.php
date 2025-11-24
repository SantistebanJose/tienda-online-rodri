<?php
// registro.php

$page_title = "Registrarse";
require_once __DIR__ . '/includes/header.php';
include ("includes/conexion_nube.php");

require_once __DIR__ . '/includes/cliente.php';

$mensaje = '';
$tipo_mensaje = ''; // 'error' o 'exito'

// Inicializar array de datos
$datos = [
    'dni' => '',
    'nombres' => '',
    'apellidos' => '',
    'email' => '',
    'telefono' => '',
    'direccion' => ''
];

// Si el usuario ya está logeado, redirigir al catálogo
if (isset($_SESSION['cliente_id'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    require_once 'includes/db.php';
    $database = new DB();
    $clienteManager = new Cliente($database->pdo);
    
    // Recoger datos del formulario
    $datos = [
        'dni' => trim($_POST['dni'] ?? ''),
        'nombres' => trim($_POST['nombres'] ?? ''),
        'apellidos' => trim($_POST['apellidos'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'telefono' => trim($_POST['telefono'] ?? ''),
        'direccion' => trim($_POST['direccion'] ?? ''),
        'password' => $_POST['password'] ?? '',
        'password_confirm' => $_POST['password_confirm'] ?? ''
    ];
    
    // Validaciones
    if (empty($datos['dni']) || empty($datos['nombres']) || empty($datos['apellidos']) || 
        empty($datos['email']) || empty($datos['password']) || empty($datos['password_confirm'])) {
        $mensaje = 'Error: Los campos marcados con (*) son obligatorios.';
        $tipo_mensaje = 'error';
    } elseif (strlen($datos['dni']) !== 8 || !ctype_digit($datos['dni'])) {
        $mensaje = 'Error: El DNI debe tener exactamente 8 dígitos.';
        $tipo_mensaje = 'error';
    } elseif (!filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
        $mensaje = 'Error: El correo electrónico no es válido.';
        $tipo_mensaje = 'error';
    } elseif ($datos['password'] !== $datos['password_confirm']) {
        $mensaje = 'Error: Las contraseñas no coinciden.';
        $tipo_mensaje = 'error';
    } elseif (strlen($datos['password']) < 6) {
        $mensaje = 'Error: La contraseña debe tener al menos 6 caracteres.';
        $tipo_mensaje = 'error';
    } elseif (!empty($datos['telefono']) && (strlen($datos['telefono']) !== 9 || !ctype_digit($datos['telefono']))) {
        $mensaje = 'Error: El teléfono debe tener exactamente 9 dígitos.';
        $tipo_mensaje = 'error';
    } else {
        // Verificar si el DNI ya existe
        $clienteExistente = $clienteManager->verificarEstadoPorDNI($datos['dni']);
        
        if ($clienteExistente) {
            // CASO 1: El cliente existe y YA tiene contraseña (es usuario web)
            if (!empty($clienteExistente['password'])) {
                $mensaje = 'Error: Este DNI ya está registrado como usuario web. <a href="login.php">¿Deseas iniciar sesión?</a>';
                $tipo_mensaje = 'error';
            } 
            // CASO 2: El cliente existe (POS) pero NO tiene contraseña (activar cuenta web)
            else {
                // Actualizar sus datos y establecer contraseña
                $datos_actualizar = [
                    'email' => $datos['email'],
                    'telefono' => $datos['telefono'],
                    'direccion' => $datos['direccion']
                ];
                
                if ($clienteManager->activarCuentaWeb($clienteExistente['id'], $datos['password'], $datos_actualizar)) {
                    $mensaje = '¡Cuenta activada correctamente! Ya eres cliente nuestro, hemos actualizado tus credenciales. Redirigiendo...';
                    $tipo_mensaje = 'exito';
                    
                    // Auto-login o redirigir
                    header("refresh:3;url=login.php");
                } else {
                    $mensaje = 'Error al activar tu cuenta. Por favor intenta más tarde.';
                    $tipo_mensaje = 'error';
                }
            }
        } 
        // CASO 3: DNI Nuevo - Verificar Email duplicado en otros registros
        elseif ($clienteManager->existeEmail($datos['email'])) {
            $mensaje = 'Error: Este correo electrónico ya está registrado con otro usuario. <a href="login.php">¿Deseas iniciar sesión?</a>';
            $tipo_mensaje = 'error';
        } else {
            // CASO 4: Registro Completamente Nuevo
            $nuevo_id = $clienteManager->registrarNuevo($datos);
            
            if ($nuevo_id) {
                $mensaje = '¡Registro exitoso! Tu cuenta ha sido creada. Redirigiendo al inicio de sesión...';
                $tipo_mensaje = 'exito';
                
                // Limpiar datos
                $datos = ['dni'=>'', 'nombres'=>'', 'apellidos'=>'', 'email'=>'', 'telefono'=>'', 'direccion'=>''];
                header("refresh:2;url=login.php");
            } else {
                $mensaje = 'Error: No se pudo completar el registro. Intenta nuevamente o contacta al administrador.';
                $tipo_mensaje = 'error';
            }
        }
    }
}
?>

<div class="registro-container">
    <div class="registro-header">
        <i class="fas fa-user-plus header-icon"></i>
        <h1>Crear Nueva Cuenta</h1>
        <p>Regístrate para empezar a comprar en nuestra tienda online</p>
    </div>

    <?php if ($mensaje): ?>
        <div class="alerta <?= $tipo_mensaje ?>">
            <i class="fas <?= $tipo_mensaje === 'exito' ? 'fa-check-circle' : 'fa-exclamation-triangle' ?>"></i>
            <span><?= $mensaje ?></span>
        </div>
    <?php endif; ?>

    <div class="registro-card">
        <div class="info-box">
            <h3><i class="fas fa-info-circle"></i> Información de Registro</h3>
            <p>Completa todos los campos obligatorios (*) para crear tu cuenta.</p>
            <p>Tu información estará protegida y solo será usada para gestionar tus compras.</p>
        </div>

        <form action="registro.php" method="POST" class="form-registro" onsubmit="return validarFormulario()">
            
            <div class="form-section">
                <h3><i class="fas fa-id-card"></i> Datos de Identificación</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="dni">
                            <i class="fas fa-id-card"></i> DNI: *
                        </label>
                        <input 
                            type="text" 
                            id="dni" 
                            name="dni" 
                            required 
                            maxlength="8"
                            pattern="[0-9]{8}"
                            placeholder="12345678"
                            value="<?= htmlspecialchars($datos['dni']) ?>">
                        <small>Ingresa tu número de DNI de 8 dígitos</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">
                            <i class="fas fa-envelope"></i> Email: *
                        </label>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            required
                            placeholder="correo@ejemplo.com"
                            value="<?= htmlspecialchars($datos['email']) ?>">
                        <small>Usarás este correo para iniciar sesión</small>
                    </div>
                </div>
            </div>
            
            <div class="form-section">
                <h3><i class="fas fa-user"></i> Datos Personales</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="nombres">
                            <i class="fas fa-user"></i> Nombres: *
                        </label>
                        <input 
                            type="text" 
                            id="nombres" 
                            name="nombres" 
                            required
                            placeholder="Juan Carlos"
                            value="<?= htmlspecialchars($datos['nombres']) ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="apellidos">
                            <i class="fas fa-user"></i> Apellidos: *
                        </label>
                        <input 
                            type="text" 
                            id="apellidos" 
                            name="apellidos" 
                            required
                            placeholder="Pérez García"
                            value="<?= htmlspecialchars($datos['apellidos']) ?>">
                    </div>
                </div>
            </div>
            
            <div class="form-section">
                <h3><i class="fas fa-address-book"></i> Datos de Contacto (Opcionales)</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="telefono">
                            <i class="fas fa-phone"></i> Teléfono:
                        </label>
                        <input 
                            type="tel" 
                            id="telefono" 
                            name="telefono"
                            maxlength="9"
                            pattern="[0-9]{9}"
                            placeholder="999999999"
                            value="<?= htmlspecialchars($datos['telefono']) ?>">
                        <small>Número de celular de 9 dígitos</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="direccion">
                            <i class="fas fa-map-marker-alt"></i> Dirección:
                        </label>
                        <input 
                            type="text" 
                            id="direccion" 
                            name="direccion"
                            placeholder="Av. Principal 123, Piura"
                            value="<?= htmlspecialchars($datos['direccion']) ?>">
                        <small>Dirección de entrega de productos</small>
                    </div>
                </div>
            </div>
            
            <div class="form-section">
                <h3><i class="fas fa-lock"></i> Seguridad de la Cuenta</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="password">
                            <i class="fas fa-lock"></i> Contraseña: *
                        </label>
                        <div class="password-input-group">
                            <input 
                                type="password" 
                                id="password" 
                                name="password" 
                                required
                                minlength="6"
                                placeholder="Mínimo 6 caracteres">
                            <button type="button" class="toggle-password" onclick="togglePassword('password', 'toggleIcon1')">
                                <i class="fas fa-eye" id="toggleIcon1"></i>
                            </button>
                        </div>
                        <small>Mínimo 6 caracteres</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="password_confirm">
                            <i class="fas fa-lock"></i> Confirmar Contraseña: *
                        </label>
                        <div class="password-input-group">
                            <input 
                                type="password" 
                                id="password_confirm" 
                                name="password_confirm" 
                                required
                                minlength="6"
                                placeholder="Repite tu contraseña">
                            <button type="button" class="toggle-password" onclick="togglePassword('password_confirm', 'toggleIcon2')">
                                <i class="fas fa-eye" id="toggleIcon2"></i>
                            </button>
                        </div>
                        <small id="password-match-message"></small>
                    </div>
                </div>
                
                <div class="password-strength">
                    <div class="strength-bar" id="strengthBar"></div>
                    <span id="strengthText"></span>
                </div>
            </div>

            <p class="form-note">
                <i class="fas fa-info-circle"></i> Los campos marcados con (*) son obligatorios
            </p>

            <button type="submit" class="btn-primary">
                <i class="fas fa-user-plus"></i> Crear Cuenta
            </button>
            
            <p class="form-footer">
                ¿Ya tienes cuenta? <a href="login.php">Inicia Sesión aquí</a>
            </p>
        </form>
    </div>
</div>

<style>
.registro-container {
    max-width: 900px;
    margin: 2rem auto;
    padding: 0 1rem;
    animation: fadeInUp 0.6s ease;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.registro-header {
    text-align: center;
    margin-bottom: 2rem;
}

.header-icon {
    font-size: 4rem;
    color: var(--absolute-zero);
    margin-bottom: 1rem;
    animation: pulse 2s infinite;
}

.registro-header h1 {
    color: var(--cetacean-blue);
    margin-bottom: 0.5rem;
    font-size: 2.5rem;
    font-weight: 800;
}

.registro-header p {
    color: #666;
    font-size: 1.1rem;
}

.registro-card {
    background: white;
    padding: 2.5rem;
    border-radius: 16px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
}

.info-box {
    background: linear-gradient(135deg, #e3f2fd 0%, #e8f4f8 100%);
    border-left: 4px solid var(--absolute-zero);
    padding: 1.5rem;
    margin-bottom: 2rem;
    border-radius: 8px;
}

.info-box h3 {
    color: var(--cetacean-blue);
    margin-bottom: 0.8rem;
    font-size: 1.2rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.info-box p {
    margin: 0.5rem 0 0;
    color: #555;
    line-height: 1.6;
}

.alerta {
    padding: 1.2rem;
    border-radius: 8px;
    margin-bottom: 2rem;
    display: flex;
    align-items: flex-start;
    gap: 0.8rem;
    animation: slideDown 0.5s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.alerta i {
    font-size: 1.3rem;
    flex-shrink: 0;
    margin-top: 0.2rem;
}

.alerta.error {
    background: linear-gradient(135deg, #ffe8e8 0%, #ffd4d4 100%);
    color: #c62828;
    border-left: 4px solid #d32f2f;
    font-weight: 600;
}

.alerta.exito {
    background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
    color: #2e7d32;
    border-left: 4px solid #4caf50;
    font-weight: 600;
}

.alerta a {
    color: inherit;
    text-decoration: underline;
    font-weight: 700;
}

.form-registro {
    display: flex;
    flex-direction: column;
    gap: 2rem;
}

.form-section {
    background: #f8fbfd;
    padding: 1.8rem;
    border-radius: 12px;
    border: 2px solid #e0e8f0;
}

.form-section h3 {
    color: var(--cetacean-blue);
    margin-bottom: 1.5rem;
    font-size: 1.2rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding-bottom: 0.8rem;
    border-bottom: 2px solid var(--absolute-zero);
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.form-group label {
    font-weight: 700;
    color: var(--cetacean-blue);
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.95rem;
}

.form-group label i {
    color: var(--absolute-zero);
    font-size: 1.1rem;
}

.form-group input {
    padding: 0.9rem 1.2rem;
    border: 2px solid #e0e8f0;
    border-radius: 8px;
    font-size: 1rem;
    transition: all 0.3s ease;
    width: 100%;
    background: white;
}

.form-group input:focus {
    outline: none;
    border-color: var(--absolute-zero);
    box-shadow: 0 0 0 4px rgba(1, 70, 199, 0.1);
    transform: translateY(-2px);
}

.form-group input:hover {
    border-color: var(--accent-color);
}

.form-group small {
    color: #666;
    font-size: 0.85rem;
    margin-top: -0.2rem;
}

.password-input-group {
    position: relative;
    display: flex;
    align-items: center;
}

.password-input-group input {
    padding-right: 3rem;
}

.toggle-password {
    position: absolute;
    right: 0.8rem;
    background: none;
    border: none;
    color: var(--absolute-zero);
    cursor: pointer;
    padding: 0.5rem;
    font-size: 1.2rem;
    transition: all 0.3s ease;
    border-radius: 4px;
}

.toggle-password:hover {
    background: rgba(1, 70, 199, 0.1);
    color: var(--cetacean-blue);
}

.password-strength {
    margin-top: 1rem;
    display: none;
}

.password-strength.active {
    display: block;
}

.strength-bar {
    height: 6px;
    background: #e0e8f0;
    border-radius: 3px;
    margin-bottom: 0.5rem;
    overflow: hidden;
    position: relative;
}

.strength-bar::after {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    height: 100%;
    width: 0%;
    transition: all 0.3s ease;
    border-radius: 3px;
}

.strength-bar.weak::after {
    width: 33%;
    background: #f44336;
}

.strength-bar.medium::after {
    width: 66%;
    background: #ff9800;
}

.strength-bar.strong::after {
    width: 100%;
    background: #4caf50;
}

#strengthText {
    font-size: 0.85rem;
    font-weight: 600;
}

#password-match-message {
    font-weight: 600;
}

#password-match-message.match {
    color: #4caf50;
}

#password-match-message.no-match {
    color: #f44336;
}

.form-note {
    color: #666;
    font-size: 0.95rem;
    font-style: italic;
    margin: -0.5rem 0 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-primary {
    background: linear-gradient(135deg, var(--absolute-zero) 0%, #0158e8 100%);
    color: white;
    padding: 1.2rem;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 1.1rem;
    font-weight: 700;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.7rem;
    box-shadow: 0 4px 15px rgba(1, 70, 199, 0.3);
}

.btn-primary:hover {
    background: linear-gradient(135deg, #0158e8 0%, var(--absolute-zero) 100%);
    transform: scale(1.05);
    box-shadow: 0 6px 25px rgba(1, 70, 199, 0.5);
}

.btn-primary:active {
    transform: scale(0.98);
}

.form-footer {
    text-align: center;
    margin-top: 0.5rem;
    color: #666;
    font-size: 1rem;
}

.form-footer a {
    color: var(--absolute-zero);
    text-decoration: none;
    font-weight: 700;
    transition: color 0.3s;
}

.form-footer a:hover {
    color: var(--cetacean-blue);
    text-decoration: underline;
}

@media (max-width: 768px) {
    .registro-header h1 {
        font-size: 2rem;
    }
    
    .registro-card {
        padding: 2rem 1.5rem;
    }
    
    .form-row {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }
    
    .form-section {
        padding: 1.5rem;
    }
}

@media (max-width: 480px) {
    .registro-container {
        padding: 0 0.5rem;
    }
    
    .header-icon {
        font-size: 3rem;
    }
    
    .registro-header h1 {
        font-size: 1.6rem;
    }
    
    .registro-header p {
        font-size: 0.95rem;
    }
    
    .registro-card {
        padding: 1.5rem 1rem;
    }
    
    .form-section {
        padding: 1.2rem;
    }
    
    .info-box {
        padding: 1.2rem;
    }
    
    .btn-primary {
        padding: 1rem;
        font-size: 1rem;
    }
}
</style>

<script>
// Toggle password visibility
function togglePassword(inputId, iconId) {
    const passwordInput = document.getElementById(inputId);
    const toggleIcon = document.getElementById(iconId);
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleIcon.classList.remove('fa-eye');
        toggleIcon.classList.add('fa-eye-slash');
    } else {
        passwordInput.type = 'password';
        toggleIcon.classList.remove('fa-eye-slash');
        toggleIcon.classList.add('fa-eye');
    }
}

// Validar fortaleza de contraseña
document.getElementById('password')?.addEventListener('input', function() {
    const password = this.value;
    const strengthBar = document.getElementById('strengthBar');
    const strengthText = document.getElementById('strengthText');
    const container = document.querySelector('.password-strength');
    
    if (password.length === 0) {
        container.classList.remove('active');
        return;
    }
    
    container.classList.add('active');
    
    let strength = 0;
    if (password.length >= 6) strength++;
    if (password.length >= 8) strength++;
    if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
    if (/\d/.test(password)) strength++;
    if (/[^a-zA-Z\d]/.test(password)) strength++;
    
    strengthBar.className = 'strength-bar';
    
    if (strength <= 2) {
        strengthBar.classList.add('weak');
        strengthText.textContent = 'Contraseña débil';
        strengthText.style.color = '#f44336';
    } else if (strength <= 4) {
        strengthBar.classList.add('medium');
        strengthText.textContent = 'Contraseña media';
        strengthText.style.color = '#ff9800';
    } else {
        strengthBar.classList.add('strong');
        strengthText.textContent = 'Contraseña fuerte';
        strengthText.style.color = '#4caf50';
    }
});

// Validar que las contraseñas coincidan
document.getElementById('password_confirm')?.addEventListener('input', function() {
    const password = document.getElementById('password').value;
    const passwordConfirm = this.value;
    const message = document.getElementById('password-match-message');
    
    if (passwordConfirm.length === 0) {
        message.textContent = '';
        message.className = '';
        return;
    }
    
    if (password === passwordConfirm) {
        message.textContent = '✓ Las contraseñas coinciden';
        message.className = 'match';
    } else {
        message.textContent = '✗ Las contraseñas no coinciden';
        message.className = 'no-match';
    }
});

// Validar DNI (solo números)
document.getElementById('dni')?.addEventListener('input', function() {
    this.value = this.value.replace(/[^0-9]/g, '');
});

// Validar teléfono (solo números)
document.getElementById('telefono')?.addEventListener('input', function() {
    this.value = this.value.replace(/[^0-9]/g, '');
});

// Validación final del formulario
function validarFormulario() {
    const password = document.getElementById('password').value;
    const passwordConfirm = document.getElementById('password_confirm').value;
    const dni = document.getElementById('dni').value;
    const telefono = document.getElementById('telefono').value;
    
    // Validar DNI
    if (dni.length !== 8) {
        alert('El DNI debe tener exactamente 8 dígitos');
        document.getElementById('dni').focus();
        return false;
    }
    
    // Validar contraseñas
    if (password !== passwordConfirm) {
        alert('Las contraseñas no coinciden');
        document.getElementById('password_confirm').focus();
        return false;
    }
    
    if (password.length < 6) {
        alert('La contraseña debe tener al menos 6 caracteres');
        document.getElementById('password').focus();
        return false;
    }
    
    // Validar teléfono si se ingresó
    if (telefono.length > 0 && telefono.length !== 9) {
        alert('El teléfono debe tener exactamente 9 dígitos o dejarlo vacío');
        document.getElementById('telefono').focus();
        return false;
    }
    
    return true;
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>