<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    protected $guarded = [];

    public static function valueOf(string $key, string $default = ''): string
    {
        return static::find($key)?->value ?? $default;
    }

    public static function softwareName(): string
    {
        return static::valueOf('software_name', 'BDU Media');
    }

    public static function footerText(): string
    {
        return static::valueOf('footer_text', static::valueOf('organization_name', 'BDU Media'));
    }

    public static function logoUrl(): ?string
    {
        $path = static::valueOf('logo_path');

        return $path !== '' ? route('branding.logo', ['v' => basename($path)]) : null;
    }
}
