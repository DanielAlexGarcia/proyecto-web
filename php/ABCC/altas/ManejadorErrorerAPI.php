<?php

class ManejadorErroresApi
{
    private const TIPOS_VALIDOS = [
        'persona',
        'paciente',
        'paciente_existente',
        'paciente_transaccion',
        'empleado',
        'empleado_existente',
        'empleado_transaccion',
        'cita'
    ];

    public static function manejar(conexionBaseDatos $db, Throwable $e): void
    {
        if ($e instanceof ExcepcionCamposFaltantes) {
            $db->responder(422, [
                'ok'     => false,
                'error'  => $e->getMessage(),
                'faltan' => $e->faltan
            ]);
        }

        if ($e instanceof InvalidArgumentException) {
            if ($e->getMessage() === 'TIPO_INVALIDO') {
                $db->responder(400, [
                    'ok'      => false,
                    'error'   => 'Campo "tipo" inválido o ausente.',
                    'validos' => self::TIPOS_VALIDOS
                ]);
            }
            $db->responder(422, ['ok' => false, 'error' => $e->getMessage()]);
        }

        if ($e instanceof DomainException) {
            $codigo = $e->getCode() ?: 400;
            $db->responder($codigo, ['ok' => false, 'error' => $e->getMessage()]);
        }

        if ($e instanceof PDOException) {
            self::manejarPDO($db, $e);
        }

        $db->responder(500, [
            'ok'      => false,
            'error'   => 'Error interno.',
            'detalle' => $e->getMessage()
        ]);
    }

    private static function manejarPDO(conexionBaseDatos $db, PDOException $e): void
    {
        $codigoMySQL = $e->errorInfo[1] ?? 0;
        $sqlState    = $e->errorInfo[0] ?? '';

        if ($sqlState === '45000') {
            $db->responder(404, ['ok' => false, 'error' => $e->errorInfo[2] ?? $e->getMessage()]);
        } elseif ($codigoMySQL === 1062) {
            $db->responder(409, [
                'ok'      => false,
                'error'   => 'Registro duplicado (num_seguro, usuario o la persona ya tiene ese rol).',
                'detalle' => $e->getMessage()
            ]);
        } elseif ($codigoMySQL === 1452) {
            $db->responder(422, [
                'ok'      => false,
                'error'   => 'Llave foránea inválida: el paciente, doctor o persona indicado no existe.',
                'detalle' => $e->getMessage()
            ]);
        } else {
            $db->responder(500, [
                'ok'      => false,
                'error'   => 'Error de base de datos.',
                'detalle' => $e->getMessage()
            ]);
        }
    }
}