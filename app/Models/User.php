<?php

// namespace App\Models;

// use Illuminate\Foundation\Auth\User as Authenticatable;
// use Illuminate\Notifications\Notifiable;
// use Laravel\Sanctum\HasApiTokens;
// use Illuminate\Database\Eloquent\Factories\HasFactory;

// class User extends Authenticatable
// {
//     use HasApiTokens, HasFactory, Notifiable;

//     protected $table = 'ptdt_user';

//     protected $fillable = [
//         'name','email','password','phone','username',
//         'address','avatar','roles','status','created_by',
//     ];

//     protected $hidden = ['password','remember_token'];

//     protected function casts(): array
//     {
//         return [
//             'email_verified_at' => 'datetime',
//             'password' => 'hashed',
//         ];
//     }
// }


namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    // ✅ Bảng người dùng tuỳ chỉnh
    protected $table = 'ptdt_user';

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'username',
        'address',
        'avatar',
        'roles',
        'status',
        'created_by',
    ];

    // ✅ Ẩn mật khẩu khi trả về JSON
    protected $hidden = [
        'password',
        'remember_token',
    ];

    // ✅ Kiểu dữ liệu tự động
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];
}
