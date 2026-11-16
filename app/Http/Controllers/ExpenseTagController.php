<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Despesas → Tags: the labels that classify expenses. Create them ahead of time, rename,
 * recolour or delete them. Renaming a tag to the name of another one merges the two.
 */
class ExpenseTagController extends Controller
{
    public function index(): View
    {
        $tags = Tag::query()
            ->withCount('expenses')
            ->withSum('expenses', 'amount')
            ->withMax('expenses', 'spent_on')
            ->orderBy('name')
            ->get();

        return view('expenses.tags', [
            'tags' => $tags,
            'counts' => ExpenseController::tabCounts(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $tag = Tag::findOrCreateByName($data['name']);
        if (! $tag->wasRecentlyCreated) {
            return back()->with('error', __('The tag #:name already exists.', ['name' => $tag->name]));
        }

        $tag->update(['color' => $data['color'] ?? $tag->color]);

        return back()->with('success', __('Tag #:name created.', ['name' => $tag->name]));
    }

    public function update(Request $request, Tag $tag): RedirectResponse
    {
        $data = $this->validated($request);
        $name = Tag::normalize($data['name']);
        $slug = Str::slug($name) ?: mb_strtolower($name);

        $other = Tag::query()->where('slug', $slug)->whereKeyNot($tag->id)->first();

        if ($other) {
            $this->merge($tag, $other);

            return back()->with('success', __('#:from merged into #:into.', ['from' => $tag->name, 'into' => $other->name]));
        }

        $tag->update(['name' => $name, 'slug' => $slug, 'color' => $data['color'] ?? $tag->color]);

        return back()->with('success', __('Tag #:name saved.', ['name' => $tag->name]));
    }

    public function destroy(Tag $tag): RedirectResponse
    {
        $tag->delete(); // expense_tag rows cascade; the expenses themselves stay.

        return back()->with('success', __('Tag #:name deleted. Its expenses were kept.', ['name' => $tag->name]));
    }

    /** Moves every expense of $from to $into (skipping ones that already have it), then deletes $from. */
    private function merge(Tag $from, Tag $into): void
    {
        DB::transaction(function () use ($from, $into): void {
            $already = DB::table('expense_tag')->where('tag_id', $into->id)->pluck('expense_id');

            DB::table('expense_tag')->where('tag_id', $from->id)->whereNotIn('expense_id', $already)->update(['tag_id' => $into->id]);
            $from->delete();
        });
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:40', 'regex:/[\pL\pN]/u'],
            'color' => ['nullable', Rule::in(Tag::COLORS)],
        ], [], ['name' => __('name'), 'color' => __('colour')]);
    }
}
