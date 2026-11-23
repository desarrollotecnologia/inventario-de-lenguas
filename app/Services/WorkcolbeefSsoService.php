<?php

namespace App\Services;

use App\Exceptions\WorkColbeefSsoException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class WorkcolbeefSsoService
{
    /** @return array{sub: string, username: string, name: string, email: string|null} */
    public function exchange(string $code): array
    {
        $baseUrl = rtrim((string) config('services.workcolbeef.base_url'), '/');
        $path = '/'.ltrim((string) config('services.workcolbeef.exchange_path'), '/');
        $clientId = trim((string) config('services.workcolbeef.client_id'));
        $secret = (string) config('services.workcolbeef.client_secret');
        $audience = trim((string) config('services.workcolbeef.audience'));
        $timeout = max(1, min((float) config('services.workcolbeef.timeout', 5), 5));

        if (! filter_var($baseUrl, FILTER_VALIDATE_URL)
            || $clientId === ''
            || $secret === ''
            || $audience === '') {
            throw new WorkColbeefSsoException('Configuración SSO incompleta.');
        }

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->timeout($timeout)
                ->connectTimeout(min($timeout, 3))
                ->withHeaders([
                    'X-SSO-Client-Id' => $clientId,
                    'X-SSO-Client-Secret' => $secret,
                ])
                ->post($baseUrl.$path, [
                    'code' => $code,
                    'audience' => $audience,
                ]);
        } catch (ConnectionException) {
            throw new WorkColbeefSsoException('WorkColbeef no está disponible.');
        }

        $identity = $response->json();
        if (! $response->successful()
            || ! is_array($identity)
            || ($identity['active'] ?? false) !== true
            || ! is_scalar($identity['sub'] ?? null)
            || ! is_string($identity['username'] ?? null)) {
            throw new WorkColbeefSsoException('El código SSO fue rechazado.');
        }

        $sub = trim((string) $identity['sub']);
        $username = mb_strtolower(trim($identity['username']));
        $name = is_string($identity['name'] ?? null) ? trim($identity['name']) : $username;
        $email = is_string($identity['email'] ?? null) ? mb_strtolower(trim($identity['email'])) : null;

        if ($sub === '' || mb_strlen($sub) > 255 || $username === '' || mb_strlen($username) > 255) {
            throw new WorkColbeefSsoException('La identidad SSO no es válida.');
        }

        return [
            'sub' => $sub,
            'username' => $username,
            'name' => $name !== '' ? $name : $username,
            'email' => $email !== '' ? $email : null,
        ];
    }
}
