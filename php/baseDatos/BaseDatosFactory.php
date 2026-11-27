<?php
declare(strict_types=1);

require_once __DIR__ . '/ConexionInterface.php';
require_once __DIR__ . '/RespuestaJson.php';

/**
 * Punto único para obtener conexión y DAOs del motor activo.
 * El motor se elige con DB_DRIVER (variable de entorno o php/auth/secretos.php): 'mysql' | 'supabase'.
 */
final class BaseDatosFactory
{
    public const MYSQL = 'mysql';
    public const SUPABASE = 'supabase';

    private static ?string $driver = null;

    private function __construct() {}

    // ------------------------------------------------------------------
    // Motor activo
    // ------------------------------------------------------------------
    public static function driver(): string
    {
        if (self::$driver === null) {
            $d = getenv('DB_DRIVER');
            if (!$d) {
                $ruta = __DIR__ . '/../auth/secretos.php';
                if (is_file($ruta)) {
                    $cfg = require $ruta;
                    $d = is_array($cfg) ? ($cfg['DB_DRIVER'] ?? '') : '';
                }
            }
            $d = strtolower(trim((string) ($d ?: self::MYSQL)));
            if (!in_array($d, [self::MYSQL, self::SUPABASE], true)) {
                throw new RuntimeException("DB_DRIVER no válido: '{$d}'. Usa 'mysql' o 'supabase'.");
            }
            self::$driver = $d;
        }
        return self::$driver;
    }

    /** Útil en pruebas: fuerza un motor sin depender de la configuración. */
    public static function forzarDriver(?string $driver): void
    {
        self::$driver = $driver === null ? null : strtolower($driver);
    }

    public static function esSupabase(): bool
    {
        return self::driver() === self::SUPABASE;
    }

    // ------------------------------------------------------------------
    // Conexión
    // ------------------------------------------------------------------
    private static function cargarModelos(): void
    {
        require_once __DIR__ . '/../modelos/persona.php';
        require_once __DIR__ . '/../modelos/paciente.php';
        require_once __DIR__ . '/../modelos/empleado.php';
        require_once __DIR__ . '/../modelos/cita.php';
    }

    /** Singleton de conexión del motor activo. También deja cargados los modelos. */
    public static function conexion(): ConexionInterface
    {
        self::cargarModelos();
        if (self::esSupabase()) {
            require_once __DIR__ . '/BDsupabase.php';
            return ConexionSupabase::getInstancia();
        }
        require_once __DIR__ . '/conexionBaseDatos.php';
        return ConexionMySQL::getInstancia();
    }

    public static function pdo(): PDO
    {
        return self::conexion()->getConexion();
    }

    // ------------------------------------------------------------------
    // DAOs (cada uno es el adaptador del motor activo)
    // ------------------------------------------------------------------
    public static function persona(?PDO $pdo = null): PersonaDAOInterface
    {
        $pdo ??= self::pdo();
        if (self::esSupabase()) {
            require_once __DIR__ . '/../modelosDAO/modelosDAOSupabase/personaDAOsup.php';
            return new PersonaDAOSupabase($pdo);
        }
        require_once __DIR__ . '/../modelosDAO/personaDAO.php';
        return new PersonaDAOMySQL($pdo);
    }

    public static function paciente(?PDO $pdo = null): PacienteDAOInterface
    {
        $pdo ??= self::pdo();
        if (self::esSupabase()) {
            require_once __DIR__ . '/../modelosDAO/modelosDAOSupabase/pacienteDAOsup.php';
            return new PacienteDAOSupabase($pdo);
        }
        require_once __DIR__ . '/../modelosDAO/pacienteDAO.php';
        return new PacienteDAOMySQL($pdo);
    }

    public static function empleado(?PDO $pdo = null): EmpleadoDAOInterface
    {
        $pdo ??= self::pdo();
        if (self::esSupabase()) {
            require_once __DIR__ . '/../modelosDAO/modelosDAOSupabase/empleadoDAOsup.php';
            return new EmpleadoDAOSupabase($pdo);
        }
        require_once __DIR__ . '/../modelosDAO/empleadoDAO.php';
        return new EmpleadoDAOMySQL($pdo);
    }

    public static function cita(?PDO $pdo = null): CitaDAOInterface
    {
        $pdo ??= self::pdo();
        if (self::esSupabase()) {
            require_once __DIR__ . '/../modelosDAO/modelosDAOSupabase/citaDAOsup.php';
            return new CitaDAOSupabase($pdo);
        }
        require_once __DIR__ . '/../modelosDAO/citaDAO.php';
        return new CitaDAOMySQL($pdo);
    }

    public static function usuario(?PDO $pdo = null): UsuarioDAOInterface
    {
        $pdo ??= self::pdo();
        if (self::esSupabase()) {
            require_once __DIR__ . '/../modelosDAO/modelosDAOSupabase/usuarioDAOsup.php';
            return new UsuarioDAOSupabase($pdo);
        }
        require_once __DIR__ . '/../modelosDAO/UsuarioDAO.php';
        return new UsuarioDAOMySQL($pdo);
    }

    // ------------------------------------------------------------------
    // Helpers de dialecto SQL
    // ------------------------------------------------------------------

    /** Operador de búsqueda parcial: ILIKE en Postgres (ignora mayúsculas), LIKE en MySQL. */
    public static function like(): string
    {
        return self::esSupabase() ? 'ILIKE' : 'LIKE';
    }

    /** Convierte una columna a texto (en Postgres LIKE no funciona sobre date/int). */
    public static function texto(string $columna): string
    {
        return self::esSupabase() ? "CAST($columna AS TEXT)" : $columna;
    }

    /**
     * Tipa un parámetro (Postgres lo necesita al llamar funciones/procedimientos).
     * $tipo: 'int' | 'fecha' | 'texto' | 'numero'. En MySQL devuelve la expresión intacta.
     */
    public static function tipado(string $expr, string $tipo): string
    {
        if (!self::esSupabase()) {
            return $expr;
        }
        $pg = ['int' => 'INTEGER', 'fecha' => 'TIMESTAMP', 'texto' => 'TEXT', 'numero' => 'NUMERIC'][$tipo] ?? 'TEXT';
        return "CAST($expr AS $pg)";
    }

    /** Clasifica un error de BD sin depender del motor: duplicado | fk | regla | formato | otro. */
    public static function tipoError(PDOException $e): string
    {
        $estado = (string) ($e->errorInfo[0] ?? $e->getCode());
        $codigo = (int) ($e->errorInfo[1] ?? 0);

        if (self::esSupabase()) {
            return match ($estado) {
                '23505' => 'duplicado',
                '23503' => 'fk',
                'P0001' => 'regla',
                '22P02', '22007', '22008', '23514' => 'formato',
                default => 'otro',
            };
        }
        return match (true) {
            $codigo === 1062 => 'duplicado',
            $codigo === 1452 => 'fk',
            $estado === '45000' => 'regla',
            in_array($codigo, [1292, 1366, 1265], true) => 'formato',
            default => 'otro',
        };
    }

    /** Mensaje limpio de un error lanzado desde un procedimiento (SIGNAL / RAISE EXCEPTION). */
    public static function mensajeError(PDOException $e): string
    {
        $texto = (string) ($e->errorInfo[2] ?? $e->getMessage());
        $linea = strtok($texto, "\n") ?: $texto;
        return trim(preg_replace('/^ERROR:\s*/i', '', $linea));
    }
}
