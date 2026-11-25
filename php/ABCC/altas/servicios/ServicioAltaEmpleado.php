<?php
require_once __DIR__ . '/../../../modelos/empleado.php';
require_once __DIR__ . '/../../../modelosDAO/empleadoDAO.php';

class ServicioAltaEmpleado
{
    private EmpleadoDAO $dao;

    public function __construct(PDO $pdo)
    {
        $this->dao = new EmpleadoDAO($pdo);
    }

    public function crearSimple(array $datos): array
    {
        $empleado = $this->construirModelo($datos, (int) $datos['id_persona']);
        $this->dao->insertar($empleado);

        return ['ok' => true, 'mensaje' => 'Empleado registrado.'];
    }

    public function crearConProcedimiento(array $datos): array
    {
        $empleado = $this->construirModelo($datos, (int) $datos['id_persona']);
        $this->dao->crearConPersonaExistente($empleado, $datos['user'], $datos['password']);

        return ['ok' => true, 'mensaje' => 'Empleado y usuario creados con el procedimiento almacenado.'];
    }

    public function crearConTransaccion(array $datos): array
    {
        $empleado = $this->construirModelo($datos, 0);
        $this->dao->crearConTransaccion(
            $datos['nombres'],
            $datos['primer_apellido'],
            $datos['segundo_apellido'] ?? null,
            $datos['fecha_nacimiento'],
            $datos['genero'] ?? null,
            $datos['direccion'] ?? null,
            $empleado,
            $datos['user'],
            $datos['password']
        );

        return ['ok' => true, 'mensaje' => 'Empleado y usuario creados mediante transacción.'];
    }

    private function construirModelo(array $datos, int $idPersona): empleado
    {
        return new empleado(
            $idPersona,
            $datos['cargo'],
            $datos['especializacion'] ?? null,
            $datos['salario']
        );
    }
}