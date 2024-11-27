<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TachesController extends Controller
{
    /**
     * Afficher toutes les tâches de l'utilisateur connecté
     */
    public function index()
    {
        $userId = Auth::id();

        // Récupérer les tâches créées par l'utilisateur ou assignées à lui
        $taches = DB::table('taches')
            ->where('assigne_a', $userId)
            ->get();

        if ($taches->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Aucune tâche trouvée.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Tâches récupérées avec succès.',
            'data' => $taches,
        ], 200);
    }

    /**
     * Créer une nouvelle tâche
     */
    public function store(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'titre' => 'required|string|max:255',
                'description' => 'required|string',
                'projet_id' => 'required|integer|exists:projets,id',
                'priorite' => 'required|string|in:faible,moyen,urgent',
                'assigne_a' => 'required|integer|exists:users,id',
            ]);

            $tacheId = DB::table('taches')->insertGetId([
                'titre' => $validatedData['titre'],
                'description' => $validatedData['description'],
                'statut' => 'non commencé', // Statut par défaut
                'priorite' => $validatedData['priorite'],
                'projet_id' => $validatedData['projet_id'],
                'assigne_a' => $validatedData['assigne_a'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Tâche créée avec succès.',
                'data' => [
                    'id' => $tacheId,
                    'titre' => $validatedData['titre'],
                    'description' => $validatedData['description'],
                    'statut' => 'non commencé',
                    'priorite' => $validatedData['priorite'],
                    'projet_id' => $validatedData['projet_id'],
                    'assigne_a' => $validatedData['assigne_a'],
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de la tâche.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Afficher une tâche spécifique
     */
    public function show($id)
    {
        $userId = Auth::id();

        $tache = DB::table('taches')
            ->where('id', $id)
            ->where('assigne_a', $userId)
            ->first();

        if (!$tache) {
            return response()->json([
                'success' => false,
                'message' => 'Tâche non trouvée.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $tache,
        ], 200);
    }

    /**
     * Mettre à jour une tâche
     */
    public function update(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'id' => 'required|integer|exists:taches,id',
                'titre' => 'required|string|max:255',
                'description' => 'required|string',
                'statut' => 'required|string|in:non commencé,en cours,terminé',
                'priorite' => 'required|string|in:faible,moyen,urgent',
                'assigne_a' => 'required|integer|exists:users,id',
            ]);

            $tacheId = $validatedData['id'];
            $userId = Auth::id();

            // Vérifier si l'utilisateur a le droit de modifier cette tâche
            $tache = DB::table('taches')
                ->where('id', $tacheId)
                ->first();

            if (!$tache) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tâche non trouvée.',
                ], 404);
            }

            $misAJour = DB::table('taches')
                ->where('id', $tacheId)
                ->update([
                    'titre' => $validatedData['titre'],
                    'description' => $validatedData['description'],
                    'statut' => $validatedData['statut'],
                    'priorite' => $validatedData['priorite'],
                    'assigne_a' => $validatedData['assigne_a'],
                    'updated_at' => now(),
                ]);

            if ($misAJour) {
                return response()->json([
                    'success' => true,
                    'message' => 'Tâche mise à jour avec succès.',
                    'data' => $validatedData,
                ], 200);
            }

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour de la tâche.',
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour de la tâche.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Supprimer une tâche
     */
    public function destroy($id)
    {
        try {
            $userId = Auth::id();

            // Vérifier si la tâche existe et appartient à l'utilisateur
            $tache = DB::table('taches')
                ->where('id', $id)
                ->first();

            if (!$tache) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tâche non trouvée.',
                ], 404);
            }

            // Supprimer la tâche
            DB::table('taches')->where('id', $id)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Tâche supprimée avec succès.',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression de la tâche.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mettre à jour le statut d'une tâche
     */
    public function updateStatut(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'id' => 'required|integer|exists:taches,id',
                'statut' => 'required|string|in:non commencé,en cours,terminé',
            ]);

            $misAJour = DB::table('taches')
                ->where('id', $validatedData['id'])
                ->update([
                    'statut' => $validatedData['statut'],
                    'updated_at' => now(),
                ]);

            if ($misAJour) {
                return response()->json([
                    'success' => true,
                    'message' => 'Statut de la tâche mis à jour avec succès.',
                    'data' => $validatedData,
                ], 200);
            }

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du statut.',
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du statut.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mettre à jour la priorité d'une tâche
     */
    public function updatePriorite(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'id' => 'required|integer|exists:taches,id',
                'priorite' => 'required|string|in:faible,moyen,urgent',
            ]);

            $misAJour = DB::table('taches')
                ->where('id', $validatedData['id'])
                ->update([
                    'priorite' => $validatedData['priorite'],
                    'updated_at' => now(),
                ]);

            if ($misAJour) {
                return response()->json([
                    'success' => true,
                    'message' => 'Priorité de la tâche mise à jour avec succès.',
                    'data' => $validatedData,
                ], 200);
            }

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour de la priorité.',
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour de la priorité.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}