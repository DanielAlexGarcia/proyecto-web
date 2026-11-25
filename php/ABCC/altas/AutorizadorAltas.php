<?php
class AutorizadorAltas
{
    private const SOLO_PERSONAL = [
        'persona',
        'paciente',
        'paciente_existente',
        'paciente_transaccion',
        'empleado',
        'empleado_existente',
        'empleado_transaccion'
    ];

    private const TIPOS_EMPLEADO = [
        'empleado',
        'empleado_existente',
        'empleado_transaccion'
    ];

    public static function verificarYSanitizar(array $usuario, string $tipo, array $datos): array
    {
        if (
            $usuario['tipo'] === 'personal' &&
            in_array($tipo, self::TIPOS_EMPLEADO, true) &&
            mb_strtolower($usuario['cargo'] ?? '') !== 'administrador'
        ) {
            throw new DomainException('Solo administración puede dar de alta empleados.', 403);
        }

        if ($usuario['tipo'] === 'paciente') {
            if (in_array($tipo, self::SOLO_PERSONAL, true)) {
                throw new DomainException('No tienes permisos para esta acción.', 403);
            }

            if ($tipo === 'cita') {
                $datos['paciente_id'] = (int) $usuario['paciente_id'];
                $datos['estado'] = 'Pendiente';
            }
        }

        return $datos;
    }
}