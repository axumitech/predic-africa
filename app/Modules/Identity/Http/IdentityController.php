<?php

namespace App\Modules\Identity\Http;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Identity\RegistrationRegions;
use App\Modules\Identity\RegistrationPhone;
use App\Modules\Identity\SessionAccess;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class IdentityController extends Controller
{
    public function registration(RegistrationRegions $regions)
    {
        return Inertia::render('Register', ['countries' => $regions->options()]);
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100', 'email' => 'required|email|max:255|unique:users',
            'password' => ['required', 'confirmed', Password::min(10)],
            'phone' => ['required', 'string', 'max:32'],
            'country' => ['bail', 'required', 'string', Rule::in(array_keys(config('regions.countries')))],
            'currency' => ['bail', 'required', 'string', Rule::in(app(RegistrationRegions::class)->currenciesFor($request->input('country')))],
        ], [
            'country.required' => 'Choisissez votre pays.',
            'country.in' => 'Ce pays n’est pas disponible à l’inscription.',
            'currency.required' => 'Choisissez la devise de votre portefeuille.',
            'currency.in' => 'Cette devise n’est pas disponible pour le pays choisi.',
            'phone.required' => 'Renseignez votre numéro de téléphone.',
        ]);
        $data['phone'] = app(RegistrationPhone::class)->normalize($data['country'], $data['phone']);
        try {
            $user = DB::transaction(function () use ($data) {
                $user = User::create(\Illuminate\Support\Arr::except($data, ['currency']));
                DB::table('accounts')->insert(['user_id' => $user->id, 'code' => "user:$user->id", 'currency' => $data['currency'], 'balance' => 0]);
                DB::table('payment_wallets')->insert(['user_id' => $user->id, 'currency' => $data['currency'], 'balance' => 0]);

                return $user->refresh();
            });
        } catch (UniqueConstraintViolationException $exception) {
            if (! User::where('email', $data['email'])->exists()) {
                throw $exception;
            }
            throw ValidationException::withMessages(['email' => 'Cette adresse e-mail est déjà utilisée.']);
        }
        Auth::login($user);
        $request->session()->regenerate();
        SessionAccess::establish($request, $user);

        return redirect('/dashboard');
    }

    public function login(Request $request)
    {
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        if (! Auth::attemptWhen([...$data, 'suspended' => false], fn (User $user) => in_array($user->role, ['trader', 'admin', 'superadmin'], true))) {
            throw ValidationException::withMessages(['email' => 'Identifiants incorrects ou compte suspendu.']);
        }
        $request->session()->regenerate();

        SessionAccess::establish($request, Auth::user());

        return redirect()->intended(Auth::user()->isSuperadmin() ? '/superadmin' : (Auth::user()->isAdministrator() ? '/admin' : '/dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Inertia::clearHistory();

        return redirect('/');
    }

    public function profile(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'current_password' => 'required|string',
            'password' => ['nullable', 'confirmed', Password::min(10)],
        ]);
        try {
            $user = DB::transaction(function () use ($user, $data) {
                $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
                abort_if($locked->suspended, 403, 'Compte suspendu.');
                if (! Hash::check($data['current_password'], $locked->password)) {
                    throw ValidationException::withMessages(['current_password' => 'Le mot de passe actuel est incorrect.']);
                }
                $locked->name = $data['name'];
                if ($locked->email !== $data['email']) {
                    $locked->email_verified_at = null;
                }
                $locked->email = $data['email'];
                if (! empty($data['password'])) {
                    $locked->password = Hash::make($data['password']);
                    $locked->auth_version++;
                    $locked->remember_token = Str::random(60);
                }
                $locked->save();

                return $locked;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => 'Cette adresse e-mail est déjà utilisée.']);
        }
        Auth::setUser($user);
        if (! empty($data['password'])) {
            $request->session()->regenerate(true);
            $request->session()->regenerateToken();
            SessionAccess::establish($request, $user);
            Inertia::clearHistory();
        }

        return back()->with('success', 'Profil enregistré.');
    }

    public function suspend(Request $request, int $id)
    {
        Gate::authorize('admin');
        abort_if($id === $request->user()->id, 422, 'Vous ne pouvez pas suspendre votre propre compte.');
        $data = $request->validate(['suspended' => 'required|boolean']);
        DB::transaction(function () use ($request, $id, $data) {
            // Lock actor and target in the same order for mutual suspensions.
            $users = User::whereIn('id', [$request->user()->id, $id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $actor = $users->get($request->user()->id);
            abort_unless($actor && $actor->isAdministrator() && ! $actor->suspended, 403);
            $target = $users->get($id);
            abort_unless($target, 404);
            abort_if($target->isSuperadmin() && ! $actor->isSuperadmin(), 403, 'Compte superadmin protégé.');
            $suspended = (bool) $data['suspended'];
            if ($suspended && ! $target->suspended) {
                $target->auth_version++;
                $target->remember_token = Str::random(60);
            }
            $target->suspended = $suspended;
            $target->save();
        }, 3);

        return back()->with('success', 'Accès utilisateur mis à jour.');
    }
}
