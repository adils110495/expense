<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Working defaults for every event, on both channels.
     *
     * Seeded as a migration rather than a seeder so the feature is usable the
     * moment it is deployed - an empty template table would mean silence with
     * no obvious cause. All of them are editable in the admin panel.
     *
     * updateOrInsert keyed on event+channel, so re-running never duplicates a
     * row and never overwrites wording the admin has since changed.
     */
    public function up(): void
    {
        $now = now();

        foreach ($this->templates() as $template) {
            DB::table('notification_templates')->updateOrInsert(
                ['event' => $template['event'], 'channel' => $template['channel']],
                $template + ['created_at' => $now, 'updated_at' => $now],
            );
        }
    }

    public function down(): void
    {
        DB::table('notification_templates')->delete();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function templates(): array
    {
        $signature = "\n\n-- {{company_name}}";

        return [
            [
                'event' => 'expense_created',
                'channel' => 'whatsapp',
                'subject' => null,
                'body' => "*Expense Added*\n\nProject: {{project_name}}\nPerson: {{person_name}}\n"
                    ."Expense: {{expense_title}}\nAmount: {{expense_amount}}\nDate: {{transaction_date}}".$signature,
                'is_active' => true,
            ],
            [
                'event' => 'expense_created',
                'channel' => 'email',
                'subject' => 'Expense added: {{expense_title}} ({{expense_amount}})',
                'body' => "An expense has been recorded against {{project_name}}.\n\n"
                    ."Person: {{person_name}}\nExpense: {{expense_title}}\nAmount: {{expense_amount}}\n"
                    .'Date: {{transaction_date}}',
                'is_active' => true,
            ],

            [
                'event' => 'expense_updated',
                'channel' => 'whatsapp',
                'subject' => null,
                'body' => "*Transaction Updated*\n\nProject: {{project_name}}\nPerson: {{person_name}}\n"
                    ."Type: {{transaction_type}}\n{{expense_title}}\n\n"
                    ."Was: {{previous_amount}}\nNow: {{expense_amount}}\nDate: {{transaction_date}}".$signature,
                'is_active' => true,
            ],
            [
                'event' => 'expense_updated',
                'channel' => 'email',
                'subject' => 'Transaction updated: {{expense_title}}',
                'body' => "A transaction on {{project_name}} has been changed.\n\n"
                    ."Person: {{person_name}}\nType: {{transaction_type}}\n"
                    ."Previous amount: {{previous_amount}}\nNew amount: {{expense_amount}}\n"
                    .'Date: {{transaction_date}}',
                'is_active' => true,
            ],

            [
                'event' => 'expense_deleted',
                'channel' => 'whatsapp',
                'subject' => null,
                'body' => "*Transaction Removed*\n\n{{expense_title}}\nAmount: {{expense_amount}}\n"
                    ."Project: {{project_name}}\n\nThe settlement calculation has been updated.".$signature,
                'is_active' => true,
            ],
            [
                'event' => 'expense_deleted',
                'channel' => 'email',
                'subject' => 'Transaction removed: {{expense_title}}',
                'body' => "A transaction has been removed from {{project_name}}.\n\n"
                    ."{{expense_title}}\nAmount: {{expense_amount}}\n\n"
                    .'The settlement calculation has been updated.',
                'is_active' => true,
            ],

            [
                'event' => 'credit_created',
                'channel' => 'whatsapp',
                'subject' => null,
                'body' => "*Credit Added*\n\nProject: {{project_name}}\nPerson: {{person_name}}\n"
                    ."Amount: {{credit_amount}}\nDate: {{transaction_date}}".$signature,
                'is_active' => true,
            ],
            [
                'event' => 'credit_created',
                'channel' => 'email',
                'subject' => 'Credit added: {{credit_amount}} on {{project_name}}',
                'body' => "A credit has been recorded against {{project_name}}.\n\n"
                    ."Person: {{person_name}}\nAmount: {{credit_amount}}\nDate: {{transaction_date}}",
                'is_active' => true,
            ],

            [
                'event' => 'settlement_reminder',
                'channel' => 'whatsapp',
                'subject' => null,
                'body' => "*Settlement Reminder*\n\nYou need to pay:\n\n*{{amount_to_pay}}*\n\n"
                    ."To: {{receiver_name}}\nProject: {{project_name}}\n\n"
                    ."Reason: Equal project distribution\nStatus: {{settlement_status}}".$signature,
                'is_active' => true,
            ],
            [
                'event' => 'settlement_reminder',
                'channel' => 'email',
                'subject' => 'Settlement reminder: {{amount_to_pay}} to {{receiver_name}}',
                'body' => "Hello {{partner_name}},\n\n"
                    ."You need to pay {{amount_to_pay}} to {{receiver_name}} on {{project_name}}.\n\n"
                    ."Reason: Equal project distribution\nStatus: {{settlement_status}}\n\n"
                    .'Your equal share on this project is {{equal_share}}.',
                'is_active' => true,
            ],

            [
                'event' => 'settlement_summary',
                'channel' => 'whatsapp',
                'subject' => null,
                'body' => "*Settlement Summary*\n\nYou need to receive:\n\n*{{amount_to_receive}}*\n\n"
                    ."From:\n{{payer_breakdown}}\n\nProject: {{project_name}}".$signature,
                'is_active' => true,
            ],
            [
                'event' => 'settlement_summary',
                'channel' => 'email',
                'subject' => 'You are owed {{amount_to_receive}} on {{project_name}}',
                'body' => "Hello {{partner_name}},\n\n"
                    ."You are due to receive {{amount_to_receive}} on {{project_name}}.\n\n"
                    ."From:\n{{payer_breakdown}}",
                'is_active' => true,
            ],

            [
                'event' => 'settlement_paid',
                'channel' => 'whatsapp',
                'subject' => null,
                'body' => "*Settlement Paid*\n\n{{payer_name}} has paid {{settlement_amount}} "
                    ."to {{receiver_name}}.\n\nProject: {{project_name}}\nStatus: {{settlement_status}}".$signature,
                'is_active' => true,
            ],
            [
                'event' => 'settlement_paid',
                'channel' => 'email',
                'subject' => 'Settlement paid: {{settlement_amount}}',
                'body' => "{{payer_name}} has paid {{settlement_amount}} to {{receiver_name}}.\n\n"
                    ."Project: {{project_name}}\nStatus: {{settlement_status}}",
                'is_active' => true,
            ],

            [
                'event' => 'monthly_summary',
                'channel' => 'whatsapp',
                'subject' => null,
                'body' => "*Monthly Financial Summary*\n\nProject: {{project_name}}\n\n"
                    ."Total Credit: {{total_credit}}\nTotal Expense: {{total_expense}}\n"
                    ."Balance: {{balance}}\n\nPending Settlement: {{pending_settlement}}".$signature,
                'is_active' => true,
            ],
            [
                'event' => 'monthly_summary',
                'channel' => 'email',
                'subject' => 'Monthly summary for {{project_name}}',
                'body' => "Hello {{partner_name}},\n\nHere is where {{project_name}} stands.\n\n"
                    ."Total Credit: {{total_credit}}\nTotal Expense: {{total_expense}}\nBalance: {{balance}}\n\n"
                    ."Your equal share: {{equal_share}}\nPending settlement: {{pending_settlement}}",
                'is_active' => true,
            ],

            [
                'event' => 'test',
                'channel' => 'whatsapp',
                'subject' => null,
                'body' => 'Test message from {{company_name}}. If you can read this, WhatsApp is configured correctly.',
                'is_active' => true,
            ],
            [
                'event' => 'test',
                'channel' => 'email',
                'subject' => 'Test email from {{company_name}}',
                'body' => 'This is a test. If you can read this, email delivery is configured correctly.',
                'is_active' => true,
            ],
        ];
    }
};
