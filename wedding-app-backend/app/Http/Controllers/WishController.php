<?php

namespace App\Http\Controllers;

use App\Models\Wish;
use Illuminate\Http\Request;

class WishController extends Controller
{
    public function index(Request $request)
    {
        $query = Wish::latest();
        
        // Show hidden wishes only if the user is an admin
        $user = $request->user('sanctum');
        if (!$user || $user->role !== 'admin') {
            $query->where('is_hidden', false);
        }
        
        return $query->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'message' => 'required|string',
            'category' => 'nullable|string',
            'image' => 'nullable|string'
        ]);

        return Wish::create($validated);
    }

    public function toggleVisibility(Request $request, Wish $wish)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Only Super Admin can perform this action.'], 403);
        }
        
        $wish->is_hidden = !$wish->is_hidden;
        $wish->save();
        return response()->json($wish);
    }

    public function destroy(Request $request, Wish $wish)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Only Super Admin can perform this action.'], 403);
        }

        $wish->delete();
        return response()->json(['message' => 'Wish deleted successfully.']);
    }
}
