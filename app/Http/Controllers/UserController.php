<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index()
    {
        $users = User::select('id', 'name', 'email', 'role')->get();
        return Inertia::render('Admin/UsersList', [
            'users' => $users
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|in:admin,user'
        ]);
        try {
            User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => $request->role
            ]);
            return redirect()->route('admin.users.index')->with('success', 'User created successfully');
        } catch (\Exception $e) {
            return redirect()->route('admin.users.index')->with('error', $e->getMessage());
        }
    }

    public function destroy(User $user)
    {
        try {
            if (optional(Auth::user())->id === $user->id) {
                throw new \Exception('Cannot delete your own account');
            }
            if ($user->role === 'admin' && User::where('role', 'admin')->count() === 1) {
                throw new \Exception('Cannot delete the only admin user');
            }
            $user->delete();
            return redirect()->route('admin.users.index')->with('success', 'User deleted successfully');
        } catch (\Exception $e) {
            return redirect()->route('admin.users.index')->with('error', $e->getMessage());
        }
    }

    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:8|confirmed',
            'role' => 'required|in:admin,user'
        ]);
        try {
            $updateData = [
                'name' => $request->name,
                'email' => $request->email,
                'role' => $request->role
            ];
            if ($request->password) {
                $updateData['password'] = Hash::make($request->password);
            }
            $user->update($updateData);
            return redirect()->route('admin.users.index')->with('success', 'User updated successfully');
        } catch (\Exception $e) {
            return redirect()->route('admin.users.index')->with('error', $e->getMessage());
        }
    }
}
