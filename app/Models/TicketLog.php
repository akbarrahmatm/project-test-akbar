<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'user_id',
        'action'
    ];

    public function log(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, "ticket_id", "id");
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, "user_id", "id");
    }
}
