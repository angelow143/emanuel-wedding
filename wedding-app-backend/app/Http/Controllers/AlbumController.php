<?php

namespace App\Http\Controllers;

use App\Models\Album;
use Illuminate\Http\Request;

class AlbumController extends Controller
{
    public function index()
    {
        return response()->json(Album::with(['photos', 'user'])->orderBy('created_at', 'desc')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255'
        ]);

        $album = Album::create([
            'name' => $validated['name'],
            'user_id' => $request->user()->id,
        ]);

        return response()->json($album->load(['photos', 'user']), 201);
    }

    public function update(Request $request, Album $album)
    {
        if ($request->user()->id !== $album->user_id && $request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255'
        ]);

        $album->update(['name' => $validated['name']]);

        return response()->json($album->load(['photos', 'user']));
    }

    public function destroy(Request $request, Album $album)
    {
        if ($request->user()->id !== $album->user_id && $request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $album->photos()->delete(); // Delete all photos in the album first
        $album->delete();

        return response()->json(['message' => 'Album deleted successfully']);
    }
}
