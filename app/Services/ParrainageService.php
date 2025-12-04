<?php

namespace App\Services;

use App\Models\Parrainage;


// Services/ParrainageService.php
class ParrainageService
{
    public function demanderVerificationEmail(Parrainage $parrainage, string $email)
    {
        // Vérifier que le parrainage est en attente
        if ($parrainage->statut !== 'en_attente') {
            throw new \Exception('Ce parrainage a déjà été traité');
        }

        // Générer le code
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        
        $parrainage->update([
            'email_verification' => $email,
            'code_verification' => $code,
            'code_expire_le' => now()->addHours(24),
        ]);

        // Envoyer l'email

        return $code;
    }

 
// Services/ParrainageService.php
public function verifierEmailEtAttribuerBonus(string $email, string $code)
{
    $parrainage = Parrainage::where('email_verification', $email)
        ->where('code_verification', $code)
        ->where('statut', 'en_attente')
        ->where('code_expire_le', '>', now())
        ->first();

    if (!$parrainage) {
        throw new \Exception('Code invalide, expiré ou déjà utilisé');
    }

    // Marquer comme vérifié
    $parrainage->update([
        'email_verifie' => true,
        'email_verifie_le' => now(),
        'statut' => 'bonus_attribue',
    ]);

    // Attribuer le bonus au parrain
    $this->attribuerBonusAuParrain($parrainage);

    return $parrainage;
}

private function attribuerBonusAuParrain(Parrainage $parrainage)
{
    $parrain = $parrainage->parrain;
    
    // Ajouter les tokens
    $parrain->jetons += 1;
    $parrain->save();
    // Marquer le bonus comme attribué
    $parrainage->update([
        'bonus_attribue' => true,
        'bonus_attribue_le' => now(),
    ]);

}


}