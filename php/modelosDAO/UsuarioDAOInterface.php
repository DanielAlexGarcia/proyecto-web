<?php

interface UsuarioDAOInterface {
    public function buscarPorCredenciales(string $user, string $password): ?array; // ['id', 'id_persona']
    public function crear(string $user, string $password, int $idPersona): bool;
}

?>