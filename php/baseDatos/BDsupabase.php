<?php

class ConexionSupabase implements ConexionInterface
{
    private static ?self $instancia = null;

    // Datos obtenidos en Supabase: Botón "Connect" -> "Session pooler"
    private $host = 'aws-0-us-east-1.pooler.supabase.com'; // Reemplaza con el host de tu región
    private $puerto = '5432';
    private $usuario = 'postgres.uyfnodirunbtuvhepzqt';          // Formato: postgres.[id-proyecto]
    private $base_de_datos = 'postgres';                   // En Supabase la BD principal es 'postgres'
    private $conexion;
    private $contrasena;

    private function __construct()
    {
        $pass = getenv('DB_PASS_SUPABASE');
        if (!$pass) {
            $cfg = require __DIR__ . '/../auth/secretos.php';
            $pass = $cfg['DB_PASS_SUPABASE'] ?? '';
        }
        $this->contrasena = $pass;

        try {
            $dsn = "pgsql:host={$this->host};port={$this->puerto};dbname={$this->base_de_datos};sslmode=require";

            $this->conexion = new PDO($dsn, $this->usuario, $this->contrasena, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            error_log('[bd] ' . $e->getMessage());
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

    // Método para obtener la conexión activa
    public function getConexion()
    {
        return $this->conexion;
    }
}