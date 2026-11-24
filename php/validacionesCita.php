<?php
// php/validacionesCita.php
declare(strict_types=1);

class ValidadorCitas
{
    private const HORARIOS_PERMITIDOS = [
        '08:30:00', '09:30:00', '10:30:00', '11:30:00',
        '13:00:00', '16:00:00', '17:30:00'
    ];

    private const ESTADOS_PERMITIDOS = [
        'Pendiente', 'Confirmada', 'Cancelada'
    ];

    public static function validarEnteroPositivo(mixed $valor, string $campo): int
    {
        if (is_bool($valor) || filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            self::responderError(422, "El campo '{$campo}' debe ser un número entero positivo.");
        }
        return (int) $valor;
    }

    public static function validarFechaHora(mixed $fechaHora, bool $permitirPasada = false): string
    {
        if (!is_string($fechaHora) || !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $fechaHora)) {
            self::responderError(422, 'El formato de fecha y hora debe ser YYYY-MM-DD HH:MM:SS.');
        }

        [$fecha, $hora] = explode(' ', $fechaHora);

        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $fecha);
        if (!$dt || $dt->format('Y-m-d') !== $fecha) {
            self::responderError(422, 'La fecha ingresada no es válida.');
        }

        if (!in_array($hora, self::HORARIOS_PERMITIDOS, true)) {
            self::responderError(422, 'El horario seleccionado no es válido.');
        }

        if (!$permitirPasada) {
            $dtCompleta = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $fechaHora);
            if ($dtCompleta <= new DateTimeImmutable('now')) {
                self::responderError(422, 'La fecha y hora de la cita deben ser futuras.');
            }
        }

        return $fechaHora;
    }

    public static function validarMotivo(mixed $motivo): string
    {
        if (!is_string($motivo)) {
            self::responderError(422, 'El motivo debe ser una cadena de texto.');
        }

        $limpio = trim($motivo);
        $len = mb_strlen($limpio, 'UTF-8');

        if ($len < 5 || $len > 255) {
            self::responderError(422, 'El motivo debe tener entre 5 y 255 caracteres.');
        }

        if (strip_tags($limpio) !== $limpio || !preg_match('/^[\p{L}\p{N}\s.,;:()\-¿?¡!]+$/u', $limpio)) {
            self::responderError(422, 'El motivo contiene caracteres no permitidos.');
        }

        return $limpio;
    }

    public static function validarEstado(mixed $estado): string
    {
        if (!is_string($estado) || !in_array($estado, self::ESTADOS_PERMITIDOS, true)) {
            self::responderError(422, 'El estado de la cita no es válido.');
        }
        return $estado;
    }

    public static function responderError(int $codigo, string $mensaje): never
    {
        http_response_code($codigo);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => $mensaje], JSON_UNESCAPED_UNICODE);
        exit;
    }
}