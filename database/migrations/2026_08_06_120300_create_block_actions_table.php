<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Journal des décisions, jamais modifié après écriture.
        //
        // groupe_name, platform_name, actor_name et actor_email sont des
        // instantanés figés au moment de l'action : c'est ce qui permet de
        // répondre à un client en litige des mois plus tard, alors que le
        // compte de l'agent a été supprimé ou que le groupe a disparu.
        Schema::create('block_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('groupe_id')->nullable()->constrained('groupes')->nullOnDelete();
            $table->string('groupe_name');
            $table->string('platform_name');
            $table->enum('action', ['block', 'unblock']);
            $table->text('reason')->nullable();
            $table->string('actor_name');
            $table->string('actor_email');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['groupe_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('block_actions');
    }
};
