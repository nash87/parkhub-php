import { describe, it, expect } from 'vitest';
import { toDateTimeLocalValue } from './datetime';

describe('toDateTimeLocalValue', () => {
  it('formats a date as the local wall clock the input expects', () => {
    // Constructed from local parts, so this is 14:30 local by definition.
    expect(toDateTimeLocalValue(new Date(2026, 0, 15, 14, 30))).toBe('2026-01-15T14:30');
  });

  it('pads single-digit months, days, hours and minutes', () => {
    expect(toDateTimeLocalValue(new Date(2026, 8, 5, 7, 4))).toBe('2026-09-05T07:04');
  });

  it('round-trips: parsing the value back yields the same local instant', () => {
    const original = new Date(2026, 5, 30, 23, 15);
    const parsed = new Date(toDateTimeLocalValue(original));

    expect(parsed.getFullYear()).toBe(original.getFullYear());
    expect(parsed.getHours()).toBe(original.getHours());
    expect(parsed.getMinutes()).toBe(original.getMinutes());
    expect(parsed.getTime()).toBe(new Date(2026, 5, 30, 23, 15).getTime());
  });

  /**
   * The defect this replaces: `toISOString()` is UTC, so wherever the
   * browser is not at UTC+0 it disagrees with the local wall clock — and a
   * `datetime-local` input renders that string verbatim.
   *
   * The timezone is pinned rather than inherited, otherwise this assertion
   * is vacuously true on a UTC CI runner — which is exactly where it would
   * be relied upon.
   */
  it('does not drift with the UTC offset the way toISOString does', () => {
    const previous = process.env.TZ;
    process.env.TZ = 'Europe/Berlin';

    try {
      // 12:00 UTC is 13:00 in Berlin in January (UTC+1).
      const date = new Date(Date.UTC(2026, 0, 15, 12, 0));

      expect(date.getTimezoneOffset()).not.toBe(0);
      expect(toDateTimeLocalValue(date)).toBe('2026-01-15T13:00');
      expect(date.toISOString().slice(0, 16)).toBe('2026-01-15T12:00');
      expect(toDateTimeLocalValue(date)).not.toBe(date.toISOString().slice(0, 16));
    } finally {
      process.env.TZ = previous;
    }
  });
});
