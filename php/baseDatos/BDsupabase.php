<?php

class conexionBaseDatos
{
    // Datos obtenidos en Supabase: Botón "Connect" -> "Session pooler"
    private $host = 'aws-0-us-east-1.pooler.supabase.com'; // Reemplaza con el host de tu región
    private $puerto = '5432';
    private $usuario = 'postgres.uyfnodirunbtuvhepzqt';          // Formato: postgres.[id-proyecto]
    private $contrasena = 'D@ni1el2030';
    private $base_de_datos = 'postgres';                   // En Supabase la BD principal es 'postgres'
    private $conexion;

    public function __construct()
    {
        try {
            $dsn = "pgsql:host={$this->host};port={$this->puerto};dbname={$this->base_de_datos};sslmode=require";

            $this->conexion = new PDO($dsn, $this->usuario, $this->contrasena, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            die("Error de conexión: " . $e->getMessage());
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