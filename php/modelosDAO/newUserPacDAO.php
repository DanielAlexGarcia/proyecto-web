<?php
// DAO compuesto (persona + paciente). Único para ambos motores: los DAOs
// concretos (MySQL / Supabase) los entrega BaseDatosFactory.
require_once __DIR__ . '/../baseDatos/BaseDatosFactory.php';
require_once __DIR__ . '/../modelos/newUsersPac.php';

class NewUsersPacDAO
{
    private PDO $conexion;
    private PersonaDAOInterface $personaDAO;
    private PacienteDAOInterface $pacienteDAO;

    public function __construct(PDO $conexion)
    {
        $this->conexion    = $conexion;
        $this->personaDAO  = BaseDatosFactory::persona($conexion);
        $this->pacienteDAO = BaseDatosFactory::paciente($conexion);
    }

    public function insertar(NewUsersPac $nuevoPaciente): bool
    {
        return $this->insertarRetornandoId($nuevoPaciente) !== null;
    }

    public function insertarRetornandoId(NewUsersPac $nuevoPaciente): ?int
    {
        $persona  = $nuevoPaciente->getPersona();
        $paciente = $nuevoPaciente->getPaciente();
        if (!$persona || !$paciente) {
            return null;
        }

        try {
            $this->conexion->beginTransaction();

            $idPersona = $this->personaDAO->insertarRetornandoId($persona);
            if ($idPersona === null) {
                throw new Exception('No se pudo insertar el registro en personas.');
            }

            $paciente->setIDPersona($idPersona);
            if (!$this->pacienteDAO->insertar($paciente)) {
                throw new Exception('No se pudo insertar el registro en pacientes.');
            }

            $this->conexion->commit();
            return $idPersona;
        } catch (Throwable $e) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }
            throw $e;
        }
    }

    public function eliminar(int $idPersona): bool
    {
        try {
            $this->conexion->beginTransaction();
            $this->pacienteDAO->eliminar($idPersona);
            $exito = $this->personaDAO->eliminar($idPersona);
            $this->conexion->commit();
            return $exito;
        } catch (Throwable $e) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }
            throw $e;
        }
    }

    public function actualizar(NewUsersPac $datosPaciente): bool
    {
        $persona  = $datosPaciente->getPersona();
        $paciente = $datosPaciente->getPaciente();
        if (!$persona || !$paciente || !$paciente->getIDPersona()) {
            return false;
        }
        $idPersona = (int) $paciente->getIDPersona();

        try {
            $this->conexion->beginTransaction();
            $okPersona  = $this->personaDAO->actualizar($persona, $idPersona);
            $okPaciente = $this->pacienteDAO->actualizar($paciente);
            $this->conexion->commit();
            return $okPersona && $okPaciente;
        } catch (Throwable $e) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }
            throw $e;
        }
    }

    public function obtenerTodos(): array
    {
        return $this->pacienteDAO->obtenerTodos();
    }

    public function obtenerPorId(int $idPersona): ?NewUsersPac
    {
        $persona  = $this->personaDAO->obtenerPorId($idPersona);
        $paciente = $this->pacienteDAO->obtenerPorId($idPersona);
        if (!$persona || !$paciente) {
            return null;
        }
        return new NewUsersPac($persona, $paciente);
    }
}
