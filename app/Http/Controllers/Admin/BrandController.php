<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BrandController extends Controller
{
    public function index()
    {
        $brands = Brand::latest()->get();

        return response()->json([
            'status' => true,
            'data' => $brands,
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
            $image->move(public_path('uploads/brand'), $imageName);
            $imagePath = 'uploads/brand/'.$imageName;
        }

        $brand = Brand::create([
            'name' => $request->name,
            'description' => $request->description,
            'status' => $request->status,
            'image' => $imagePath,
            'slug' => Str::slug($request->name, '-'),
        ]);

        return response()->json([
            'status' => 'success',
            'category' => $brand,
        ]);
    }

    public function edit($id)
    {
        $brand = Brand::find($id);
        if (! $brand) {
            return response()->json(['message' => 'Brand not found'], 404);
        }

        return response()->json($brand);
    }

    public function update(Request $request, $id)
    {

        $brand = Brand::findOrFail($id);

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
        $brand = Brand::find($id);

        if (! $brand) {
            return response()->json(['message' => 'Brand not found'], 404);
        }

        if ($brand->image && file_exists(public_path($brand->image))) {
            unlink(public_path($brand->image));
        }
        $brand->delete();

        return response()->json(['message' => 'Brand deleted successfully']);
    }
}
