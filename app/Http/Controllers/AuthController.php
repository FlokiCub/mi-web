<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Show login form.
     */
    public function showLogin()
    {
        if (Auth::check()) {
            return Auth::user()->isAdmin() 
                ? redirect()->route('admin.dashboard')
                : redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    /**
     * Handle login attempt.
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ], [
            'login.required' => 'Ingresa tu usuario o correo electrónico.',
            'password.required' => 'Ingresa tu contraseña.',
        ]);

        $loginInput = $credentials['login'];
        $password = $credentials['password'];
        $remember = $request->boolean('remember');

        // Determine if login input is email or username
        $fieldType = filter_var($loginInput, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        // Check if user exists and is active
        $user = User::where($fieldType, $loginInput)->first();

        if (!$user) {
            // Also check by username if not formatted like email
            if ($fieldType === 'email') {
                $user = User::where('email', $loginInput)->first();
            } else {
                $user = User::where('username', $loginInput)->orWhere('email', $loginInput)->first();
            }
        }

        if (!$user || !Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'login' => ['Las credenciales proporcionadas no son válidas.'],
            ]);
        }

        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'login' => ['Tu cuenta ha sido desactivada por el administrador.'],
            ]);
        }

        Auth::login($user, $remember);
        $request->session()->regenerate();

        if ($user->isAdmin()) {
            return redirect()->intended(route('admin.dashboard'))
                ->with('success', '¡Bienvenido al Panel de Administración, ' . $user->name . '!');
        }

        return redirect()->intended(route('dashboard'))
            ->with('success', '¡Bienvenido de nuevo, ' . $user->name . '!');
    }

    /**
     * Handle user logout.
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('info', 'Has cerrado sesión correctamente.');
    }
}
