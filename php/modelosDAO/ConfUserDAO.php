<?php
require_once __DIR__ . '/confUserInterface.php';
require_once __DIR__ . '/../baseDatos/BaseDatosFactory.php';

/**
 * Un solo DAO para MySQL y Supabase: las únicas diferencias son el quoting de la
 * columna "user" (reservada en Postgres). Los nombres de columna en minúsculas
 * (iddocpac) funcionan en ambos motores. Hash = sha1 en PHP, igual que el resto.
 */
class ConfUserDAO implements ConfUserInterface
{
    private const MAX_INTENTOS = 5;
    private const BLOQUEO_MIN  = 15;

    private PDO $c;
    private string $colUser;

    public function __construct(PDO $conexion)
    {
        $this->c = $conexion;
        $this->colUser = BaseDatosFactory::esSupabase() ? '"user"' : '`user`';
    }

    // ------------------------------------------------------------------ lectura
    private function filaPorPersona(int $id): ?array
    {
        $s = $this->c->prepare('SELECT id, password, pin FROM usuarios WHERE iddocpac = :p ORDER BY id LIMIT 1');
        $s->execute([':p' => $id]);
        return $s->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function filaPorUser(string $user): ?array
    {
        $s = $this->c->prepare("SELECT id, password, pin FROM usuarios WHERE {$this->colUser} = :u LIMIT 1");
        $s->execute([':u' => sha1(trim($user))]);
        return $s->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function igual(?string $guardado, string $plano): bool
    {
        return $guardado !== null && $guardado !== '' && hash_equals($guardado, sha1($plano));
    }

    private function guardar(int $idUsuario, string $campo, string $valorPlano): bool
    {
        if (!in_array($campo, ['password', 'pin'], true)) {
            throw new InvalidArgumentException('Campo no permitido.');
        }
        $s = $this->c->prepare("UPDATE usuarios SET {$campo} = :v WHERE id = :id");
        return $s->execute([':v' => sha1($valorPlano), ':id' => $idUsuario]);
    }

    // ------------------------------------------------------- límite de intentos
    private function revisarBloqueo(string $clave): void
    {
        $s = $this->c->prepare('SELECT bloqueado_hasta FROM intentos_seguridad WHERE clave = :k');
        $s->execute([':k' => $clave]);
        $f = $s->fetch(PDO::FETCH_ASSOC);
        if ($f && $f['bloqueado_hasta'] && ($t = strtotime((string) $f['bloqueado_hasta'])) > time()) {
            throw new ConfUserBloqueadoException((int) ceil(($t - time()) / 60));
        }
    }

    private function registrarFallo(string $clave): void
    {
        $s = $this->c->prepare('SELECT intentos FROM intentos_seguridad WHERE clave = :k');
        $s->execute([':k' => $clave]);
        $f = $s->fetch(PDO::FETCH_ASSOC);

        $n = $f ? (int) $f['intentos'] + 1 : 1;
        $hasta = null;
        if ($n >= self::MAX_INTENTOS) {
            $n = 0;
            $hasta = date('Y-m-d H:i:s', time() + self::BLOQUEO_MIN * 60);
        }
        $sql = $f
            ? 'UPDATE intentos_seguridad SET intentos = :n, bloqueado_hasta = :h WHERE clave = :k'
            : 'INSERT INTO intentos_seguridad (clave, intentos, bloqueado_hasta) VALUES (:k, :n, :h)';
        $this->c->prepare($sql)->execute([':k' => $clave, ':n' => $n, ':h' => $hasta]);
    }

    private function limpiar(string $clave): void
    {
        $this->c->prepare('DELETE FROM intentos_seguridad WHERE clave = :k')->execute([':k' => $clave]);
    }

    private function conIntentos(string $clave, callable $verifica): bool
    {
        $this->revisarBloqueo($clave);
        if (!$verifica()) {
            $this->registrarFallo($clave);
            return false;
        }
        $this->limpiar($clave);
        return true;
    }

    private function claveUser(string $user, string $accion): string
    {
        return 'r' . sha1(mb_strtolower(trim($user), 'UTF-8')) . ':' . $accion; // máx. 44 chars
    }

    // --------------------------------------------------------- con sesión
    public function tienePin(int $idPersona): bool
    {
        $f = $this->filaPorPersona($idPersona);
        return $f !== null && $f['pin'] !== null && $f['pin'] !== '';
    }

    public function actualizarPassword(int $idPersona, string $pin, string $anterior, string $nueva): bool
    {
        $f = $this->filaPorPersona($idPersona);
        if (!$f) {
            return false;
        }
        $ok = $this->conIntentos("c{$idPersona}:pw", fn() =>
            $this->igual($f['pin'], $pin) && $this->igual($f['password'], $anterior));
        return $ok && $this->guardar((int) $f['id'], 'password', $nueva);
    }

    public function actualizarPin(int $idPersona, string $password, string $anterior, string $nuevo): bool
    {
        $f = $this->filaPorPersona($idPersona);
        if (!$f) {
            return false;
        }
        $sinPin = ($f['pin'] === null || $f['pin'] === '');
        $ok = $this->conIntentos("c{$idPersona}:pin", fn() =>
            $this->igual($f['password'], $password) && ($sinPin || $this->igual($f['pin'], $anterior)));
        return $ok && $this->guardar((int) $f['id'], 'pin', $nuevo);
    }

    // --------------------------------------------------------- recuperación
    public function verificarEntidadSinPin(string $user, string $password): bool
    {
        $f = $this->filaPorUser($user);
        return $this->conIntentos($this->claveUser($user, 'pin'), fn() =>
            $f !== null && $this->igual($f['password'], $password));
    }

    public function verificarEntidadSinPassword(string $user, string $pin): bool
    {
        $f = $this->filaPorUser($user);
        return $this->conIntentos($this->claveUser($user, 'pw'), fn() =>
            $f !== null && $this->igual($f['pin'], $pin));
    }

    public function recuperarPin(string $user, string $password, string $nuevoPin): bool
    {
        if (!$this->verificarEntidadSinPin($user, $password)) {
            return false;
        }
        $f = $this->filaPorUser($user);
        return $f !== null && $this->guardar((int) $f['id'], 'pin', $nuevoPin);
    }

    public function recuperarPassword(string $user, string $pin, string $nuevaPassword): bool
    {
        if (!$this->verificarEntidadSinPassword($user, $pin)) {
            return false;
        }
        $f = $this->filaPorUser($user);
        return $f !== null && $this->guardar((int) $f['id'], 'password', $nuevaPassword);
    }
}
