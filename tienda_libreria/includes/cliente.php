<?php
// includes/Cliente.php

class Cliente {
    private $conn;
    private $table = 'persona_web';
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    /**
     * Verifica el estado de un cliente por DNI para el registro
     * @param string $dni
     * @return array|false Retorna datos básicos si existe, false si no
     */
    public function verificarEstadoPorDNI($dni) {
        try {
            $query = "SELECT id, nombres, apellidos, email, password 
                      FROM " . $this->table . " 
                      WHERE numero_documento = :dni 
                      AND deleted_at IS NULL 
                      AND tipo_persona = 'cliente'";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':dni', $dni);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                return $stmt->fetch(PDO::FETCH_ASSOC);
            }
            return false;
        } catch (PDOException $e) {
            error_log("Error en verificarEstadoPorDNI: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Activa una cuenta existente estableciendo la contraseña
     * @param int $id
     * @param string $password
     * @param array $datos_actualizar (Opcional) Datos extra a actualizar
     * @return bool
     */
    public function activarCuentaWeb($id, $password, $datos_actualizar = []) {
        try {
            $password_hash = password_hash($password, PASSWORD_BCRYPT);
            
            $sql = "UPDATE " . $this->table . " 
                    SET password = :password, 
                        updated_at = CURRENT_TIMESTAMP";
            
            // Si se envían datos adicionales (email, telefono) actualizarlos también
            if (!empty($datos_actualizar['email'])) {
                $sql .= ", email = :email";
            }
            if (!empty($datos_actualizar['telefono'])) {
                $sql .= ", telefonomovil = :telefono";
            }
            if (!empty($datos_actualizar['direccion'])) {
                $sql .= ", direccion = :direccion";
            }
            
            $sql .= " WHERE id = :id";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':password', $password_hash);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            
            if (!empty($datos_actualizar['email'])) {
                $stmt->bindParam(':email', $datos_actualizar['email']);
            }
            if (!empty($datos_actualizar['telefono'])) {
                $stmt->bindParam(':telefono', $datos_actualizar['telefono']);
            }
            if (!empty($datos_actualizar['direccion'])) {
                $stmt->bindParam(':direccion', $datos_actualizar['direccion']);
            }
            
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error en activarCuentaWeb: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verifica si existe un documento (DNI) en la base de datos
     * @param string $dni
     * @return bool
     */
    public function existeDNI($dni) {
        try {
            $query = "SELECT id FROM " . $this->table . " 
                      WHERE numero_documento = :dni 
                      AND deleted_at IS NULL 
                      AND tipo_persona = 'cliente'";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':dni', $dni);
            $stmt->execute();
            
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Error en existeDNI: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verifica si existe un email en la base de datos
     * @param string $email
     * @return bool
     */
    public function existeEmail($email) {
        try {
            $query = "SELECT id FROM " . $this->table . " 
                      WHERE email = :email 
                      AND deleted_at IS NULL 
                      AND tipo_persona = 'cliente'";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Error en existeEmail: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Registra un nuevo cliente completo
     * @param array $datos
     * @return int|false ID del nuevo cliente o false si falla
     */
    public function registrarNuevo($datos) {
        try {
            // Verificar que no exista el DNI
            if ($this->existeDNI($datos['dni'])) {
                return false;
            }
            
            // Verificar que no exista el email
            if ($this->existeEmail($datos['email'])) {
                return false;
            }
            
            // Hash de la contraseña
            $password_hash = password_hash($datos['password'], PASSWORD_BCRYPT);
            
            $query = "INSERT INTO " . $this->table . " 
                      (numero_documento, nombres, apellidos, email, telefonomovil, 
                       direccion, password, tipo_persona, condicion, created_at, updated_at)
                      VALUES 
                      (:dni, :nombres, :apellidos, :email, :telefono, 
                       :direccion, :password, 'cliente', 'activo', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                      RETURNING id";
            
            $stmt = $this->conn->prepare($query);
            
            $stmt->bindParam(':dni', $datos['dni']);
            $stmt->bindParam(':nombres', $datos['nombres']);
            $stmt->bindParam(':apellidos', $datos['apellidos']);
            $stmt->bindParam(':email', $datos['email']);
            
            // Campos opcionales
            $telefono = $datos['telefono'] ?? null;
            $direccion = $datos['direccion'] ?? null;
            
            $stmt->bindParam(':telefono', $telefono);
            $stmt->bindParam(':direccion', $direccion);
            $stmt->bindParam(':password', $password_hash);
            
            if ($stmt->execute()) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                return $row['id'];
            }
            
            return false;
        } catch (PDOException $e) {
            error_log("Error en registrarNuevo: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Realiza el login de un cliente
     * @param string $identificador DNI o email
     * @param string $password
     * @return array|false Datos del usuario o false si falla
     */
    public function login($identificador, $password) {
        try {
            $query = "SELECT id, numero_documento, nombres, apellidos, email, 
                             telefonomovil, direccion, password, condicion
                      FROM " . $this->table . " 
                      WHERE (numero_documento = :identificador OR email = :identificador)
                      AND deleted_at IS NULL 
                      AND tipo_persona = 'cliente'
                      AND condicion = 'activo'";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':identificador', $identificador);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Verificar la contraseña
                if (password_verify($password, $usuario['password'])) {
                    // Construir nombre completo
                    $usuario['nombre'] = trim($usuario['nombres'] . ' ' . $usuario['apellidos']);
                    
                    // No devolver el hash de la contraseña
                    unset($usuario['password']);
                    
                    return $usuario;
                }
            }
            
            return false;
        } catch (PDOException $e) {
            error_log("Error en login: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtiene los datos de un cliente por ID
     * @param int $id
     * @return array|false
     */
    public function obtenerPorId($id) {
        try {
            $query = "SELECT id, numero_documento, nombres, apellidos, email, 
                             telefonomovil, telefonofijo, direccion, fecha_nacimiento,
                             condicion, tipo_persona
                      FROM " . $this->table . " 
                      WHERE id = :id 
                      AND deleted_at IS NULL 
                      AND tipo_persona = 'cliente'";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
                $cliente['nombre'] = trim($cliente['nombres'] . ' ' . $cliente['apellidos']);
                return $cliente;
            }
            
            return false;
        } catch (PDOException $e) {
            error_log("Error en obtenerPorId: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Actualiza los datos de un cliente
     * @param int $id
     * @param array $datos
     * @return bool
     */
    public function actualizar($id, $datos) {
        try {
            $query = "UPDATE " . $this->table . " 
                      SET nombres = :nombres,
                          apellidos = :apellidos,
                          email = :email,
                          telefonomovil = :telefono,
                          direccion = :direccion,
                          updated_at = CURRENT_TIMESTAMP
                      WHERE id = :id 
                      AND deleted_at IS NULL 
                      AND tipo_persona = 'cliente'";
            
            $stmt = $this->conn->prepare($query);
            
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':nombres', $datos['nombres']);
            $stmt->bindParam(':apellidos', $datos['apellidos']);
            $stmt->bindParam(':email', $datos['email']);
            $stmt->bindParam(':telefono', $datos['telefono']);
            $stmt->bindParam(':direccion', $datos['direccion']);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error en actualizar: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Cambia la contraseña de un cliente
     * @param int $id
     * @param string $password_actual
     * @param string $password_nueva
     * @return bool|string true si éxito, mensaje de error si falla
     */
    public function cambiarPassword($id, $password_actual, $password_nueva) {
        try {
            // Verificar contraseña actual
            $query = "SELECT password FROM " . $this->table . " 
                      WHERE id = :id 
                      AND deleted_at IS NULL 
                      AND tipo_persona = 'cliente'";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!password_verify($password_actual, $row['password'])) {
                    return 'La contraseña actual es incorrecta';
                }
                
                // Actualizar con la nueva contraseña
                $password_hash = password_hash($password_nueva, PASSWORD_BCRYPT);
                
                $query_update = "UPDATE " . $this->table . " 
                                SET password = :password,
                                    updated_at = CURRENT_TIMESTAMP
                                WHERE id = :id";
                
                $stmt_update = $this->conn->prepare($query_update);
                $stmt_update->bindParam(':password', $password_hash);
                $stmt_update->bindParam(':id', $id, PDO::PARAM_INT);
                
                if ($stmt_update->execute()) {
                    return true;
                }
            }
            
            return 'Error al cambiar la contraseña';
        } catch (PDOException $e) {
            error_log("Error en cambiarPassword: " . $e->getMessage());
            return 'Error al cambiar la contraseña';
        }
    }
    
    /**
     * Añade campo password a la tabla persona si no existe
     * Método de utilidad para actualizar la base de datos
     */
    public static function agregarCampoPassword($conn) {
        try {
            $query = "ALTER TABLE persona ADD COLUMN IF NOT EXISTS password VARCHAR(255)";
            $conn->exec($query);
            return true;
        } catch (PDOException $e) {
            error_log("Error al agregar campo password: " . $e->getMessage());
            return false;
        }
    }
}