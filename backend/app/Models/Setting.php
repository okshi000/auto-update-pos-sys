<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'description',
        'is_public',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_public' => 'boolean',
    ];

    /**
     * Get setting value with proper type casting.
     */
    public function getTypedValueAttribute(): mixed
    {
        return match ($this->type) {
            'integer' => (int) $this->value,
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($this->value, true),
            'float' => (float) $this->value,
            default => $this->value,
        };
    }

    /**
     * Set value with proper type handling.
     */
    public function setTypedValue(mixed $value): void
    {
        $this->value = match ($this->type) {
            'json' => json_encode($value),
            'boolean' => $value ? 'true' : 'false',
            default => (string) $value,
        };
    }

    /**
     * Get a setting value by key.
     */
    public static function getValue(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();

        return $setting ? $setting->typed_value : $default;
    }

    /**
     * Set a setting value by key.
     */
    public static function setValue(string $key, mixed $value): void
    {
        $setting = static::where('key', $key)->first();

        if ($setting) {
            $setting->setTypedValue($value);
            $setting->save();
        }
    }

    /**
     * Scope: Filter by group.
     */
    public function scopeByGroup($query, string $group)
    {
        return $query->where('group', $group);
    }

    /**
     * Scope: Only public settings.
     */
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }
}
