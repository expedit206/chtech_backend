    <?php

    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Database\Schema\Blueprint;
    use Illuminate\Support\Facades\Schema;

    return new class extends Migration
    {
        /**
         * Run the migrations.
         */
        public function up(): void
        {
            Schema::create('services', function (Blueprint $table) {
            $table->uuid('id')->primary();
                
                // Références
                $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');
                $table->foreignUuid('category_id')->constrained('category_services')->onDelete('cascade');
                
                // Informations de base
                $table->string('titre', 255);
                $table->text('description');

                // Détails professionnels
                $table->integer('annees_experience')->nullable();
                $table->json('competences')->nullable();
                $table->text('qualifications')->nullable();
                
                // Localisation
                $table->string('localisation', 100);
                $table->string('ville', 100)->nullable();
                
                // Disponibilité
                $table->enum('disponibilite', ['disponible', 'indisponible'])->default('disponible');
                // Médias
                $table->json('images')->nullable();
                    
                    // Métadonnées
                    $table->decimal('note_moyenne', 3, 1)->default(0.00);
                    $table->integer('nombre_avis')->default(0);
                    
        $table->timestamps();
                
                // Index
                $table->index('user_id');
                $table->index('id');
                $table->index('category_id');
                $table->index('localisation');
                $table->index('ville');
                $table->index('disponibilite');
                $table->index('note_moyenne');
            });
        }

        /**
         * Reverse the migrations.
         */
        public function down(): void
        {
            Schema::dropIfExists('services');
        }
    };
