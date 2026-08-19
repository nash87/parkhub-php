/**
 * Helpers for `<input type="datetime-local">`.
 *
 * A `datetime-local` input reads and writes a **local** wall-clock string
 * with no timezone designator. `Date.prototype.toISOString()` returns UTC,
 * so using it to seed such an input shifts the displayed time by the
 * browser's UTC offset — and because the value is later parsed back with
 * `new Date(value)`, which treats an unsuffixed string as local time, the
 * shift is then baked into the submitted instant.
 *
 * For a product whose primary market runs at UTC+1/+2 the booking form
 * opened one or two hours in the past by default.
 */

/** Zero-pad to two digits without pulling in a date library. */
function pad(value: number): string {
  return String(value).padStart(2, '0');
}

/**
 * Format a `Date` as the local wall-clock string a `datetime-local` input
 * expects: `YYYY-MM-DDTHH:mm`, in the browser's own timezone.
 */
export function toDateTimeLocalValue(date: Date): string {
  return (
    `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}` +
    `T${pad(date.getHours())}:${pad(date.getMinutes())}`
  );
}
