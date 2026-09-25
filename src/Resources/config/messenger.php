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

use FOS\ElasticaBundle\Message\AsyncDeleteObjects;
use FOS\ElasticaBundle\Message\AsyncInsertObjects;
use FOS\ElasticaBundle\Message\AsyncReplaceObjects;
use FOS\ElasticaBundle\Message\Handler\AsyncPersistObjectsHandler;
use FOS\ElasticaBundle\Persister\AsyncObjectPersister;
use FOS\ElasticaBundle\Persister\AsyncPagerPersister;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('fos_elastica.async_pager_persister', AsyncPagerPersister::class)
        ->args([
            service('fos_elastica.pager_persister_registry'),
            service('fos_elastica.pager_provider_registry'),
            service('fos_elastica.messenger.bus'),
        ])
        ->tag('fos_elastica.pager_persister', ['persisterName' => 'async'])
    ;

    // Given to the Doctrine listener of the indexes configured with "listener: { async: true }"
    $services->set('fos_elastica.async_object_persister.prototype', AsyncObjectPersister::class)
        ->abstract()
        ->args([
            abstract_arg('object persister'),
            service('fos_elastica.messenger.bus'),
            abstract_arg('index name'),
            abstract_arg('identifier'),
            service('fos_elastica.property_accessor'),
        ])
    ;

    $services->set('fos_elastica.async_persist_objects_handler', AsyncPersistObjectsHandler::class)
        ->args([
            service('fos_elastica.persister_registry'),
            service('fos_elastica.indexable'),
            abstract_arg('indexes with an async listener'),
        ])
        ->tag('messenger.message_handler', ['handles' => AsyncInsertObjects::class, 'method' => 'insert'])
        ->tag('messenger.message_handler', ['handles' => AsyncReplaceObjects::class, 'method' => 'replace'])
        ->tag('messenger.message_handler', ['handles' => AsyncDeleteObjects::class, 'method' => 'delete'])
    ;
};
