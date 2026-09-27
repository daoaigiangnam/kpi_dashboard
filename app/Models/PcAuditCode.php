<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PcAuditCode extends Model
{
    use HasFactory;

    protected $fillable = ['branch_id', 'code', 'name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(CustomerBranch::class, 'branch_id');
    }

    public function audits(): HasMany
    {
        return $this->hasMany(PcAudit::class, 'pc_audit_code_id');
    }
}
