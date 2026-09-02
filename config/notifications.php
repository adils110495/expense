<?php

/*
|--------------------------------------------------------------------------
| Notification channel credentials
|--------------------------------------------------------------------------
|
| Fallbacks for the WhatsApp and email channels. Anything configured in
| Admin -> Settings wins over what is here, so this file is the way to
| provision a deployment from the environment without anyone typing a
| credential into a browser.
|
| Nothing here is ever sent to a view. App\Support\NotificationConfig is the
| single place that resolves a value, and only the sending services ask it.
|
*/

return [

    'whatsapp' => [
        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),
        'webhook_verify_token' => env('WHATSAPP_WEBHOOK_VERIFY_TOKEN'),
        'api_base' => env('WHATSAPP_API_BASE', 'https://graph.facebook.com'),
        'api_version' => env('WHATSAPP_API_VERSION', 'v21.0'),
        'default_country_code' => env('WHATSAPP_DEFAULT_COUNTRY_CODE', '91'),
    ],

    'email' => [
        // sendgrid | mailgun | resend | postmark | ses | smtp
        'provider' => env('EMAIL_PROVIDER', 'smtp'),
        'api_key' => env('EMAIL_API_KEY'),
        'from_name' => env('EMAIL_FROM_NAME', env('APP_NAME', 'Fyrfirst Manager')),
        'from_address' => env('EMAIL_FROM_ADDRESS'),
        'reply_to' => env('EMAIL_REPLY_TO'),
        // Mailgun sends through a region-specific host and needs the domain.
        'domain' => env('EMAIL_DOMAIN'),
        'endpoint' => env('EMAIL_ENDPOINT'),
    ],

    /*
    | How hard to try before giving up. Each attempt is a queued job retry;
    | the log records the count so a run of failures is visible rather than
    | silently swallowed.
    */
    'retries' => (int) env('NOTIFICATION_RETRIES', 3),

    /*
    | Seconds between retries. Backs off so a provider having a bad minute is
    | not hammered.
    */
    'retry_backoff' => [30, 120, 600],

    /*
    | Requests to a provider are given up on rather than left hanging - a
    | queue worker blocked on a dead API helps nobody.
    */
    'timeout' => (int) env('NOTIFICATION_TIMEOUT', 15),

];
