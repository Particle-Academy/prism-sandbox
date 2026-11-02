<?php

declare(strict_types=1);
use App\Docs\DocsRepository;
use App\ProviderSetup\ProviderGuides;
use Illuminate\Contracts\Console\Kernel;

// Test/CI renderer input: only public guide fields and literal config names.
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$guides = array_values((new ProviderGuides)->all());
$version = (new DocsRepository)->version();
echo json_encode(['index' => ['providers' => $guides, 'version' => $version], 'providers' => array_map(fn ($guide) => ['provider' => $guide, 'version' => $version], $guides)], JSON_THROW_ON_ERROR);
