<?php

namespace App\Enum;

enum Gearbox: string
{
    case Manual = 'manual';
    case Automatic = 'automatic';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manuelle',
            self::Automatic => 'Automatique',
        };
    }
}
