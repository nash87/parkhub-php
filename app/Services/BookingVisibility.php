<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;

/**
 * How much of a booking owner's identity other users may see.
 *
 * The `booking_visibility` setting already governed the team view. The
 * shared calendar (#571) widens where booking data is displayed, which
 * makes this control load-bearing rather than cosmetic: in a workplace
 * deployment, "which slot is free" is scheduling information, while "who
 * parked when" is behavioural data about employees. The two are separated
 * here so an operator can grant the first without the second.
 *
 * The masking logic lives in one place so the team view and the calendar
 * cannot drift apart.
 */
final class BookingVisibility
{
    public const string MODE_FULL = 'full';

    public const string MODE_FIRST_NAME = 'firstName';

    public const string MODE_INITIALS = 'initials';

    public const string MODE_OCCUPIED = 'occupied';

    public const array MODES = [
        self::MODE_FULL,
        self::MODE_FIRST_NAME,
        self::MODE_INITIALS,
        self::MODE_OCCUPIED,
    ];

    /**
     * Absence reasons a colleague may see as-is.
     *
     * "Who is around today" is ordinary workplace scheduling information,
     * and `homeoffice` / `vacation` / `training` are part of that. `sick`
     * is health data — special-category under GDPR Art. 9 — and `other` is
     * effectively free-form, so neither is disclosed to colleagues. Admins
     * see the real value.
     */
    public const array COLLEAGUE_VISIBLE_ABSENCE_TYPES = ['homeoffice', 'vacation', 'training'];

    /** Generic label for an absence whose reason is not disclosed. */
    public const string ABSENCE_UNDISCLOSED = 'absent';

    /**
     * The absence reason to show to $viewerIsAdmin, for a given raw type.
     */
    public static function absenceType(?string $type, bool $viewerIsAdmin): string
    {
        if ($viewerIsAdmin) {
            return (string) $type;
        }

        return in_array($type, self::COLLEAGUE_VISIBLE_ABSENCE_TYPES, true)
            ? (string) $type
            : self::ABSENCE_UNDISCLOSED;
    }

    /** The configured mode, falling back to the historical default. */
    public static function mode(): string
    {
        $mode = (string) Setting::get('booking_visibility', self::MODE_FULL);

        return in_array($mode, self::MODES, true) ? $mode : self::MODE_FULL;
    }

    /**
     * Render an owner label for another user's booking.
     *
     * @param  string  $anonymousLabel  shown in `occupied` mode. The team
     *                                  view says "User" (it lists people);
     *                                  the calendar says "Occupied" (it
     *                                  lists slots). Same policy, different
     *                                  noun.
     */
    public static function label(
        ?string $name,
        ?string $username = null,
        ?string $mode = null,
        string $anonymousLabel = 'User',
    ): string {
        $mode ??= self::mode();
        $fallback = $username ?: 'User';
        $name = trim((string) $name);

        if ($name === '') {
            $name = $fallback;
        }

        return match ($mode) {
            self::MODE_FIRST_NAME => explode(' ', $name)[0] ?: $fallback,
            self::MODE_INITIALS => collect(explode(' ', $name))
                ->filter()
                ->map(fn (string $part) => strtoupper(substr($part, 0, 1)))
                ->join('.'),
            self::MODE_OCCUPIED => $anonymousLabel,
            default => $name,
        };
    }
}
