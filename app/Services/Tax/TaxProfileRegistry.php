<?php

declare(strict_types=1);

namespace App\Services\Tax;

use App\Models\Setting;

/**
 * Registry + resolver for the ten country tax profiles ParkHub ships.
 *
 * Mirrors the Rust-side `parkhub_server::api::tax` module so the PHP
 * backend and Rust backend produce identical VAT results for the same
 * seller/buyer combination. See `App\Services\Tax\TaxProfile` for the
 * per-country data shape and the legal disclaimer.
 *
 * # Scope (deliberately minimal)
 *
 * Configuration layer, not a full tax engine: no historical rate tables,
 * no per-state US sales tax, no EU-OSS routing. The rate in force today
 * is the only one stored; historical invoices keep whatever rate they
 * were originally issued with because the persisted `tax_amount` on the
 * booking is authoritative.
 */
final class TaxProfileRegistry
{
    /** Canonical fallback country code used when no default is configured. */
    public const string DEFAULT_COUNTRY = 'DE';

    /** Invoice note emitted when reverse-charge applies. */
    public const string REVERSE_CHARGE_NOTE = 'Reverse charge per Art. 194 VAT Directive';

    /** @var array<int, TaxProfile>|null */
    private static ?array $profiles = null;

    /**
     * All profiles shipped with the product, keyed by alpha-2 country.
     *
     * @return array<string, TaxProfile>
     */
    public static function all(): array
    {
        if (self::$profiles === null) {
            self::$profiles = self::build();
        }

        $out = [];
        foreach (self::$profiles as $profile) {
            $out[$profile->country] = $profile;
        }

        return $out;
    }

    /**
     * Resolve a profile by ISO 3166-1 alpha-2 code (case-insensitive).
     * Unknown/empty codes fall back to {@see self::DEFAULT_COUNTRY}.
     */
    public static function resolveProfile(string $country): TaxProfile
    {
        $upper = strtoupper(trim($country));
        $all = self::all();

        if ($upper !== '' && array_key_exists($upper, $all)) {
            return $all[$upper];
        }

        return $all[self::DEFAULT_COUNTRY];
    }

    /**
     * Decide whether EU B2B reverse-charge applies for a given seller/
     * buyer pair. Mirrors the Rust-side rule exactly.
     *
     * All three preconditions must hold:
     *   1. Buyer has a non-empty, plausible VAT ID (>= 4 chars after trim).
     *   2. Seller country profile is in the EU reverse-charge regime.
     *   3. Seller and buyer countries differ and both participate.
     */
    public static function reverseChargeApplies(
        string $sellerCountry,
        string $buyerCountry,
        ?string $buyerVatId,
    ): bool {
        $vat = is_string($buyerVatId) ? trim($buyerVatId) : '';
        if ($vat === '' || strlen($vat) < 4) {
            return false;
        }

        $seller = self::profileForStrict($sellerCountry);
        $buyer = self::profileForStrict($buyerCountry);
        if ($seller === null || $buyer === null) {
            return false;
        }

        if ($seller->country === $buyer->country) {
            return false;
        }

        return $seller->reverseChargeEu && $buyer->reverseChargeEu;
    }

    /**
     * Full resolver: given seller/buyer country and buyer VAT ID,
     * return a {@see ResolvedRate}.
     */
    public static function resolveRate(
        string $sellerCountry,
        string $buyerCountry,
        ?string $buyerVatId,
    ): ResolvedRate {
        if (self::reverseChargeApplies($sellerCountry, $buyerCountry, $buyerVatId)) {
            return ResolvedRate::reverseCharge();
        }

        return ResolvedRate::standard(self::resolveProfile($sellerCountry)->standardRate);
    }

    /**
     * Read the seller country for this deployment from the settings store.
     *
     * Precedence (highest first):
     *   1. `tax_seller_country` admin setting — explicit override.
     *   2. `impressum_country` admin setting — already part of DDG § 5
     *      Impressum, so international operators get the right rate out
     *      of the box once their Impressum is filled.
     *   3. `PARKHUB_TAX_COUNTRY` env var — operations-level override.
     *   4. {@see self::DEFAULT_COUNTRY} — matches historical behaviour.
     */
    public static function resolveSellerCountryFromSettings(): string
    {
        foreach (['tax_seller_country', 'impressum_country'] as $key) {
            $value = trim((string) Setting::get($key, ''));
            if ($value !== '') {
                return $value;
            }
        }

        $env = trim((string) (getenv('PARKHUB_TAX_COUNTRY') ?: ''));
        if ($env !== '') {
            return $env;
        }

        return self::DEFAULT_COUNTRY;
    }

    /**
     * Strict profile lookup — returns null for unknown codes instead of
     * falling back to the default. Used internally by
     * {@see self::reverseChargeApplies} so "unknown" seller/buyer codes
     * cannot accidentally round to DE and trigger a false positive.
     */
    private static function profileForStrict(string $country): ?TaxProfile
    {
        $upper = strtoupper(trim($country));
        if (strlen($upper) !== 2) {
            return null;
        }

        return self::all()[$upper] ?? null;
    }

    /**
     * Build the canonical profile table. Kept private + lazily cached
     * behind {@see self::$profiles} so the array literal is constructed
     * at most once per request.
     *
     * @return array<int, TaxProfile>
     */
    private static function build(): array
    {
        return [
            // Austria — Umsatzsteuer, § 10 UStG
            new TaxProfile('AT', 0.20, 0.10, true),
            // Switzerland — MWSTG Art. 25 (non-EU). 8.1% standard / 2.6%
            // reduced since 2024-01-01; a rise to 8.5% is proposed for 2028
            // and is not in force. Source: Swiss Federal Tax Administration,
            // https://www.estv.admin.ch/en/vat-rates-switzerland
            new TaxProfile('CH', 0.081, 0.026, false),
            // Germany — Umsatzsteuergesetz § 12 Abs. 1
            new TaxProfile('DE', 0.19, 0.07, true),
            // Spain — IVA, Ley 37/1992
            new TaxProfile('ES', 0.21, 0.10, true),
            // France — TVA, CGI art. 278
            new TaxProfile('FR', 0.20, 0.055, true),
            // Italy — IVA, DPR 633/1972
            new TaxProfile('IT', 0.22, 0.10, true),
            // Netherlands — BTW, Wet op de omzetbelasting 1968
            new TaxProfile('NL', 0.21, 0.09, true),
            // Poland — VAT, Ustawa o podatku od towarów i usług
            new TaxProfile('PL', 0.23, 0.08, true),
            // United Kingdom — VAT Act 1994 (post-Brexit, non-EU)
            new TaxProfile('GB', 0.20, 0.05, false),
            // United States — no federal VAT; state sales tax out of scope
            new TaxProfile('US', 0.0, null, false),
        ];
    }
}
