<?php

namespace AlamiaSoft\AlamiaAccounts\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Display a listing of the users.
     */
    public function index(): JsonResponse
    {
        $users = User::select('id', 'name', 'email', 'role', 'status', 'created_at')
            ->orderBy('id', 'asc')
            ->get();

        return response()->json($users);
    }

    /**
     * Store a newly created user in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'nullable|string|min:6',
            'role' => ['nullable', Rule::in(['admin', 'accountant', 'viewer'])],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password'] ?? 'password'),
            'role' => $validated['role'] ?? 'accountant',
            'status' => $validated['status'] ?? 'active',
        ]);

        return response()->json($user, 201);
    }

    /**
     * Display the specified user.
     */
    public function show($id): JsonResponse
    {
        $user = User::select('id', 'name', 'email', 'role', 'status', 'created_at')->findOrFail($id);

        return response()->json($user);
    }

    /**
     * Update the specified user in storage.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:6',
            'role' => ['nullable', Rule::in(['admin', 'accountant', 'viewer'])],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        return response()->json($user);
    }

    /**
     * Remove the specified user from storage.
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $user = User::findOrFail($id);

        // Prevent deleting the currently authenticated user
        if ($request->user() && $request->user()->id == $user->id) {
            return response()->json(['message' => 'You cannot delete your own user account.'], 422);
        }

        $user->delete();

        return response()->json(['message' => 'User deleted successfully.']);
    }
}
