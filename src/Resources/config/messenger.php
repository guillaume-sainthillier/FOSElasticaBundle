<?php

/*
 * This file is part of the FOSElasticaBundle package.
 *
 * (c) FriendsOfSymfony <https://friendsofsymfony.github.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use FOS\ElasticaBundle\Message\Handler\AsyncPersistPageHandler;
use FOS\ElasticaBundle\Persister\AsyncPagerPersister;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('fos_elastica.async_pager_persister', AsyncPagerPersister::class)
        ->args([
            service('fos_elastica.pager_persister_registry'),
            service('fos_elastica.pager_provider_registry'),
            service('fos_elastica.messenger.bus'),
            service('fos_elastica.index_manager'),
        ])
        ->tag('fos_elastica.pager_persister', ['persisterName' => 'async'])
    ;

    // Named after its class, so that an application which registered it itself keeps its own definition
    // (and AsyncPersistPageHandlerPass drops it when the application has another handler for the message)
    $services->set(AsyncPersistPageHandler::class)
        ->args([service('fos_elastica.async_pager_persister')])
        ->tag('messenger.message_handler')
    ;
};
