<?php
interface PacienteDAOInterface
{
    public function insertar(paciente $paciente): bool;
    public function crearConTransaccion(
        string $nombres,
        string $primerApellido,
        ?string $segundoApellido,
        string $fechaNacimiento,
        ?string $genero,
        ?string $direccion,
        paciente $paciente,
        string $user,
        string $password
    ): bool;
    public function crearConPersonaExistente(paciente $paciente, string $user, string $password): bool;
    public function actualizar(paciente $paciente): bool;
    public function eliminar(int $idPersona): bool;
    public function obtenerTodos(): array;
    public function obtenerPorId(int $idPersona): ?paciente;
}
