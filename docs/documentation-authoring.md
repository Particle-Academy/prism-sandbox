# Documentation authoring

Use the [Prism documentation style guide](https://github.com/Particle-Academy/prism/blob/main/docs/documentation-style-guide.md)
for voice, structure, examples and review requirements.

Write professional, task-focused documentation: describe behavior and setup,
retain actionable limitations, and keep debugging stories, design deliberations,
promotional language and speculative roadmap details out of published guides.

## Sources

- PHP reference pages come from the installed `particle-academy/prism` package.
  Edit their source in that repository's `docs/` directory. A package release
  and dependency update are required to publish those changes.
- Ecosystem and Harness guides live in `resources/docs/` in this repository.
- TypeScript and Python reference variants live in `resources/docs/ports/`.
  These are internal sources, not public routes.
- The package page takes precedence when both sources define the same path.
  Do not add a duplicate site page to override a PHP reference page.

## Language coverage

Use a `language-group` with native examples for PHP, TypeScript and Python
where the API exists. Scope Laravel instructions to PHP. If a capability is
unavailable, explain the limitation and link to a supported alternative; do not
invent an equivalent API. Core tool-loop limitations do not imply that the
TypeScript or Python Harness packages lack tool execution or approvals.

## Checks

Run `composer test` for routes, rendering, language coverage and navigation.
Run `node scripts/check-port-docs.mjs` to type-check TypeScript port examples
and exercise Python imports and builders with network access blocked. This
example checker uses sibling `prism-ts` and `prism-py` source checkouts and
requires Python 3.11+ for its runner; it does not change package requirements.
Both roots can be passed as arguments if your checkouts are elsewhere.

Examples that read an environment variable receive a placeholder value, not the
one in your shell. A configuration example reading `OPENAI_API_KEY` otherwise
passes on a machine that has the key exported and fails where it is absent, and
a check that answers differently depending on who runs it reports nothing. Write
examples against the variables the page documents; the runner supplies them.

The `Port docs` workflow runs the example checker on every push and pull
request, and weekly against the ports' `main` branches. The weekly run is the
one that reports an example a port broke rather than one a change here did.
For UI changes, also run `npm run build` and check the language selector in
the browser.

Local previews of edited package docs require refreshing the installed docs
and clearing the docs cache. Changes under `vendor/` are preview-only and must
not be treated as the durable source or committed.
