<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Seule base sur laquelle la suite accepte de s'exécuter.
     */
    private const TEST_DATABASE = 'acan_blockaccess_test';

    protected function setUp(): void
    {
        parent::setUp();

        // RefreshDatabase efface les tables : sans ce contrôle, une variable
        // d'environnement mal placée suffirait à vider la base de travail.
        $database = DB::connection()->getDatabaseName();

        if ($database !== self::TEST_DATABASE)
        {
            throw new RuntimeException(sprintf(
                'Les tests doivent viser la base "%s", or la connexion pointe sur "%s". Abandon.',
                self::TEST_DATABASE,
                $database
            ));
        }
    }
}
