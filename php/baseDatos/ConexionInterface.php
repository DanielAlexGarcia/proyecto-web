<?php
/**
 * Contrato común de las conexiones (MySQL local / Supabase).
 * Solo firmas: la implementación de responder() y leerJSON() vive en el trait RespuestaJson.
 */
interface ConexionInterface
{
    public function getConexion(): PDO;
    public function responder(int $codigo, array $datos): never;
    public function leerJSON(): ?array;
}
