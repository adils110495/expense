<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificationTemplate;
use App\Models\Setting;
use App\Services\Notifications\EmailService;
use App\Services\Notifications\TemplateRenderer;
use App\Services\Notifications\WhatsAppService;
use App\Support\NotificationConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Settings -> WhatsApp, Email and Notification Templates.
 *
 * Credentials go in but never come back out: the forms render a mask from
 * Setting::masked() and a blank field means "keep what is stored", so a token
 * is never present in any HTML the browser receives.
 */
class ChannelSettingsController extends Controller
{
    public function __construct(
        private readonly WhatsAppService $whatsapp,
        private readonly EmailService $email,
    ) {}

    /* ===================== WhatsApp ===================== */

    public function whatsapp(): View
    {
        return view('admin.settings.whatsapp', [
            'settings' => Setting::all_settings(),
            // Only ever the last four characters.
            'tokenMask' => Setting::masked('whatsapp_access_token'),
            'verifyMask' => Setting::masked('whatsapp_webhook_verify_token'),
            'ready' => NotificationConfig::ready('whatsapp'),
            'webhookUrl' => route('admin.webhooks.whatsapp'),
        ]);
    }

    public function updateWhatsApp(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'whatsapp_enabled' => ['required', 'boolean'],
            'whatsapp_access_token' => ['nullable', 'string', 'max:1000'],
            'whatsapp_phone_number_id' => ['nullable', 'string', 'max:60'],
            'whatsapp_business_account_id' => ['nullable', 'string', 'max:60'],
            'whatsapp_webhook_verify_token' => ['nullable', 'string', 'max:255'],
            'whatsapp_api_base' => ['required', 'url', 'max:255'],
            'whatsapp_api_version' => ['required', 'string', 'max:10', 'regex:/^v\d+\.\d+$/'],
            'whatsapp_default_country_code' => ['nullable', 'string', 'max:5'],
        ], [
            'whatsapp_api_version.regex' => 'The API version looks like v21.0.',
        ]);

        foreach ($data as $key => $value) {
            Setting::isSecret($key)
                ? Setting::putSecret($key, $value)
                : Setting::put($key, (string) $value);
        }

        return back()->with('success', 'WhatsApp settings saved.');
    }

    /** Clears a stored credential, since a blank field means "keep it". */
    public function forgetWhatsAppSecret(Request $request): RedirectResponse
    {
        $key = $request->input('key');

        abort_unless(in_array($key, ['whatsapp_access_token', 'whatsapp_webhook_verify_token'], true), 404);

        Setting::forgetSecret($key);

        return back()->with('success', 'Credential cleared.');
    }

    public function testWhatsApp(): RedirectResponse
    {
        $result = $this->whatsapp->testConnection();

        return $result->ok
            ? back()->with('success', 'Connected to WhatsApp as '.$result->messageId)
            : back()->with('error', $result->error);
    }

    public function sendTestWhatsApp(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'test_number' => ['required', 'string', 'max:30'],
        ], [], ['test_number' => 'test number']);

        $template = NotificationTemplate::resolve('test', 'whatsapp');
        $body = app(TemplateRenderer::class)->fill(
            $template?->body ?? 'Test message from {{company_name}}.',
            ['company_name' => config('app.name')],
        );

        $result = $this->whatsapp->send($data['test_number'], $body, $template?->whatsapp_template_name);

        return $result->ok
            ? back()->with('success', 'Test message accepted by WhatsApp'
                .($result->messageId ? ' (id '.$result->messageId.').' : '.'))
            : back()->with('error', $result->error);
    }

    /* ===================== Email ===================== */

    public function email(): View
    {
        return view('admin.settings.email', [
            'settings' => Setting::all_settings(),
            'keyMask' => Setting::masked('email_api_key'),
            'providers' => EmailService::PROVIDERS,
            'ready' => NotificationConfig::ready('email'),
        ]);
    }

    public function updateEmail(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email_enabled' => ['required', 'boolean'],
            'email_provider' => ['required', Rule::in(array_keys(EmailService::PROVIDERS))],
            'email_api_key' => ['nullable', 'string', 'max:500'],
            'email_from_name' => ['nullable', 'string', 'max:100'],
            'email_from_address' => ['nullable', 'email', 'max:255'],
            'email_reply_to' => ['nullable', 'email', 'max:255'],
            'email_domain' => ['nullable', 'string', 'max:255'],
            'email_endpoint' => ['nullable', 'url', 'max:255'],
        ]);

        foreach ($data as $key => $value) {
            Setting::isSecret($key)
                ? Setting::putSecret($key, $value)
                : Setting::put($key, (string) $value);
        }

        return back()->with('success', 'Email settings saved.');
    }

    public function forgetEmailSecret(): RedirectResponse
    {
        Setting::forgetSecret('email_api_key');

        return back()->with('success', 'API key cleared.');
    }

    public function testEmail(): RedirectResponse
    {
        $result = $this->email->testConnection();

        return $result->ok
            ? back()->with('success', $result->messageId)
            : back()->with('error', $result->error);
    }

    public function sendTestEmail(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'test_email' => ['required', 'email', 'max:255'],
        ], [], ['test_email' => 'test email address']);

        $template = NotificationTemplate::resolve('test', 'email');
        $renderer = app(TemplateRenderer::class);
        $variables = ['company_name' => config('app.name')];

        $subject = $renderer->fill($template?->subject ?? 'Test email', $variables);
        $body = $renderer->fill($template?->body ?? 'This is a test.', $variables);

        $result = $this->email->send(
            $data['test_email'],
            $subject,
            $renderer->toHtml($subject, $body, config('app.name')),
            $body,
        );

        return $result->ok
            ? back()->with('success', 'Test email sent to '.$data['test_email'].'.')
            : back()->with('error', $result->error);
    }

    /* ===================== Templates ===================== */

    public function templates(): View
    {
        return view('admin.settings.templates', [
            'templates' => NotificationTemplate::query()
                ->orderBy('event')
                ->orderBy('channel')
                ->get()
                ->groupBy('event'),
            'events' => NotificationTemplate::EVENTS,
            'variables' => NotificationTemplate::VARIABLES,
            'globals' => Setting::all_settings(),
        ]);
    }

    public function updateTemplate(Request $request, NotificationTemplate $template): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
            'whatsapp_template_name' => ['nullable', 'string', 'max:120'],
            'language' => ['nullable', 'string', 'max:10'],
            'is_active' => ['required', 'boolean'],
        ]);

        $template->update([
            // A WhatsApp template has no subject line, so one is never stored.
            'subject' => $template->channel === 'email' ? $data['subject'] : null,
            'body' => $data['body'],
            'whatsapp_template_name' => $template->channel === 'whatsapp'
                ? $data['whatsapp_template_name']
                : null,
            'language' => $data['language'] ?: 'en_US',
            'is_active' => $data['is_active'],
        ]);

        return back()->with('success', $template->event_label.' '.$template->channel.' template saved.');
    }

    /** The global per-event switches that sit above each partner's own. */
    public function updateGlobals(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'notify_expense' => ['required', 'boolean'],
            'notify_credit' => ['required', 'boolean'],
            'notify_settlement' => ['required', 'boolean'],
            'notify_summary' => ['required', 'boolean'],
        ]);

        foreach ($data as $key => $value) {
            Setting::put($key, (string) $value);
        }

        return back()->with('success', 'Notification switches saved.');
    }
}
