<?php
require_once __DIR__ . '/UsuarioDAOInterface.php';

class UsuarioDAOMySQL implements UsuarioDAOInterface
{
    private PDO $conexion;

    public function __construct(PDO $conexion)
    {
        $this->conexion = $conexion;
    }

    public function buscarPorCredenciales(string $user, string $password): ?array
    {
        $stmt = $this->conexion->prepare(
            'SELECT id, idDocPac AS id_persona, password FROM usuarios WHERE `user` = :u LIMIT 1'
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
        // El trigger trg_usuarios_before_insert no vuelve a hashear valores que ya son SHA1 (40 hex).
        $stmt = $this->conexion->prepare(
            'INSERT INTO usuarios (`user`, password, idDocPac) VALUES (:u, :p, :id)'
        );
        return $stmt->execute([
            ':u'  => sha1(trim($user)),
            ':p'  => sha1($password),
            ':id' => $idPersona
        ]);
    }
}
