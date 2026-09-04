<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * The worked example from the spec: three users, two companies, and the
 * mapping between them.
 *
 * Deliberately NOT a migration. Migrations run automatically on deploy, and
 * creating login accounts with a known password is not something that should
 * happen to a live database without somebody asking for it. Run it by hand:
 *
 *     php artisan db:seed --class=Database\\Seeders\\UserCompanySeeder
 *
 * Idempotent, and additive: users are keyed on email and companies on name,
 * so re-running repairs rather than duplicates, and an existing Fryfirst
 * company keeps its id, its projects and all of its history. The mapping uses
 * syncWithoutDetaching, so any company an admin has already mapped by hand
 * survives a re-run.
 */
class UserCompanySeeder extends Seeder
{
    /** Every seeded account starts with the same password; change them after. */
    private const DEFAULT_PASSWORD = 'password123';

    private const MAPPING = [
        'Nazim' => ['Fryfirst', 'Sabright'],
        'Adil' => ['Fryfirst', 'Sabright'],
        'Aabid' => ['Fryfirst'],
    ];

    public function run(): void
    {
        // firstOrCreate, not updateOrCreate: if these companies already exist
        // they are real ones with real money on them, and their description
        // and status are not this seeder's to overwrite.
        $companies = collect(['Fryfirst', 'Sabright'])
            ->mapWithKeys(fn (string $name) => [
                $name => Company::firstOrCreate(['name' => $name], ['status' => true]),
            ]);

        foreach (self::MAPPING as $name => $companyNames) {
            $user = User::updateOrCreate(
                ['email' => mb_strtolower($name).'@example.com'],
                [
                    'name' => $name,
                    // Hashed by the model's 'hashed' cast.
                    'password' => self::DEFAULT_PASSWORD,
                    'status' => true,
                ],
            );

            $user->companies()->syncWithoutDetaching(
                $companies->only($companyNames)->pluck('id')->all(),
            );
        }

        $this->command?->info('Seeded 3 users and 2 companies. Password: '.self::DEFAULT_PASSWORD);
        $this->command?->warn('Change these passwords before using them for anything real.');
    }
}
