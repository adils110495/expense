<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(Request $request): View
    {
        $type = in_array($request->query('type'), Category::TYPES, true)
            ? $request->query('type')
            : null;

        return view('admin.categories.index', [
            'categories' => Category::query()
                // Trashed rows still hold a foreign key, so they count towards
                // "in use" - otherwise the Delete button would be offered for
                // a category the database will refuse to drop.
                ->withCount(['transactions' => fn ($q) => $q->withTrashed()])
                ->when($type, fn ($q) => $q->where('type', $type))
                ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->query('q').'%'))
                // Deleted rows are already excluded by the soft-delete scope;
                // this filter is only Active vs Inactive.
                ->when($this->statusFilter($request) !== null,
                    fn ($q) => $q->where('status', $this->statusFilter($request)))
                ->orderBy('type')
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString(),
            'activeType' => $type,
            'activeStatus' => $request->query('status'),
        ]);
    }

    /**
     * '1' => active, '0' => inactive, anything else => no filter.
     */
    private function statusFilter(Request $request): ?bool
    {
        return match ($request->query('status')) {
            '1' => true,
            '0' => false,
            default => null,
        };
    }

    public function store(Request $request): RedirectResponse
    {
        Category::create($this->validated($request));

        return back()->with('success', 'Category added.');
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $category->update($this->validated($request, $category));

        return back()->with('success', 'Category updated.');
    }

    /**
     * Flipping status is separate from a full edit so the table can toggle
     * a category inline without resubmitting its name and type.
     */
    public function toggle(Category $category): RedirectResponse
    {
        $category->update(['status' => ! $category->status]);

        return back()->with('success', sprintf(
            'Category "%s" %s.',
            $category->name,
            $category->status ? 'activated' : 'deactivated'
        ));
    }

    public function destroy(Category $category): RedirectResponse
    {
        // A category in use is never deleted - the transactions would lose
        // their classification. Deactivating hides it from new entries instead.
        if ($category->transactions()->withTrashed()->exists()) {
            return back()->with('error', sprintf(
                'Cannot delete "%s" - it is used by %d transaction(s). Deactivate it instead.',
                $category->name,
                $category->transactions()->withTrashed()->count()
            ));
        }

        $category->delete();

        return back()->with('success', 'Category deleted.');
    }

    private function validated(Request $request, ?Category $category = null): array
    {
        $data = $request->validate([
            'name' => [
                'required', 'string', 'min:2', 'max:80',
                // whereNull('deleted_at') so a name freed up by a deleted
                // category can be used again.
                Rule::unique('categories', 'name')
                    ->where('type', $request->input('type'))
                    ->whereNull('deleted_at')
                    ->ignore($category?->id),
            ],
            'type' => ['required', Rule::in(Category::TYPES)],
            'status' => ['nullable', 'boolean'],
        ]);

        // Forms post a hidden status=0 alongside the checkbox, so an unchecked
        // box reliably deactivates instead of being read as "absent".
        $data['status'] = $request->boolean('status');

        return $data;
    }
}
