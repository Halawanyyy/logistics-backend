<?php

namespace App\Modules\UserManagement\Controllers;

use App\Models\User;
use App\Modules\RolePermission\Services\UserRoleAssignmentService;
use App\Modules\UserManagement\Enums\UserRole;
use App\Modules\UserManagement\Requests\StoreUserRequest;
use App\Modules\UserManagement\Requests\UpdateUserRequest;
use App\Modules\UserManagement\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Hash;

class UserController
{
    public function __construct(
        private readonly UserRoleAssignmentService $userRoleAssignmentService,
    ) {
    }

    public function index(): AnonymousResourceCollection
    {
        $users = User::query()
            ->with('roleRelation.permissions')
            ->latest()
            ->get();

        return UserResource::collection($users);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $attributes = $this->userRoleAssignmentService->applyRoleAttributes([
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->toString(),
            'password' => Hash::make($request->string('password')->toString()),
            'role' => $request->input('role', UserRole::STAFF),
            'role_id' => $request->input('role_id'),
        ]);

        $user = User::create($attributes);

        return (new UserResource($user->load('roleRelation.permissions')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(User $user): UserResource
    {
        return new UserResource($user->load('roleRelation.permissions'));
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $validated = $request->validated();

        if (array_key_exists('password', $validated) && filled($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $validated = $this->userRoleAssignmentService->applyRoleAttributes($validated);
        $user->update($validated);

        return new UserResource($user->fresh()->load('roleRelation.permissions'));
    }

    public function destroy(User $user): JsonResponse
    {
        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully',
        ]);
    }
}
