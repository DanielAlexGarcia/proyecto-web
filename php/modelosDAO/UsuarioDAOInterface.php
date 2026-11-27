<?php
interface UsuarioDAOInterface
{
    /** Devuelve ['id' => int, 'id_persona' => int] si user/password son correctos; null si no. */
    public function buscarPorCredenciales(string $user, string $password): ?array;

    /** Crea el usuario (user y password se guardan con sha1 calculado en PHP). */
    public function crear(string $user, string $password, int $idPersona): bool;
}
