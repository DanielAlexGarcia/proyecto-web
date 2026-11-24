<?php
require_once __DIR__ . '/php/auth/autenticacion.php';
destruirToken();
header('Location: index.html');
exit;
?>