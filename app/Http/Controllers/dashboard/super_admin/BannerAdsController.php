<?php

namespace App\Http\Controllers\dashboard\super_admin;

use App\Http\Controllers\Controller;
use App\Models\BannerAd;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class BannerAdsController extends Controller
{
    public function index()
    {
        $bannerAds = BannerAd::orderBy('sort_order')->latest()->paginate(10);

        return view('dashboard.super_admin.banner_ads.index', compact('bannerAds'));
    }

    public function store(Request $request)
    {
        $validated = Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:255'],
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'click_url' => ['nullable', 'url'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        if ($validated->fails()) {
            flash()->addError('حدث خطأ أثناء إضافة الإعلان.');
            return redirect()->back()->withErrors($validated)->withInput();
        }

        $data = $validated->validated();
        $imagePath = $request->file('image')->store('banner-ads', 'public');

        BannerAd::create([
            'title' => $data['title'],
            'image_path' => $imagePath,
            'click_url' => $data['click_url'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'status' => $data['status'],
        ]);

        flash()->addSuccess('تم إضافة الإعلان بنجاح.');

        return redirect()->route('banner-ads.index');
    }

    public function update(Request $request, $id)
    {
        $validated = Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'click_url' => ['nullable', 'url'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        if ($validated->fails()) {
            flash()->addError('حدث خطأ أثناء تحديث الإعلان.');
            return redirect()->back()->withErrors($validated)->withInput();
        }

        $bannerAd = BannerAd::findOrFail($id);
        $data = $validated->validated();
        $imagePath = $bannerAd->image_path;

        if ($request->hasFile('image')) {
            if ($imagePath) {
                Storage::disk('public')->delete($imagePath);
            }

            $imagePath = $request->file('image')->store('banner-ads', 'public');
        }

        $bannerAd->update([
            'title' => $data['title'],
            'image_path' => $imagePath,
            'click_url' => $data['click_url'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'status' => $data['status'],
        ]);

        flash()->addSuccess('تم تحديث الإعلان بنجاح.');

        return redirect()->route('banner-ads.index');
    }

    public function destroy($id)
    {
        $bannerAd = BannerAd::findOrFail($id);

        if ($bannerAd->image_path) {
            Storage::disk('public')->delete($bannerAd->image_path);
        }

        $bannerAd->delete();

        flash()->addSuccess('تم حذف الإعلان بنجاح.');

        return redirect()->route('banner-ads.index');
    }
}
