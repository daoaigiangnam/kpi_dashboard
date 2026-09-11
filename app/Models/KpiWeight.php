<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KpiWeight extends Model
{
    protected $table = 'kpi_weights';

    protected $fillable = ['code', 'name', 'weight', 'sort_order'];

    protected $casts = ['weight' => 'integer', 'sort_order' => 'integer'];
}
