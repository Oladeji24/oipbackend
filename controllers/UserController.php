<?php
// UserController.php
// Controller for user management: list users, get user details, flag/ban users

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;

class UserController extends Controller
{
    // List all users (admin only)
    public function index(Request $request)
    {
        // Placeholder: Add admin check
        $users = User::all(['id', 'name', 'email', 'created_at', 'status']);
        return response()->json(['success' => true, 'users' => $users]);
    }

    // Get user details
    public function show($id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found'], 404);
        }
        return response()->json(['success' => true, 'user' => $user]);
    }

    // Flag or ban a user (admin only)
    public function flag(Request $request, $id)
    {
        // Placeholder: Add admin check
        $user = User::find($id);
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found'], 404);
        }
        $user->status = $request->input('status', 'flagged');
        $user->save();
        return response()->json(['success' => true, 'message' => 'User status updated']);
    }

    // Update user profile (email, password)
    public function updateProfile(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'email' => 'required|email|unique:users,email,' . $user->id,
            'password' => 'nullable|min:6',
        ]);
        $user->email = $data['email'];
        if (!empty($data['password'])) {
            $user->password = bcrypt($data['password']);
        }
        $user->save();
        return response()->json(['email' => $user->email]);
    }
}
