<?php

declare(strict_types=1);

namespace App\ProviderSetup;

use RuntimeException;
use stdClass;

final class CoreProviderConfig
{
    public function providers(): array
    {
        $file = base_path('vendor/particle-academy/prism/config/prism.php');
        if (! is_file($file) || filesize($file) > 262_144) {
            throw new RuntimeException('Installed Prism provider configuration is unavailable.');
        }

        return $this->fromSource((string) file_get_contents($file));
    }

    public function fromSource(string $source): array
    {
        $config = (new ConfigSourceParser)->parse($source);
        $providers = $config['providers'] ?? null;
        if (! is_array($providers) || $providers === []) {
            throw new RuntimeException('Installed Prism has no literal provider configuration.');
        }

        $result = [];
        foreach ($providers as $id => $options) {
            if (! is_string($id) || preg_match('/^[a-z][a-z0-9_]*$/D', $id) !== 1 || ! is_array($options)) {
                throw new RuntimeException('Unsupported provider configuration entry.');
            }
            $result[$id] = $this->variables($options);
        }

        return $result;
    }

    private function variables(array $options, string $prefix = ''): array
    {
        $result = [];
        foreach ($options as $key => $value) {
            $option = $prefix.(string) $key;
            if ($value instanceof stdClass) {
                $sensitive = preg_match('/(?:key|token|credential)/i', $option) === 1;
                $result[] = ['option' => $option, 'name' => $value->name, 'default' => $sensitive ? null : $value->default, 'sensitive' => $sensitive];
            } elseif (is_array($value)) {
                array_push($result, ...$this->variables($value, $option.'.'));
            }
        }

        return $result;
    }
}
