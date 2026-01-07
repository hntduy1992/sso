<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $fillable = [
        'id',
        'name',
        'slug',
        'sort',
        'is_active',
        'parent_id',
    ];
}
