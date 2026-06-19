<?php

declare(strict_types=1);

namespace Fedale\AuthorizationTokenBundle\Tests\Support;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Fedale\AuthorizationTokenBundle\Infrastructure\Doctrine\Entity\PersistedAuthorizationToken;

/**
 * Builds a throw-away EntityManager backed by an in-memory SQLite database and
 * creates the schema from the entity attribute mapping.
 */
final class EntityManagerFactory
{
    public static function create(): EntityManagerInterface
    {
        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [\dirname(__DIR__, 2).'/src/Infrastructure/Doctrine/Entity'],
            isDevMode: true,
        );

        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config);
        $entityManager = new EntityManager($connection, $config);

        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->createSchema([
            $entityManager->getClassMetadata(PersistedAuthorizationToken::class),
        ]);

        return $entityManager;
    }
}
