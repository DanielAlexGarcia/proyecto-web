<?php

interface ConfUserInterface{
    public function actualizarPassword(string $anterior, string $actualizado, int $pin, string $user): bool;
    public function actualizarPin(string $anterior, string $actualizado, int $password, string $user):bool;
    public function verificarEntidadSinPin(string $password, string $user):bool;
    public function verificarEntidadSinPassword(string $pin, string $user):bool;
    public function recuperarPin(string $password, string $user, int $newPin):bool;
    public function recuperarPassword(string $pin, string $user, string $newPassword):bool;
    

}

?>