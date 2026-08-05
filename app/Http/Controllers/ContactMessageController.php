<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactMessageRequest;
use App\Models\ContactMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class ContactMessageController extends Controller
{
    /**
     * Answers twice over, deliberately.
     *
     * contact.js posts with fetch and gets JSON back, so the page can show the
     * confirmation without a reload. With JavaScript off, the same route
     * handles an ordinary form post and redirects back with the enquiry
     * flashed — so the form works either way rather than being a JS feature.
     */
    public function __invoke(StoreContactMessageRequest $request): JsonResponse|RedirectResponse
    {
        $message = ContactMessage::create($request->toMessageAttributes());

        if ($request->wantsJson()) {
            return response()->json([
                'ok' => true,
                'reference' => $this->reference($message),
            ]);
        }

        return back()
            ->with('contact.sent', $this->reference($message))
            // Anchors the redirect at the form rather than the top of a long
            // page, where the confirmation would be off-screen.
            ->withFragment('enquire');
    }

    /**
     * A human-quotable handle for the enquiry, so a follow-up call can name it.
     * Derived from the id rather than stored — there is nothing to keep in sync.
     */
    private function reference(ContactMessage $message): string
    {
        return 'SCBD-'.str_pad((string) $message->getKey(), 5, '0', STR_PAD_LEFT);
    }
}
