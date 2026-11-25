<?php
require_once __DIR__ . '/../modelos/cita.php';

class CitaDAO
{
    private PDO $conexion;

    public function __construct(PDO $conexion)
    {
        $this->conexion = $conexion;
    }

    // ALTA (Insertar registro)
    public function insertar(Cita $cita): bool
    {
        $sql = "INSERT INTO citas (fecha_hora, motivo, estado, paciente_id, doctor_id) 
                VALUES (:fechaHora, :motivo, :estado, :pacienteID, :doctorID)";

        $stmt = $this->conexion->prepare($sql);

        return $stmt->execute([
            ':fechaHora'  => $cita->getFechaHora(),
            ':motivo'     => $cita->getMotivo(),
            ':estado'     => $cita->getEstado(),
            ':pacienteID' => $cita->getPacienteID(),
            ':doctorID'   => $cita->getDoctorID()
        ]);
    }

    // BAJA (Eliminar por ID de cita)
    public function eliminar(int $id): bool
    {
        $sql = "DELETE FROM citas WHERE id = :id";
        $stmt = $this->conexion->prepare($sql);

        return $stmt->execute([':id' => $id]);
    }

    // CAMBIO (Actualizar cita por ID)
    public function actualizar(Cita $cita, int $id): bool
    {
        $sql = "UPDATE citas SET 
                    fecha_hora = :fechaHora, 
                    motivo = :motivo, 
                    estado = :estado, 
                    paciente_id = :pacienteID, 
                    doctor_id = :doctorID 
                WHERE id = :id";

        $stmt = $this->conexion->prepare($sql);

        return $stmt->execute([
            ':fechaHora'  => $cita->getFechaHora(),
            ':motivo'     => $cita->getMotivo(),
            ':estado'     => $cita->getEstado(),
            ':pacienteID' => $cita->getPacienteID(),
            ':doctorID'   => $cita->getDoctorID(),
            ':id'         => $id
        ]);
    }

    // CONSULTA GENERAL (Incluye nombres de paciente y doctor listos para JSON)
    public function obtenerTodos(): array
    {
        $sql = "SELECT c.id, c.fecha_hora, c.motivo, c.estado,
                       c.paciente_id,
                       CONCAT(pp.nombres, ' ', pp.primer_apellido, ' ', IFNULL(pp.segundo_apellido, '')) AS nombre_paciente,
                       c.doctor_id,
                       CONCAT(pd.nombres, ' ', pd.primer_apellido, ' ', IFNULL(pd.segundo_apellido, '')) AS nombre_doctor
                FROM citas c
                INNER JOIN personas pp ON c.paciente_id = pp.id
                INNER JOIN personas pd ON c.doctor_id = pd.id
                ORDER BY c.fecha_hora DESC";

        $stmt = $this->conexion->query($sql);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // CONSULTA INDIVIDUAL (Devuelve una instancia del modelo Cita)
    public function obtenerPorId(int $id): ?Cita
    {
        $sql = "SELECT * FROM citas WHERE id = :id";
        $stmt = $this->conexion->prepare($sql);
        $stmt->execute([':id' => $id]);

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fila) {
            return null;
        }

        return new Cita(
            $fila['fecha_hora'],
            $fila['motivo'],
            $fila['estado'],
            $fila['paciente_id'],
            $fila['doctor_id']
        );
    }
    public function existeChoque(int $doctorId, string $fechaHora, ?int $excluirId = null): bool
{
    $stmt = $this->conexion->prepare(
        "SELECT 1 FROM citas
         WHERE doctor_id = :d AND fecha_hora = :f
           AND LOWER(estado) <> 'cancelada' AND id <> :x
         LIMIT 1"
    );
    $stmt->execute([':d' => $doctorId, ':f' => $fechaHora, ':x' => $excluirId ?? 0]);
    return (bool) $stmt->fetchColumn();
}
}
?>