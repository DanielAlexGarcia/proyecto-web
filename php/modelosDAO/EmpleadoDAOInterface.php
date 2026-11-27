<?php
interface EmpleadoDAOInterface
{
    public function insertar(empleado $empleado): bool;
    public function crearConTransaccion(
        string $nombres,
        string $primerApellido,
        ?string $segundoApellido,
        string $fechaNacimiento,
        ?string $genero,
        ?string $direccion,
        empleado $empleado,
        string $user,
        string $password
    ): bool;
    public function crearConPersonaExistente(empleado $empleado, string $user, string $password): bool;
    public function actualizar(empleado $empleado): bool;
    public function eliminar(int $idPersona): bool;
    public function obtenerTodos(): array;
    public function obtenerPorId(int $idPersona): ?empleado;
}
