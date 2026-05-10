<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Accepts iCal payloads either as a multipart file upload or a raw string
 * body. Rules are conditional on the transport so callers can use whichever
 * shape fits their client without us routing to two separate endpoints.
 */
class ImportIcalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        // Hard-cap payload size at 256 KiB (audit M-2): the regex parser
        // in AbsenceController::importIcal uses a lazy quantifier that
        // backtracks heavily on malformed input. 256 KiB is plenty for
        // even a multi-year calendar export, well below the worst-case
        // backtrack budget. file rule is in KB; string rule is in bytes.
        if ($this->hasFile('file')) {
            return [
                'file' => 'required|file|mimes:ics,txt,calendar|max:256',
            ];
        }

        return [
            'ical' => 'required|string|max:262144',
        ];
    }
}
