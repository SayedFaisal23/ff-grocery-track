<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Kategori extends Model
{
    protected $table = 'categories';

    protected $fillable = [
        'nama',
    ];

    public function inventori(): HasMany
    {
        return $this->hasMany(Inventori::class, 'kategori_id');
    }
}
