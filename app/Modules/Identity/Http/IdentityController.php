<?php

namespace App\Modules\Identity\Http;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class IdentityController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100', 'email' => 'required|email|max:255|unique:users',
            'password' => ['required', 'confirmed', Password::min(10)],
        ]);
        $user = DB::transaction(function () use ($data) {
            $user = User::create($data);
            DB::table('accounts')->insert(['user_id' => $user->id, 'code' => "user:$user->id", 'balance' => 0]);

            return $user;
        });
        Auth::login($user);
        $request->session()->regenerate();

        return redirect('/markets');
    }

    public function login(Request $request)
    {
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        if (! Auth::attempt([...$data, 'suspended' => false])) {
            throw ValidationException::withMessages(['email' => 'Identifiants incorrects ou compte suspendu.']);
        }
        $request->session()->regenerate();

        return redirect()->intended('/markets');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    public function profile(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'current_password' => 'required|current_password',
            'password' => ['nullable', 'confirmed', Password::min(10)],
        ]);
        $user->name = $data['name'];
        $user->email = $data['email'];
        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }
        $user->save();

        return back()->with('success', 'Profil enregistré.');
    }

    public function suspend(Request $request, int $id)
    {
        abort_unless($request->user()->role === 'admin', 403);
        abort_if($id === $request->user()->id, 422, 'Vous ne pouvez pas suspendre votre propre compte.');
        $data = $request->validate(['suspended' => 'required|boolean']);
        User::findOrFail($id);
        DB::table('users')->where('id', $id)->update(['suspended' => $data['suspended']]);

        return back()->with('success', 'Accès utilisateur mis à jour.');
    }
}
