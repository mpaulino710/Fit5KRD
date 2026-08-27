<?php
// Conexión a la Base de Datos
class Database {
    private static $instance = null;
    private $connection;

    private function __construct() {
        try {
            $this->connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

            if ($this->connection->connect_error) {
                throw new Exception("Error de conexión: " . $this->connection->connect_error);
            }

            $this->connection->set_charset("utf8mb4");
            $this->connection->query("SET time_zone = '-04:00'");
        } catch (Exception $e) {
            die("Error de base de datos: " . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }

    public function close() {
        if ($this->connection) {
            $this->connection->close();
        }
    }
}

// Función para obtener conexión
function getDB() {
    $db = Database::getInstance();
    return $db->getConnection();
}