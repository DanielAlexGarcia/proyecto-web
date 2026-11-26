<?php
interface ConexionInterface { public function getConexion(): PDO; 


public function responder($codigo, array $datos)
    {
        http_response_code($codigo);
        echo json_encode($datos, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** Lee y decodifica el cuerpo JSON de la petición. */
    public function leerJSON()
    {
        $datos = json_decode(file_get_contents('php://input'), true);
        return is_array($datos) ? $datos : null;
    }
}


?>