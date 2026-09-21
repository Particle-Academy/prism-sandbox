# Prism Browser

Prism Browser provides browser automation with explicit host and action
policies, bounded observations and session-scoped attachments.

::: language-group
```bash [language=php]
composer require particle-academy/prism-browser
```

```bash [language=typescript]
npm install @particle-academy/prism-browser
```

```bash [language=python]
pip install prism-ai-browser
```
:::

::: tip Versioning
Pin the package version for your language and review release notes when upgrading. Source at
[Particle-Academy/prism-browser](https://github.com/Particle-Academy/prism-browser).
:::

## Features

`prism-browser` provides a provider-neutral engine contract, explicit host and
action policy, bounded structured observations, durable attachment generations,
compare-and-swap storage, stale-observation refusal, and an `in_doubt` state for
an action whose outcome is ambiguous.

The model acts through element references tied to a short-lived observation. The package
also ships Prism tool adapters for navigate, observe, act, status, and close.

## Guard a browser

::: language-group
```php
use Prism\Browser\BrowserManager;
use Prism\Browser\Security\BrowserPolicy;

$policy = new BrowserPolicy(['docs.example.com'], requireHttps: true);
$browser = new BrowserManager($engine, $store, $policy);
$attachment = $browser->open($session->key());
$observation = $browser->navigate(
    $session->key(),
    $attachment->id,
    'https://docs.example.com/start',
);
```

```ts
import { BrowserPolicy, GuardedBrowser } from '@particle-academy/prism-browser';

const policy = new BrowserPolicy({
  allowedHosts: ['docs.example.com'],
  allowedActions: ['click', 'scroll'],
});

const browser = new GuardedBrowser(engine, policy);
const observation = await browser.navigate('https://docs.example.com/start');
```

```python
from prism_browser import ActionKind, BrowserPolicy, GuardedBrowser

policy = BrowserPolicy(
    allowed_hosts=["docs.example.com"],
    allowed_actions=[ActionKind.CLICK, ActionKind.SCROLL],
)

browser = GuardedBrowser(engine, policy)
observation = browser.navigate("https://docs.example.com/start")
```
:::

The PHP package includes a version-pinned Playwright sidecar adapter. Configure
the engine explicitly; installing the package does not install a browser automatically.

## Access policy

Treat page content as untrusted input. Observations are
therefore size-bounded and framed with source provenance. Applications declare
which HTTPS hosts and action kinds are allowed before an engine receives the
request, and consequential actions use Prism's approval mechanism.

Production deployments must supply `PRISM_BROWSER_EGRESS_PROXY`. That proxy is
the network-level enforcement point for public/private address separation,
redirects, and subresources in the PHP sidecar integration. Application-level
DNS checks alone do not prevent DNS rebinding; configure equivalent egress
controls for other engine integrations.

::: danger Keep the engine behind the policy
Expose the guarded browser API to the model. Keep credentials, raw checkpoints,
downloads, arbitrary selectors and page JavaScript behind application-controlled
interfaces. Direct engine access bypasses the policy checks.
:::

## Relationship to Harness and Human+

A browser attachment can be keyed to a durable Harness session. Harness stays a
suggested dependency, and each package installs on its own.

Human+ covers the other half: `prism-human-plus` joins a controlled Fancy
surface as a visible participant, while Browser automates the open web. Run
both when your application needs both forms of interaction.

Source: [Particle-Academy/prism-browser](https://github.com/Particle-Academy/prism-browser).
