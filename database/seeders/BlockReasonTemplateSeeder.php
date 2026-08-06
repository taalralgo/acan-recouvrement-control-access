<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\BlockReasonTemplate;
use Illuminate\Database\Seeder;

/**
 * Motifs de départ, modifiables depuis l'administration.
 *
 * Ces textes sont lus par les clients : les fournir tout faits évite qu'une
 * équipe non technique improvise un message maladroit dans l'urgence.
 */
class BlockReasonTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'label' => 'Facture impayée',
                'body_fr' => "L'accès à votre espace a été suspendu en raison de factures impayées. Merci de contacter notre service de recouvrement pour régulariser votre situation et rétablir votre accès.",
                'body_en' => 'Your workspace access has been suspended due to unpaid invoices. Please contact our collections team to settle your account and restore access.',
                'position' => 1,
            ],
            [
                'label' => 'Régularisation requise',
                'body_fr' => "Votre accès est temporairement suspendu dans l'attente de la régularisation de votre compte. Notre service de recouvrement se tient à votre disposition.",
                'body_en' => 'Your access is temporarily suspended pending settlement of your account. Our collections team is available to assist you.',
                'position' => 2,
            ],
            [
                'label' => 'Relance sans réponse',
                'body_fr' => "Plusieurs relances sont restées sans réponse et l'accès à votre espace a été suspendu. Merci de contacter notre service de recouvrement dans les meilleurs délais.",
                'body_en' => 'Several reminders have gone unanswered and your workspace access has been suspended. Please contact our collections team as soon as possible.',
                'position' => 3,
            ],
        ];

        foreach ($templates as $template)
        {
            BlockReasonTemplate::updateOrCreate(['label' => $template['label']], $template);
        }
    }
}
