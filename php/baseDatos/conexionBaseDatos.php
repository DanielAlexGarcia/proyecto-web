<?php
require_once __DIR__ . '/ConexionInterface.php';
require_once __DIR__ . '/RespuestaJson.php';

class ConexionMySQL implements ConexionInterface
{
    use RespuestaJson;

    private static ?self $instancia = null;

    private string $host = 'localhost';
    private string $usuario = 'root';
    private string $contrasena = '';
    private string $base_de_datos = 'clinica';
    private PDO $conexion;

    private function __construct()
    {
        try {
            $this->conexion = new PDO(
                "mysql:host={$this->host};dbname={$this->base_de_datos};charset=utf8mb4",
                $this->usuario,
                $this->contrasena,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
        } catch (PDOException $e) {
            error_log('[bd-mysql] ' . $e->getMessage());
            throw new RuntimeException('No se pudo conectar a la base de datos.', 0, $e);
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

    public function getConexion(): PDO
    {
        return $this->conexion;
    }
}
