<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', User::class);

        $actor = $request->user();
        $query = User::query()->orderBy('name');

        if ($actor->esEmpleado()) {
            $query->where('rol', 'usuario');
        } elseif ($request->filled('rol')) {
            $query->where('rol', $request->string('rol')->toString());
        }

        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->toString().'%';
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', $search)
                    ->orWhere('email', 'like', $search);
            });
        }

        $perPage = min(max($request->integer('per_page', 15), 1), 100);

        return UserResource::collection($query->paginate($perPage)->withQueryString());
    }

    public function store(Request $request): UserResource
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'rol' => ['required', Rule::in(['administrador', 'empleado', 'usuario'])],
        ]);

        Gate::authorize('create', [User::class, $validated['rol']]);

        $user = User::query()->create($validated);
        $user->syncRoles($user->rol);

        return new UserResource($user);
    }

    public function show(User $usuario): UserResource
    {
        Gate::authorize('view', $usuario);

        return new UserResource($usuario);
    }

    public function update(Request $request, User $usuario): UserResource
    {
        $actor = $request->user();
        $nextRole = $request->input('rol', $usuario->rol);
        Gate::authorize('update', [$usuario, $nextRole]);

        $rules = [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($usuario->id),
            ],
        ];

        if ($actor->esAdmin()) {
            $rules['rol'] = ['sometimes', Rule::in(['administrador', 'empleado', 'usuario'])];
            $rules['password'] = ['sometimes', 'required', 'string', 'min:8'];
        } else {
            $rules['rol'] = ['prohibited'];
            $rules['password'] = ['prohibited'];
        }

        $validated = $request->validate($rules);
        $usuario->update($validated);

        if (array_key_exists('rol', $validated)) {
            $usuario->syncRoles($validated['rol']);
        }

        return new UserResource($usuario->refresh());
    }

    public function destroy(User $usuario): Response
    {
        Gate::authorize('delete', $usuario);
        $usuario->delete();

        return response()->noContent();
    }
}
