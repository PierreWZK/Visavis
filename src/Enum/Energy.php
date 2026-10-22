<?php

namespace App\Enum;

enum Energy: string
{
    case Petrol = 'petrol';
    case Diesel = 'diesel';
    case Hybrid = 'hybrid';
    case Electric = 'electric';

    public function label(): string
    {
        return match ($this) {
            self::Petrol => 'Essence',
            self::Diesel => 'Diesel',
            self::Hybrid => 'Hybride',
            self::Electric => 'Électrique',
        };
    }

    public function consumptionUnit(): string
    {
        return self::Electric === $this ? 'kWh/100 km' : 'L/100 km';
    }
}
