<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Mail;

class AuthController extends Controller
{
    // ================= Đăng nhập ADMIN =================
    public function adminLogin(Request $request)
    {
        $data = $request->validate([
            'email'    => ['required','email'],
            'password' => ['required','string'],
        ]);

        if (!Auth::attempt($data)) {
            return response()->json(['message' => 'Sai email hoặc mật khẩu'], 401);
        }

        $user = Auth::user();

        $role = strtolower((string)($user->roles ?? $user->role ?? ''));
        if ($role !== 'admin') {
            return response()->json(['message' => 'Chỉ Admin được phép đăng nhập'], 403);
        }

        // XÓA token cũ để tránh xung đột ability
        $user->tokens()->delete();

        // Token admin có ability 'admin'
        $token = $user->createToken('auth_admin', ['admin'])->plainTextToken;

        return response()->json([
            'message' => 'Đăng nhập Admin thành công',
            'token'   => $token,
            'user'    => [
                'id'       => $user->id,
                'name'     => $user->name,
                'email'    => $user->email,
                'phone'    => $user->phone,
                'username' => $user->username,
                'roles'    => $user->roles,
                'status'   => $user->status,
            ],
        ], 200);
    }

    // ================= Đăng ký =================
    public function register(Request $request)
    {
        $data = $request->validate([
            'name'     => ['required','string','max:100'],
            'email'    => ['required','email','max:255', Rule::unique('ptdt_user','email')],
            'password' => ['required','string','min:6','confirmed'],
            'phone'    => ['required','string','max:20'],
            'address'  => ['nullable','string','max:1000'],
            'avatar'   => ['nullable','string','max:255'],
        ]);

        $base = $this->makeBaseUsername($data['name'], $data['email']);
        $username = $this->uniqueUsername($base);

        $user = User::create([
            'name'       => $data['name'],
            'email'      => $data['email'],
            'password'   => $data['password'],
            'phone'      => $data['phone'],
            'username'   => $username,
            'address'    => $data['address'] ?? '',
            'avatar'     => $data['avatar'] ?? '',
            'roles'      => 'customer',
            'created_by' => 0,
            'status'     => 1,
        ]);

        return response()->json([
            'message' => 'Đăng ký thành công',
            'user'    => [
                'id'       => $user->id,
                'name'     => $user->name,
                'email'    => $user->email,
                'phone'    => $user->phone,
                'username' => $user->username,
                'roles'    => $user->roles,
                'status'   => $user->status,
            ],
        ], 201);
    }

    // ================= Đăng nhập KHÁCH =================
    public function login(Request $request)
    {
        $data = $request->validate([
            'email'    => ['required','email'],
            'password' => ['required','string'],
        ]);

        if (!Auth::attempt($data)) {
            return response()->json(['message' => 'Sai email hoặc mật khẩu'], 401);
        }

        $user = Auth::user();

        $role = strtolower((string)($user->roles ?? $user->role ?? ''));
        if ($role !== 'customer') {
            return response()->json(['message' => 'Tài khoản không phải khách hàng'], 403);
        }

        $token = $user->createToken('auth_customer', ['customer'])->plainTextToken;

        return response()->json([
            'message' => 'Đăng nhập thành công',
            'token'   => $token,
            'user'    => [
                'id'       => $user->id,
                'name'     => $user->name,
                'email'    => $user->email,
                'phone'    => $user->phone,
                'username' => $user->username,
                'roles'    => $user->roles,
                'status'   => $user->status,
            ],
        ], 200);
    }

    public function forgotPassword(Request $request)
    {
        $data = $request->validate(['email' => 'required|email']);

        $user = \App\Models\User::where('email', $data['email'])->first();
        if (!$user) {
            return response()->json(['message' => 'Email không tồn tại trong hệ thống'], 404);
        }

        $newPassword = Str::password(10);
        $user->password = $newPassword;
        $user->save();

        try {
            Mail::send('emails.new_password', [
                'user'        => $user,
                'newPassword' => $newPassword,
            ], function ($message) use ($user) {
                $message->to($user->email, $user->name ?? $user->username ?? 'User')
                        ->subject('Mật khẩu mới của bạn');
            });
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Gửi email thất bại. Vui lòng thử lại sau.',
                'error'   => $e->getMessage(),
            ], 500);
        }

        return response()->json(['message' => 'Đã gửi mật khẩu mới về email. Vui lòng kiểm tra hộp thư.']);
    }

    // ================= Đăng xuất =================
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Đăng xuất thành công']);
    }

    // ================= Helper =================
    private function makeBaseUsername(string $name, string $email): string
    {
        $base = Str::slug($name, '');
        if ($base === '') $base = Str::before($email, '@');
        return strtolower($base);
    }

    private function uniqueUsername(string $base): string
    {
        $username = $base; $i = 0;
        while (User::where('username', $username)->exists()) {
            $i++; $username = $base.$i;
        }
        return $username;
    }
}
