<?php

namespace App\Enums;

enum Currency: int
{
    // можно взять за основу https://en.wikipedia.org/wiki/ISO_4217
    case USD = 840;
    case EUR = 978;
    case GBP = 826;

    public static function getByISO($iso): self
    {
        return match($iso) {
            'USD' => self::USD,
            'EUR' => self::EUR,
            'GBP' => self::GBP,
            default => throw new \InvalidArgumentException("Unknown currency code: {$iso}"),
        };
    }

    public function label(): string
    {
        return match($this) {
            self::USD => 'US Dollar',
            self::EUR => 'Euro',
            self::GBP => 'British Pound',
        };
    }
}
