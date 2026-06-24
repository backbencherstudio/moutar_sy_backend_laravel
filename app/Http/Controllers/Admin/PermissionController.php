<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionController extends Controller
{
    public function index()
    {
        $permissions = Permission::all();

        return response()->json([
            'status' => true,
            'message' => 'Permission list fetched successfully',
            'data' => $permissions,
        ], 200);
    }

    public function store(Request $request)
    {

        $role = Role::find($request->role_id);

        if (! $role) {
            return response()->json(['message' => 'Role not found'], 404);
        }

        $permissions = Permission::whereIn('id', $request->permission_id)->get();

        if ($permissions->count() !== count($request->permission_id)) {
            return response()->json(['message' => 'Some permissions not found'], 404);
        }

        $role->syncPermissions($permissions);

        return response()->json([
            'message' => 'Permissions assigned successfully',
            'role' => $role->name,
            'permissions' => $permissions->pluck('name'),
        ]);
    }
}
