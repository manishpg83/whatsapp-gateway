<?php

namespace App\Http\Controllers;

use App\Mail\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Throwable;

/**
 * Public Contact / Support page. Messages are emailed to our support inbox
 * (config mail.support_address) with Reply-To set to the sender — nothing
 * is stored in the database.
 */
class ContactController extends Controller
{
    public const TOPICS = [
        'general' => 'General question',
        'sales' => 'Plans & pricing',
        'technical' => 'Technical support',
        'billing' => 'Billing & payments',
        'privacy' => 'Privacy / data request',
    ];

    public function create(Request $request): View
    {
        return view('contact.index', [
            'topics' => self::TOPICS,
            'supportEmail' => config('mail.support_address'),
            // Pre-fill for logged-in users; ?topic=privacy pre-selects a topic.
            'selectedTopic' => array_key_exists((string) $request->query('topic'), self::TOPICS) ? $request->query('topic') : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        // Honeypot: a hidden field real people never see or fill in. Bots
        // that fill every field get the normal "sent" message, but nothing
        // is actually sent.
        if (filled($request->input('website'))) {
            return redirect()->route('contact')->with('status', "Thanks — your message has been sent. We'll get back to you soon.");
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'topic' => ['required', 'in:'.implode(',', array_keys(self::TOPICS))],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        try {
            Mail::to(config('mail.support_address'))->send(new ContactMessage(
                senderName: $data['name'],
                senderEmail: $data['email'],
                topic: self::TOPICS[$data['topic']],
                messageText: $data['message'],
                userId: $request->user()?->id,
            ));
        } catch (Throwable $e) {
            // Never log the message text or the sender's details here.
            Log::error('Contact form email could not be sent', ['error' => $e->getMessage()]);

            return back()->withInput()->with(
                'error',
                'Sorry, your message could not be sent right now. Please email us directly at '.config('mail.support_address').'.'
            );
        }

        return redirect()->route('contact')->with('status', "Thanks — your message has been sent. We'll get back to you soon.");
    }
}
