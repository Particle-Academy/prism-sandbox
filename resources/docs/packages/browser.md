# Prism Browser

Guarded open-web automation for Prism agents, with the browser treated as a
security boundary rather than a convenient Playwright wrapper.

::: warning First vertical slice — source only
The implementation exists and is tested, but it has no tagged release and is
not on Packagist. Do not present the install command as available yet.
:::

## What ships

`prism-browser` provides a provider-neutral engine contract, explicit host and
action policy, bounded structured observations, durable attachment generations,
compare-and-swap storage, stale-observation refusal, and an `in_doubt` state for
an action whose outcome is ambiguous.

The model never supplies CSS, XPath, or arbitrary JavaScript. It acts through a
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

::: danger No silent trust upgrade
Credentials, raw browser checkpoints, downloads, arbitrary selectors, and page
JavaScript do not belong in model arguments or results. A direct engine binding
is infrastructure, not a safe model tool.
:::

## Relationship to Harness and Human+

A browser attachment may be keyed to a durable Harness session, but Harness is
only a suggested dependency: each package remains installable on its own.

Human+ is not a browser mode. `prism-human-plus` joins a controlled Fancy
surface as a visible participant; Browser automates the open web. They may
coexist without one silently becoming the other.

Source: [Particle-Academy/prism-browser](https://github.com/Particle-Academy/prism-browser).

