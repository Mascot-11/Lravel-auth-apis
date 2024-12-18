<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Password;
use App\Notifications\CustomResetPasswordNotification; // Correct location of the import

class UserAuthController extends Controller
{
    // Login method
    public function login(Request $request)
    {
        // Validate input
        $validator = Validator::make($request->all(), [
            'email' => 'required|email', // Email is required and must be a valid email
            'password' => 'required|string', // Password is required and must be a string
        ]);

        // Check if validation fails
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Attempt to find the user by email
        $user = User::where('email', $request->email)->first();

        // If user doesn't exist or password doesn't match
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials , Please Try Again'
            ], 401); // Unauthorized
        }

        // Generate a new personal access token using Sanctum
        $token = $user->createToken('auth_token')->plainTextToken;

        // Return response with the generated token and user details
        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user, // Include user details, like name
            'message' => 'Login successful'
        ]);
    }

    // Register method
    public function register(Request $request)
    {
        // Validate input, including password confirmation
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',  // Name is required and must be a string
            'email' => 'required|email|unique:users,email|max:255',  // Email is required, unique, and must be a valid email
            'password' => 'required|string|min:6|confirmed',  // Password is required, must be at least 6 characters, and must match the confirmation
        ]);

        // Check if validation fails
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);  // Return validation errors
        }

        // Create a new user and hash the password
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),  // Hash the password before saving
        ]);

        // Generate a new personal access token using Sanctum
        $token = $user->createToken('auth_token')->plainTextToken;

        // Return response with the generated token
        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'message' => 'Signup successful'
        ]);
    }

    // Forgot Password method
    public function forgotPassword(Request $request)
{
    // Validate the email
    $request->validate([
        'email' => 'required|email|exists:users,email',
    ]);

    // Find the user by email
    $user = User::where('email', $request->email)->first();

    if ($user) {
        // Generate a password reset token
        $token = Password::createToken($user);

        // Send custom password reset notification with the token
        $user->notify(new CustomResetPasswordNotification($user, $token));

        return response()->json([
            'message' => 'Password reset link sent to your email address.'
        ], 200);
    }

    return response()->json([
        'message' => 'Unable to send reset link.'
    ], 500);
}
    // Reset Password method
    public function resetPassword(Request $request)
    {
        // Validate the input
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'token' => 'required|string', // Validate token
            'password' => 'required|string|min:8|confirmed', // Validate password
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Attempt to reset the password using the token
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) use ($request) {
                $user->password = Hash::make($request->password);
                $user->save();
            }
        );

        // Return response based on reset result
        if ($status === Password::PASSWORD_RESET) {
            return response()->json(['message' => 'Password successfully reset.'], 200);
        }

        return response()->json(['message' => 'Failed to reset password.'], 500);

    }
    public function users()
{
    // Fetch all users, you can adjust it to exclude sensitive data
    $users = User::all();

    if ($users->isEmpty()) {
        return response()->json(['message' => 'No users found'], 404);
    }

    return response()->json($users);
}


 public function createUser(Request $request)
{
    // Validate the request data
    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:users,email',
        'password' => 'required|string|min:6|confirmed', // 'confirmed' will automatically check password and confirm_password match
    ]);

    // Create a new user
    $user = User::create([
        'name' => $validated['name'],
        'email' => $validated['email'],
        'password' => bcrypt($validated['password']), // Hash the password
    ]);

    return response()->json($user, 201); // Return the created user with status 201
}

 public function updateUser(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Validate the incoming data
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|unique:users,email,' . $id,
            'password' => 'nullable|string|min:6',
        ]);

        // Update user details
        $user->update([
            'name' => $validated['name'] ?? $user->name,
            'email' => $validated['email'] ?? $user->email,
            'password' => isset($validated['password']) ? bcrypt($validated['password']) : $user->password,
        ]);

        return response()->json($user); // Return updated user data
    }
 public function deleteUser($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Delete the user
        $user->delete();

        return response()->json(['message' => 'User deleted successfully'], 200);
    }

}
