<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Funcionarios extends Model
{
    use HasFactory;

    public function user()
    {
        return $this->belongsTo(User::class, 'id', 'users_id');
    }
}
