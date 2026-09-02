<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    use SoftDeletes;

    protected $fillable = ['code','name','address','phone','tax_code','description'];

    public function users(): HasMany { return $this->hasMany(User::class); }
}
