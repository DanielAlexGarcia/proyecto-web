<?php
require_once __DIR__ . '/../modelos/empleado.php';

class EmpleadoDAOMySQL implements EmpleadoDAOInterface
{
    private PDO $conexion;

    public function __construct(PDO $conexion)
    {
        $this->conexion = $conexion;
    }

    // ALTA (Insertar registro)
    public function insertar(empleado $empleado): bool
    {
        $sql = "INSERT INTO empleados (id_persona, cargo, especializacion, salario) 
                VALUES (:idPersona, :cargo, :especializacion, :salario)";

        $stmt = $this->conexion->prepare($sql);

        return $stmt->execute([
            ':idPersona'       => $empleado->getIDPersona(),
            ':cargo'           => $empleado->getCargo(),
            ':especializacion' => $empleado->getEspecializacion(),
            ':salario'         => $empleado->getSalario()
        ]);
    }
    // ALTA con TRANSACCIÓN COMPLETA:
    // Verifica si la persona existe; si no, la crea. Luego registra al empleado
    // y su cuenta de usuario con SHA1. Si algo falla, revierte todos los cambios.
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

            // 1. Verificar si la persona ya está registrada
            $sqlBuscar = "SELECT id FROM personas 
                          WHERE nombres = :nombres 
                            AND primer_apellido = :primer_apellido 
                            AND segundo_apellido <=> :segundo_apellido 
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

            // 2. Si no existe, dar de alta en `personas` y recuperar su ID
            if (!$persona) {
                $sqlPersona = "INSERT INTO personas (
                                   nombres, primer_apellido, segundo_apellido, 
                                   fecha_nacimiento, genero, direccion
                               ) VALUES (
                                   :nombres, :primer_apellido, :segundo_apellido, 
                                   :fecha_nacimiento, :genero, :direccion
                               )";

                $stmtPersona = $this->conexion->prepare($sqlPersona);
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

            // 3. Registrar en `empleados` usando el id_persona obtenido
            $sqlEmpleado = "INSERT INTO empleados (id_persona, cargo, especializacion, salario) 
                            VALUES (:id_persona, :cargo, :especializacion, :salario)";

            $stmtEmpleado = $this->conexion->prepare($sqlEmpleado);
            $stmtEmpleado->execute([
                ':id_persona'      => $idPersona,
                ':cargo'           => $empleado->getCargo(),
                ':especializacion' => $empleado->getEspecializacion(),
                ':salario'         => $empleado->getSalario()
            ]);

            // 4. Registrar credenciales en `usuarios`
            $sqlUsuario = "INSERT INTO usuarios (user, password, idDocPac) 
                           VALUES (SHA1(:user), SHA1(:password), :idDocPac)";

            $stmtUsuario = $this->conexion->prepare($sqlUsuario);
            $stmtUsuario->execute([
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

    // ALTA con PROCEDIMIENTO ALMACENADO:
    // crea el empleado a partir de una persona ya existente y su usuario de acceso.
    // Se envían usuario y contraseña en texto plano; el procedimiento aplica SHA1.
    // Lanza PDOException si la persona no existe, el empleado ya está dado de alta,
    // el usuario se repite, etc.
    public function crearConPersonaExistente(empleado $empleado, string $user, string $password): bool
    {
        $sql = "CALL sp_crear_empleado_existente(
                    :idPersona, :cargo, :especializacion, :salario, :user, :password
                )";

        $stmt = $this->conexion->prepare($sql);

        $resultado = $stmt->execute([
            ':idPersona'       => $empleado->getIDPersona(),
            ':cargo'           => $empleado->getCargo(),
            ':especializacion' => $empleado->getEspecializacion(),
            ':salario'         => $empleado->getSalario(),
            ':user'            => trim($user),
            ':password'        => $password
        ]);

        // Libera el cursor para poder ejecutar más consultas con la misma conexión
        $stmt->closeCursor();

        return $resultado;
    }

    // BAJA (Eliminar por ID de persona)
    public function eliminar(int $idPersona): bool
    {
        $sql = "DELETE FROM empleados WHERE id_persona = :idPersona";
        $stmt = $this->conexion->prepare($sql);

        return $stmt->execute([':idPersona' => $idPersona]);
    }

    // CAMBIO (Actualizar datos laborales del empleado)
    public function actualizar(empleado $empleado): bool
    {
        $sql = "UPDATE empleados SET 
                    cargo = :cargo, 
                    especializacion = :especializacion, 
                    salario = :salario 
                WHERE id_persona = :idPersona";

        $stmt = $this->conexion->prepare($sql);

        return $stmt->execute([
            ':cargo'           => $empleado->getCargo(),
            ':especializacion' => $empleado->getEspecializacion(),
            ':salario'         => $empleado->getSalario(),
            ':idPersona'       => $empleado->getIDPersona()
        ]);
    }

    // CONSULTA GENERAL (Combina datos de personas y empleados para respuestas JSON)
    public function obtenerTodos(): array
    {
        $sql = "SELECT p.id, p.nombres, p.primer_apellido, p.segundo_apellido, 
                       p.fecha_nacimiento, p.genero, p.direccion,
                       e.cargo, e.especializacion, e.salario
                FROM empleados e
                INNER JOIN personas p ON e.id_persona = p.id";

        $stmt = $this->conexion->query($sql);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // CONSULTA INDIVIDUAL (Devuelve una instancia del modelo empleado)
    public function obtenerPorId(int $idPersona): ?empleado
    {
        $sql = "SELECT * FROM empleados WHERE id_persona = :idPersona";
        $stmt = $this->conexion->prepare($sql);
        $stmt->execute([':idPersona' => $idPersona]);

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fila) {
            return null;
        }

        return new empleado(
            $fila['id_persona'],
            $fila['cargo'],
            $fila['especializacion'],
            $fila['salario']
        );
    }
}
?>