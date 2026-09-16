<?php

namespace App\Enums;

/**
 * Where an activity can happen. `Either` means it works rain or shine, which
 * is what makes it eligible for a "rained off" re-spin.
 */
enum Location: string
{
    case Indoor = 'indoor';
    case Outdoor = 'outdoor';
    case Either = 'either';

    public function label(): string
    {
        return match ($this) {
            self::Indoor => 'Indoor',
            self::Outdoor => 'Outdoor',
            self::Either => 'Indoor or outdoor',
        };
    }

    /** Locations that survive bad weather. */
    public static function weatherProof(): array
    {
        return [self::Indoor, self::Either];
    }
}
