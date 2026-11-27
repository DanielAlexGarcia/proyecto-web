<?php
require_once __DIR__ . '/ConexionInterface.php';
require_once __DIR__ . '/RespuestaJson.php';

class ConexionSupabase implements ConexionInterface
{
    use RespuestaJson;

    private static ?self $instancia = null;

    // Supabase: botón "Connect" -> "Session pooler"
    private string $host = 'aws-0-us-east-1.pooler.supabase.com';
    private string $puerto = '5432';
    private string $usuario = 'postgres.uyfnodirunbtuvhepzqt';
    private string $base_de_datos = 'postgres';
    private PDO $conexion;

    private function __construct()
    {
        $pass = getenv('DB_PASS_SUPABASE');
        if (!$pass && is_file(__DIR__ . '/../auth/secretos.php')) {
            $cfg = require __DIR__ . '/../auth/secretos.php';
            $pass = is_array($cfg) ? ($cfg['DB_PASS_SUPABASE'] ?? '') : '';
        }

        try {
            $dsn = "pgsql:host={$this->host};port={$this->puerto};dbname={$this->base_de_datos};sslmode=require";
            $this->conexion = new PDO($dsn, $this->usuario, (string) $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            error_log('[bd-supabase] ' . $e->getMessage());
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
