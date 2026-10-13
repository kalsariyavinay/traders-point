<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));

        $categories = Category::query()
            ->withCount('videos')
            ->when($search !== '', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('admin.categories.index', [
            'categories' => $categories,
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        return view('admin.categories.create', ['category' => new Category]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $name = $request->validated('name');

        $category = Category::create([
            'name' => $name,
            'slug' => Category::uniqueSlug($name),
        ]);

        return to_route('admin.categories.index')
            ->with('success', "Category \"{$category->name}\" was created.");
    }

    public function edit(Category $category): View
    {
        return view('admin.categories.edit', ['category' => $category]);
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $name = $request->validated('name');

        $category->name = $name;

        // Keep the existing slug unless the name actually changed.
        if ($category->isDirty('name')) {
            $category->slug = Category::uniqueSlug($name, $category->id);
        }

        $category->save();

        return to_route('admin.categories.index')
            ->with('success', "Category \"{$category->name}\" was updated.");
    }

    public function destroy(Category $category): RedirectResponse
    {
        // videos.category_id cascades on delete, so refuse rather than silently
        // wiping a category's whole catalogue.
        if ($category->videos()->exists()) {
            return to_route('admin.categories.index')
                ->with('error', "\"{$category->name}\" still has videos. Move or delete them first.");
        }

        $name = $category->name;
        $category->delete();

        return to_route('admin.categories.index')
            ->with('success', "Category \"{$name}\" was deleted.");
    }
}
