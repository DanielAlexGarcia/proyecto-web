<?php

/** Se lanza cuando se superan los intentos fallidos permitidos. */
class ConfUserBloqueadoException extends RuntimeException
{
    public int $minutos;

    public function __construct(int $minutos)
    {
        parent::__construct('Demasiados intentos fallidos.');
        $this->minutos = max(1, $minutos);
    }
}

/**
 * Cambios (con sesión, se identifica por id_persona del JWT) y
 * recuperación (sin sesión, se identifica por usuario/correo).
 * El NIP es string (no int) para no perder ceros a la izquierda: "0042".
 */
interface ConfUserInterface
{
    public function tienePin(int $idPersona): bool;

    /** Requiere NIP y contraseña actual correctos. */
    public function actualizarPassword(int $idPersona, string $pin, string $anterior, string $nueva): bool;

    /** Requiere contraseña y NIP actual. Si aún no hay NIP, $anterior se ignora (alta del primer NIP). */
    public function actualizarPin(int $idPersona, string $password, string $anterior, string $nuevo): bool;

    /** Valida usuario + contraseña (camino para restablecer el NIP). */
    public function verificarEntidadSinPin(string $user, string $password): bool;

    /** Valida usuario + NIP (camino para restablecer la contraseña). */
    public function verificarEntidadSinPassword(string $user, string $pin): bool;

    public function recuperarPin(string $user, string $password, string $nuevoPin): bool;

    public function recuperarPassword(string $user, string $pin, string $nuevaPassword): bool;
}
