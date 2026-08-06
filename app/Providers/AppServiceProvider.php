<?php

declare(strict_types=1);

namespace App\Providers;

use App\Saas\HttpSaasConnector;
use App\Saas\SaasConnector;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Toutes nos plateformes exposent le même contrat REST : une seule
        // implémentation suffit. L'interface reste le point d'extension si un
        // futur SAAS ne peut pas l'exposer.
        $this->app->bind(SaasConnector::class, HttpSaasConnector::class);
    }

    public function boot(): void
    {
        // Un accès à une relation non chargée passe inaperçu en développement
        // et devient une avalanche de requêtes en production.
        Model::preventLazyLoading(!$this->app->isProduction());
    }
}
