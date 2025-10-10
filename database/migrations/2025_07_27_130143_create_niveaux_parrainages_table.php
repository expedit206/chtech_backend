<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        Schema::create('niveaux_parrainages', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('emoji');
            $table->string('couleur'); // Nouvelle colonne pour la couleur
            $table->unsignedInteger('filleuls_requis');
            $table->unsignedInteger('jetons_bonus');
            $table->json('avantages');
            $table->timestamps();
            $table->index('filleuls_requis');
        });

        // Insertion initiale des niveaux avec couleurs
      DB::table('niveaux_parrainages')->insert([
    [
        'nom' => 'Débutant', 
        'emoji' => '🚀', 
        'couleur' => '#6B7280', 
        'filleuls_requis' => 0, 
        'jetons_bonus' => 0, 
        'avantages' => json_encode([
            'Bienvenue dans le programme de parrainage',
            'Accès à votre tableau de progression'
        ]), 
        'created_at' => now(), 
        'updated_at' => now()
    ],
    [
        'nom' => 'Explorateur', 
        'emoji' => '🔍', 
        'couleur' => '#10B981', 
        'filleuls_requis' => 3, 
        'jetons_bonus' => 10, 
        'avantages' => json_encode([
            '+10 jetons bonus',
            'Badge "Explorateur" sur votre profil',
            'Visibilité accrue dans les recherches'
        ]), 
        'created_at' => now(), 
        'updated_at' => now()
    ],
    [
        'nom' => 'Partenaire', 
        'emoji' => '🤝', 
        'couleur' => '#3B82F6', 
        'filleuls_requis' => 10, 
        'jetons_bonus' => 30, 
        'avantages' => json_encode([
            '+30 jetons bonus',
            'Badge animé "Partenaire"',
            'Mise en avant de vos annonces (+1 position)',
            'Statistiques de parrainage détaillées'
        ]), 
        'created_at' => now(), 
        'updated_at' => now()
    ],
    [
        'nom' => 'Influenceur', 
        'emoji' => '🌟', 
        'couleur' => '#8B5CF6', 
        'filleuls_requis' => 25, 
        'jetons_bonus' => 75, 
        'avantages' => json_encode([
            '+75 jetons bonus',
            'Badge "Influenceur" lumineux',
            'Mise en avant prioritaire des annonces (+3 positions)',
            'Apparition dans le classement des parrains',
            'Accès aux insights marché premium'
        ]), 
        'created_at' => now(), 
        'updated_at' => now()
    ],
    [
        'nom' => 'Expert', 
        'emoji' => '🎯', 
        'couleur' => '#F59E0B', 
        'filleuls_requis' => 50, 
        'jetons_bonus' => 150, 
        'avantages' => json_encode([
            '+150 jetons bonus',
            'Badge "Expert" exclusif',
            'Mise en avant maximale des annonces (+5 positions)',
            'Support prioritaire 24h/24',
            'Accès aux tendances marché en avant-première',
            'Profil vérifié et certifié'
        ]), 
        'created_at' => now(), 
        'updated_at' => now()
    ],
    [
        'nom' => 'Leader', 
        'emoji' => '🔥', 
        'couleur' => '#EF4444', 
        'filleuls_requis' => 100, 
        'jetons_bonus' => 300, 
        'avantages' => json_encode([
            '+300 jetons bonus',
            'Badge "Leader" animé avec effets',
            'Position "Top Sponsor" sur la plateforme',
            'Bannière personnalisée sur votre profil',
            'Invitations exclusives aux lancements de features',
            'Accès au programme ambassadeur'
        ]), 
        'created_at' => now(), 
        'updated_at' => now()
    ],
    [
        'nom' => 'Ambassadeur', 
        'emoji' => '🏅', 
        'couleur' => '#FFD700', 
        'filleuls_requis' => 200, 
        'jetons_bonus' => 600, 
        'avantages' => json_encode([
            '+600 jetons bonus',
            'Badge "Ambassadeur" doré animé',
            'Page de profil personnalisée avec bannière exclusive',
            'Mention "Ambassadeur Officiel"',
            'Accès aux programmes beta en avant-première',
            'Spotlight mensuel sur vos annonces',
            'Recommandation officielle auprès des nouveaux utilisateurs'
        ]), 
        'created_at' => now(), 
        'updated_at' => now()
    ],
    [
        'nom' => 'Légende', 
        'emoji' => '👑', 
        'couleur' => '#FF6B35', 
        'filleuls_requis' => 500, 
        'jetons_bonus' => 1500, 
        'avantages' => json_encode([
            '+1500 jetons bonus',
            'Badge "Légende" unique et scintillant',
            'Statut VIP éternel sur la plateforme',
            'Profil mis en avant sur la page d\'accueil',
            'Section "Légende du mois" dédiée',
            'Mentions spéciales lors des événements communautaires',
            'Accès direct à l\'équipe de direction',
            'Droit de veto sur les nouvelles features',
            'Place réservée dans le Hall of Fame'
        ]), 
        'created_at' => now(), 
        'updated_at' => now()
    ],
]);
    }

    public function down()
    {
        Schema::dropIfExists('niveaux_parrainages');
    }
};      