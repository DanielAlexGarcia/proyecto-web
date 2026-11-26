<?php

class ConexionMysql implements ConexionInterface
{
    private static ?self $instancia = null;

    private $host = 'localhost';
    private $usuario = 'root';
    private $contrasena = '';
    private $base_de_datos = 'clinica';
    private $conexion;

    private function __construct()
    {
        try {
            $this->conexion = new PDO(
                "mysql:host={$this->host};dbname={$this->base_de_datos};charset=utf8mb4",
                $this->usuario,
                $this->contrasena
            );
            $this->conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            throw new RuntimeException("Error de conexión: " . $e->getMessage(), 0, $e);
        }
    }

    public static function getInstancia(): self
    {
        if (self::$instancia === null) {
            self::$instancia = new self();
        }
        return self::$instancia;
    }

    private function __clone() {}

    public function __wakeup()
    {
        throw new Exception('No se puede deserializar un Singleton.');
    }

    // Método para obtener la conexión activa
    public function getConexion()
    {
        return $this->conexion;
    }
}