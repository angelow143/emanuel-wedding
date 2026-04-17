<?php

namespace App\Http\Controllers;

use App\Models\RSVP;
use Illuminate\Http\Request;

class RSVPController extends Controller
{
    public function index()
    {
        return RSVP::latest()->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'attending' => 'required|boolean',
            'message' => 'nullable|string'
        ]);

        return RSVP::create($validated);
    }
}
