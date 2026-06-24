<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class AdminController extends Controller
{
    public function index()
    {
        $admins = Admin::get();

        $admins->transform(function ($admin) {
            $admin->image = $admin->image
                ? asset('storage/'.$admin->image)
                : null;

            return $admin;
        });

        return response()->json([
            'status' => 'success',
            'admin' => $admins,
        ]);
    }

    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:admins,email',
            'password' => 'required|string|min:6',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        $imagePath = $request->hasFile('image')
            ? $request->file('image')->store('admins', 'public')
            : null;

        $admin = Admin::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'image' => $imagePath,
            'status' => 1,
        ]);

        $token = $admin->createToken('admin-token')->plainTextToken;

        $admin->assignRole($request->role);

        $admin->makeHidden('password');

        return response()->json([
            'status' => 'success admin register',
            'admin' => $admin,
            'token' => explode('|', $token)[1],
        ]);
    }

    public function login(Request $request)
    {

        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        $admin = Admin::where('email', $request->email)->first();

        if (! $admin || ! Hash::check($request->password, $admin->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid credentials',
            ], 401);
        }

        $token = $admin->createToken('admin-token')->plainTextToken;

        return response()->json([
            'status' => 'success admin login successfull',
            'admin' => $admin,
            'token' => explode('|', $token)[1],
        ], 200);
    }

    public function edit($id)
    {
        $admin = Admin::findOrFail($id);

        return response()->json($admin);
    }

    public function update(Request $request, $id)
    {
        $admin = Admin::find($id);

        if (! $admin) {
            return response()->json([
                'status' => 'error',
                'message' => 'Admin not found',
            ], 404);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:admins,email,'.$id,
            'password' => 'nullable|string|min:6',
            'role' => 'required|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        if ($request->hasFile('image')) {

            if ($admin->image && Storage::disk('public')->exists($admin->image)) {
                Storage::disk('public')->delete($admin->image);
            }

            $imagePath = $request->file('image')->store('admins', 'public');
            $admin->image = $imagePath;
        }

        $admin->name = $request->name;
        $admin->email = $request->email;
        $admin->role = $request->role;

        if ($request->filled('password')) {
            $admin->password = Hash::make($request->password);
        }

        $admin->save();

        $admin->syncRoles([$request->role]);

        $admin->makeHidden('password');

        return response()->json([
            'status' => 'success',
            'message' => 'Admin updated successfully',
            'admin' => $admin,
        ]);
    }

    public function destroy($id)
    {

        $admin = Admin::findOrFail($id);

        if ($admin->image && file_exists(public_path('uploads/admins/'.$admin->image))) {
            unlink(public_path('uploads/admins/'.$admin->image));
        }

        $admin->delete();

        return response()->json([
            'message' => 'Admin deleted successfully',
        ]);

    }
}
