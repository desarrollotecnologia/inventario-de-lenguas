<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use stdClass;
use Symfony\Component\HttpKernel\Exception\HttpException;

class WorkcolbeefProvisioningController extends Controller
{
    private const FIELDS = [
        'workcolbeef_user_id',
        'username',
        'name',
        'email',
        'role',
        'permissions',
        'active',
    ];

    private const ROLES = [
        'admin',
        'supervisor',
        'gerencia',
        'logistico',
        'auxiliar',
        'planillaje',
        'calidad',
        'operador',
        'user',
    ];

    public function __invoke(Request $request): JsonResponse
    {
        $rawBody = $request->getContent();
        if (! $this->hasValidSignature($request, $rawBody)) {
            return response()->json(['error' => 'Firma de aprovisionamiento no válida.'], 401);
        }

        try {
            $payload = json_decode($rawBody, false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return response()->json(['error' => 'El JSON no es válido.'], 422);
        }

        if (($error = $this->payloadError($payload)) !== null) {
            return response()->json(['error' => $error], 422);
        }

        try {
            [$user, $created] = DB::transaction(fn (): array => $this->syncUser($payload), 3);
        } catch (HttpException $exception) {
            return response()->json(['error' => $exception->getMessage()], $exception->getStatusCode());
        } catch (QueryException $exception) {
            if (in_array((string) $exception->getCode(), ['23000', '23505'], true)) {
                return response()->json(['error' => 'El correo o usuario pertenece a otra cuenta local.'], 409);
            }

            throw $exception;
        }

        return response()->json([
            'ok' => true,
            'local_user_id' => (string) $user->id,
            'role' => $user->role,
            'active' => (bool) $user->is_active,
            'created' => $created,
        ], $created ? 201 : 200);
    }

    /** @return array{User, bool} */
    private function syncUser(stdClass $payload): array
    {
        $workcolbeefUserId = trim($payload->workcolbeef_user_id);
        $username = mb_strtolower(trim($payload->username));
        $email = $payload->email === null ? null : mb_strtolower(trim($payload->email));
        $user = User::query()
            ->where('workcolbeef_user_id', $workcolbeefUserId)
            ->lockForUpdate()
            ->first();

        if ($user === null) {
            $candidates = User::query()
                ->where(function ($query) use ($username, $email): void {
                    $query->whereRaw('LOWER(username) = ?', [$username]);
                    if ($email !== null) {
                        $query->orWhereRaw('LOWER(email) = ?', [$email]);
                    }
                })
                ->lockForUpdate()
                ->get();

            if ($candidates->count() > 1) {
                abort(409, 'El correo o usuario coincide con más de una cuenta local.');
            }
            $user = $candidates->first();
            if ($user?->workcolbeef_user_id !== null
                && $user->workcolbeef_user_id !== $workcolbeefUserId) {
                abort(409, 'La cuenta local ya está vinculada con otro usuario de WorkColbeef.');
            }
        }

        if ($user !== null) {
            $conflict = User::query()
                ->where('id', '<>', $user->id)
                ->whereRaw('LOWER(username) = ?', [$username])
                ->lockForUpdate()
                ->exists();
            if ($conflict) {
                abort(409, 'El usuario pertenece a otra cuenta local.');
            }
        }

        $created = $user === null;
        $attributes = [
            'workcolbeef_user_id' => $workcolbeefUserId,
            'username' => $username,
            'name' => trim($payload->name),
            'email' => $email,
            'role' => $user?->role === 'super_admin' ? 'super_admin' : $payload->role,
            'is_active' => $payload->active,
            'must_change_password' => false,
        ];

        if ($created) {
            $attributes['password'] = Hash::make(Str::random(64));
            $user = User::query()->create($attributes);
        } else {
            $user->update($attributes);
        }

        return [$user, $created];
    }

    private function hasValidSignature(Request $request, string $rawBody): bool
    {
        $secret = (string) config('services.workcolbeef.provisioning_secret', '');
        $timestamp = (string) $request->header('X-Workcolbeef-Timestamp', '');
        $signature = (string) $request->header('X-Workcolbeef-Signature', '');

        if (strlen($secret) < 32 || ! preg_match('/^\d{10}$/', $timestamp)
            || abs(time() - (int) $timestamp) > (int) config('services.workcolbeef.provisioning_max_skew', 300)
            || ! preg_match('/^[a-f0-9]{64}$/i', $signature)) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', "{$timestamp}.{$rawBody}", $secret), mb_strtolower($signature));
    }

    private function payloadError(mixed $payload): ?string
    {
        if (! $payload instanceof stdClass) {
            return 'El JSON debe contener exactamente los campos del contrato.';
        }

        $fields = array_keys(get_object_vars($payload));
        $expected = self::FIELDS;
        sort($fields);
        sort($expected);
        if ($fields !== $expected) {
            return 'El JSON debe contener exactamente los campos del contrato.';
        }

        foreach (['workcolbeef_user_id', 'username', 'name', 'role'] as $field) {
            if (! is_string($payload->{$field}) || trim($payload->{$field}) === '') {
                return "{$field} debe ser un texto no vacío.";
            }
        }

        if (mb_strlen(trim($payload->workcolbeef_user_id)) > 255
            || mb_strlen(trim($payload->username)) > 255
            || mb_strlen($payload->name) > 255
            || ! in_array($payload->role, self::ROLES, true)) {
            return 'Uno de los campos de identidad o rol no es válido.';
        }

        if ($payload->email !== null
            && (! is_string($payload->email) || ! filter_var($payload->email, FILTER_VALIDATE_EMAIL)
                || mb_strlen($payload->email) > 255)) {
            return 'email debe ser un correo válido o null.';
        }

        if (! $payload->permissions instanceof stdClass || ! is_bool($payload->active)) {
            return 'permissions debe ser un objeto y active debe ser booleano.';
        }

        return null;
    }
}
