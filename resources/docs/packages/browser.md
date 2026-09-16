# Prism Browser

Guarded open-web automation for Prism agents. The browser is a security
boundary here, and the package is built as one.

```bash
composer require particle-academy/prism-browser
```

::: tip Pre-1.0 — pin what you install
`prism-browser` is at **`v0.1.0`**, its first release. Source at
[Particle-Academy/prism-browser](https://github.com/Particle-Academy/prism-browser).
:::

## What ships

`prism-browser` provides a provider-neutral engine contract, explicit host and
action policy, bounded structured observations, durable attachment generations,
compare-and-swap storage, stale-observation refusal, and an `in_doubt` state for
an action whose outcome is ambiguous.

The model acts through package-issued element references, resolved through a
package-issued element reference tied to a short-lived observation. The package
also ships Prism tool adapters for navigate, observe, act, status, and close.

A version-pinned Playwright sidecar is included as the first engine adapter, but
the application must bind the engine explicitly; no browser executable becomes
an invisible default dependency of a Laravel install.

## The boundary

Every page is attacker-authored text delivered to the model. Observations are
therefore size-bounded and framed with source provenance. Applications declare
which HTTPS hosts and action kinds are allowed before an engine receives the
request, and consequential actions use Prism's approval mechanism.

Production deployments must supply `PRISM_BROWSER_EGRESS_PROXY`. That proxy is
the network-level enforcement point for public/private address separation,
redirects, and subresources. The local dogfood override does not claim that DNS
checks in application code eliminate rebinding races.

::: danger Keep the engine behind the policy
Credentials, raw browser checkpoints, downloads, arbitrary selectors and page
JavaScript belong in your infrastructure. A direct engine binding is
infrastructure too — hand the model the guarded surface, and it keeps every
check in this page in front of it.
:::

## Relationship to Harness and Human+

A browser attachment can be keyed to a durable Harness session. Harness stays a
suggested dependency, and each package installs on its own.

Human+ covers the other half: `prism-human-plus` joins a controlled Fancy
surface as a visible participant, while Browser automates the open web. Run
both, each in its own lane.

Source: [Particle-Academy/prism-browser](https://github.com/Particle-Academy/prism-browser).

