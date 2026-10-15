<?php

namespace App\Http\Controllers;

use App\Models\Privilege;
use Illuminate\Http\Request;

class PrivilegeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(['data' => Privilege::with(['user:id,name,username', 'role:id,role'])->get()]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate(['user_id' => ['required', 'exists:users,id'], 'role_id' => ['required', 'exists:roles,id'], 'save' => ['required', 'in:S,N'], 'update' => ['required', 'in:S,N'], 'delete' => ['required', 'in:S,N'], 'export' => ['required', 'in:S,N']]);
        $privilege = Privilege::updateOrCreate(['user_id' => $data['user_id'], 'role_id' => $data['role_id']], $data);
        return response()->json(['message' => 'Permisos actualizados.', 'data' => $privilege], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Privilege $privilege)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Privilege $privilege)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Privilege $privilege)
    {
        //
    }
}
