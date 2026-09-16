# Building with PHP

PHP is Prism's reference implementation and its complete provider surface. It
is a Laravel package, published on Packagist as `particle-academy/prism`.

For the cross-language contract, corpus, and honest parity limits, read
[Language ports](/packages/language-ports). This page covers only what is
specific to the PHP implementation.

## Install

```bash
composer require particle-academy/prism
```

The current package requires PHP 8.2+ and Laravel 12.61+ or 13.12+. Laravel
discovers `PrismServiceProvider` and the `PrismServer` facade from the package
manifest.

## First request

```php
use Prism\Prism\Enums\Provider;
use Prism\Prism\Facades\Prism;

$response = Prism::text()
    ->using(Provider::OpenAI, 'gpt-4o')
    ->withPrompt('Explain why the sky is blue in one sentence.')
    ->asText();

echo $response->text;
```

Provider credentials come from the published Prism configuration and your
environment. See [Configuration](/getting-started/configuration) rather than
copying provider-specific variables from another language port.

## Laravel and execution model

The fluent builder is synchronous for an ordinary `asText()` call. Laravel
integration supplies configuration, container resolution, facades, events,
fakes, and testing assertions. Streaming uses its own event stream rather than
turning `asText()` into a promise.

PHP identifies failures by exception type and message, so branch on the
exception class here. The stable `code` contract belongs to the TypeScript and
Python ports; `PrismError.code` is theirs.

## Current surface

Prism ships eighteen providers and these capability families:
text, structured output, streaming, embeddings, images, audio, moderation,
text-to-speech, speech-to-text, fill-in-the-middle, batches, and files. Core
also implements the tool execution loop, approvals, provider tools, telemetry,
and the thread contract.

Durable sessions, workspaces, remote MCP tools, browsing and Human+ presence
build on that breadth from [their own packages](/packages/overview), each on its
own version.

