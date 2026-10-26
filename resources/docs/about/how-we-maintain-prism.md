# How we maintain Prism

`particle-academy/prism` is the actively maintained fork of [prism-php/prism](https://github.com/prism-php/prism), the Laravel package that gives you one interface to many LLM providers. Around it sit a dozen related packages (harness, MCP, memory, workspace, browser, Human+, OpenTelemetry, Perplexity, an agent-protocol bridge) and TypeScript and Python ports of most of them. A small number of humans and a team of AI agents keep all of it moving.

That raises the obvious question: how do you keep a codebase that size trustworthy when much of the work is done by agents? Our answer has two parts. Continuous integration that is built to *refuse*, and a loop in which every mistake we find makes the system better at finding the next one.

## Trust is the foundation, not a feature

Everything below follows from one rule written at the top of the workspace: if a change makes Prism more capable and less trustworthy, it is not an improvement. In practice that means we test the security property rather than the happy path, and we do not accept "it's covered" as a claim. If we say something is covered, something must fail when it is not.

## CI that refuses

Every package has gates that run on every pull request and on main. For the core package that is:

- **Tests** across PHP 8.2, 8.3 and 8.4, Laravel 12 and 13, at both the lowest and the latest allowed dependency versions.
- **PHPStan**, **formatting** (Pint and Rector), and **composer-require-checker**, so a class we use is a dependency we declare.
- **CodeQL** and a **third-party dependency allowlist**: a new dependency is a decision, not an accident.
- **Factcheck**, which checks the claims in our docs against the code. A README that says "18 providers" has to be true.
- A **release-notes check** that refuses a release whose notes don't say, structurally, whether it breaks anything.

Publishing is guarded the same way. The publish workflow refuses to release a commit on which *any* other workflow failed, and after publishing it installs the package from the public registry and exercises it. A green build is not proof a consumer can install the package, so we check the thing a consumer actually touches. When we ship a security fix, we install the published version in a scratch project and run the attack against it.

## Agreement is not safety: the cross-language corpora

Prism exists in three languages, and a separate repository, `prism-parity`, holds conformance suites for all nine package families. Each suite runs the same inputs through PHP, TypeScript and Python and compares the bytes. Every suite we have written has found at least one disagreement.

But a corpus that compares languages has a blind spot by construction: a bug all three share looks like agreement. So the suites are also scored by a **trust rubric**, enforced in CI, that asks whether the security corpora contain adversarial rows: hostile names, padded input, homoglyphs, boundary values. It counts 197 such rows across 16 security corpora today, and the build fails if that regresses.

## Measure, don't assume

The rule we lean on most is to read the authority rather than the channel that reports it. A cached registry, an alert feed or a "latest run" lookup can each be confidently wrong. We check the registry directly, select a CI run by the exact commit, and measure behaviour against the real CLI or browser rather than trusting a flag. Some of the most useful findings came from that habit. A coding agent's "restricted" mode, measured by capturing what it actually offered the model, still allowed command execution on one model. A capture "ready" signal fired while the page was still rendering in a fallback font.

## The loop: how the system improves itself

This is the part we think of as recursive self-improvement. It isn't a model training itself. It's a process in which every defect becomes a durable check, so the system gets better at finding the next one.

1. **Find, including before anything breaks.** A **drift watcher** polls the machine-readable signals each provider publishes every day: the public models.dev catalog for model rosters, and the GitHub release and commit feeds on providers' spec and SDK repositories. It diffs them against a reviewed baseline and reports new models, deprecations and API changes, each annotated with the parts of Prism it touches. Drift is triaged into work before a user hits it, and a watch item closes only once it has been triaged. The rest surfaces from CI, the cross-language suites, the live probes in our Lab, consumers, and the agents' own work. One recent example: we asked agents to finish every half-written documentation page. To write accurate docs they had to read the code, and in doing so they found four real security bugs. One let Azure streaming run tools that should wait for human approval. Another let an agent call the human's confirmation tool if the tool was named `terminal.confirm` instead of `terminal_confirm`. A third leaked a provider API key to an output URL, and the fourth skipped a permission check when listing files.
2. **Register.** Every finding and every deferral goes into a gaps register *the moment it's decided*: what is wrong, the evidence, and what would close it. Deferrals live in that one register, not in commit messages.
3. **Prove the fix bites.** A fix comes with a test, and we break the fix on purpose to watch the test fail. A test that passes either way proves nothing.
4. **Teach the system.** The class of bug becomes permanent coverage, not just the instance. The confirmation-tool bug became thirteen new cross-language corpus rows and a new question in the Lab's live probe. That probe had reported "safe" the whole time, because it only ever asked with a well-behaved name. Learnings the Lab's agents file become work items. And when a check turns out to be asking the wrong question, we fix the question.
5. **Fix the capability, not the instance.** The test we apply is not "what did we send?" but "what can still be invoked?" A fix that does not change the second answer is cosmetic.

The same loop applies to how we work. When a release goes wrong, or a probe misses a bug, the lesson is written down where the next agent and the next human will read it, so the same mistake is not made twice.

## Staying on the rails: Genie and Tynn

A loop like this only works if the agents running it stay inside their lanes. Two tools do most of that work.

**Genie** is the workspace the agents live in. Each agent has a defined role: one implements, another writes specs, reviews and gates releases, and each package has exactly one owner, so a change to it has to come through the agent that releases it. Agents talk to each other through Genie's inbox instead of editing each other's files. A decision only a person can make goes to that person as a structured question, and the agent does not act until it gets an answer. When an agent stops, it writes a handoff, so the next run starts from what was actually done rather than from what someone remembers. Genie also runs the long-lived pieces (workers, test sites, databases), so no agent starts background processes outside it.

**[Tynn](https://tynn.ai)** holds the plan. Work is organised as stories inside versions, and the drift watcher files what it finds there, so provider drift lands in the same backlog as every other piece of work and cannot be quietly ignored. Tynn also builds a human approval into the workflow: an agent can start a story, but closing it waits for a person to approve. An agent cannot mark its own work as done.

Between them, the question "what is this agent allowed to do next?" has a written answer that a person controls, and not just a prompt.

## Humans stay in charge

Agents do much of the implementation and review here, but the decisions that matter stay with people: whether to release, what the scoring policy should be, and which risks are acceptable. Agents ask instead of assuming, and they report failures plainly, with the evidence.

## Upstream

Prism is TJ Miller's project, and the fork exists to keep moving fast without asking upstream to absorb everything at once. The upstream maintainer merged the fork's history up to v0.103.0 as a merge commit, so the original commits and their authors came through and 33 community pull requests were marked merged. We are sending the rest as small, reviewable pull requests, one concern at a time and in the order he asked for. Where upstream's design and ours overlap, upstream's wins, and we also take upstream fixes into the fork, crediting their authors.

If you build on Prism, the fork is at [Particle-Academy/prism](https://github.com/Particle-Academy/prism) and you can discuss this post in [prism-php/prism discussion #1049](https://github.com/prism-php/prism/discussions/1049).
