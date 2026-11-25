<?php

class conexionBaseDatos
{
    // Datos obtenidos en Supabase: Botón "Connect" -> "Session pooler"
    private $host = 'aws-0-us-east-1.pooler.supabase.com'; // Reemplaza con el host de tu región
    private $puerto = '5432';
    private $usuario = 'postgres.uyfnodirunbtuvhepzqt';          // Formato: postgres.[id-proyecto]
    private $base_de_datos = 'postgres';                   // En Supabase la BD principal es 'postgres'
    private $conexion;
    private $contrasena;

    public function __construct()
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
            http_response_code(500);
            exit('No se pudo conectar a la base de datos.');
        }
    }

    // Método para obtener la conexión activa
    public function getConexion()
    {
        return $this->conexion;
    }

    function responder($codigo, array $datos)
    {
        http_response_code($codigo);
        echo json_encode($datos, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** Lee y decodifica el cuerpo JSON de la petición. */
    function leerJSON()
    {
        $datos = json_decode(file_get_contents('php://input'), true);
        return is_array($datos) ? $datos : null;
    }
}
?>