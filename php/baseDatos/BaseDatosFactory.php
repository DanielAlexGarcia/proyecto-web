<?php
// php/baseDatos/BaseDatosFactory.php
//
// Fábrica que decide qué motor de base de datos se usa (MySQL local o Supabase)
// y entrega la conexión y los DAOs correspondientes. El resto del proyecto solo
// conoce las interfaces (ConexionInterface y *DAOInterface), nunca el motor.
//
// Cambiar de motor = definir la variable de entorno DB_DRIVER:
//   DB_DRIVER=mysql      (valor por defecto)
//   DB_DRIVER=supabase
//
// Requisitos previos (según el refactor propuesto):
//   php/baseDatos/ConexionInterface.php
//   php/baseDatos/conexionBaseDatos.php   -> class ConexionMySQL    implements ConexionInterface (Singleton)
//   php/baseDatos/BDsupabase.php          -> class ConexionSupabase implements ConexionInterface (Singleton)
//   php/modelosDAO/{Persona,Paciente,Empleado,Cita}DAOInterface.php
//   php/modelosDAO/{persona,paciente,empleado,cita}DAO.php                       -> *DAOMySQL
//   php/modelosDAO/modelosDAOSupabase/{persona,paciente,empleado,cita}DAOsup.php -> *DAOSupabase

declare(strict_types=1);

require_once __DIR__ . '/ConexionInterface.php';

final class BaseDatosFactory
{
    public const MYSQL    = 'mysql';
    public const SUPABASE = 'supabase';

    private static ?string $driver = null;

    /** Evita instanciar la fábrica: solo se usan sus métodos estáticos. */
    private function __construct() {}

    // ---------------------------------------------------------------------
    // Motor activo
    // ---------------------------------------------------------------------

    /** Devuelve el motor activo ('mysql' o 'supabase'). */
    public static function driver(): string
    {
        if (self::$driver === null) {
            $d = strtolower(trim((string) (getenv('DB_DRIVER') ?: self::MYSQL)));
            if (!in_array($d, [self::MYSQL, self::SUPABASE], true)) {
                throw new RuntimeException("DB_DRIVER no válido: '{$d}'. Usa 'mysql' o 'supabase'.");
            }
            self::$driver = $d;
        }
        return self::$driver;
    }

    /** Útil en pruebas: fuerza un motor sin depender de la variable de entorno. */
    public static function forzarDriver(?string $driver): void
    {
        self::$driver = $driver === null ? null : strtolower($driver);
    }

    private static function esSupabase(): bool
    {
        return self::driver() === self::SUPABASE;
    }

    // ---------------------------------------------------------------------
    // Conexión
    // ---------------------------------------------------------------------

    /** Singleton de conexión del motor activo (envuelto por su adaptador). */
    public static function conexion(): ConexionInterface
    {
        if (self::esSupabase()) {
            require_once __DIR__ . '/BDsupabase.php';
            return ConexionSupabase::getInstancia();
        }
        require_once __DIR__ . '/conexionBaseDatos.php';
        return ConexionMySQL::getInstancia();
    }

    /** Atajo: el objeto PDO del motor activo. */
    public static function pdo(): PDO
    {
        return self::conexion()->getConexion();
    }

    // ---------------------------------------------------------------------
    // DAOs (cada uno es el adaptador del motor activo)
    // ---------------------------------------------------------------------

    public static function persona(?PDO $pdo = null): PersonaDAOInterface
    {
        $pdo ??= self::pdo();
        require_once __DIR__ . '/../modelos/persona.php';
        require_once __DIR__ . '/../modelosDAO/PersonaDAOInterface.php';

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
        require_once __DIR__ . '/../modelos/paciente.php';
        require_once __DIR__ . '/../modelosDAO/PacienteDAOInterface.php';

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
        require_once __DIR__ . '/../modelos/empleado.php';
        require_once __DIR__ . '/../modelosDAO/EmpleadoDAOInterface.php';

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
        require_once __DIR__ . '/../modelos/cita.php';
        require_once __DIR__ . '/../modelosDAO/CitaDAOInterface.php';

        if (self::esSupabase()) {
            require_once __DIR__ . '/../modelosDAO/modelosDAOSupabase/citaDAOsup.php';
            return new CitaDAOSupabase($pdo);
        }
        require_once __DIR__ . '/../modelosDAO/citaDAO.php';
        return new CitaDAOMySQL($pdo);
    }
}
?>