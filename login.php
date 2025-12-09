<?php
// login.php - Formulario de inicio de sesión (CORREGIDO)

// ============================================
// PASO 1: INICIAR SESIÓN (si no está iniciada en header.php)
// ============================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================
// PASO 2: VERIFICAR SI YA ESTÁ LOGUEADO (ANTES DE CUALQUIER HTML)
// ============================================
if (isset($_SESSION['cliente_id'])) {
    header('Location: index.php');
    exit;
}

// ============================================
// PASO 3: PROCESAR EL FORMULARIO (ANTES DE CUALQUIER HTML)
// ============================================
$mensaje = '';
$identificador = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // USAR LA CONEXIÓN CENTRALIZADA
    require_once 'includes/db.php';
    require_once __DIR__ . '/includes/cliente.php';
    
    $database = new DB();
    $clienteManager = new Cliente($database->pdo);

    $identificador = trim($_POST['identificador'] ?? ''); // DNI o Email
    $password = $_POST['password'] ?? '';

    // Validar que los campos no estén vacíos
    if (empty($identificador) || empty($password)) {
        $mensaje = 'Todos los campos son obligatorios.';
    } else {
        // Intentar iniciar sesión
        $usuario = $clienteManager->login($identificador, $password);

        if ($usuario) {
            // ============================================
            // INICIO DE SESIÓN EXITOSO
            // ============================================
            
            // Almacenar datos del usuario en la sesión
            $_SESSION['cliente_id'] = $usuario['id'];
            $_SESSION['cliente_nombre'] = $usuario['nombre'];
            $_SESSION['cliente_email'] = $usuario['email'];
            $_SESSION['cliente_dni'] = $usuario['numero_documento'];
            $_SESSION['cliente_telefono'] = $usuario['telefonomovil'] ?? '';
            $_SESSION['cliente_direccion'] = $usuario['direccion'] ?? '';
            
            // Registrar fecha de último acceso
            $_SESSION['ultimo_acceso'] = date('Y-m-d H:i:s');
            
            // Redirigir al catálogo o página de inicio
            header('Location: index.php');
            exit;
        } else {
            // Credenciales incorrectas
            $mensaje = 'DNI/Email o contraseña incorrectos. Por favor verifica tus datos.';
        }
    }
}

// ============================================
// PASO 4: AHORA SÍ, INCLUIR EL HEADER (DESPUÉS DE TODA LA LÓGICA)
// ============================================
$page_title = "Iniciar Sesión";
require_once __DIR__ . '/includes/header.php';
include("includes/conexion_nube.php");
?>

<div class="login-container">
    <div class="login-card">
        <div class="login-header">
            <i class="fas fa-sign-in-alt login-icon"></i>
            <h1>Iniciar Sesión</h1>
            <p>Ingresa a tu cuenta para continuar</p>
        </div>

        <?php if ($mensaje): ?>
            <div class="alerta error">
                <i class="fas fa-exclamation-triangle"></i>
                <span><?= htmlspecialchars($mensaje) ?></span>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST" class="form-login" autocomplete="on">
            <div class="form-group">
                <label for="identificador">
                    <i class="fas fa-user"></i> DNI o Email:
                </label>
                <input 
                    type="text" 
                    id="identificador" 
                    name="identificador" 
                    required 
                    placeholder="Ingresa tu DNI o correo electrónico"
                    value="<?= htmlspecialchars($identificador) ?>"
                    autocomplete="username"
                    autofocus>
                <small>Puedes usar tu DNI o correo electrónico</small>
            </div>
            
            <div class="form-group">
                <label for="password">
                    <i class="fas fa-lock"></i> Contraseña:
                </label>
                <div class="password-input-group">
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        required
                        placeholder="Ingresa tu contraseña"
                        autocomplete="current-password">
                    <button type="button" class="toggle-password" onclick="togglePassword()">
                        <i class="fas fa-eye" id="toggleIcon"></i>
                    </button>
                </div>
                <small>
                    <a href="recuperar-password.php" class="forgot-password">
                        ¿Olvidaste tu contraseña?
                    </a>
                </small>
            </div>

            <button type="submit" class="btn-primary">
                <i class="fas fa-sign-in-alt"></i> Entrar
            </button>
            
            <div class="form-divider">
                <span>o</span>
            </div>
            
            <p class="form-footer">
                ¿No tienes cuenta? <a href="registro.php" class="register-link">Regístrate aquí</a>
            </p>
        </form>
    </div>
</div>

<style>
/* Estilos específicos para login.php */
.login-container {
    max-width: 500px;
    margin: 3rem auto;
    padding: 0 1rem;
    min-height: calc(100vh - 200px);
    display: flex;
    align-items: center;
    justify-content: center;
}

.login-card {
    background: white;
    padding: 2.5rem;
    border-radius: 16px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
    width: 100%;
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

.login-header {
    text-align: center;
    margin-bottom: 2rem;
}

.login-icon {
    font-size: 3rem;
    color: var(--absolute-zero);
    margin-bottom: 1rem;
    animation: pulse 2s infinite;
}

.login-header h1 {
    color: var(--cetacean-blue);
    margin-bottom: 0.5rem;
    font-size: 1.8rem;
    font-weight: 800;
}

.login-header p {
    color: #666;
    margin: 0;
    font-size: 0.95rem;
}

.form-login {
    display: flex;
    flex-direction: column;
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

.form-group small {
    color: #666;
    font-size: 0.85rem;
}

.forgot-password {
    color: var(--absolute-zero);
    text-decoration: none;
    font-size: 0.85rem;
    font-weight: 600;
    transition: color 0.3s;
}

.forgot-password:hover {
    color: var(--cetacean-blue);
    text-decoration: underline;
}

.btn-primary {
    background: linear-gradient(135deg, var(--absolute-zero) 0%, #0158e8 100%);
    color: white;
    padding: 1.1rem;
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

.form-divider {
    text-align: center;
    margin: 0.5rem 0;
    position: relative;
}

.form-divider::before,
.form-divider::after {
    content: '';
    position: absolute;
    top: 50%;
    width: 40%;
    height: 1px;
    background: #e0e0e0;
}

.form-divider::before {
    left: 0;
}

.form-divider::after {
    right: 0;
}

.form-divider span {
    background: white;
    padding: 0 1rem;
    color: #999;
    font-size: 0.9rem;
    font-weight: 500;
}

.form-footer {
    text-align: center;
    margin: 0;
    color: #666;
}

.register-link {
    color: var(--absolute-zero);
    font-weight: 700;
    text-decoration: none;
    transition: color 0.3s;
}

.register-link:hover {
    color: var(--cetacean-blue);
    text-decoration: underline;
}

.alerta {
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.7rem;
    animation: shake 0.5s ease;
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-10px); }
    75% { transform: translateX(10px); }
}

.alerta.error {
    background: linear-gradient(135deg, #ffe8e8 0%, #ffd4d4 100%);
    color: #c62828;
    border-left: 4px solid #d32f2f;
    font-weight: 600;
}

.alerta i {
    font-size: 1.2rem;
}

@media (max-width: 576px) {
    .login-container {
        margin: 2rem auto;
        padding: 0 0.5rem;
    }
    
    .login-card {
        padding: 2rem 1.5rem;
    }
    
    .login-icon {
        font-size: 2.5rem;
    }
    
    .login-header h1 {
        font-size: 1.5rem;
    }
    
    .btn-primary {
        padding: 1rem;
        font-size: 1rem;
    }
}
</style>

<script>
function togglePassword() {
    const passwordInput = document.getElementById('password');
    const toggleIcon = document.getElementById('toggleIcon');
    
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
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>