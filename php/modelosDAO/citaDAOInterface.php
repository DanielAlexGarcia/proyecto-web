<?php
interface CitaDAOInterface {
    public function insertar(Cita $cita): bool;
    public function insertarRetornandoId(Cita $cita): ?int;
    public function actualizar(Cita $cita, int $id): bool;
    public function eliminar(int $id): bool;
    public function obtenerTodos(): array;
    public function obtenerPorId(int $id): ?Cita;
    public function existeChoque(int $doctorId, string $fechaHora, ?int $excluirId = null): bool;
    public function guardarSinChoque(Cita $cita, ?int $id = null): ?int;
}
?>