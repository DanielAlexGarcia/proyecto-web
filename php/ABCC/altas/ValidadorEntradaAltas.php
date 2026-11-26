<?php

require_once __DIR__ . '/../../validaciones/validacionesPersona.php';
require_once __DIR__ . '/../../validacionesCita.php';

class ValidadorEntradaAltas
{
    private const REQUERIDOS_POR_TIPO = [
        'persona'              => ['nombres', 'primer_apellido', 'fecha_nacimiento'],
        'paciente'             => ['id_persona', 'num_seguro'],
        'paciente_existente'   => ['id_persona', 'num_seguro', 'user', 'password'],
        'paciente_transaccion' => ['nombres', 'primer_apellido', 'fecha_nacimiento', 'num_seguro', 'user', 'password'],
        'empleado'             => ['id_persona', 'cargo', 'salario'],
        'empleado_existente'   => ['id_persona', 'cargo', 'salario', 'user', 'password'],
        'empleado_transaccion' => ['nombres', 'primer_apellido', 'fecha_nacimiento', 'cargo', 'salario', 'user', 'password'],
        'cita'                 => ['fecha_hora', 'motivo', 'paciente_id', 'doctor_id']
    ];

    public static function validar(string $tipo, array &$datos): void
    {
        if (!isset(self::REQUERIDOS_POR_TIPO[$tipo])) {
            return; // El enrutador manejará el tipo inválido
        }

        self::exigirCampos($datos, self::REQUERIDOS_POR_TIPO[$tipo]);

        switch ($tipo) {
            case 'persona':
                ValidadorPersonas::persona($datos);
                break;
            case 'paciente':
                ValidadorPersonas::paciente($datos);
                break;
            case 'paciente_existente':
                ValidadorPersonas::paciente($datos);
                ValidadorPersonas::credenciales($datos);
                break;
            case 'paciente_transaccion':
                ValidadorPersonas::persona($datos);
                ValidadorPersonas::paciente($datos);
                ValidadorPersonas::credenciales($datos);
                break;
            case 'empleado':
                ValidadorPersonas::empleado($datos);
                break;
            case 'empleado_existente':
                ValidadorPersonas::empleado($datos);
                ValidadorPersonas::credenciales($datos);
                break;
            case 'empleado_transaccion':
                ValidadorPersonas::persona($datos);
                ValidadorPersonas::empleado($datos);
                ValidadorPersonas::credenciales($datos);
                break;
            case 'cita':
                $datos['fecha_hora'] = ValidadorCitas::validarFechaHora($datos['fecha_hora']);
                $datos['motivo']     = ValidadorCitas::validarMotivo($datos['motivo']);
                $datos['estado']     = ValidadorCitas::validarEstado($datos['estado'] ?? 'Pendiente');
                $datos['doctor_id']  = ValidadorCitas::validarEnteroPositivo($datos['doctor_id'], 'doctor_id');
                break;
        }
    }

    private static function exigirCampos(array $datos, array $requeridos): void
    {
        $faltan = [];
        foreach ($requeridos as $campo) {
            if (!isset($datos[$campo]) || trim((string) $datos[$campo]) === '') {
                $faltan[] = $campo;
            }
        }

        if (!empty($faltan)) {
            throw new ExcepcionCamposFaltantes($faltan);
        }
    }
}

class ExcepcionCamposFaltantes extends InvalidArgumentException
{
    public function __construct(public array $faltan)
    {
        parent::__construct('Faltan campos obligatorios.');
    }
}