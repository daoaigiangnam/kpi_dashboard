<?php

namespace App\\Models;

use Illuminate\\Database\\Eloquent\\Model;
use Illuminate\\Database\\Eloquent\\Relations\\BelongsTo;

class SoftwareException extends Model
{
    protected $fillable = ['software_request_id','description','business_reason','risk_impact','scope','mitigation','rollback_plan','expires_at','owner_id','it_opinion','approval_status','approver_id','approved_at','closure_result','closed_at','created_by'];
    protected $casts = ['expires_at' => 'date', 'approved_at' => 'datetime', 'closed_at' => 'datetime'];

    public function request(): BelongsTo
    {
        return $this->belongsTo(SoftwareRequest::class, 'software_request_id');
    }
}
