<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Purpose;

class PurposeController extends Controller
{
    public function index()
    {
        $purposes = Purpose::orderBy('name', 'asc')->get();
        return view('admin.purposes.index', compact('purposes'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:purposes',
        ]);

        // Fix #1: new purposes default to active so they appear in student dropdown immediately
        Purpose::create([
            'name'      => $request->name,
            'is_active' => true,
        ]);

        return back()->with('success', 'Purpose added successfully!');
    }

    public function update(Request $request, $id)
    {
        $purpose = Purpose::findOrFail($id);
        $purpose->update(['is_active' => $request->is_active]);
        return back()->with('success', 'Purpose status updated!');
    }

    public function destroy($id)
    {
        Purpose::findOrFail($id)->delete();
        return back()->with('success', 'Purpose removed!');
    }
}
