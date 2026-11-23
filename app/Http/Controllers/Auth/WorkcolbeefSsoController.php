<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\WorkColbeefSsoException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\WorkcolbeefSsoService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class WorkcolbeefSsoController extends Controller
{
    public function __construct(private readonly WorkcolbeefSsoService $sso) {}

    public function __invoke(Request $request): RedirectResponse
    {
        if (! config('services.workcolbeef.enabled')) {
            return $this->fallback('El acceso con WorkColbeef no está habilitado.');
        }

        $validator = Validator::make($request->query(), [
            'sso_code' => ['required', 'string', 'min:32', 'max:200'],
        ]);
        if ($validator->fails()) {
            return $this->fallback('El acceso con WorkColbeef no es válido.');
        }

        try {
            $identity = $this->sso->exchange($validator->validated()['sso_code']);
            $user = DB::transaction(fn (): User => $this->resolveUser($identity));
        } catch (WorkColbeefSsoException|QueryException) {
            return $this->fallback('No fue posible ingresar con WorkColbeef. Usa tu acceso local.');
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('menu');
    }

    /** @param array{sub: string, username: string, name: string, email: string|null} $identity */
    private function resolveUser(array $identity): User
    {
        $linked = User::query()
            ->where('workcolbeef_user_id', $identity['sub'])
            ->lockForUpdate()
            ->first();
        if ($linked !== null) {
            if (! $linked->is_active) {
                throw new WorkColbeefSsoException('Usuario inactivo.');
            }

            return $linked;
        }

        $candidates = User::query()
            ->where('is_active', true)
            ->where(function ($query) use ($identity): void {
                $query->whereRaw('LOWER(username) = ?', [$identity['username']]);
                if ($identity['email'] !== null) {
                    $query->orWhereRaw('LOWER(email) = ?', [$identity['email']]);
                }
            })
            ->lockForUpdate()
            ->limit(2)
            ->get();

        if ($candidates->count() !== 1) {
            throw new WorkColbeefSsoException('No existe una cuenta local inequívoca.');
        }

        $user = $candidates->firstOrFail();
        if ($user->workcolbeef_user_id !== null
            && $user->workcolbeef_user_id !== $identity['sub']) {
            throw new WorkColbeefSsoException('La cuenta local ya está vinculada.');
        }

        $user->update([
            'workcolbeef_user_id' => $identity['sub'],
            'must_change_password' => false,
        ]);

        return $user;
    }

    private function fallback(string $message): RedirectResponse
    {
        return redirect()->route('login')->withErrors(['username' => $message]);
    }
}
