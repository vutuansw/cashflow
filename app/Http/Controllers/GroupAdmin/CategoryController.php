<?php

namespace App\Http\Controllers\GroupAdmin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Category;
use App\Models\Group;
use App\Models\Transaction;
use App\Support\CurrentGroup;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Loại thu chi của nhóm, xem plan/03 mục 1. */
class CategoryController extends Controller
{
    public function index()
    {
        $used = Transaction::query()->withTrashed()
            ->selectRaw('category_id, count(*) as total')
            ->groupBy('category_id')
            ->pluck('total', 'category_id');

        return Category::query()
            ->orderBy('type')->orderBy('sort_order')->orderBy('id')
            ->get()
            ->map(fn (Category $c) => $this->present($c, (int) ($used[$c->id] ?? 0)));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['sort_order'] = (int) Category::where('type', $data['type'])->max('sort_order') + 1;

        return response()->json($this->present(Category::create($data), 0), 201);
    }

    public function update(Request $request, Group $group, Category $category)
    {
        $data = $this->validated($request, $category);
        unset($data['type']);
        $data['archived_at'] = $request->boolean('archived') ? ($category->archived_at ?? now()) : null;
        $category->update($data);

        return $this->present($category, $this->usage($category));
    }

    public function reorder(Request $request)
    {
        $data = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']]);
        $categories = Category::whereIn('id', $data['ids'])->get()->keyBy('id');

        foreach (array_values($data['ids']) as $i => $id) {
            $categories->get($id)?->update(['sort_order' => $i]);
        }

        return $this->index();
    }

    public function destroy(Group $group, Category $category)
    {
        if ($this->usage($category) > 0 || Campaign::where('category_id', $category->id)->exists()) {
            throw ValidationException::withMessages(['category' => 'Loại này đã có phiếu hoặc chiến dịch, chỉ ẩn được chứ không xóa được.']);
        }

        $category->delete();

        return response()->noContent();
    }

    private function validated(Request $request, ?Category $category = null): array
    {
        $type = $category?->type->value ?? $request->input('type');

        return $request->validate([
            'type' => [$category ? 'nullable' : 'required', 'in:income,expense'],
            'name' => [
                'required', 'string', 'max:80',
                Rule::unique('categories', 'name')
                    ->where('group_id', app(CurrentGroup::class)->id())
                    ->where('type', $type)
                    ->ignore($category?->id),
            ],
            'icon' => ['nullable', 'string', 'max:40', 'regex:/^[a-z0-9-]+$/'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);
    }

    private function usage(Category $category): int
    {
        return Transaction::withTrashed()->where('category_id', $category->id)->count();
    }

    private function present(Category $c, int $used): array
    {
        return [
            'id' => $c->id,
            'type' => $c->type->value,
            'name' => $c->name,
            'icon' => $c->icon,
            'color' => $c->color,
            'is_system' => $c->is_system,
            'archived' => $c->archived_at !== null,
            'transactions_count' => $used,
        ];
    }
}
