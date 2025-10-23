<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    // ✅ Lấy danh sách tất cả user
    public function index()
    {
        $users = User::select('id', 'name', 'email', 'phone', 'username', 'roles', 'status', 'created_at')
            ->orderBy('id', 'desc')
            ->get();

        return response()->json($users);
    }

    // ✅ Lấy chi tiết user theo id
    public function show($id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }
        return response()->json($user);
    }

    // ✅ Tạo user mới (Admin thêm user)
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:ptdt_user,email',
            'phone'    => 'nullable|string|max:20',
            'username' => 'required|string|max:100|unique:ptdt_user,username',
            'password' => 'required|string|min:6',
            'roles'    => 'required|string|in:customer,admin',
            'status'   => 'nullable|integer',
        ]);

        $data['password'] = Hash::make($data['password']);
        $data['status'] = $data['status'] ?? 1;

        $user = User::create($data);

        return response()->json([
            'message' => 'Tạo user thành công',
            'user'    => $user,
        ], 201);
    }

    // ✅ Cập nhật user
    public function update(Request $request, $id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $data = $request->validate([
            'name'     => 'nullable|string|max:255',
            'email'    => 'nullable|email|unique:ptdt_user,email,' . $id,
            'phone'    => 'nullable|string|max:20',
            'username' => 'nullable|string|max:100|unique:ptdt_user,username,' . $id,
            'password' => 'nullable|string|min:6',
            'roles'    => 'nullable|string|in:customer,admin',
            'status'   => 'nullable|integer',
        ]);

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        return response()->json([
            'message' => 'Cập nhật user thành công',
            'user'    => $user,
        ]);
    }

    // ✅ Xoá user
    public function destroy($id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $user->delete();

        return response()->json(['message' => 'Xoá user thành công']);
    }

    // ✅ Khoá user
public function lock($id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $user->status = 0;
        $user->save();

        return response()->json(['message' => 'User đã bị khoá']);
    }

    // ✅ Mở khoá user
    public function unlock($id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $user->status = 1;
        $user->save();

        return response()->json(['message' => 'User đã được mở khoá']);
    }
}