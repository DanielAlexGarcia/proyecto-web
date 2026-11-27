<?php
require_once __DIR__ . '/../../modelos/persona.php';
require_once __DIR__ . '/../PersonaDAOInterface.php';

class PersonaDAOSupabase implements PersonaDAOInterface
{
    private PDO $conexion;

    public function __construct(PDO $conexion)
    {
        $this->conexion = $conexion;
    }

    public function insertar(persona $persona): bool
    {
        return $this->insertarRetornandoId($persona) !== null;
    }

    public function insertarRetornandoId(persona $persona): ?int
    {
        $sql = "INSERT INTO personas (nombres, primer_apellido, segundo_apellido, fecha_nacimiento, genero, direccion)
                VALUES (:nombres, :primerApellido, :segundoApellido, :fechaNacimiento, :genero, :direccion)
                RETURNING id";
        $stmt = $this->conexion->prepare($sql);
        $stmt->execute([
            ':nombres'         => $persona->getNombres(),
            ':primerApellido'  => $persona->getPrimerApellido(),
            ':segundoApellido' => $persona->getSegundoApellido(),
            ':fechaNacimiento' => $persona->getFechaNacimiento(),
            ':genero'          => $persona->getGenero(),
            ':direccion'       => $persona->getCURP()
        ]);
        $id = $stmt->fetchColumn();
        return $id !== false ? (int) $id : null;
    }

    public function eliminar(int $id): bool
    {
        $stmt = $this->conexion->prepare("DELETE FROM personas WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    public function actualizar(persona $persona, int $id): bool
    {
        $sql = "UPDATE personas SET
                    nombres = :nombres,
                    primer_apellido = :primerApellido,
                    segundo_apellido = :segundoApellido,
                    fecha_nacimiento = :fechaNacimiento,
                    genero = :genero,
                    direccion = :direccion
                WHERE id = :id";
        $stmt = $this->conexion->prepare($sql);

        return $stmt->execute([
            ':nombres'         => $persona->getNombres(),
            ':primerApellido'  => $persona->getPrimerApellido(),
            ':segundoApellido' => $persona->getSegundoApellido(),
            ':fechaNacimiento' => $persona->getFechaNacimiento(),
            ':genero'          => $persona->getGenero(),
            ':direccion'       => $persona->getCURP(),
            ':id'              => $id
        ]);
    }

    public function obtenerTodos(): array
    {
        return $this->conexion->query("SELECT * FROM personas")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorId(int $id): ?persona
    {
        $stmt = $this->conexion->prepare("SELECT * FROM personas WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$fila) {
            return null;
        }
        return new persona(
            $fila['nombres'],
            $fila['primer_apellido'],
            $fila['segundo_apellido'],
            $fila['fecha_nacimiento'],
            $fila['genero'],
            $fila['direccion']
        );
    }
}
