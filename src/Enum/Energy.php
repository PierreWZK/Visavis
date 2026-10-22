<?php

namespace App\Enum;

enum Energy: string
{
    case Petrol = 'petrol';
    case Diesel = 'diesel';
    case Hybrid = 'hybrid';
    case PluginHybrid = 'plugin';
    case Electric = 'electric';
    case Ethanol = 'ethanol';
    case Lpg = 'lpg';

    public function label(): string
    {
        return match ($this) {
            self::Petrol => 'Essence',
            self::Diesel => 'Diesel',
            self::Hybrid => 'Hybride',
            self::PluginHybrid => 'Hybride rechargeable',
            self::Electric => 'Électrique',
            self::Ethanol => 'Superéthanol',
            self::Lpg => 'GPL',
        };
    }

    public function consumptionUnit(): string
    {
        return self::Electric === $this ? 'kWh/100 km' : 'L/100 km';
    }
}
