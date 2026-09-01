<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const COMPANY = 'Unassigned Company';

    private const PROJECT = 'Unassigned Project';

    private const PERSON = 'Unassigned';

    /**
     * Gives transactions that predate the hierarchy somewhere to hang.
     *
     * Nothing is invented on a fresh install: the placeholder company,
     * project and person are only created when there is actually an orphaned
     * transaction to attach. They are ordinary editable records, so the admin
     * can rename them or move the transactions onto the real hierarchy and
     * then delete them.
     */
    public function up(): void
    {
        $orphans = DB::table('transactions')->whereNull('company_id')->count();

        if ($orphans === 0) {
            return;
        }

        $now = now();

        $companyId = DB::table('companies')->insertGetId([
            'name' => self::COMPANY,
            'description' => 'Created automatically for transactions recorded before companies, projects and people existed. Move them onto the real hierarchy, then delete this.',
            'status' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $projectId = DB::table('projects')->insertGetId([
            'company_id' => $companyId,
            'name' => self::PROJECT,
            'description' => 'Holding project for transactions recorded before the hierarchy existed.',
            'status' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $personId = DB::table('people')->insertGetId([
            'name' => self::PERSON,
            'designation' => 'Placeholder',
            'status' => true,
            'notes' => 'Holding person for transactions recorded before the hierarchy existed.',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('project_person')->insert([
            'project_id' => $projectId,
            'person_id' => $personId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // withTrashed by nature - this is the query builder, so soft deleted
        // transactions are updated too. They must stay consistent in case
        // they are ever restored.
        DB::table('transactions')
            ->whereNull('company_id')
            ->update([
                'company_id' => $companyId,
                'project_id' => $projectId,
                'person_id' => $personId,
            ]);
    }

    /**
     * Detaches the transactions again and removes the placeholders.
     *
     * Deliberately narrow: only rows still carrying the exact placeholder
     * names are touched, and each one is removed only once nothing else
     * depends on it. If the admin renamed the placeholders, or added real
     * projects and people under them, those are real records now and this
     * leaves them alone rather than deleting someone's data on a rollback.
     */
    public function down(): void
    {
        $company = DB::table('companies')->where('name', self::COMPANY)->first();

        if (! $company) {
            return;
        }

        // Put the transactions back where they were before the backfill.
        DB::table('transactions')
            ->where('company_id', $company->id)
            ->update(['company_id' => null, 'project_id' => null, 'person_id' => null]);

        $project = DB::table('projects')
            ->where('company_id', $company->id)
            ->where('name', self::PROJECT)
            ->first();

        if ($project) {
            $personIds = DB::table('project_person')
                ->where('project_id', $project->id)
                ->pluck('person_id');

            DB::table('project_person')->where('project_id', $project->id)->delete();

            if (! DB::table('transactions')->where('project_id', $project->id)->exists()) {
                DB::table('projects')->where('id', $project->id)->delete();
            }

            // The placeholder person goes only if nothing at all still points
            // at them - no other project, no transaction of any kind.
            foreach ($personIds as $personId) {
                $stillUsed = DB::table('project_person')->where('person_id', $personId)->exists()
                    || DB::table('transactions')->where('person_id', $personId)->exists();

                if (! $stillUsed) {
                    DB::table('people')
                        ->where('id', $personId)
                        ->where('name', self::PERSON)
                        ->delete();
                }
            }
        }

        // And the company only once it is genuinely empty.
        $companyStillUsed = DB::table('projects')->where('company_id', $company->id)->exists()
            || DB::table('transactions')->where('company_id', $company->id)->exists();

        if (! $companyStillUsed) {
            DB::table('companies')->where('id', $company->id)->delete();
        }
    }
};
