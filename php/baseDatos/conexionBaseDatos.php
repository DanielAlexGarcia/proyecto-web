<?php

class conexionBaseDatos
{
    private $host = 'localhost';
    private $usuario = 'root';
    private $contrasena = '';
    private $base_de_datos = 'clinica';
    private $conexion;

    public function __construct()
    {
        try {
            $this->conexion = new PDO(
                "mysql:host={$this->host};dbname={$this->base_de_datos};charset=utf8mb4",
                $this->usuario,
                $this->contrasena
            );

            // Activar excepciones en PDO para manejo de errores
            $this->conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

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