<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::latest()->get();

        return response()->json([
            'status' => true,
            'data' => $categories,
        ], 200);
    }

    public function store(Request $request)
    {

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required',
            'status' => 'required',

        ]);

        $imagePath = null;

        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = time().'_'.$image->getClientOriginalName();
            $image->move(public_path('uploads/category'), $imageName);
            $imagePath = 'uploads/category/'.$imageName;
        }

        $category = Category::create([
            'name' => $request->name,
            'description' => $request->description,
            'status' => $request->status,
            'image' => $imagePath,
            'slug' => Str::slug($request->name, '-'),
        ]);

        return response()->json([
            'status' => 'success',
            'category' => $category,
        ]);
    }

    public function edit($id)
    {
        $category = Category::find($id);
        if (! $category) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        return response()->json($category);
    }

    public function update(Request $request, $id)
    {

        $category = Category::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'status' => 'required|in:0,1',

        ]);

        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = time().'_'.$image->getClientOriginalName();
            $image->move(public_path('uploads/category'), $imageName);
            $category->image = 'uploads/category/'.$imageName;
        }

        $category->name = $request->name;
        $category->description = $request->description;
        $category->status = $request->status;
        $category->slug = Str::slug($request->name, '-');

        $category->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Category updated successfully!',
            'category' => $category,
        ]);
    }

    public function destroy($id)
    {
        $category = Category::find($id);

        if (! $category) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        if ($category->image && file_exists(public_path($category->image))) {
            unlink(public_path($category->image));
        }

        $category->delete();

        return response()->json(['message' => 'Category deleted successfully']);
    }
}
