<?php

namespace App\Http\Requests;

use App\Models\SiteSetting;
use Illuminate\Foundation\Http\FormRequest;

class StoreContactMessageRequest extends FormRequest
{
    /** The honeypot field. Named plausibly so a bot fills it in. */
    public const TRAP = 'company_website';

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            // 'email' only, not dns/spoof: a DNS lookup on every submission
            // makes the response time depend on a third party, and rejects
            // valid addresses whose MX record is briefly unreachable.
            'email' => ['required', 'string', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:60'],
            'subject' => ['nullable', 'string', 'max:120'],
            'message' => ['required', 'string', 'min:10', 'max:4000'],
            // Rejected rather than silently accepted: a human never sees this
            // field, so anything in it is a bot.
            self::TRAP => ['prohibited'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'message.min' => 'Please tell us a little more — at least 10 characters.',
            self::TRAP.'.prohibited' => 'This submission looks automated.',
        ];
    }

    /** The validated payload plus the reader's locale. */
    public function toMessageAttributes(): array
    {
        $locale = (string) $this->input('locale', '');

        return [
            ...$this->safe()->except([self::TRAP, 'locale']),
            'locale' => array_key_exists($locale, SiteSetting::LOCALES)
                ? $locale
                : SiteSetting::FALLBACK_LOCALE,
        ];
    }
}
