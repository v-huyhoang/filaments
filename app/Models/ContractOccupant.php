<?php

namespace App\Models;

use Database\Factories\ContractOccupantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractOccupant extends Model
{
    /** @use HasFactory<ContractOccupantFactory> */
    use HasFactory;

    protected $fillable = ['full_name', 'move_in_date', 'move_out_date'];

    protected function casts(): array
    {
        return ['move_in_date' => 'immutable_date', 'move_out_date' => 'immutable_date'];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }
}
