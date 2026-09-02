<?php

namespace App\Services\Notifications;

use App\Models\NotificationTemplate;
use Illuminate\Support\Str;

/**
 * Substitutes {{variable}} placeholders with real values.
 *
 * Deliberately not Blade. A template is content typed into an admin form, and
 * handing user input to a template compiler that can execute PHP is how an
 * editable-wording feature turns into remote code execution. This does one
 * thing: swap a name for a string.
 */
class TemplateRenderer
{
    /**
     * @param  array<string, string|null>  $variables
     * @return array{subject: string, body: string}
     */
    public function render(NotificationTemplate $template, array $variables): array
    {
        return [
            'subject' => $this->fill((string) $template->subject, $variables),
            'body' => $this->fill($template->body, $variables),
        ];
    }

    /**
     * Replaces every {{name}} it knows about, and strips any it does not so a
     * stale placeholder from a renamed variable never reaches a recipient as
     * literal braces.
     *
     * @param  array<string, string|null>  $variables
     */
    public function fill(string $text, array $variables): string
    {
        foreach ($variables as $name => $value) {
            $text = str_replace('{{'.$name.'}}', (string) $value, $text);
        }

        return trim(preg_replace('/\{\{\s*[a-z0-9_]+\s*\}\}/i', '', $text));
    }

    /**
     * A plain-text body turned into the HTML half of an email.
     *
     * Table based and inline styled on purpose: Outlook still renders with
     * Word, which ignores most modern CSS, and every client agrees on a
     * centred table with a fixed max width.
     */
    public function toHtml(string $subject, string $body, string $companyName): string
    {
        $paragraphs = collect(preg_split('/\n{2,}/', trim($body)))
            ->filter()
            ->map(fn (string $block) => '<p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#0f172a;">'
                // Single newlines are line breaks within a paragraph.
                .nl2br(e(trim($block)))
                .'</p>')
            ->implode('');

        $safeSubject = e($subject);
        $safeCompany = e($companyName);
        $year = date('Y');

        return <<<HTML
        <!doctype html>
        <html lang="en">
        <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{$safeSubject}</title>
        </head>
        <body style="margin:0;padding:0;background:#f4f6fb;-webkit-font-smoothing:antialiased;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f4f6fb;">
        <tr><td align="center" style="padding:24px 12px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
               style="max-width:560px;background:#ffffff;border:1px solid #e2e8f0;border-radius:10px;font-family:-apple-system,'Segoe UI',Roboto,Arial,sans-serif;">
        <tr><td style="padding:20px 24px;border-bottom:1px solid #e2e8f0;">
        <span style="font-size:15px;font-weight:700;color:#4f46e5;">{$safeCompany}</span>
        </td></tr>
        <tr><td style="padding:24px;">
        <h1 style="margin:0 0 16px;font-size:18px;line-height:1.35;color:#0f172a;font-weight:650;">{$safeSubject}</h1>
        {$paragraphs}
        </td></tr>
        <tr><td style="padding:16px 24px;border-top:1px solid #e2e8f0;">
        <p style="margin:0;font-size:12px;line-height:1.5;color:#64748b;">
        Sent automatically by {$safeCompany}. &copy; {$year}
        </p>
        </td></tr>
        </table>
        </td></tr>
        </table>
        </body>
        </html>
        HTML;
    }

    /**
     * WhatsApp accepts a limited markdown and caps a text message at 4096
     * characters; anything longer is rejected outright rather than truncated
     * by the provider.
     */
    public function forWhatsApp(string $body): string
    {
        return Str::limit(trim($body), 4000, '...');
    }
}
