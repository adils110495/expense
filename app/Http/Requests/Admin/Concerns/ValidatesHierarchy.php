<?php

namespace App\Http\Requests\Admin\Concerns;

use App\Support\CompanyAccess;
use Illuminate\Validation\Rule;

/**
 * The Company -> Project -> Person rules, in one place.
 *
 * Shared by the expense/credit forms and by the bulk assign screen so the two
 * can never drift apart: whatever a single transaction has to satisfy, a
 * hundred reassigned at once satisfy too.
 */
trait ValidatesHierarchy
{
    /**
     * All three levels required, and each checked against the one above it.
     * This is what makes an ambiguous record impossible - a project that is
     * not the company's, or a person who is not on the project, is rejected
     * rather than saved into a broken branch.
     *
     * @param  bool  $requireActive  Whether the company and project must also
     *                               be active. Creating demands it; editing or
     *                               reassigning an older record does not, or a
     *                               company deactivated after the fact would
     *                               make its history uneditable.
     */
    protected function hierarchyRules(bool $requireActive): array
    {
        $company = Rule::exists('companies', 'id')->whereNull('deleted_at');
        $project = Rule::exists('projects', 'id')
            ->whereNull('deleted_at')
            ->where('company_id', $this->input('company_id'));

        if ($requireActive) {
            $company->where('status', true);
            $project->where('status', true);
        }

        // The authorisation half, and the reason a hand-built POST cannot file
        // money into a company the actor is not mapped to. The form only ever
        // offers their own companies, but an option list is not a guard - this
        // is. Null means an admin, who is restricted to nothing.
        //
        // Only the company needs it: the project is already required to belong
        // to that company, and the person to be assigned to that project, so
        // pinning the top of the chain pins all three.
        $allowed = CompanyAccess::allowedIds();

        if ($allowed !== null) {
            $company->whereIn('id', $allowed ?: [0]);
        }

        return [
            'company_id' => ['required', $company],

            'project_id' => ['required', $project],

            'person_id' => [
                'required',
                Rule::exists('people', 'id')->whereNull('deleted_at'),
                // The pivot is the authority on who may be charged to a
                // project, so the check is against the assignment itself.
                Rule::exists('project_person', 'person_id')
                    ->where('project_id', $this->input('project_id')),
            ],
        ];
    }

    /** @return array<string, string> */
    protected function hierarchyMessages(string $subject = 'record'): array
    {
        return [
            'company_id.required' => 'Please choose the company this '.$subject.' belongs to.',
            // Covers both "no such company" and "not one of yours" - which
            // company exists but is out of reach is not worth telling anyone.
            'company_id.exists' => 'Please choose an active company you have access to.',
            'project_id.required' => 'Please choose a project.',
            'project_id.exists' => 'Please choose a project that belongs to the selected company.',
            'person_id.required' => 'Please choose the person this '.$subject.' belongs to.',
            'person_id.exists' => 'Please choose a person assigned to the selected project.',
        ];
    }
}
