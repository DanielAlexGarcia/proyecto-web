<?php

interface EmpleadoDAOInterface{
    public function insertar(empleado $empleado):bool;
    public function crearConTransaccion(tring $nombres,
        string $primerApellido,
        ?string $segundoApellido,
        string $fechaNacimiento,
        ?string $genero,
        ?string $direccion,
        empleado $empleado,
        string $user,
        string $password):bool;
    public function crearConPersonaExistente(empleado $empleado, string $user, string $password):bool;
    public function actualizar(empleado $empleado, int $id):bool;
    public function eliminar(int $id):bool;
    public function obtenerTodos(): array;
    public function obtenerPorId(int $id): empleado;
}

?>