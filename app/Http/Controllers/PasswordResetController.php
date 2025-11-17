<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class PasswordResetController extends Controller
{
    /**
     * Génère un token de réinitialisation
     */
    public function generateResetToken(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        try {
            $email = $request->email;
            
            // Vérifier si l'utilisateur existe
            $user = User::where('email', $email)->first();
            
            if (!$user) {
                return response()->json([
                    'message' => 'Aucun utilisateur trouvé avec cette adresse email.'
                ], 404);
            }

            // Générer un token unique
            $token = Str::random(60);
            $tokenExpiresAt = Carbon::now()->addHours(1); // Expire dans 1 heure

            // Stocker ou mettre à jour le token
            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $email],
                [
                    'token' => $token,
                    'token_expires_at' => $tokenExpiresAt,
                    'created_at' => Carbon::now(),
                ]
            );

            return response()->json([
                'message' => 'Token généré avec succès',
                'token' => $token,
                'expires_at' => $tokenExpiresAt->toISOString(),
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Erreur génération token: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Une erreur est survenue lors de la génération du token.'
            ], 500);
        }
    }

    /**
     * Réinitialise le mot de passe avec le token
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|email|exists:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        try {
            $email = $request->email;
            $token = $request->token;
            $password = $request->password;

            // Récupérer le token de réinitialisation
            $passwordReset = DB::table('password_reset_tokens')
                ->where('email', $email)
                ->first();

            // Vérifier si le token existe
            if (!$passwordReset) {
                return response()->json([
                    'message' => 'Token de réinitialisation invalide.'
                ], 400);
            }

            // Vérifier si le token correspond
            if (!hash_equals($passwordReset->token, $token)) {
                return response()->json([
                    'message' => 'Token de réinitialisation invalide.'
                ], 400);
            }

            // Vérifier si le token n'a pas expiré
            if (Carbon::parse($passwordReset->token_expires_at)->isPast()) {
                DB::table('password_reset_tokens')->where('email', $email)->delete();
                
                return response()->json([
                    'message' => 'Le token a expiré. Veuillez en demander un nouveau.'
                ], 400);
            }

            // Trouver l'utilisateur
            $user = User::where('email', $email)->first();
            
            if (!$user) {
                return response()->json([
                    'message' => 'Utilisateur non trouvé.'
                ], 404);
            }

            // Mettre à jour le mot de passe
            $user->update([
                'mot_de_passe' => Hash::make($password),
            ]);

            // Supprimer le token utilisé
            DB::table('password_reset_tokens')->where('email', $email)->delete();

            return response()->json([
                'message' => 'Votre mot de passe a été réinitialisé avec succès.'
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Erreur réinitialisation mot de passe: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Une erreur est survenue lors de la réinitialisation du mot de passe.'
            ], 500);
        }
    }

    /**
     * Vérifie la validité d'un token
     */
    public function verifyToken(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|email|exists:users,email',
        ]);

        try {
            $email = $request->email;
            $token = $request->token;

            $passwordReset = DB::table('password_reset_tokens')
                ->where('email', $email)
                ->first();

            if (!$passwordReset) {
                return response()->json([
                    'valid' => false,
                    'message' => 'Token non trouvé.'
                ], 404);
            }

            if (!hash_equals($passwordReset->token, $token)) {
                return response()->json([
                    'valid' => false,
                    'message' => 'Token invalide.'
                ], 400);
            }

            if (Carbon::parse($passwordReset->token_expires_at)->isPast()) {
                DB::table('password_reset_tokens')->where('email', $email)->delete();
                
                return response()->json([
                    'valid' => false,
                    'message' => 'Token expiré.'
                ], 400);
            }

            return response()->json([
                'valid' => true,
                'message' => 'Token valide.',
                'expires_at' => $passwordReset->token_expires_at
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Erreur vérification token: ' . $e->getMessage());
            
            return response()->json([
                'valid' => false,
                'message' => 'Erreur lors de la vérification du token.'
            ], 500);
        }
    }
}