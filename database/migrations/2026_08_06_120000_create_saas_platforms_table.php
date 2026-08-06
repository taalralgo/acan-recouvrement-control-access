<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Plateformes raccordées (TVe, puis les suivantes). base_url et
        // api_token sont modifiables depuis l'interface : les projets changent
        // de serveur, et l'équipe doit pouvoir corriger sans redéploiement.
        Schema::create('saas_platforms', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('base_url');
            $table->text('api_token');
            $table->boolean('active')->default(true);
            $table->timestamp('last_reachable_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saas_platforms');
    }
};
