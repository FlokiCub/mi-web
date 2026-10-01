<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\RegionalWarehouse;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    /**
     * Display a listing of users with search and filter.
     */
    public function index(Request $request): View
    {
        $search = $request->query('search');
        $roleFilter = $request->query('role');
        $statusFilter = $request->query('status');

        $query = User::with('regionalWarehouse');

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%");
            });
        }

        if (!empty($roleFilter) && UserRole::tryFrom((string) $roleFilter)) {
            $query->where('role', $roleFilter);
        }

        if ($statusFilter !== null && $statusFilter !== '') {
            $query->where('is_active', $statusFilter === '1');
        }

        $users = $query->latest()->paginate(10)->withQueryString();
        $roles = UserRole::cases();

        return view('admin.users.index', compact('users', 'search', 'roleFilter', 'statusFilter', 'roles'));
    }

    /**
     * Show form to create a new user.
     */
    public function create(): View
    {
        $roles = UserRole::cases();
        $warehouses = RegionalWarehouse::where('is_active', true)->orderBy('name')->get();

        return view('admin.users.create', compact('roles', 'warehouses'));
    }

    /**
     * Store newly created user.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:50', 'alpha_dash', 'unique:users,username'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'regional_warehouse_id' => ['nullable', 'uuid', 'exists:regional_warehouses,id'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'name.required' => 'El nombre completo es obligatorio.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.unique' => 'Este correo electrónico ya está registrado.',
            'username.unique' => 'Este nombre de usuario ya está en uso.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.min' => 'La contraseña debe tener al menos 6 caracteres.',
            'role.required' => 'Debes seleccionar un rol para el usuario.',
            'regional_warehouse_id.exists' => 'El almacén seleccionado no es válido.',
        ]);

        User::create([
            'name' => $validated['name'],
            'username' => $validated['username'] ?: null,
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'regional_warehouse_id' => $validated['regional_warehouse_id'] ?? null,
            'is_active' => $request->boolean('is_active', true),
            'email_verified_at' => now(),
        ]);

        return redirect()->route('admin.users.index')
            ->with('success', 'Usuario "' . $validated['name'] . '" creado exitosamente.');
    }

    /**
     * Show form to edit user.
     */
    public function edit(User $user): View
    {
        $roles = UserRole::cases();
        $warehouses = RegionalWarehouse::where('is_active', true)->orderBy('name')->get();

        return view('admin.users.edit', compact('user', 'roles', 'warehouses'));
    }

    /**
     * Update specified user in storage.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:50', 'alpha_dash', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['nullable', 'string', 'min:6'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'regional_warehouse_id' => ['nullable', 'uuid', 'exists:regional_warehouses,id'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'name.required' => 'El nombre completo es obligatorio.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.unique' => 'Este correo ya pertenece a otro usuario.',
            'username.unique' => 'Este nombre de usuario ya pertenece a otra cuenta.',
            'password.min' => 'La contraseña debe tener al menos 6 caracteres.',
            'regional_warehouse_id.exists' => 'El almacén seleccionado no es válido.',
        ]);

        // Evitar que el director se retire a sí mismo el rol de director
        if (Auth::id() === $user->id && $validated['role'] !== UserRole::DIRECTOR->value && $user->isDirector()) {
            return back()->withInput()->with('error', 'No puedes despojarte a ti mismo del rol de Director General.');
        }

        $userData = [
            'name' => $validated['name'],
            'username' => $validated['username'] ?: null,
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'role' => $validated['role'],
            'regional_warehouse_id' => $validated['regional_warehouse_id'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ];

        if (!empty($validated['password'])) {
            $userData['password'] = Hash::make($validated['password']);
        }

        $user->update($userData);

        return redirect()->route('admin.users.index')
            ->with('success', 'Usuario "' . $user->name . '" actualizado correctamente.');
    }

    /**
     * Remove specified user.
     */
    public function destroy(User $user): RedirectResponse
    {
        if (Auth::id() === $user->id) {
            return back()->with('error', 'No puedes eliminar tu propia cuenta activa.');
        }

        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', 'Usuario eliminado satisfactoriamente.');
    }

    /**
     * Toggle user active status.
     */
    public function toggleStatus(User $user): RedirectResponse
    {
        if (Auth::id() === $user->id) {
            return back()->with('error', 'No puedes desactivar tu propia cuenta activa.');
        }

        $user->update([
            'is_active' => !$user->is_active,
        ]);

        $statusLabel = $user->is_active ? 'activado' : 'desactivado';

        return redirect()->route('admin.users.index')
            ->with('success', "Usuario \"{$user->name}\" {$statusLabel} con éxito.");
    }
}
