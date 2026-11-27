<?php
require_once __DIR__ . '/../modelos/paciente.php';
require_once __DIR__ . '/PacienteDAOInterface.php';

class PacienteDAOMySQL implements PacienteDAOInterface
{
    private PDO $conexion;

    public function __construct(PDO $conexion)
    {
        $this->conexion = $conexion;
    }

    public function insertar(paciente $paciente): bool
    {
        $sql = "INSERT INTO pacientes (id_persona, num_seguro, grupo_sanguineo, alergias)
                VALUES (:idPersona, :numSeg, :grupSangineo, :alergias)";
        $stmt = $this->conexion->prepare($sql);

        return $stmt->execute([
            ':idPersona'    => $paciente->getIDPersona(),
            ':numSeg'       => $paciente->getNumSeg(),
            ':grupSangineo' => $paciente->getGrupSangineo(),
            ':alergias'     => $paciente->getAlergias()
        ]);
    }

    // Reutiliza la persona si ya existe; registra paciente + usuario (SHA1 en SQL). Revierte si algo falla.
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
    ): bool {
        try {
            $this->conexion->beginTransaction();

            $stmtBuscar = $this->conexion->prepare(
                "SELECT id FROM personas
                 WHERE nombres = :nombres AND primer_apellido = :primer_apellido
                   AND segundo_apellido <=> :segundo_apellido
                   AND fecha_nacimiento = :fecha_nacimiento
                 LIMIT 1"
            );
            $stmtBuscar->execute([
                ':nombres'          => trim($nombres),
                ':primer_apellido'  => trim($primerApellido),
                ':segundo_apellido' => $segundoApellido !== null ? trim($segundoApellido) : null,
                ':fecha_nacimiento' => $fechaNacimiento
            ]);
            $persona = $stmtBuscar->fetch(PDO::FETCH_ASSOC);

            if (!$persona) {
                $stmtPersona = $this->conexion->prepare(
                    "INSERT INTO personas (nombres, primer_apellido, segundo_apellido, fecha_nacimiento, genero, direccion)
                     VALUES (:nombres, :primer_apellido, :segundo_apellido, :fecha_nacimiento, :genero, :direccion)"
                );
                $stmtPersona->execute([
                    ':nombres'          => trim($nombres),
                    ':primer_apellido'  => trim($primerApellido),
                    ':segundo_apellido' => $segundoApellido !== null ? trim($segundoApellido) : null,
                    ':fecha_nacimiento' => $fechaNacimiento,
                    ':genero'           => $genero,
                    ':direccion'        => $direccion
                ]);
                $idPersona = (int) $this->conexion->lastInsertId();
            } else {
                $idPersona = (int) $persona['id'];
            }

            $this->conexion->prepare(
                "INSERT INTO pacientes (id_persona, num_seguro, grupo_sanguineo, alergias)
                 VALUES (:id_persona, :num_seguro, :grupo_sanguineo, :alergias)"
            )->execute([
                ':id_persona'      => $idPersona,
                ':num_seguro'      => $paciente->getNumSeg(),
                ':grupo_sanguineo' => $paciente->getGrupSangineo(),
                ':alergias'        => $paciente->getAlergias()
            ]);

            $this->conexion->prepare(
                "INSERT INTO usuarios (user, password, idDocPac) VALUES (SHA1(:user), SHA1(:password), :idDocPac)"
            )->execute([
                ':user'     => trim($user),
                ':password' => $password,
                ':idDocPac' => $idPersona
            ]);

            $this->conexion->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }
            throw $e;
        }
    }

    // El procedimiento aplica SHA1 a user y password.
    public function crearConPersonaExistente(paciente $paciente, string $user, string $password): bool
    {
        $stmt = $this->conexion->prepare(
            "CALL sp_crear_paciente_existente(:idPersona, :numSeg, :grupSangineo, :alergias, :user, :password)"
        );
        $resultado = $stmt->execute([
            ':idPersona'    => $paciente->getIDPersona(),
            ':numSeg'       => $paciente->getNumSeg(),
            ':grupSangineo' => $paciente->getGrupSangineo(),
            ':alergias'     => $paciente->getAlergias(),
            ':user'         => trim($user),
            ':password'     => $password
        ]);
        $stmt->closeCursor();
        return $resultado;
    }

    public function eliminar(int $idPersona): bool
    {
        $stmt = $this->conexion->prepare("DELETE FROM pacientes WHERE id_persona = :idPersona");
        return $stmt->execute([':idPersona' => $idPersona]);
    }

    public function actualizar(paciente $paciente): bool
    {
        $stmt = $this->conexion->prepare(
            "UPDATE pacientes SET num_seguro = :numSeg, grupo_sanguineo = :grupSangineo, alergias = :alergias
             WHERE id_persona = :idPersona"
        );
        return $stmt->execute([
            ':numSeg'       => $paciente->getNumSeg(),
            ':grupSangineo' => $paciente->getGrupSangineo(),
            ':alergias'     => $paciente->getAlergias(),
            ':idPersona'    => $paciente->getIDPersona()
        ]);
    }

    public function obtenerTodos(): array
    {
        $sql = "SELECT p.id, p.nombres, p.primer_apellido, p.segundo_apellido,
                       p.fecha_nacimiento, p.genero, p.direccion,
                       pa.num_seguro, pa.grupo_sanguineo, pa.alergias
                FROM pacientes pa
                INNER JOIN personas p ON pa.id_persona = p.id";
        return $this->conexion->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorId(int $idPersona): ?paciente
    {
        $stmt = $this->conexion->prepare("SELECT * FROM pacientes WHERE id_persona = :idPersona");
        $stmt->execute([':idPersona' => $idPersona]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$fila) {
            return null;
        }
        return new paciente($fila['id_persona'], $fila['num_seguro'], $fila['grupo_sanguineo'], $fila['alergias']);
    }
}
