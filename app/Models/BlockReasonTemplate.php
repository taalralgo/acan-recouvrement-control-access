<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Motif pré-rédigé, proposé à l'agent au moment de suspendre un accès.
 */
#[Fillable(['label', 'body_fr', 'body_en', 'position'])]
class BlockReasonTemplate extends Model
{
    use HasFactory;

    /**
     * Texte à envoyer à la plateforme, dans la langue du groupe concerné.
     *
     * La résolution se fait ici plutôt que sur les plateformes : celles-ci ne
     * stockent qu'une chaîne et n'ont aucune logique de traduction à porter.
     */
    public function bodyForLang(?string $lang): string
    {
        return $lang === 'en' ? $this->body_en : $this->body_fr;
    }
}
