<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Commercant;
use Illuminate\Validation\Rule;

class PartenaireController extends Controller
{
    public function index()
    {
        $partenaires = Commercant::orderBy('created_at', 'desc')->get();
        return response()->json($partenaires);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom_boutique' => 'required|string|max:255',
            'nom_responsable' => 'required|string|max:255',
            'telephone' => 'required|string|max:20',
            'marche' => 'required|string|max:255',
            'numero_boutique' => 'nullable|string|max:50',
            'statut' => ['required', Rule::in(['prospect', 'partenaire', 'inactif'])],
            'notes' => 'nullable|string',
        ]);

        $partenaire = Commercant::create($validated);

        return response()->json($partenaire, 201);
    }

    public function show($id)
    {
        $partenaire = Commercant::with('produits')->findOrFail($id);
        return response()->json($partenaire);
    }

    public function update(Request $request, $id)
    {
        $partenaire = Commercant::findOrFail($id);

        $validated = $request->validate([
            'nom_boutique' => 'sometimes|required|string|max:255',
            'nom_responsable' => 'sometimes|required|string|max:255',
            'telephone' => 'sometimes|required|string|max:20',
            'marche' => 'sometimes|required|string|max:255',
            'numero_boutique' => 'nullable|string|max:50',
            'statut' => ['sometimes', 'required', Rule::in(['prospect', 'partenaire', 'inactif'])],
            'notes' => 'nullable|string',
        ]);

        $partenaire->update($validated);

        return response()->json($partenaire);
    }

    public function destroy($id)
    {
        $partenaire = Commercant::findOrFail($id);
        // Les produits associés auront commercant_id mis à NULL grâce à onDelete('set null') dans la migration
        $partenaire->delete();

        return response()->json(['message' => 'Partenaire supprimé avec succès']);
    }
}
