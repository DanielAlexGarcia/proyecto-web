<?php
//ini_set('display_errors', '1');
//error_reporting(E_ALL);
require_once __DIR__ . '/../php/auth/autenticacion.php';

$usuario = usuarioActual();

if (!$usuario || !isset($usuario['tipo'])) {
    header('Location: login');
    exit;
}

if ($usuario['tipo'] === 'paciente') {
    require __DIR__ . '/homePaciente.php';
} elseif ($usuario['tipo'] === 'personal') {
    require __DIR__ . '/homePersonal.php';
} else {
    header('Location: login');
    exit;
}