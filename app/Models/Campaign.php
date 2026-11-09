<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Chiến dịch thu, vd "Thu quỹ năm 2026". Xem plan/05. */
class Campaign extends Model
{
    use BelongsToGroup;

    protected $fillable = [
        'category_id', 'name', 'slug', 'transfer_code', 'amount_per_member', 'target_amount',
        'starts_on', 'ends_on', 'status', 'description', 'cover_path', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount_per_member' => 'integer',
            'target_amount' => 'integer',
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(CampaignMember::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
