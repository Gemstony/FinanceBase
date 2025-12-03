<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\SubShop;
use Illuminate\Http\Request;

class CategoriesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $subshopId = session('subshop_id');
        
        if (!$subshopId) {
            return redirect()->route('subshops.choose', ['intended' => route('categories.index')]);
        }
        
        $subshop = SubShop::findOrFail($subshopId);
        
        if($subshop->is_active != 1) {
            session()->forget('subshop_id');
            return redirect()->route('subshops.choose', ['intended' => route('categories.index')])
                ->with('error', 'Shop is not active. Please contact the owner to activate it.');
        }
        $categories = Category::where('subshop_id', $subshopId);
        
        if ($request->has('search') && !empty($request->search)) {
            $categories->where('name', 'like', '%' . $request->search . '%');
        }
        
        $categories = $categories->paginate(10)->appends($request->query());
        
        return view("inventory.categories.categories", compact("categories", "subshop"));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'subshop_id' => 'required|exists:sub_shops,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $data = $request->only(['subshop_id', 'name', 'description']);
        $data['is_active'] = $request->has('is_active');

        Category::create($data);

        return redirect()->back()->with('success', 'Category created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Category $category)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $data = $request->only(['name', 'description']);
        $data['is_active'] = $request->has('is_active');

        $category->update($data);

        return redirect()->back()->with('success', 'Category updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Category $category)
    {
        $category->delete();

        return redirect()->back()->with('success', 'Category deleted successfully.');
    }

    public function subshops(){
        return redirect()->route('subshops.choose', ['intended' => route('categories.index')]);
    }


  
}
