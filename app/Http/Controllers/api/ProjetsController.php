<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class ProjetsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Récupérer tous les utilisateurs
        $utilisateurs = DB::table('users')->get();
    
        // Vérifier si des utilisateurs existent
        if ($utilisateurs->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun utilisateur trouvé.',
            ], 404); // Aucun utilisateur trouvé
        }
    
        $resultats = [];
    
        // Pour chaque utilisateur, récupérer ses projets
        foreach ($utilisateurs as $utilisateur) {
            // Récupérer les projets de cet utilisateur et les trier par ordre alphabétique
            $projets = DB::table('projets')
                         ->where('userp_id', $utilisateur->id) // Filtrer par ID utilisateur
                         ->orderBy('titre', 'asc') // Trier les projets par titre
                         ->get();
    
            // Ajouter les informations de l'utilisateur et ses projets à la réponse
            $resultats[] = [
                'utilisateur' => [
                    'nom' => $utilisateur->name,
                ],
                'projets' => $projets,
            ];
        }
    
        // Retourner les données utilisateur avec leurs projets associés
        return response()->json([
            'success' => true,
            'message' => 'Utilisateurs et projets récupérés avec succès.',
            'data' => $resultats,
        ], 200); // Code HTTP 200 pour récupération réussie
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
        // Validation des données du formulaire
        try {
            $validatedData = $request->validate([
                'titre' => 'required|string|max:255',
                'description' => 'required|string',
                'date_limite' => 'required|date|after:today',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation échouée.',
                'errors' => $e->errors(),
            ], 422); // Code HTTP 422 pour validation échouée
        }
    
        // Récupérer l'utilisateur connecté
        $userId = Auth::id();
    
        // Tentative d'insertion dans la base de données
        try {
            $projetId = DB::table('projets')->insertGetId([
                'titre' => $validatedData['titre'],
                'description' => $validatedData['description'],
                'date_limite' => $validatedData['date_limite'],
                'statut' => 'en cours', // Statut par défaut
                'userp_id' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    
            // Retourner la réponse JSON avec le projet créé
            return response()->json([
                'success' => true,
                'message' => 'Projet créé avec succès.',
                'data' => [
                    'id' => $projetId,
                    'titre' => $validatedData['titre'],
                    'description' => $validatedData['description'],
                    'date_limite' => $validatedData['date_limite'],
                    'statut' => 'en cours',
                    'userp_id' => $userId,
                ],
            ], 201); // Code HTTP 201 pour création réussie
        } catch (\Exception $e) {
            // En cas d'échec de l'insertion
            return response()->json([
                'success' => false,
                'message' => 'Une erreur s\'est produite lors de la création du projet.',
                'error' => $e->getMessage(),
            ], 500); // Code HTTP 500 pour erreur serveur
        }
    }
    

    /**
     * Display the specified resource.
     */
    public function show()
    {
        // Récupère l'ID de l'utilisateur connecté
        $userId = Auth::id();

        // Requête pour récupérer les projets liés à cet utilisateur
        $projets = DB::table('projets')
            ->where('userp_id', $userId)
            ->get();

        // Si aucun projet n'est trouvé
        if ($projets->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun projet trouvé pour cet utilisateur.',
            ], 404);
        }

        // Retourne les projets sous forme JSON
        return response()->json([
            'success' => true,
            'data' => $projets,
        ], 200);
    }


    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request)
    {
        // Validation de l'ID passé dans la requête
        $validatedData = $request->validate([
            'id' => 'required|integer|exists:projets,id', // ID obligatoire et doit exister dans la table projets
        ]);
    
        // Récupérer l'ID du projet
        $projetId = $validatedData['id'];
    
        // Récupérer les informations du projet
        $projet = DB::table('projets')->where('id', $projetId)->first();
    
        // Vérifier si le projet existe (cette étape est redondante grâce à la validation)
        if (!$projet) {
            return response()->json([
                'success' => false,
                'message' => 'Projet non trouvé.',
            ], 404); // Code HTTP 404 si le projet est introuvable
        }
    
        // Retourner les informations du projet
        return response()->json([
            'success' => true,
            'message' => 'Données du projet récupérées avec succès.',
            'data' => $projet,
        ], 200); // Code HTTP 200 pour succès
    }
    
    public function updateStatut(Request $request)
    {
        // Validation des données du formulaire
        $request->validate([
            'id' => 'required|integer|exists:projets,id', // Vérifie que l'ID est valide et existe dans la table projets
            'statut' => 'required|string|in:en cours,terminé', // Les seuls statuts valides
        ]);

        // Récupérer l'ID du projet depuis le formulaire
        $projetId = $request->input('id');

        // Récupérer le projet en question
        $projet = DB::table('projets')->where('id', $projetId)->first();

        // Mettre à jour le statut du projet
        $misAJour = DB::table('projets')
                    ->where('id', $projetId)
                    ->update(['statut' => $request->input('statut')]);

        if ($misAJour) {
            return response()->json([
                'success' => true,
                'message' => 'Statut du projet mis à jour avec succès.',
                'data' => [
                    'id' => $projetId,
                    'ancien_statut' => $projet->statut,
                    'nouveau_statut' => $request->input('statut'),
                ],
            ], 200); // Code HTTP 200 pour mise à jour réussie
        }

        // En cas d'échec de la mise à jour
        return response()->json([
            'success' => false,
            'message' => 'Une erreur s\'est produite lors de la mise à jour du statut.',
        ], 500); // Code HTTP 500 pour erreur serveur
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request)
    {
        // Validation des données du formulaire
        $validatedData = $request->validate([
            'id' => 'required|integer|exists:projets,id', 
            'titre' => 'required|string|max:255',
            'description' => 'required|string',
            'date_limite' => 'required|date|after:today',
            'statut' => 'required|string|in:en cours,terminé', 
        ]);

        // Récupérer l'ID du projet à mettre à jour
        $projetId = $validatedData['id'];

        // Récupérer les informations du projet avant la mise à jour
        $projet = DB::table('projets')->where('id', $projetId)->first();

        // Vérifier si le projet existe (précaution supplémentaire)
        if (!$projet) {
            return response()->json([
                'success' => false,
                'message' => 'Projet non trouvé.',
            ], 404); // Code HTTP 404 pour projet introuvable
        }

        // Mettre à jour le projet dans la base de données
        $misAJour = DB::table('projets')
                    ->where('id', $projetId)
                    ->update([
                        'titre' => $validatedData['titre'],
                        'description' => $validatedData['description'],
                        'date_limite' => $validatedData['date_limite'],
                        'statut' => $validatedData['statut'],
                        'updated_at' => now(), // Mettre à jour la colonne `updated_at`
                    ]);

        if ($misAJour) {
            return response()->json([
                'success' => true,
                'message' => 'Projet mis à jour avec succès.',
                'data' => [
                    'id' => $projetId,
                    'ancien' => [
                        'titre' => $projet->titre,
                        'description' => $projet->description,
                        'date_limite' => $projet->date_limite,
                        'statut' => $projet->statut,
                    ],
                    'nouveau' => [
                        'titre' => $validatedData['titre'],
                        'description' => $validatedData['description'],
                        'date_limite' => $validatedData['date_limite'],
                        'statut' => $validatedData['statut'],
                    ],
                ],
            ], 200); // Code HTTP 200 pour mise à jour réussie
        }

        // En cas d'échec de la mise à jour
        return response()->json([
            'success' => false,
            'message' => 'Une erreur s\'est produite lors de la mise à jour du projet.',
        ], 500); // Code HTTP 500 pour erreur serveur
    }


    /**
     * Remove the specified resource from storage.
     */
    public function delete(Request $request)
{
    // Validation de l'ID du projet envoyé dans la requête
    $request->validate([
        'projet_id' => 'required|integer|exists:projets,id', 
    ]);

    $projetId = 'projet_id';


    // Trouver le projet par ID 
    $projet = DB::table('projets')->where('id', $projetId)->first();

    if (!$projet) {
        return response()->json([
            'success' => false,
            'message' => 'Le projet avec cet ID n\'existe pas.',
        ], 404); 
    }



    // Suppression du projet
    try {
        DB::table('projets')->where('id', $projetId)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Projet supprimé avec succès.',
        ], 200); // Projet supprimé avec succès
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Une erreur s\'est produite lors de la suppression du projet.',
            'error' => $e->getMessage(),
        ], 500); // Erreur serveur
    }
}

    
}
