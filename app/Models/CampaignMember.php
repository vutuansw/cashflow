<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Một người trong danh sách phải đóng của chiến dịch. */
class CampaignMember extends Model
{
    protected $fillable = ['campaign_id', 'contact_id', 'expected_amount', 'exempt', 'note'];

    protected function casts(): array
    {
        return ['expected_amount' => 'integer', 'exempt' => 'boolean'];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
