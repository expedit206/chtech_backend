<?php

namespace App\Http\Controllers\Admin;

use App\Models\Service;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class AdminServiceController extends Controller
{
    /**
     * Liste tous les services avec pagination
     */
    public function index(Request $request)
    {
        $query = Service::with(['user', 'category']);

        if ($request->has('search')) {
            $search = $request->search;
            $query->where('titre', 'like', "%{$search}%");
        }

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        $services = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json($services);
    }

    /**
     * Active/Désactive un service
     */
    public function toggleStatus($id)
    {
        $service = Service::findOrFail($id);
        $status = $service->disponibilite === 'disponible' ? 'indisponible' : 'disponible';
        $service->update(['disponibilite' => $status]);

        return response()->json([
            'message' => "Service marqué comme $status",
            'service' => $service
        ]);
    }

    /**
     * Supprime un service
     */
    public function destroy($id)
    {
        $service = Service::findOrFail($id);
        $service->delete();

        return response()->json(['message' => 'Service supprimé avec succès']);
    }
}
