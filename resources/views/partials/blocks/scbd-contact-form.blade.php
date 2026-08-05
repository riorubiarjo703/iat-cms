@php
    use App\Http\Requests\StoreContactMessageRequest;
    use App\PageBuilder\BlockData;

    $locale = \App\Models\SiteSetting::singleton()->default_locale ?? 'en';

    $heading = BlockData::t($data, 'heading', $locale) ?: 'Make an enquiry';
    $intro = BlockData::t($data, 'intro', $locale);
    $submit = BlockData::t($data, 'submit', $locale) ?: 'Send enquiry';
    $success = BlockData::t($data, 'success', $locale)
        ?: 'Thank you — your enquiry has reached us. We will be in touch shortly.';

    $subjects = collect(BlockData::get($data, 'subjects', []))
        ->map(fn ($s) => is_array($s) ? ($s['label'] ?? null) : $s)
        ->filter()
        ->values();

    $sent = session('contact.sent');
    $trap = StoreContactMessageRequest::TRAP;

    // Only this block's errors. $errors is shared, so without the check a
    // failure elsewhere on the page would light up these fields.
    $failed = $errors->hasAny(['name', 'email', 'phone', 'subject', 'message', $trap]);
@endphp

<section id="enquire" class="scbd-pad scbd-form-section">
    <div class="scbd-form-grid">
        <div class="scbd-form-intro">
            <h2 data-split class="scbd-h2 scbd-form-heading" data-i18n="{{ BlockData::i18nKey($blockId, 'heading') }}">{!! nl2br(e($heading)) !!}</h2>

            @if ($intro)
                <p data-fade class="scbd-form-lede" data-i18n="{{ BlockData::i18nKey($blockId, 'intro') }}">{{ $intro }}</p>
            @endif
        </div>

        <div class="scbd-form-panel">
            {{-- The confirmation. Rendered server-side after a no-JS post, and
                 revealed in place by contact.js after a fetch post — one panel
                 for both paths, so the two cannot drift apart. --}}
            <div class="scbd-form-done" data-contact-done @unless ($sent) hidden @endunless>
                <div class="scbd-form-done-mark" aria-hidden="true">✓</div>
                <p class="scbd-form-done-text" data-contact-done-text data-i18n="{{ BlockData::i18nKey($blockId, 'success') }}">{{ $success }}</p>
                <p class="scbd-form-done-ref">Reference <strong data-contact-reference>{{ $sent }}</strong></p>
            </div>

            <form method="POST"
                  action="{{ route('contact.store') }}"
                  class="scbd-form"
                  data-contact-form
                  @if ($sent) hidden @endif
                  novalidate>
                @csrf
                <input type="hidden" name="locale" value="{{ $locale }}">

                {{-- The honeypot. Hidden from sight and from assistive tech, and
                     taken out of the tab order, so only a bot ever fills it. --}}
                <div class="scbd-form-trap" aria-hidden="true">
                    <label for="{{ $trap }}">Company website</label>
                    <input type="text" id="{{ $trap }}" name="{{ $trap }}" tabindex="-1" autocomplete="off">
                </div>

                @if ($failed)
                    <p class="scbd-form-alert" role="alert">Please check the fields marked below.</p>
                @endif

                <div class="scbd-field">
                    <label for="contact-name">Name <span aria-hidden="true">*</span></label>
                    <input type="text" id="contact-name" name="name" value="{{ old('name') }}"
                           required autocomplete="name"
                           @error('name') aria-invalid="true" aria-describedby="err-name" @enderror>
                    @error('name') <span class="scbd-field-error" id="err-name">{{ $message }}</span> @enderror
                </div>

                <div class="scbd-field-row">
                    <div class="scbd-field">
                        <label for="contact-email">Email <span aria-hidden="true">*</span></label>
                        <input type="email" id="contact-email" name="email" value="{{ old('email') }}"
                               required autocomplete="email"
                               @error('email') aria-invalid="true" aria-describedby="err-email" @enderror>
                        @error('email') <span class="scbd-field-error" id="err-email">{{ $message }}</span> @enderror
                    </div>

                    <div class="scbd-field">
                        <label for="contact-phone">Phone</label>
                        <input type="tel" id="contact-phone" name="phone" value="{{ old('phone') }}" autocomplete="tel">
                        @error('phone') <span class="scbd-field-error">{{ $message }}</span> @enderror
                    </div>
                </div>

                @if ($subjects->isNotEmpty())
                    <div class="scbd-field">
                        <label for="contact-subject">Enquiry type</label>
                        <select id="contact-subject" name="subject">
                            <option value="">Choose one</option>
                            @foreach ($subjects as $subject)
                                <option value="{{ $subject }}" @selected(old('subject') === $subject)>{{ $subject }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="scbd-field">
                    <label for="contact-message">Message <span aria-hidden="true">*</span></label>
                    <textarea id="contact-message" name="message" rows="5" required
                              @error('message') aria-invalid="true" aria-describedby="err-message" @enderror>{{ old('message') }}</textarea>
                    @error('message') <span class="scbd-field-error" id="err-message">{{ $message }}</span> @enderror
                </div>

                <button type="submit" class="btn btn-primary scbd-form-submit" data-contact-submit>
                    <span data-contact-submit-label data-i18n="{{ BlockData::i18nKey($blockId, 'submit') }}">{{ $submit }}</span>
                </button>

                {{-- Announced politely rather than as an alert: it reports
                     progress, and an assertive region would interrupt. --}}
                <p class="scbd-form-status" data-contact-status role="status" aria-live="polite"></p>
            </form>
        </div>
    </div>
</section>
