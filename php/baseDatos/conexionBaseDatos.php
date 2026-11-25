<?php

class conexionBaseDatos
{
    private static ?conexionBaseDatos $instancia = null;

    private $host = 'localhost';
    private $usuario = 'root';
    private $contrasena = '';
    private $base_de_datos = 'clinica';
    private $conexion;

    private function __construct()      // antes: public
    {
        try {
            $this->conexion = new PDO(
                "mysql:host={$this->host};dbname={$this->base_de_datos};charset=utf8mb4",
                $this->usuario,
                $this->contrasena
            );
            $this->conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die("Error de conexión: " . $e->getMessage());
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
    public function __wakeup() { throw new Exception('No se puede deserializar un Singleton.'); }


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