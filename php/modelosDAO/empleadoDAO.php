<?php
require_once __DIR__ . '/../modelos/empleado.php';
require_once __DIR__ . '/EmpleadoDAOInterface.php';

class EmpleadoDAOMySQL implements EmpleadoDAOInterface
{
    private PDO $conexion;

    public function __construct(PDO $conexion)
    {
        $this->conexion = $conexion;
    }

    public function insertar(empleado $empleado): bool
    {
        $stmt = $this->conexion->prepare(
            "INSERT INTO empleados (id_persona, cargo, especializacion, salario)
             VALUES (:idPersona, :cargo, :especializacion, :salario)"
        );
        return $stmt->execute([
            ':idPersona'       => $empleado->getIDPersona(),
            ':cargo'           => $empleado->getCargo(),
            ':especializacion' => $empleado->getEspecializacion(),
            ':salario'         => $empleado->getSalario()
        ]);
    }

    public function crearConTransaccion(
        string $nombres,
        string $primerApellido,
        ?string $segundoApellido,
        string $fechaNacimiento,
        ?string $genero,
        ?string $direccion,
        empleado $empleado,
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
                $this->conexion->prepare(
                    "INSERT INTO personas (nombres, primer_apellido, segundo_apellido, fecha_nacimiento, genero, direccion)
                     VALUES (:nombres, :primer_apellido, :segundo_apellido, :fecha_nacimiento, :genero, :direccion)"
                )->execute([
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
                "INSERT INTO empleados (id_persona, cargo, especializacion, salario)
                 VALUES (:id_persona, :cargo, :especializacion, :salario)"
            )->execute([
                ':id_persona'      => $idPersona,
                ':cargo'           => $empleado->getCargo(),
                ':especializacion' => $empleado->getEspecializacion(),
                ':salario'         => $empleado->getSalario()
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

    public function crearConPersonaExistente(empleado $empleado, string $user, string $password): bool
    {
        $stmt = $this->conexion->prepare(
            "CALL sp_crear_empleado_existente(:idPersona, :cargo, :especializacion, :salario, :user, :password)"
        );
        $resultado = $stmt->execute([
            ':idPersona'       => $empleado->getIDPersona(),
            ':cargo'           => $empleado->getCargo(),
            ':especializacion' => $empleado->getEspecializacion(),
            ':salario'         => $empleado->getSalario(),
            ':user'            => trim($user),
            ':password'        => $password
        ]);
        $stmt->closeCursor();
        return $resultado;
    }

    public function eliminar(int $idPersona): bool
    {
        $stmt = $this->conexion->prepare("DELETE FROM empleados WHERE id_persona = :idPersona");
        return $stmt->execute([':idPersona' => $idPersona]);
    }

    public function actualizar(empleado $empleado): bool
    {
        $stmt = $this->conexion->prepare(
            "UPDATE empleados SET cargo = :cargo, especializacion = :especializacion, salario = :salario
             WHERE id_persona = :idPersona"
        );
        return $stmt->execute([
            ':cargo'           => $empleado->getCargo(),
            ':especializacion' => $empleado->getEspecializacion(),
            ':salario'         => $empleado->getSalario(),
            ':idPersona'       => $empleado->getIDPersona()
        ]);
    }

    public function obtenerTodos(): array
    {
        $sql = "SELECT p.id, p.nombres, p.primer_apellido, p.segundo_apellido,
                       p.fecha_nacimiento, p.genero, p.direccion,
                       e.cargo, e.especializacion, e.salario
                FROM empleados e
                INNER JOIN personas p ON e.id_persona = p.id";
        return $this->conexion->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorId(int $idPersona): ?empleado
    {
        $stmt = $this->conexion->prepare("SELECT * FROM empleados WHERE id_persona = :idPersona");
        $stmt->execute([':idPersona' => $idPersona]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$fila) {
            return null;
        }
        return new empleado($fila['id_persona'], $fila['cargo'], $fila['especializacion'], $fila['salario']);
    }
}
