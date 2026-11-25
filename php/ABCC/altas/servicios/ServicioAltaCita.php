<?php
require_once __DIR__ . '/../../../modelos/cita.php';
require_once __DIR__ . '/../../../modelosDAO/citaDAO.php';

class ServicioAltaCita
{
    private CitaDAO $dao;

    public function __construct(private PDO $pdo)
    {
        $this->dao = new CitaDAO($pdo);
    }

    public function crear(array $datos): array
    {
        $doctorId = (int) $datos['doctor_id'];

        if ($this->dao->existeChoque($doctorId, $datos['fecha_hora'])) {
            throw new DomainException('Ese médico ya tiene una cita en ese horario.', 409);
        }

        $cita = new Cita(
            $datos['fecha_hora'],
            $datos['motivo'],
            $datos['estado'] ?? 'Pendiente',
            (int) $datos['paciente_id'],
            $doctorId
        );

        $this->dao->insertar($cita);

        return [
            'ok'      => true,
            'mensaje' => 'Cita registrada.',
            'idCita'  => (int) $this->pdo->lastInsertId()
        ];
    }
}