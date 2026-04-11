<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupplierRequest;
use App\Models\User;
use Illuminate\Http\Request;

class SupplierRequestController extends Controller
{
    public function index(Request $request)
    {
        $query = SupplierRequest::with('user')->orderBy('created_at', 'desc');
        
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $requests = $query->paginate(15);
        return response()->json(['success' => true, 'data' => $requests]);
    }

    public function update(Request $request, $id)
    {
        $supplierRequest = SupplierRequest::findOrFail($id);
        
        $request->validate([
            'status' => 'required|in:approved,rejected',
        ]);

        $supplierRequest->update([
            'status' => $request->status,
            'admin_notes' => $request->admin_notes,
        ]);

        if ($request->status === 'approved') {
            $user = $supplierRequest->user;
            $user->role = User::ROLE_VENDEUR;
            $user->save();
        }

        return response()->json(['success' => true, 'message' => 'Statut mis à jour avec succès']);
    }
}
