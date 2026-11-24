<?php
require_once __DIR__ . '/jwt.php';

// ⚠️ Define JWT_SECRET como variable de entorno (mínimo 32 caracteres aleatorios).
// Genera uno con: php -r "echo bin2hex(random_bytes(32));"
// El valor de respaldo es SOLO para desarrollo local.
$secret = getenv('JWT_SECRET');
if (!$secret || strlen($secret) < 32) {
    http_response_code(500);
    exit('Configuración inválida: falta JWT_SECRET.');
}
define('JWT_SECRET', $secret);
define('JWT_COOKIE', 'jwt');
define('JWT_TTL', 60 * 60 * 2); // 2 horas

/** Crea el token y lo guarda en una cookie HttpOnly. */
function emitirToken(array $claims): string
{
    $ahora = time();
    $payload = array_merge($claims, [
        'iat' => $ahora,
        'exp' => $ahora + JWT_TTL,
    ]);

    $token = JWT::encode($payload, JWT_SECRET);

    setcookie(JWT_COOKIE, $token, [
        'expires'  => $payload['exp'],
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,          // JS no puede leerla (mitiga XSS)
        'samesite' => 'Strict',      // mitiga CSRF
    ]);

    return $token;
}

/** Borra la cookie del token. */
function destruirToken(): void
{
    setcookie(JWT_COOKIE, '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    unset($_COOKIE[JWT_COOKIE]);
}

/** Obtiene el token del header Authorization: Bearer o de la cookie. */
function obtenerTokenDeRequest(): ?string
{
    $header = $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        ?? (function_exists('getallheaders') ? (getallheaders()['Authorization'] ?? '') : '');

    if ($header && preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
        return $m[1];
    }
    return $_COOKIE[JWT_COOKIE] ?? null;
}

/**
 * Devuelve los claims si el token es válido; si no, null.
 */
function usuarioActual(): ?array
{
    $token = obtenerTokenDeRequest();
    if (!$token) {
        return null;
    }
    try {
        return JWT::decode($token, JWT_SECRET);
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Para PÁGINAS (homePaciente.php, homePersonal.php):
 * redirige al login si no hay token válido o el tipo no coincide.
 */
function requerirSesionPagina(string $tipo, string $loginUrl = 'login.php'): array
{
    $u = usuarioActual();
    if (!$u || ($u['tipo'] ?? '') !== $tipo) {
        header('Location: ' . $loginUrl);
        exit;
    }
    return $u;
}

/**
 * Para ENDPOINTS JSON (altas.php, cambios.php, etc.):
 * responde 401/403 en JSON. $tipos = tipos de usuario permitidos.
 */
function requerirSesionApi(array $tipos = ['paciente', 'personal']): array
{
    $u = usuarioActual();

    if (!$u) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'No autenticado o token expirado.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!in_array($u['tipo'] ?? '', $tipos, true)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'No tienes permisos para esta acción.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    return $u;
}