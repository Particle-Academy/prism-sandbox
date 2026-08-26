# AGENTS.md — particle-academy/prism-sandbox

The public sandbox app: a Laravel + Inertia + React 19 + Tailwind v4 site on the
Fancy UI starter kit, dogfooding Prism.

> **Read [the shared guide](https://github.com/Particle-Academy/prism-parity/blob/main/docs/AGENTS.md)
> first** — the boundary, the gates, the binding decisions, the review skills.
> This file is only what is true of *this* repository.

## This one is deployed. That changes every judgement call.

`prism-labs` is local-only, holds real provider keys, and spends real money.
**This is the opposite**: public, deployed, credential-light.

**No live provider keys beyond a free tier used for a small, bounded demo.** A
deployed app with a paid provider key is an unmetered spend endpoint on the open
internet, and the first person to find it will not be a user. Any demo surface
must degrade to a visibly disabled state when its key is unset — not to a broken
page, and not to a silent no-op that looks like the model declining.

Anything that needs a real key, a real budget, or a real research loop belongs
in the Lab.

## It dogfoods dev releases on purpose

This app tracks Prism's `-alpha.N` releases ahead of stable, which is what makes
a bad release visible before anyone else installs it. `track-prism.yml` is that
mechanism; treat a failure there as a signal about the release rather than about
this app.

Composer will **not** resolve a prerelease from `minimum-stability` alone — the
constraint floor itself has to be a prerelease. A constraint quietly rewritten
to a stable floor stops the dogfooding without anything going red.

## Security posture

A public Laravel app with webhooks, so the ordinary rules apply and are worth
naming because this repo is where they actually bite: HMAC verification on
inbound webhooks, no CSRF exemptions added for convenience, no server-only data
leaking into Inertia page props, nothing rendered through
`dangerouslySetInnerHTML` from an API response.

Run **`security-review`** before pushing substantive work here. It is the only
repo in the ecosystem where a mistake is reachable from the internet.

## Gates

```sh
composer test     # config:clear, then artisan test
npm run build     # CI builds the front end; a type error fails the job
```

CI runs `tests`, `formatting`, `build` and `track-prism`.

The React surfaces come from the Fancy UI kit. Reach for an existing component
before writing one — a bespoke component here is a component that stops matching
the kit the moment the kit moves.
