<?php

require_once __DIR__ . '/../../../modelos/persona.php';
require_once __DIR__ . '/../../../modelosDAO/personaDAO.php';

class ServicioAltaPersona
{
    private PersonaDAO $dao;

    public function __construct(private PDO $pdo)
    {
        $this->dao = new PersonaDAO($pdo);
    }

    public function crear(array $datos): array
    {
        $persona = new persona(
            trim($datos['nombres']),
            trim($datos['primer_apellido']),
            $datos['segundo_apellido'] ?? null,
            $datos['fecha_nacimiento'],
            $datos['genero'] ?? null,
            $datos['direccion'] ?? null
        );

        $this->dao->insertar($persona);

        return [
            'ok'        => true,
            'mensaje'   => 'Persona registrada.',
            'idPersona' => (int) $this->pdo->lastInsertId()
        ];
    }
}