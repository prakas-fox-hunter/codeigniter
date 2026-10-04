<?php

namespace App\Libraries;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class Auth
{
    private static function secret(): string
    {
        $secret = (string) env('JWT_SECRET', '');
        if (strlen($secret) < 32) {
            throw new \RuntimeException('JWT_SECRET must contain at least 32 characters.');
        }
        return $secret;
    }

    public static function issue(array $user): string
    {
        return JWT::encode([
            'iss' => config('App')->baseURL, 'aud' => 'skybook', 'iat' => time(),
            'nbf' => time(), 'exp' => time() + 3600, 'sub' => (string) $user['id'],
            'ver' => (int) $user['token_version'],
        ], self::secret(), 'HS256');
    }

    public static function user(): ?array
    {
        try {
            $header = service('request')->getHeaderLine('Authorization');
            if (!preg_match('/^Bearer\s+(\S+)$/i', $header, $matches)) {
                return null;
            }
            $claims = JWT::decode($matches[1], new Key(self::secret(), 'HS256'));
            if ($claims->iss !== config('App')->baseURL || $claims->aud !== 'skybook') {
                return null;
            }
            $user = db_connect()->table('users')->where('id', $claims->sub)->get()->getRowArray();
            return $user && (int) $user['token_version'] === $claims->ver ? $user : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function publicUser(array $user): array
    {
        unset($user['password_hash'], $user['token_version']);
        return $user;
    }
}
