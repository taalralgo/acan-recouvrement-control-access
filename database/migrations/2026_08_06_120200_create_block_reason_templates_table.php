<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Motifs pré-rédigés. Ces textes sont lus par les clients : les
        // proposer tout faits évite qu'une équipe non technique improvise un
        // message maladroit dans l'urgence.
        Schema::create('block_reason_templates', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->text('body_fr');
            $table->text('body_en');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('block_reason_templates');
    }
};
