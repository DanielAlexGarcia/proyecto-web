<?php
declare(strict_types=1);

class ValidadorPersonas
{
    private const GRUPOS    = ['O+','O-','A+','A-','B+','B-','AB+','AB-'];
    private const GENEROS   = ['M','F','Otro'];
    private const RE_NOMBRE = '/^[\p{L}\s.\'-]+$/u';

    private static function fallo(string $m): never
    {
        throw new InvalidArgumentException($m);
    }

    public static function nombre(mixed $v, string $etiqueta, bool $obligatorio = true): ?string
    {
        $v = trim((string) $v);
        if ($v === '') {
            if ($obligatorio) self::fallo("$etiqueta es obligatorio.");
            return null;
        }
        if (mb_strlen($v) < 2 || mb_strlen($v) > 100 || !preg_match(self::RE_NOMBRE, $v)) {
            self::fallo("$etiqueta no es válido.");
        }
        return $v;
    }

    /** Claves snake_case: nombres, primer_apellido, segundo_apellido, fecha_nacimiento, genero, direccion */
    public static function persona(array $d): void
    {
        self::nombre($d['nombres'] ?? '', 'El nombre');
        self::nombre($d['primer_apellido'] ?? '', 'El primer apellido');
        self::nombre($d['segundo_apellido'] ?? '', 'El segundo apellido', false);

        $f = (string) ($d['fecha_nacimiento'] ?? '');
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $f);
        if (!$dt || $dt->format('Y-m-d') !== $f || $dt > new DateTimeImmutable('today')
            || $dt < new DateTimeImmutable('1900-01-01')) {
            self::fallo('La fecha de nacimiento no es válida.');
        }

        $g = trim((string) ($d['genero'] ?? ''));
        if ($g !== '' && !in_array($g, self::GENEROS, true)) self::fallo('El género no es válido.');

        if (mb_strlen(trim((string) ($d['direccion'] ?? ''))) > 255) self::fallo('La dirección es demasiado larga.');
    }

    /** Claves: num_seguro, grupo_sanguineo, alergias */
    public static function paciente(array $d): void
    {
        if (!preg_match('/^[A-Za-z0-9-]{5,50}$/', trim((string) ($d['num_seguro'] ?? '')))) {
            self::fallo('El número de seguro debe tener de 5 a 50 caracteres (letras, números o guion).');
        }
        $gs = trim((string) ($d['grupo_sanguineo'] ?? ''));
        if ($gs !== '' && !in_array($gs, self::GRUPOS, true)) self::fallo('El grupo sanguíneo no es válido.');

        if (mb_strlen(trim((string) ($d['alergias'] ?? ''))) > 500) self::fallo('Las alergias exceden 500 caracteres.');
    }

    /** Claves: cargo, especializacion, salario */
    public static function empleado(array $d): void
    {
        $c = trim((string) ($d['cargo'] ?? ''));
        if ($c === '' || mb_strlen($c) > 100) self::fallo('El cargo no es válido.');
        if (mb_strlen(trim((string) ($d['especializacion'] ?? ''))) > 100) self::fallo('La especialización es demasiado larga.');

        $s = $d['salario'] ?? null;
        if (!is_numeric($s) || (float) $s <= 0 || (float) $s > 99999999.99) self::fallo('El salario debe ser un número positivo.');
    }

    /** Claves: user, password */
    public static function credenciales(array $d): void
    {
        $u = trim((string) ($d['user'] ?? ''));
        if (mb_strlen($u) < 3 || mb_strlen($u) > 100) self::fallo('El usuario debe tener entre 3 y 100 caracteres.');

        $p = (string) ($d['password'] ?? '');
        if (strlen($p) < 8 || strlen($p) > 100) self::fallo('La contraseña debe tener entre 8 y 100 caracteres.');
    }
}
?>