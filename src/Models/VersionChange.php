<?php

namespace Pelican\Versions\Models;

use App\Models\Server;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VersionChange extends Model
{
    public const STATUS_PENDING  = 'pending';
    public const STATUS_CHANGING = 'changing';
    public const STATUS_DONE     = 'done';
    public const STATUS_FAILED   = 'failed';

    protected $table = 'version_changes';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'server_id'     => 'integer',
            'build_number'  => 'integer',
            'jar_size'      => 'integer',
            'clean_install' => 'boolean',
            'created_at'    => 'immutable_datetime',
            'updated_at'    => 'immutable_datetime',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_CHANGING]);
    }

    public static function staleThresholdMinutes(): int
    {
        return max(5, (int) config('versions.stale_after_minutes', 90));
    }

    public static function failStale(?int $serverId = null): int
    {
        $query = static::query()
            ->active()
            ->where('updated_at', '<', now()->subMinutes(static::staleThresholdMinutes()));

        if ($serverId !== null) {
            $query->where('server_id', $serverId);
        }

        return $query->update([
            'status'        => self::STATUS_FAILED,
            'error_message' => 'Marked as failed: no progress was reported (worker stopped or timed out).',
            'updated_at'    => now(),
        ]);
    }

    public function isStale(): bool
    {
        return $this->isActive()
            && $this->updated_at !== null
            && $this->updated_at->lt(now()->subMinutes(static::staleThresholdMinutes()));
    }

    public function appendLog(string $line): void
    {
        $timestamp = now()->format('H:i:s');
        $current   = $this->log ?? '';
        $this->update(['log' => $current . "[{$timestamp}] {$line}" . PHP_EOL]);
    }

    public function markChanging(): void
    {
        $this->update(['status' => self::STATUS_CHANGING]);
    }

    public function markDone(): void
    {
        $this->update(['status' => self::STATUS_DONE]);
    }

    public function markFailed(string $error): void
    {
        $this->update([
            'status'        => self::STATUS_FAILED,
            'error_message' => $error,
        ]);
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_CHANGING], true);
    }
}
