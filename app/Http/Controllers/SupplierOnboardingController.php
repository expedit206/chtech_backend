<?php

namespace App\Http\Controllers;

use App\Models\SupplierRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SupplierOnboardingController extends Controller
{
    public function store(Request $request)
    {
        $user = Auth::user();

        // Check if user already has a pending or approved request
        $existing = SupplierRequest::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'approved'])
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Vous avez déjà une demande en cours ou approuvée.'
            ], 400);
        }

        $request->validate([
            'company_name' => 'required|string|max:255',
            'description' => 'required|string|min:20',
        ]);

        SupplierRequest::create([
            'user_id' => $user->id,
            'company_name' => $request->company_name,
            'description' => $request->description,
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Votre demande a été envoyée avec succès et est en attente de révision.'
        ]);
    }

    public function status()
    {
        $request = SupplierRequest::where('user_id', Auth::id())->first();
        return response()->json(['success' => true, 'data' => $request]);
    }
}
