<?php

require_once __DIR__ . '/../../../modelos/paciente.php';
require_once __DIR__ . '/../../../modelosDAO/pacienteDAO.php';

class ServicioAltaPaciente
{
    private PacienteDAO $dao;

    public function __construct(PDO $pdo)
    {
        $this->dao = new PacienteDAO($pdo);
    }

    public function crearSimple(array $datos): array
    {
        $paciente = $this->construirModelo($datos, (int) $datos['id_persona']);
        $this->dao->insertar($paciente);

        return ['ok' => true, 'mensaje' => 'Paciente registrado.'];
    }

    public function crearConProcedimiento(array $datos): array
    {
        $paciente = $this->construirModelo($datos, (int) $datos['id_persona']);
        $this->dao->crearConPersonaExistente($paciente, $datos['user'], $datos['password']);

        return ['ok' => true, 'mensaje' => 'Paciente y usuario creados con el procedimiento almacenado.'];
    }

    public function crearConTransaccion(array $datos): array
    {
        $paciente = $this->construirModelo($datos, 0);
        $this->dao->crearConTransaccion(
            $datos['nombres'],
            $datos['primer_apellido'],
            $datos['segundo_apellido'] ?? null,
            $datos['fecha_nacimiento'],
            $datos['genero'] ?? null,
            $datos['direccion'] ?? null,
            $paciente,
            $datos['user'],
            $datos['password']
        );

        return ['ok' => true, 'mensaje' => 'Paciente y usuario creados mediante transacción.'];
    }

    private function construirModelo(array $datos, int $idPersona): paciente
    {
        return new paciente(
            $idPersona,
            $datos['num_seguro'],
            $datos['grupo_sanguineo'] ?? null,
            $datos['alergias'] ?? null
        );
    }
}