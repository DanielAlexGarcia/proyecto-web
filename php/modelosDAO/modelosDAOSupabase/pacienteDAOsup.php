<?php
require_once __DIR__ . '/../../modelos/paciente.php';

class PacienteDAO
{
    private PDO $conexion;

    public function __construct(PDO $conexion)
    {
        $this->conexion = $conexion;
    }

    // ALTA (Insertar registro)
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

    // ALTA con TRANSACCIÓN COMPLETA (Adaptada a PostgreSQL / Supabase)
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

            // 1. Verificar si la persona ya está registrada (IS NOT DISTINCT FROM reemplaza a <=>)
            $sqlBuscar = "SELECT id FROM personas 
                          WHERE nombres = :nombres 
                            AND primer_apellido = :primer_apellido 
                            AND segundo_apellido IS NOT DISTINCT FROM :segundo_apellido 
                            AND fecha_nacimiento = :fecha_nacimiento 
                          LIMIT 1";

            $stmtBuscar = $this->conexion->prepare($sqlBuscar);
            $stmtBuscar->execute([
                ':nombres'          => trim($nombres),
                ':primer_apellido'  => trim($primerApellido),
                ':segundo_apellido' => $segundoApellido !== null ? trim($segundoApellido) : null,
                ':fecha_nacimiento' => $fechaNacimiento
            ]);

            $persona = $stmtBuscar->fetch(PDO::FETCH_ASSOC);

            // 2. Si no existe, dar de alta en personas y recuperar su ID con RETURNING id
            if (!$persona) {
                $sqlPersona = "INSERT INTO personas (
                                   nombres, primer_apellido, segundo_apellido, 
                                   fecha_nacimiento, genero, direccion
                               ) VALUES (
                                   :nombres, :primer_apellido, :segundo_apellido, 
                                   :fecha_nacimiento, :genero, :direccion
                               ) RETURNING id";

                $stmtPersona = $this->conexion->prepare($sqlPersona);
                $stmtPersona->execute([
                    ':nombres'          => trim($nombres),
                    ':primer_apellido'  => trim($primerApellido),
                    ':segundo_apellido' => $segundoApellido !== null ? trim($segundoApellido) : null,
                    ':fecha_nacimiento' => $fechaNacimiento,
                    ':genero'           => $genero,
                    ':direccion'        => $direccion
                ]);

                $idPersona = (int) $stmtPersona->fetchColumn();
            } else {
                $idPersona = (int) $persona['id'];
            }

            // 3. Registrar en pacientes usando el id_persona obtenido
            $sqlPaciente = "INSERT INTO pacientes (id_persona, num_seguro, grupo_sanguineo, alergias) 
                            VALUES (:id_persona, :num_seguro, :grupo_sanguineo, :alergias)";

            $stmtPaciente = $this->conexion->prepare($sqlPaciente);
            $stmtPaciente->execute([
                ':id_persona'      => $idPersona,
                ':num_seguro'      => $paciente->getNumSeg(),
                ':grupo_sanguineo' => $paciente->getGrupSangineo(),
                ':alergias'        => $paciente->getAlergias()
            ]);

            // 4. Registrar credenciales en usuarios ("user" entre comillas y SHA1 calculado en PHP)
            $sqlUsuario = 'INSERT INTO usuarios ("user", password, iddocpac) 
                           VALUES (:user, :password, :idDocPac)';

            $stmtUsuario = $this->conexion->prepare($sqlUsuario);
            $stmtUsuario->execute([
                ':user'     => sha1(trim($user)),
                ':password' => sha1($password),
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

    // ALTA con PROCEDIMIENTO ALMACENADO
    // Nota: Requiere que sp_crear_paciente_existente esté creado en Supabase en sintaxis PL/pgSQL
    public function crearConPersonaExistente(paciente $paciente, string $user, string $password): bool
    {
        $sql = "CALL sp_crear_paciente_existente(
                    :idPersona, :numSeg, :grupSangineo, :alergias, :user, :password
                )";

        $stmt = $this->conexion->prepare($sql);

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

    // BAJA (Eliminar por ID de persona)
    public function eliminar(int $idPersona): bool
    {
        $sql = "DELETE FROM pacientes WHERE id_persona = :idPersona";
        $stmt = $this->conexion->prepare($sql);

        return $stmt->execute([':idPersona' => $idPersona]);
    }

    // CAMBIO (Actualizar datos clínicos del paciente)
    public function actualizar(paciente $paciente): bool
    {
        $sql = "UPDATE pacientes SET 
                    num_seguro = :numSeg, 
                    grupo_sanguineo = :grupSangineo, 
                    alergias = :alergias 
                WHERE id_persona = :idPersona";

        $stmt = $this->conexion->prepare($sql);

        return $stmt->execute([
            ':numSeg'       => $paciente->getNumSeg(),
            ':grupSangineo' => $paciente->getGrupSangineo(),
            ':alergias'     => $paciente->getAlergias(),
            ':idPersona'    => $paciente->getIDPersona()
        ]);
    }

    // CONSULTA GENERAL (Combina datos de personas y pacientes para respuestas JSON)
    public function obtenerTodos(): array
    {
        $sql = "SELECT p.id, p.nombres, p.primer_apellido, p.segundo_apellido, 
                       p.fecha_nacimiento, p.genero, p.direccion,
                       pa.num_seguro, pa.grupo_sanguineo, pa.alergias
                FROM pacientes pa
                INNER JOIN personas p ON pa.id_persona = p.id";

        $stmt = $this->conexion->query($sql);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // CONSULTA INDIVIDUAL (Devuelve una instancia del modelo paciente)
    public function obtenerPorId(int $idPersona): ?paciente
    {
        $sql = "SELECT * FROM pacientes WHERE id_persona = :idPersona";
        $stmt = $this->conexion->prepare($sql);
        $stmt->execute([':idPersona' => $idPersona]);

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fila) {
            return null;
        }

        return new paciente(
            $fila['id_persona'],
            $fila['num_seguro'],
            $fila['grupo_sanguineo'],
            $fila['alergias']
        );
    }
}
?>