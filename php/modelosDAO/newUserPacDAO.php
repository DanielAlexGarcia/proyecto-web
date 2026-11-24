<?php
require_once __DIR__ . '/personaDAO.php';
require_once __DIR__ . '/pacienteDAO.php';
// Ajusta la subcarpeta si guardaste newUsersPac.php dentro de un subdirectorio de modelos/
require_once __DIR__ . '/../modelos/newUsersPac.php';

class NewUsersPacDAO
{
    private PDO $conexion;
    private PersonaDAO $personaDAO;
    private PacienteDAO $pacienteDAO;

    public function __construct(PDO $conexion)
    {
        $this->conexion    = $conexion;
        $this->personaDAO  = new PersonaDAO($conexion);
        $this->pacienteDAO = new PacienteDAO($conexion);
    }

    // ALTA COMPUESTA (Inserta primero en personas, obtiene su ID y luego inserta en pacientes)
    public function insertar(NewUsersPac $nuevoPaciente): bool
    {
        return $this->insertarRetornandoId($nuevoPaciente) !== null;
    }

    // ALTA RETORNANDO ID (Ejecuta ambas inserciones en transacción y devuelve el id_persona)
    public function insertarRetornandoId(NewUsersPac $nuevoPaciente): ?int
    {
        $persona  = $nuevoPaciente->getPersona();
        $paciente = $nuevoPaciente->getPaciente();

        if (!$persona || !$paciente) {
            return null;
        }

        try {
            $this->conexion->beginTransaction();

            // 1. Insertar Persona y recuperar su ID autogenerado
            $idPersona = $this->personaDAO->insertarRetornandoId($persona);
            if ($idPersona === null) {
                throw new Exception('No se pudo insertar el registro en personas.');
            }

            // 2. Asignar el ID generado al objeto Paciente e insertarlo
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

    // BAJA COMPUESTA (Elimina tanto el registro clínico en pacientes como el registro base en personas)
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

    // CAMBIO COMPUESTO (Actualiza datos personales y clínicos en una sola operación)
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

    // CONSULTA GENERAL (Reutiliza el INNER JOIN de PacienteDAO listo para JSON)
    public function obtenerTodos(): array
    {
        return $this->pacienteDAO->obtenerTodos();
    }

    // CONSULTA INDIVIDUAL (Devuelve una instancia de NewUsersPac con ambos modelos cargados)
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
?>