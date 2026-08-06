<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Miroir local des groupes de chaque plateforme.
        //
        // Ce n'est pas une commodité : les plateformes vivent sur d'autres
        // serveurs, et sans copie locale une seule injoignable rendrait toute
        // l'interface inutilisable. L'état de blocage n'y est écrit qu'après
        // confirmation de la plateforme concernée.
        Schema::create('groupes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_id')->constrained('saas_platforms')->cascadeOnDelete();
            $table->unsignedBigInteger('external_id');
            $table->string('code')->nullable();
            $table->string('name');
            $table->string('lang', 5)->default('fr');
            $table->unsignedInteger('users_count')->default(0);
            $table->boolean('is_blocked')->default(false);
            $table->timestamp('blocked_at')->nullable();
            $table->text('block_reason')->nullable();
            // Reflet du flag `enabled` de la plateforme, décidé par ses admins.
            // Affiché séparément : sans quoi un déblocage laisserait le client
            // dehors sans que l'équipe comprenne pourquoi.
            $table->boolean('platform_enabled')->default(true);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['platform_id', 'external_id']);
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('groupes');
    }
};
