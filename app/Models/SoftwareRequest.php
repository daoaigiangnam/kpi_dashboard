<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SoftwareRequest extends Model
{
    protected $fillable = ['ticket_code','requester_id','hod_id','assigned_it_id','department','device_name','asset_code','software_name','vendor','version','business_purpose','request_type','license_path','permission_type','data_access','needed_at','usage_until','estimated_cost','budget_source','branch_type','status','hod_decision','hod_comment','it_conclusion','it_assessment','installation_result','installed_at','accepted_at','closed_at','created_by'];
    protected $casts = ['needed_at'=>'datetime','usage_until'=>'date','installed_at'=>'datetime','accepted_at'=>'datetime','closed_at'=>'datetime','estimated_cost'=>'decimal:2'];
    public function approvals(): HasMany { return $this->hasMany(SoftwareApproval::class)->orderBy('id'); }
    public function assessments(): HasMany { return $this->hasMany(SoftwareAssessment::class)->orderBy('id'); }
    public function installations(): HasMany { return $this->hasMany(SoftwareInstallation::class)->orderBy('id'); }
    public function checklists(): HasMany { return $this->hasMany(SoftwareChecklist::class)->orderBy('id'); }
    public function exceptions(): HasMany { return $this->hasMany(SoftwareException::class)->orderByDesc('id'); }
    public function attachments(): HasMany { return $this->hasMany(SoftwareAttachment::class)->orderByDesc('id'); }
    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requester_id'); }
    public function hod(): BelongsTo { return $this->belongsTo(User::class, 'hod_id'); }
    public function assignedIt(): BelongsTo { return $this->belongsTo(User::class, 'assigned_it_id'); }
}