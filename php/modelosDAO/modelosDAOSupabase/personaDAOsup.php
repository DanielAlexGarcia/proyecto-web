<?php
require_once __DIR__ . '/../../modelos/persona.php';

class PersonaDAO
{
    private PDO $conexion;

    // Recibe la conexión PDO como parámetro
    public function __construct(PDO $conexion){
        $this->conexion = $conexion;
    }

    // ALTA (Insertar registro)
    public function insertar(persona $persona): bool{
        $sql = "INSERT INTO personas (nombres, primer_apellido, segundo_apellido, fecha_nacimiento, genero, direccion) 
                VALUES (:nombres, :primerApellido, :segundoApellido, :fechaNacimiento, :genero, :direccion)";

        $stmt = $this->conexion->prepare($sql);

        return $stmt->execute([
            ':nombres'         => $persona->getNombres(),
            ':primerApellido'  => $persona->getPrimerApellido(),
            ':segundoApellido' => $persona->getSegundoApellido(),
            ':fechaNacimiento' => $persona->getFechaNacimiento(),
            ':genero'          => $persona->getGenero(),
            ':direccion'       => $persona->getCURP()
        ]);
    }

    // ALTA RETORNANDO ID (Inserta la persona y devuelve su ID autogenerado)
    public function insertarRetornandoId(persona $persona): ?int
    {
        $sql = "INSERT INTO personas (nombres, primer_apellido, segundo_apellido, fecha_nacimiento, genero, direccion) 
                VALUES (:nombres, :primerApellido, :segundoApellido, :fechaNacimiento, :genero, :direccion)
                RETURNING id";

        $stmt = $this->conexion->prepare($sql);

        $ejecutado = $stmt->execute([
            ':nombres'         => $persona->getNombres(),
            ':primerApellido'  => $persona->getPrimerApellido(),
            ':segundoApellido' => $persona->getSegundoApellido(),
            ':fechaNacimiento' => $persona->getFechaNacimiento(),
            ':genero'          => $persona->getGenero(),
            ':direccion'       => $persona->getCURP()
        ]);

        if ($ejecutado) {
            $id = $stmt->fetchColumn();
            return $id !== false ? (int) $id : null;
        }

        return null;
    }

    // BAJA (Eliminar por ID)
    public function eliminar(int $id): bool{
        $sql = "DELETE FROM personas WHERE id = :id";
        $stmt = $this->conexion->prepare($sql);

        return $stmt->execute([':id' => $id]);
    }

    // CAMBIO (Actualizar por ID)
    public function actualizar(persona $persona, int $id): bool{
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

    // CONSULTA GENERAL (Devuelve arreglos asociativos listos para json_encode)
    public function obtenerTodos(): array{
        $sql = "SELECT * FROM personas";
        $stmt = $this->conexion->query($sql);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // CONSULTA INDIVIDUAL (Devuelve una instancia del modelo persona)
    public function obtenerPorId(int $id): ?persona{
        $sql = "SELECT * FROM personas WHERE id = :id";
        $stmt = $this->conexion->prepare($sql);
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
?>