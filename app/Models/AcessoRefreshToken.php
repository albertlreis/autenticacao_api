<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcessoRefreshToken extends Model
{
    protected $table = 'acesso_refresh_tokens';

    protected $fillable = [
        'usuario_id',
        'token_hash',
        'expires_at',
        'revoked_at',
        'last_used_at',
        'created_ip',
        'created_user_agent',
        'replaced_by_id',
    ];

    protected $casts = [
        'expires_at'   => 'datetime',
        'revoked_at'   => 'datetime',
        'last_used_at' => 'datetime',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(AcessoUsuario::class, 'usuario_id');
    }

    public function isValid(): bool
    {
        return $this->revoked_at === null && $this->expires_at->isFuture();
    }
}
