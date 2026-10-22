<?php

namespace App\Enum;

enum Body: string
{
    case CityCar = 'city';
    case Compact = 'compact';
    case Sedan = 'sedan';
    case Estate = 'estate';
    case Suv = 'suv';
    case Mpv = 'mpv';

    public function label(): string
    {
        return match ($this) {
            self::CityCar => 'Citadine',
            self::Compact => 'Compacte',
            self::Sedan => 'Berline',
            self::Estate => 'Break',
            self::Suv => 'SUV',
            self::Mpv => 'Monospace',
        };
    }
}
