<?php
class JWT
{
    private static function b64e(string $d): string
    {
        return rtrim(strtr(base64_encode($d), '+/', '-_'), '=');
    }

    private static function b64d(string $d): string
    {
        $r = strlen($d) % 4;
        if ($r) $d .= str_repeat('=', 4 - $r);
        $out = base64_decode(strtr($d, '-_', '+/'), true);
        return $out === false ? '' : $out;
    }

    public static function encode(array $payload, string $key): string
    {
        $h = self::b64e(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
        $p = self::b64e(json_encode($payload, JSON_UNESCAPED_UNICODE));
        $s = self::b64e(hash_hmac('sha256', "$h.$p", $key, true));
        return "$h.$p.$s";
    }

    public static function decode(string $jwt, string $key): array
    {
        $partes = explode('.', $jwt);
        if (count($partes) !== 3) {
            throw new Exception('Token malformado.');
        }
        [$h, $p, $s] = $partes;

        $header = json_decode(self::b64d($h), true);
        if (!is_array($header) || ($header['alg'] ?? '') !== 'HS256') {
            throw new Exception('Algoritmo no permitido.');
        }

        $firmaEsperada = self::b64e(hash_hmac('sha256', "$h.$p", $key, true));
        if (!hash_equals($firmaEsperada, $s)) {
            throw new Exception('Firma inválida.');
        }

        $payload = json_decode(self::b64d($p), true);
        if (!is_array($payload)) {
            throw new Exception('Payload inválido.');
        }
        if (!isset($payload['exp']) || time() >= (int)$payload['exp']) {
            throw new Exception('Token expirado.');
        }
        return $payload;
    }
}