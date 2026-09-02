<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

/**
 * Editable wording for one event on one channel.
 */
class NotificationTemplate extends Model
{
    use LogsActivity;

    /** Every event the system can raise, in the order the settings page lists them. */
    public const EVENTS = [
        'expense_created' => 'Expense Created',
        'expense_updated' => 'Transaction Updated',
        'expense_deleted' => 'Transaction Deleted',
        'credit_created' => 'Credit Created',
        'settlement_reminder' => 'Settlement Reminder',
        'settlement_summary' => 'Settlement Summary',
        'settlement_paid' => 'Settlement Paid',
        'monthly_summary' => 'Monthly Financial Summary',
        'test' => 'Test Message',
    ];

    public const CHANNELS = [
        'whatsapp' => 'WhatsApp',
        'email' => 'Email',
    ];

    /**
     * Every placeholder a template may use, with a one-line explanation. Shown
     * beside the editor so the list cannot drift from what the renderer
     * actually substitutes.
     */
    public const VARIABLES = [
        'partner_name' => 'The person being messaged',
        'person_name' => 'The person a transaction belongs to',
        'company_name' => 'Company the project belongs to',
        'project_name' => 'Project name',
        'expense_title' => 'Title of the transaction',
        'transaction_type' => 'Expense or Credit',
        'expense_amount' => 'Amount of the expense',
        'credit_amount' => 'Amount of the credit',
        'previous_amount' => 'Amount before an edit',
        'transaction_date' => 'Date on the transaction',
        'total_expense' => 'Project total expenses',
        'total_credit' => 'Project total credit',
        'balance' => 'Project credit less expenses',
        'equal_share' => 'This partner\'s equal share',
        'amount_to_pay' => 'What this partner owes',
        'amount_to_receive' => 'What this partner is owed',
        'payer_name' => 'Partner making the payment',
        'receiver_name' => 'Partner receiving the payment',
        'payer_breakdown' => 'Who owes this partner, line by line',
        'settlement_amount' => 'Amount of one settlement',
        'settlement_status' => 'Pending, Paid and so on',
        'pending_settlement' => 'Still to settle on the project',
    ];

    protected $fillable = [
        'event', 'channel', 'subject', 'body',
        'whatsapp_template_name', 'language', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function getEventLabelAttribute(): string
    {
        return self::EVENTS[$this->event] ?? $this->event;
    }

    /**
     * The template for an event and channel, or null when there is none or it
     * has been switched off - in which case nothing is sent, rather than
     * something unworded being sent.
     */
    public static function resolve(string $event, string $channel): ?self
    {
        return self::query()
            ->where('event', $event)
            ->where('channel', $channel)
            ->where('is_active', true)
            ->first();
    }
}
