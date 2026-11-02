<?php

declare(strict_types=1);

namespace App\ProviderSetup;

use DateTimeImmutable;
use Prism\Prism\Enums\Provider as ProviderId;
use Prism\Prism\Providers\Provider;
use ReflectionClass;
use RuntimeException;

final class ProviderGuides
{
    public function all(): array
    {
        $config = (new CoreProviderConfig)->providers();
        $ports = json_decode((string) file_get_contents(resource_path('providers/port-environment.json')), true, flags: JSON_THROW_ON_ERROR);
        $guides = [];
        foreach (glob(resource_path('providers/*.json')) ?: [] as $file) {
            $id = basename($file, '.json');
            if (in_array($id, ['coverage', 'port-environment', 'schema'], true)) {
                continue;
            }
            $content = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
            if (! isset($config[$id]) || ($content['id'] ?? null) !== $id) {
                throw new RuntimeException('Provider guide does not match the installed Prism configuration.');
            }
            $latest = max($content['researchedOn'], $content['verifiedOn'] ?? '');
            $guides[$id] = [...$content,
                'environment' => $config[$id],
                'portEnvironment' => [
                    'typescript' => $ports['typescript'][$id] ?? [],
                    'python' => $ports['python'][$id] ?? [],
                ],
                'capabilities' => $this->capabilities($id),
                'stale' => (new DateTimeImmutable($latest))->modify('+90 days') < new DateTimeImmutable('today'),
            ];
        }

        return $guides;
    }

    private function capabilities(string $id): array
    {
        $provider = ProviderId::from($id);
        $class = new ReflectionClass('Prism\\Prism\\Providers\\'.$provider->name.'\\'.$provider->name);
        $capabilities = [];
        foreach (['text' => ['text'], 'images' => ['images'], 'embeddings' => ['embeddings'], 'audio' => ['textToSpeech', 'speechToText'], 'fim' => ['fim']] as $capability => $methods) {
            foreach ($methods as $method) {
                if ($class->getMethod($method)->getDeclaringClass()->getName() !== Provider::class) {
                    $capabilities[] = $capability;
                    break;
                }
            }
        }

        return $capabilities;
    }
}
