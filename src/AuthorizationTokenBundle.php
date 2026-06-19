<?php

declare(strict_types=1);

namespace Fedale\AuthorizationTokenBundle;

use Fedale\AuthorizationTokenBundle\DependencyInjection\Compiler\RegisterConstraintValidatorsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class AuthorizationTokenBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new RegisterConstraintValidatorsPass());
    }
}
