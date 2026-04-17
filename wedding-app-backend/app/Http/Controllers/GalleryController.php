<?php

namespace App\Http\Controllers;

use App\Models\Gallery;
use Illuminate\Http\Request;

class GalleryController extends Controller
{
    public function index()
    {
        return response()->json(Gallery::with('user')->orderBy('created_at', 'desc')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'image' => 'nullable|string', // for single
            'images' => 'nullable|array', // for bulk
            'title' => 'nullable|string|max:255',
            'album_id' => 'nullable|exists:albums,id'
        ]);

        $userId = $request->user()->id;
        $albumId = $validated['album_id'] ?? null;
        $title = $validated['title'] ?? 'Wedding Memory';

        $results = [];

        // Prefer bulk `images` array if provided (even if it has only 1 item)
        // This prevents double-creation when frontend sets both `image` and `images`
        if (!empty($validated['images'])) {
            foreach ($validated['images'] as $imgData) {
                $gallery = Gallery::create([
                    'image' => $imgData,
                    'title' => $title,
                    'user_id' => $userId,
                    'album_id' => $albumId
                ]);
                $results[] = $gallery;
            }
        } elseif (!empty($validated['image'])) {
            // Fallback to single image only if images array is absent/empty
            $gallery = Gallery::create([
                'image' => $validated['image'],
                'title' => $title,
                'user_id' => $userId,
                'album_id' => $albumId
            ]);
            $results[] = $gallery;
        }

        return response()->json($results, 201);
    }

    public function destroy(Request $request, Gallery $gallery)
    {
        if ($request->user()->id !== $gallery->user_id && $request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $gallery->delete();

        return response()->json(['message' => 'Photo deleted successfully']);
    }
}
