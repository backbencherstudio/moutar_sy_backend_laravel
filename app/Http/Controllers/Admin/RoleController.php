<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::all();

        return response()->json([
            'status' => true,
            'message' => 'Role list fetched successfully',
            'data' => $roles,
        ], 200);
    }

    public function store(Request $request)
    {

        $request->validate([
            'name' => 'required|string|max:225',
            'permissions' => 'array',
            'permissions.*' => 'integer|exists:permissions,id',
        ]);

        $role = Role::create([
            'name' => $request->name,
            'guard_name' => 'api',
        ]);

        if ($request->has('permissions')) {
            $role->syncPermissions($request->permissions);

        }

        return response()->json([
            'status' => true,
            'message' => 'Role created successfully',
            'data' => $role->load('permissions'),
        ], 201);
    }

    public function edit($id)
    {
        $role = Role::find($id);

        if (! $role) {
            return response()->json([
                'status' => false,
                'message' => 'Role not found',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Role fetched successfully',
            'data' => $role,
        ], 200);
    }

    public function update(Request $request, $id)
    {

        $request->validate([
            'name' => 'required|string|max:225',
        ]);

        $role = Role::find($id);

        if (! $role) {
            return response()->json([
                'status' => false,
                'message' => 'Role not found',
            ], 404);
        }

        $role->update([
            'name' => $request->name,
            'guard_name' => 'api',
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Role updated successfully',
            'data' => $role,
        ], 200);
    }
}
