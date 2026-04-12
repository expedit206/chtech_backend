<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Parrainage;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
     /**
      * Enregistre un nouvel utilisateur
      */
    public function register(Request $request)
    {
        $request->validate([
            'nom' => 'required|string|max:255',
            'telephone' => 'required|string|max:20|unique:users,telephone',
            'mot_de_passe' => 'required|string|min:8',
        ]);

        $user = User::create([
            'nom' => $request->nom,
            'telephone' => $request->telephone,
            'mot_de_passe' => Hash::make($request->mot_de_passe),
            'premium' => false,
        ]);

        $codeGenerate = $this->generateCode($user);
        $user->parrainage_code = $codeGenerate;
        $user->save();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Inscription réussie',
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    /**
     * Authentifie un utilisateur par email ou téléphone
     */
   public function login(Request $request)
    {
        $request->validate([
            'login' => 'required|string',
            'mot_de_passe' => 'required|string',
        ]);

        $field = filter_var($request->input('login'), FILTER_VALIDATE_EMAIL) ? 'email' : 'telephone';
        $user = User::where($field, $request->input('login'))->first();

        if (!$user || !Hash::check($request->mot_de_passe, $user->mot_de_passe)) {
            throw ValidationException::withMessages([
                'login' => ['Les informations d\'identification sont incorrectes.'],
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Connexion réussie',
            'user' => $user,
            'token' => $token,
        ]);
    }


    /**
     * Génère un code de parrainage unique pour l'utilisateur
     */
     public function generateCode(User $user)
    {

        if (!$user) {
            return response()->json(['message' => 'Utilisateur non authentifié'], 401);
        }

        $suggestedCode = Str::random(3) . '' . Str::slug($user->nom);
        $suggestedCode = strtoupper(substr($suggestedCode, 0, 6));

        while (User::where('parrainage_code', $suggestedCode)->exists()) {
            $suggestedCode = Str::slug($user->nom) . '-' . Str::random(4);
            $suggestedCode = strtoupper(substr($suggestedCode, 0, 6));
        }

        return $suggestedCode;
    }


    // Redirection vers Google (WEB uniquement)
    /**
     * Redirige l'utilisateur vers la page d'authentification Google
     */
    public function redirectToGoogle(Request $request)
    {
        $driver = Socialite::driver('google')->stateless();

        // Build state with action
        $state = [];
        if ($request->query('action')) {
            $state['action'] = $request->query('action');
        }

        // Get the redirect URL from Socialite
        $redirectUrl = $driver->redirect()->getTargetUrl();

        // Append state and prompt parameters
        if (!empty($state)) {
            $redirectUrl .= strpos($redirectUrl, '?') !== false ? '&' : '?';
            $redirectUrl .= 'state=' . urlencode(http_build_query($state));
        }
        $redirectUrl .= (strpos($redirectUrl, '?') !== false ? '&' : '?') . 'prompt=select_account';

        return redirect()->away($redirectUrl);
    }
    

    // Callback Google (WEB uniquement)
    /**
     * Traite le retour de l'authentification Google (Web)
     */
    public function handleGoogleCallback(Request $request)
    {
        $action = 'login'; // Default
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();

            if ($request->state) {
                parse_str(urldecode($request->state), $stateParams);
                $action = $stateParams['action'] ?? 'login';
            }

            $parrainId = null;

            // Find user
            $user = User::where('google_id', $googleUser->id)
                ->orWhere('email', $googleUser->email)
                ->first();

            if ($action === 'register') {
                // Registration mode: prevent creation if user exists
                if ($user) {
                    $frontendUrl = env('FRONTEND_URL', 'http://localhost:4000') . '/login';
                    return redirect()->away($frontendUrl . '?error=' . urlencode('Compte déjà existant. Veuillez vous connecter.'));
                }

                // Create new user
                $user = User::create([
                    'nom' => $googleUser->name,
                    'email' => $googleUser->email,
                    'google_id' => $googleUser->id,
                    'telephone' => null,
                    'mot_de_passe' => Hash::make(Str::random(16)),
                    'premium' => false,
                    'parrain_id' => $parrainId,
                ]);
            } else {
                // Login mode: redirect if no user
                if (!$user) {
                    $frontendUrl = env('FRONTEND_URL', 'http://localhost:4000') . '/register';
                    return redirect()->away($frontendUrl . '?error=' . urlencode('Aucun compte trouvé. Veuillez vous inscrire.'));
                } elseif (!$user->google_id) {
                    $user->update([
                        'google_id' => $googleUser->id, 
                    ]);
                }
            }

            // Generate Sanctum token
            $token = $user->createToken('auth_token')->plainTextToken;

            // REDIRECTION UNIQUEMENT POUR WEB
            $frontendUrl = env('FRONTEND_URL', 'http://localhost:4000') . '/auth/google/callback';
            return redirect()->away($frontendUrl . '?token=' . urlencode($token) . '&user=' . urlencode(json_encode($user->load('niveaux_users.parrainageNiveau'))));

        } catch (\Exception $e) {
            // Gestion d'erreur pour WEB uniquement
            $frontendUrl = env('FRONTEND_URL', 'http://localhost:4000') . '/' . ($action === 'register' ? 'register' : 'login');
            return redirect()->away($frontendUrl . '?error=' . urlencode('Erreur lors de la ' . ($action === 'register' ? 'inscription' : 'connexion') . ' avec Google: ' . $e->getMessage()));
        }
    }


      // Déconnexion
    /**
     * Révoque le jeton d'accès actuel pour la déconnexion
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Déconnexion réussie'], 200);
    }
}