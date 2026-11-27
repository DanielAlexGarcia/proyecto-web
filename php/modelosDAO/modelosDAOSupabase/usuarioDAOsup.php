<?php
require_once __DIR__ . '/../UsuarioDAOInterface.php';

class UsuarioDAOSupabase implements UsuarioDAOInterface
{
    private PDO $conexion;

    public function __construct(PDO $conexion)
    {
        $this->conexion = $conexion;
    }

    public function buscarPorCredenciales(string $user, string $password): ?array
    {
        // En Postgres "user" va entre comillas y la columna idDocPac queda como iddocpac
        $stmt = $this->conexion->prepare(
            'SELECT id, iddocpac AS id_persona, password FROM usuarios WHERE "user" = :u LIMIT 1'
        );
        $stmt->execute([':u' => sha1(trim($user))]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fila || !hash_equals((string) $fila['password'], sha1($password))) {
            return null;
        }
        return ['id' => (int) $fila['id'], 'id_persona' => (int) $fila['id_persona']];
    }

    public function crear(string $user, string $password, int $idPersona): bool
    {
        $stmt = $this->conexion->prepare(
            'INSERT INTO usuarios ("user", password, iddocpac) VALUES (:u, :p, :id)'
        );
        return $stmt->execute([
            ':u'  => sha1(trim($user)),
            ':p'  => sha1($password),
            ':id' => $idPersona
        ]);
    }
}
