<?php

/*
 * This file is part of the FOSElasticaBundle package.
 *
 * (c) FriendsOfSymfony <https://friendsofsymfony.github.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FOS\ElasticaBundle\DependencyInjection\Compiler;

use FOS\ElasticaBundle\Message\AsyncPersistPage;
use FOS\ElasticaBundle\Message\Handler\AsyncPersistPageHandler;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Removes the bundle's handler for {@see AsyncPersistPage} when the application has its own,
 * so that each page is not persisted twice.
 */
class AsyncPersistPageHandlerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(AsyncPersistPageHandler::class)) {
            return;
        }

        foreach ($container->findTaggedServiceIds('messenger.message_handler') as $id => $tags) {
            if (AsyncPersistPageHandler::class === $id) {
                continue;
            }

            foreach ($tags as $tag) {
                if (\in_array(AsyncPersistPage::class, $this->getHandledMessages($container, $id, $tag), true)) {
                    $container->removeDefinition(AsyncPersistPageHandler::class);

                    return;
                }
            }
        }
    }

    /**
     * The messages a handler tag declares, or else the types of its method's argument, as Messenger guesses them.
     *
     * @param array<string, mixed> $tag
     *
     * @return list<string>
     */
    private function getHandledMessages(ContainerBuilder $container, string $id, array $tag): array
    {
        if (isset($tag['handles'])) {
            return (array) $tag['handles'];
        }

        $class = $container->getParameterBag()->resolveValue($container->getDefinition($id)->getClass() ?? $id);
        $reflection = \is_string($class) ? $container->getReflectionClass($class, false) : null;
        $method = $tag['method'] ?? '__invoke';
        if (null === $reflection || !$reflection->hasMethod($method)) {
            return [];
        }

        $type = ($reflection->getMethod($method)->getParameters()[0] ?? null)?->getType();
        $types = $type instanceof \ReflectionUnionType ? $type->getTypes() : [$type];

        return \array_values(\array_map(
            static fn (\ReflectionNamedType $type): string => $type->getName(),
            \array_filter($types, static fn (?\ReflectionType $type): bool => $type instanceof \ReflectionNamedType)
        ));
    }
}
