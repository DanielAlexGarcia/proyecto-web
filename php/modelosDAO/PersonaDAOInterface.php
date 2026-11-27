<?php
interface PersonaDAOInterface
{
    public function insertar(persona $persona): bool;
    public function insertarRetornandoId(persona $persona): ?int;
    public function actualizar(persona $persona, int $id): bool;
    public function eliminar(int $id): bool;
    public function obtenerTodos(): array;
    public function obtenerPorId(int $id): ?persona;
}
