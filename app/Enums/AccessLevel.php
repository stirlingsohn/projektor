<?php

namespace App\Enums;

/**
 * What an invited client may do in a project or list. Each level includes
 * the ones before it.
 */
enum AccessLevel: string
{
    case View = 'view';
    case Comment = 'comment';
    case Edit = 'edit';

    public function rank(): int
    {
        return match ($this) {
            self::View => 1,
            self::Comment => 2,
            self::Edit => 3,
        };
    }

    public function allows(self $level): bool
    {
        return $this->rank() >= $level->rank();
    }

    /** The higher of two levels, treating `null` as no access. */
    public static function max(?self $a, ?self $b): ?self
    {
        if ($a === null || $b === null) {
            return $a ?? $b;
        }

        return $a->rank() >= $b->rank() ? $a : $b;
    }

    public function label(): string
    {
        return match ($this) {
            self::View => __('Can view'),
            self::Comment => __('Can comment'),
            self::Edit => __('Can edit'),
        };
    }
}
