<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index()
    {
        $setting = Setting::first();

        if (! $setting) {
            $setting = Setting::create([]);
        }

        return response()->json([
            'success' => true,
            'data' => $setting,
        ]);

    }

    public function update(Request $request)
    {
        $setting = Setting::first();

        if (! $setting) {
            $setting = Setting::create([]);
        }
        $imageFields = ['site_logo', 'admin_logo', 'favicon', 'seo_image'];
        $data = $request->only($setting->getFillable());
        foreach ($imageFields as $field) {
            if ($request->hasFile($field)) {
                $image = $request->file($field);

                $request->validate([
                    $field => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048',
                ]);
                $originalName = pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME);
                $extension = $image->getClientOriginalExtension();
                $imageName = time().'_'.$originalName.'.'.$extension;
                $folder = 'uploads/settings';
                $image->move(public_path($folder), $imageName);
                $data[$field] = $folder.'/'.$imageName;
                if ($setting->$field && file_exists(public_path($setting->$field))) {
                    @unlink(public_path($setting->$field));
                }
            }
        }

        $setting->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Settings updated successfully',
            'data' => $setting,
        ]);
    }
}
