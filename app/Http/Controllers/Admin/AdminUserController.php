<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class AdminUserController extends Controller
{
    /**
     * Liste tous les utilisateurs avec pagination
     */
    public function index(Request $request)
    {
        $query = User::query();

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('telephone', 'like', "%{$search}%");
            });
        }

        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        $users = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json($users);
    }

    /**
     * Affiche les détails d'un utilisateur
     */
    public function show($id)
    {
        $user = User::with(['produits', 'services', 'filleuls'])->findOrFail($id);
        return response()->json($user);
    }

    /**
     * Met à jour le rôle d'un utilisateur
     */
    public function updateRole(Request $request, $id)
    {
        $validated = $request->validate([
            'role' => 'required|string|in:user,admin'
        ]);

        $user = User::findOrFail($id);
        $user->update(['role' => $validated['role']]);

        return response()->json([
            'message' => 'Rôle mis à jour avec succès',
            'user' => $user
        ]);
    }

    /**
     * Supprime un utilisateur (attention : cascade)
     */
    public function destroy($id)
    {
        $user = User::findOrFail($id);
        
        // Sécurité : Ne pas se supprimer soi-même
        if ($user->id === auth()->id()) {
            return response()->json(['message' => 'Vous ne pouvez pas supprimer votre propre compte.'], 403);
        }

        $user->delete();

        return response()->json(['message' => 'Utilisateur supprimé avec succès']);
    }
}
