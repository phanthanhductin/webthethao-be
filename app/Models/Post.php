<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    protected $table = 'ptdt_post';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'title','slug','image_url','summary','content','category_id','status'
    ];
}
