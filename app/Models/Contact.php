<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    // Bảng hiện có trong DB của bạn
    protected $table = 'ptdt_contact';

    protected $fillable = [
        'user_id','name','email','phone','title','content',
        'reply_content','reply_id','created_by','updated_by','status',
    ];

    public $timestamps = true;
}
