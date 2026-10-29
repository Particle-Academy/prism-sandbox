/**
 * The Prism ecosystem, as the site presents it: package families, the packages
 * in each language, where each one is published, and the copy for every
 * feature page. The features index, every feature page, the packages catalog,
 * the nav, the footer and the home page's family cards all read this file.
 *
 * Every capability sentence below is taken from this site's own docs
 * (resources/docs/**, or the release's docs for core) or from the package's
 * README. No version numbers: the registry link shows the live one.
 *
 * The feature slugs are also listed in config/features.php, which the server
 * uses to refuse unknown slugs and to title each page.
 */

export type Language = 'php' | 'ts' | 'py';

export const LANGUAGES: Record<Language, { label: string; registry: string }> = {
    php: { label: 'PHP', registry: 'Packagist' },
    ts: { label: 'TypeScript', registry: 'npm' },
    py: { label: 'Python', registry: 'PyPI' },
};

export const LANGUAGE_ORDER: Language[] = ['php', 'ts', 'py'];

export type Package = {
    /** The name on the registry: `particle-academy/<name>`, `@particle-academy/<name>` or a PyPI name. */
    name: string;
    language: Language;
    /** GitHub repository under Particle-Academy. */
    repo: string;
    /** Docs page for this package, on this site or its README. */
    docs: string;
    /** A short label for packages that are not the family's main package. */
    role?: string;
    /** Overrides the default install command (only where the default would not install). */
    install?: string;
};

export type Family = {
    id: string;
    name: string;
    /** One line, from the family's docs. */
    summary: string;
    /** The feature page this family belongs to. */
    feature: string;
    packages: Package[];
};

const GITHUB = 'https://github.com/Particle-Academy';

export function repoUrl(pkg: Package): string {
    return `${GITHUB}/${pkg.repo}`;
}

export function registryUrl(pkg: Package): string {
    switch (pkg.language) {
        case 'php':
            return `https://packagist.org/packages/${pkg.name}`;
        case 'ts':
            return `https://www.npmjs.com/package/${pkg.name}`;
        case 'py':
            return `https://pypi.org/project/${pkg.name}/`;
    }
}

export function installCommand(pkg: Package): string {
    if (pkg.install) return pkg.install;

    switch (pkg.language) {
        case 'php':
            return `composer require ${pkg.name}`;
        case 'ts':
            return `npm install ${pkg.name}`;
        case 'py':
            return `pip install ${pkg.name}`;
    }
}

export function isExternal(href: string): boolean {
    return /^https?:\/\//.test(href);
}

export const FAMILIES: Family[] = [
    {
        id: 'core',
        name: 'Prism core',
        summary: 'One request shape for text, structured output, streaming, embeddings, images, audio, files, batches and more.',
        feature: 'providers',
        packages: [
            { name: 'particle-academy/prism', language: 'php', repo: 'prism', docs: '/docs/languages/php' },
            { name: '@particle-academy/prism', language: 'ts', repo: 'prism-ts', docs: '/docs/languages/typescript' },
            { name: 'prism-ai-core', language: 'py', repo: 'prism-py', docs: '/docs/languages/python' },
        ],
    },
    {
        id: 'harness',
        name: 'Harness',
        summary: 'Durable sessions, threads, modes, approvals and subagents.',
        feature: 'harness',
        packages: [
            { name: 'particle-academy/prism-harness', language: 'php', repo: 'prism-harness', docs: '/docs/packages/harness' },
            { name: '@particle-academy/prism-harness', language: 'ts', repo: 'prism-harness-ts', docs: '/docs/packages/harness' },
            { name: 'prism-ai-harness', language: 'py', repo: 'prism-harness-py', docs: '/docs/packages/harness' },
        ],
    },
    {
        id: 'human-plus',
        name: 'Human+',
        summary: 'Participate in shared Fancy application surfaces.',
        feature: 'human-plus',
        packages: [
            { name: 'particle-academy/prism-human-plus', language: 'php', repo: 'prism-human-plus', docs: '/docs/packages/human-plus' },
            { name: '@particle-academy/prism-human-plus', language: 'ts', repo: 'prism-human-plus-ts', docs: '/docs/packages/human-plus' },
            { name: 'prism-ai-human-plus', language: 'py', repo: 'prism-human-plus-py', docs: '/docs/packages/human-plus' },
        ],
    },
    {
        id: 'browser',
        name: 'Browser',
        summary: 'Browser automation with host and action policies.',
        feature: 'browser',
        packages: [
            { name: 'particle-academy/prism-browser', language: 'php', repo: 'prism-browser', docs: '/docs/packages/browser' },
            { name: '@particle-academy/prism-browser', language: 'ts', repo: 'prism-browser-ts', docs: '/docs/packages/browser' },
            { name: 'prism-ai-browser', language: 'py', repo: 'prism-browser-py', docs: '/docs/packages/browser' },
        ],
    },
    {
        id: 'memory',
        name: 'Memory',
        summary: 'Store context and retrieve relevant records.',
        feature: 'memory',
        packages: [
            { name: 'particle-academy/prism-memory', language: 'php', repo: 'prism-memory', docs: '/docs/packages/memory' },
            { name: '@particle-academy/prism-memory', language: 'ts', repo: 'prism-memory-ts', docs: '/docs/packages/memory' },
            { name: 'prism-ai-memory', language: 'py', repo: 'prism-memory-py', docs: '/docs/packages/memory' },
        ],
    },
    {
        id: 'workspace',
        name: 'Workspace',
        summary: 'Session-scoped file storage with path validation.',
        feature: 'workspace',
        packages: [
            { name: 'particle-academy/prism-workspace', language: 'php', repo: 'prism-workspace', docs: '/docs/packages/workspace' },
            { name: '@particle-academy/prism-workspace', language: 'ts', repo: 'prism-workspace-ts', docs: '/docs/packages/workspace' },
            { name: 'prism-ai-workspace', language: 'py', repo: 'prism-workspace-py', docs: '/docs/packages/workspace' },
        ],
    },
    {
        id: 'mcp',
        name: 'MCP',
        summary: 'Connect to MCP servers with explicit tool trust policies.',
        feature: 'mcp',
        packages: [
            { name: 'particle-academy/prism-mcp', language: 'php', repo: 'prism-mcp', docs: '/docs/packages/mcp' },
            { name: '@particle-academy/prism-mcp', language: 'ts', repo: 'prism-mcp-ts', docs: '/docs/packages/mcp' },
            {
                name: '@particle-academy/prism-mcp-server',
                language: 'ts',
                repo: 'prism-mcp-server-ts',
                docs: `${GITHUB}/prism-mcp-server-ts#readme`,
                role: 'server',
            },
            { name: 'prism-ai-mcp', language: 'py', repo: 'prism-mcp-py', docs: '/docs/packages/mcp' },
        ],
    },
    {
        id: 'perplexity',
        name: 'Perplexity',
        summary: 'Perplexity search, embeddings and Agent API operations.',
        feature: 'research',
        packages: [
            { name: 'particle-academy/prism-perplexity', language: 'php', repo: 'prism-perplexity', docs: '/docs/packages/perplexity' },
            { name: '@particle-academy/prism-perplexity', language: 'ts', repo: 'prism-perplexity-ts', docs: '/docs/packages/perplexity' },
            { name: 'prism-ai-perplexity', language: 'py', repo: 'prism-perplexity-py', docs: '/docs/packages/perplexity' },
        ],
    },
    {
        id: 'opentelemetry',
        name: 'OpenTelemetry',
        summary: 'Export generation and tool activity as telemetry spans.',
        feature: 'observability',
        packages: [
            { name: 'particle-academy/prism-opentelemetry', language: 'php', repo: 'prism-opentelemetry', docs: '/docs/packages/opentelemetry' },
            { name: '@particle-academy/prism-opentelemetry', language: 'ts', repo: 'prism-opentelemetry-ts', docs: '/docs/packages/opentelemetry' },
            { name: 'prism-ai-opentelemetry', language: 'py', repo: 'prism-opentelemetry-py', docs: '/docs/packages/opentelemetry' },
        ],
    },
    {
        id: 'acp',
        name: 'Agent Client Protocol',
        summary: 'Speak the Agent Client Protocol to a coding-agent CLI the user has already authenticated.',
        feature: 'acp',
        packages: [
            {
                name: 'particle-academy/prism-acp',
                language: 'php',
                repo: 'prism-acp',
                docs: `${GITHUB}/prism-acp#readme`,
                // Only pre-releases are published, so a plain `composer require`
                // is refused under the default minimum-stability.
                install: 'composer require particle-academy/prism-acp:@alpha',
            },
            { name: '@particle-academy/prism-acp', language: 'ts', repo: 'prism-acp-ts', docs: `${GITHUB}/prism-acp-ts#readme` },
        ],
    },
];

export function family(id: string): Family {
    const found = FAMILIES.find((f) => f.id === id);
    if (!found) throw new Error(`Unknown package family: ${id}`);
    return found;
}

export type Snippet = { language: Language | 'bash'; source: string };

export type Capability = { title: string; body: string; code?: Snippet };

export type DocLink = { label: string; href: string };

export type Feature = {
    slug: string;
    title: string;
    /** The one-line category under "Feature ·". */
    category: string;
    /** Card copy on the index and in nav-adjacent lists. */
    summary: string;
    /** The hero's two or three sentences. */
    pitch: string[];
    /** Families whose packages this feature is made of, in display order. */
    families: string[];
    /** Primary docs link, then any others. */
    docs: DocLink[];
    capabilities: Capability[];
    /** "How it fits": where it sits next to core, Harness and the other packages. */
    fits: string[];
    /** Extra links for "How it fits" that are not packages (e.g. a repository). */
    extraLinks?: DocLink[];
    related: string[];
};

export const FEATURES: Feature[] = [
    {
        slug: 'providers',
        title: 'One API, every provider',
        category: 'Provider interface',
        summary: 'One request shape for text, structured output, streaming, embeddings, media, audio, files and batches, in PHP, TypeScript and Python.',
        pitch: [
            'Prism exposes the same provider-facing capabilities in PHP, TypeScript and Python. Choose a language once; the concepts, capability names and failure boundaries stay the same.',
            'The PHP reference carries eighteen providers. The TypeScript and Python ports carry Anthropic, Mistral and OpenAI.',
        ],
        families: ['core'],
        docs: [
            { label: 'Building with PHP', href: '/docs/languages/php' },
            { label: 'Building with TypeScript', href: '/docs/languages/typescript' },
            { label: 'Building with Python', href: '/docs/languages/python' },
            { label: 'Language ports', href: '/docs/packages/language-ports' },
        ],
        capabilities: [
            {
                title: 'Every capability, in every language',
                body: 'All three implementations cover text, structured output, streaming, embeddings, images, moderation, text to speech, speech to text, files, batches and fill-in-the-middle.',
            },
            {
                title: 'One request shape',
                body: 'The same fluent builder in each language: pick a provider and model, add a prompt, ask for text.',
                code: {
                    language: 'ts',
                    source: `import { Prism } from '@particle-academy/prism';

const response = await Prism.text()
  .using('openai', 'gpt-4o')
  .withPrompt('Explain why the sky is blue in one sentence.')
  .asText();`,
                },
            },
            {
                title: 'Honest provider coverage',
                body: 'Not every capability reaches every provider. In the ports, images, moderation, text to speech, files and batches work with OpenAI, and fill-in-the-middle works with Mistral. Unsupported actions fail explicitly.',
            },
            {
                title: 'Errors you can branch on',
                body: 'TypeScript and Python failures carry a stable machine-readable code. PHP applications branch on exception type. Messages are for display, never for control flow.',
            },
            {
                title: 'Testable without a socket',
                body: 'Configuration is explicit first and environment second, and each port exposes an injectable HTTP transport so tests can run without a network connection.',
            },
            {
                title: 'Human-in-the-loop tools',
                body: 'In PHP, a tool can be client-executed or require approval. Approval is deny-by-default: a tool call that never receives an approval is refused, never executed.',
            },
        ],
        fits: [
            'Core is the provider interface: every other package builds on it or beside it. Harness runs agents on top of it, and the companion packages add agent sessions, storage, retrieval, external tools, browser automation and observability.',
            'PHP requires PHP 8.2+ and Laravel. The TypeScript package requires Node 20+. The Python distribution requires Python 3.10+ and installs the `prism` import; install `prism-ai-core`, not `prism-ai`, which is an unrelated PyPI package.',
        ],
        related: ['languages', 'harness', 'observability'],
    },
    {
        slug: 'harness',
        title: 'Agent harness',
        category: 'Durable agent sessions',
        summary: 'Durable sessions, threads, modes, approvals and subagents with their own budgets, for agents that outlive a request.',
        pitch: [
            'Durable agent sessions for PHP, TypeScript and Python: threads, reconstructed session state, persisted provider, model and mode selection, exclusive locking and human approvals.',
            'A bounded agent runtime runs nested subagents that carry their own budgets. Use it for agents that need persistent conversations, configurable modes, tool execution and approvals across requests.',
        ],
        families: ['harness'],
        docs: [
            { label: 'What the Harness is', href: '/docs/packages/harness' },
            { label: 'Sessions and threads', href: '/docs/harness/sessions' },
            { label: 'Modes, tools and skills', href: '/docs/harness/modes' },
            { label: 'Subagents and budgets', href: '/docs/harness/subagents' },
            { label: 'Task lists', href: '/docs/harness/tasks' },
        ],
        capabilities: [
            {
                title: 'Sessions resolved per request',
                body: "A session is one participant's live runtime: the active mode, provider and model, and the thread it is bound to. It is resolved from the store on every request, so any worker can continue it.",
            },
            {
                title: 'Threads that keep the whole exchange',
                body: 'A thread is a stored conversation, addressed by participant and scope. Each run records the resulting exchange, including tool calls, tool results and approval decisions.',
                code: {
                    language: 'php',
                    source: `$result = $session->send('Help with my order');

echo $result->text();
$result->awaitingApproval(); // true when a tool is waiting for a person`,
                },
            },
            {
                title: 'Modes',
                body: 'A mode defines the instructions, tools, approvals and limits for a run: a system prompt, a tool allowlist, approvals, skills, subagents and a step limit.',
            },
            {
                title: 'Approvals that default to no',
                body: 'A mode names the tools a person must approve. A turn that calls one stops and returns its pending approvals; unanswered approvals are denied, never run.',
            },
            {
                title: 'Subagents and budgets',
                body: "A subagent is a nested run the parent calls like a tool. It runs in its own mode and session, with authority it was given rather than inherited, on a budget drawn from the parent's.",
            },
            {
                title: 'Durable state, checked',
                body: 'Threads, pending approvals and task lists live in the durable store, so another worker can continue a task list after a crash, a restart or a deploy. A store that reports itself as volatile is refused for durable state.',
            },
        ],
        fits: [
            'The PHP Harness builds on Prism core; in TypeScript and Python the runtime calls a model client you supply once per step. For individual model requests without session state, use Prism core directly.',
            'A Harness session has a key, so it can own a Workspace, a Browser attachment or a Human+ attachment. PHP integrates with Laravel; TypeScript and Python run independently of any framework.',
        ],
        related: ['memory', 'workspace', 'human-plus'],
    },
    {
        slug: 'human-plus',
        title: 'Human+',
        category: 'Agents in shared surfaces',
        summary: 'An agent joins a live Fancy surface as a named participant, and confirmation stays with the human.',
        pitch: [
            'Participant presence for Prism agents in live Fancy surfaces. An agent joins the same controlled, JSON-serializable surface a person sees, and works through the handles that surface already exposes.',
            'It enters through an explicit invitation, discovers only the tools your trust policy allows, and reads every result as untrusted data.',
        ],
        families: ['human-plus'],
        docs: [
            { label: 'Human+ guide', href: '/docs/packages/human-plus' },
            { label: 'Human-in-the-loop', href: '/docs/core-concepts/human-in-the-loop' },
        ],
        capabilities: [
            {
                title: 'Join by invitation',
                body: 'The Fancy application issues the invitation: relay URL, session id, token, surface id and application. It is validated when constructed: the relay must be HTTPS and the token at least 16 characters.',
            },
            {
                title: 'Trust declared before discovery',
                body: 'Trust is local configuration. Nothing the surface says about its own tools (descriptions, annotations, read-only hints) widens it, and the default Laravel binding refuses every call before a request is sent.',
            },
            {
                title: 'Confirmation stays with the human',
                body: 'A tool whose name contains confirm, reject, accept, approve or deny as a whole word is reserved for the human and never offered to the agent.',
            },
            {
                title: 'Pinned tool definitions',
                body: 'A pin maps a tool name to the digest of its definition, so a surface cannot silently change what an approved tool does.',
            },
            {
                title: 'Concurrent edits, detected',
                body: 'When a person and the agent edit the same surface, Human+ pins writes to revisions the surface mints, and the surface decides whether a revision is stale. Human+ never merges.',
            },
            {
                title: 'Visible as an agent',
                body: 'Activity announcements carry the participant’s id, name and colour with type "agent", so people on the surface can always tell the agent apart from a human.',
            },
        ],
        fits: [
            'The Fancy application owns the running surface, stable handles, staged writes, undo, presence, cursors and the MCP tools that express its actions. Human+ is how a Prism agent enters it.',
            'With Prism and Harness, a toolset turns an attachment’s trusted tools into tools a model can call, and the owner of an attachment can be a Harness session. Browser is the counterpart for the open web.',
        ],
        related: ['harness', 'browser', 'mcp'],
    },
    {
        slug: 'browser',
        title: 'Guarded browser',
        category: 'Open-web automation',
        summary: 'Let an agent use a real browser within limits you set: hosts, actions, and how much of a page it may read.',
        pitch: [
            'Prism Browser lets an agent use a real web browser within limits you set: which hosts it may visit, which actions it may take, and how much page content it may read.',
            'Every page observation is size-limited and wrapped as untrusted input before a model sees it.',
        ],
        families: ['browser'],
        docs: [{ label: 'Browser guide', href: '/docs/packages/browser' }],
        capabilities: [
            {
                title: 'Policy before the engine',
                body: 'A browser policy answers two questions before the engine receives anything: where may the browser go, and what may it do there. Hosts, actions, HTTPS and ports are all explicit.',
                code: {
                    language: 'ts',
                    source: `const policy = new BrowserPolicy({
  allowedHosts: ['docs.example.com', '*.example.org'],
  allowedActions: ['click', 'scroll'],
  requireHttps: true,
  allowedPorts: [443],
});`,
                },
            },
            {
                title: 'Private addresses refused',
                body: 'Loopback, private, link-local (including cloud metadata at 169.254.169.254), carrier-grade NAT, multicast and reserved ranges are refused, in IPv4 and IPv6. PHP always refuses them.',
            },
            {
                title: 'Framed, bounded observations',
                body: 'An observation is framed with a random id per observation, so page text cannot close the wrapper. One larger than the byte limit is refused, never truncated.',
            },
            {
                title: 'Refusals you can branch on',
                body: 'Every refusal carries a stable code. Branch on the code, never on the message.',
            },
            {
                title: 'Owner-bound attachments (PHP)',
                body: 'Each browser session belongs to an owner. An attachment id used with a different owner is refused, so knowing another session’s attachment id is not enough to use it.',
            },
            {
                title: 'Model tools with approval (PHP)',
                body: 'A toolset gives a model navigate, observe and act tools bound to one owner and one attachment. Actions that change state, such as click and fill, require approval.',
            },
        ],
        fits: [
            'PHP ships a bundled engine: a Playwright sidecar that listens on loopback only and requires a bearer token. TypeScript and Python ship no engine: you implement the engine interface over your own automation library and wrap it in a guarded browser.',
            'A Harness session can own a PHP attachment; run the browser tools through Harness when approvals must survive across requests. Human+ is for a different job: joining a surface your application controls.',
        ],
        related: ['human-plus', 'harness', 'mcp'],
    },
    {
        slug: 'memory',
        title: 'Memory',
        category: 'Context recall',
        summary: 'Store what was said and recall the records that bear on a new question, sized to the token budget you have.',
        pitch: [
            'Prism Memory stores what was said in a conversation and retrieves the records that bear on a new question, ranked by meaning and, optionally, by how recent they are.',
            'Use it with a Harness thread or on its own to select context without sending the complete conversation on every turn.',
        ],
        families: ['memory'],
        docs: [{ label: 'Memory guide', href: '/docs/packages/memory' }],
        capabilities: [
            {
                title: 'Remember and recall',
                body: 'Store a whole exchange, including tool steps, then recall by meaning and add the result to the prompt as context.',
                code: {
                    language: 'php',
                    source: `$memory = PrismMemory::for($user, scope: 'support');

$ids = $memory->remember($response->messages);

$recollection = $memory->recall('billing address', budget: 1500);`,
                },
            },
            {
                title: 'Fits a token budget',
                body: 'Recall can fill up to a number of estimated tokens instead of a fixed limit. Memories are taken best first; one that does not fit is skipped and smaller ones behind it can still be taken.',
            },
            {
                title: 'Relevance and recency',
                body: 'A weighting mixes relevance and recency, or uses relevance only.',
            },
            {
                title: 'Scoped by owner',
                body: 'Memory is addressed by owner and scope. Recall in one scope never returns another scope’s memories.',
            },
            {
                title: 'Your database, then pgvector (PHP)',
                body: 'The PHP package embeds through Prism and stores in your database, with no Redis, vector database or queue worker required. Larger collections can switch to pgvector, and other vector stores can be registered.',
            },
            {
                title: 'Harness recall (PHP)',
                body: 'Memory can act as the Harness eviction sink: turns compaction pushes out of the context window are stored, and the agent gets a recall tool that searches them within the budget Harness passes.',
            },
        ],
        fits: [
            'The PHP package is a complete memory layer: it embeds through Prism, stores in your database, queues the embedding work and fits recall to a token budget.',
            'The TypeScript and Python packages provide the storage contract, vectors, ranking and an in-memory store. They do not embed text: you supply the vectors from your own embeddings call.',
        ],
        related: ['harness', 'workspace', 'providers'],
    },
    {
        slug: 'workspace',
        title: 'Workspace',
        category: 'Agent file storage',
        summary: 'A directory of its own for an agent, where every path is checked before it touches the disk.',
        pitch: [
            'Prism Workspace gives an agent a directory of its own to keep its work in, in PHP, TypeScript and Python.',
            'Every path is checked before it touches the disk, so a path the model wrote cannot read or write outside the workspace.',
        ],
        families: ['workspace'],
        docs: [{ label: 'Workspace guide', href: '/docs/packages/workspace' }],
        capabilities: [
            {
                title: 'One owner, one directory',
                body: 'The same owner always resolves to the same directory, so a later request or a fresh worker finds the files an earlier run wrote.',
                code: {
                    language: 'ts',
                    source: `const workspace = new Workspace('/srv/agent-workspaces', session);

await workspace.write('reports/q1.md', content);
const markdown = await workspace.read('reports/q1.md');`,
                },
            },
            {
                title: 'A path guard in front of every call',
                body: 'Traversal, absolute and device paths, null and control characters, look-alike separators, percent-encoded dots, NTFS streams and reserved names are refused without touching the disk.',
            },
            {
                title: 'Links cannot leave',
                body: 'On local disks a second check resolves the real path and refuses one that leaves the workspace through a symbolic link or junction.',
            },
            {
                title: 'Verify the boundary yourself',
                body: 'The escape corpus ships with the package: 134 hostile paths, each with the refusal code it must produce, written into a real workspace and checked for anything that got out.',
            },
            {
                title: 'Streaming listings',
                body: 'Listing streams, so a directory of ten thousand files does not have to be loaded at once.',
            },
            {
                title: 'Your authorization rule on top',
                body: 'Authorization adds your own rule: whether this user or agent may do this operation here. In PHP it maps to Gate abilities. It is off by default.',
            },
        ],
        fits: [
            'Workspace is a storage API for your application code. It does not register model tools and it never executes files; to let a model read or write files, define your own tools that call it.',
            'PHP builds each workspace on a Laravel filesystem disk (local, S3 or any other Flysystem driver). TypeScript and Python have no runtime dependencies and work on the local filesystem. A Harness session can be the owner, so a resumed session finds its files again.',
        ],
        related: ['harness', 'memory', 'browser'],
    },
    {
        slug: 'mcp',
        title: 'MCP',
        category: 'Model Context Protocol',
        summary: 'Discover and call MCP server tools with explicit trust policies, definition pinning and bounded results.',
        pitch: [
            'A Model Context Protocol client for PHP, TypeScript and Python. Discover and call server tools with explicit trust policies, definition pinning and bounded results.',
            'For TypeScript, a server package registers and dispatches your own tools over a transport you supply.',
        ],
        families: ['mcp'],
        docs: [
            { label: 'MCP guide', href: '/docs/packages/mcp' },
            { label: 'MCP server (TypeScript)', href: `${GITHUB}/prism-mcp-server-ts#readme` },
        ],
        capabilities: [
            {
                title: 'Trust is required',
                body: 'Every server needs a trust policy. Without one, asking for its tools throws server_not_trusted and no tool is offered.',
                code: {
                    language: 'ts',
                    source: `const github = new Client({
  server: 'github',
  transport: httpTransport(process.env.MCP_GITHUB_URL!),
  trust: TrustPolicy.allowing(['search_repositories']),
});`,
                },
            },
            {
                title: 'Pinned definitions',
                body: 'A pin records the digest of a tool definition you have reviewed. If the server later changes it, the tool is refused instead of being offered to the model.',
            },
            {
                title: 'Guarded results',
                body: 'Every result passes a guard before it reaches the model: one larger than the cap is refused, not truncated, and the text is framed as data.',
            },
            {
                title: 'A gate per call',
                body: 'Trust decides which tools the model is told about; a gate decides whether a given caller may run one, checked before each call is sent.',
            },
            {
                title: 'Tool names that cannot collide (PHP)',
                body: 'PHP names tools mcp__<server>__<tool>, so two servers can both publish search.',
            },
            {
                title: 'Serve your own tools (TypeScript)',
                body: 'The server package registers tools and dispatches calls over a consumer-supplied transport, with a per-request visibility predicate that hides a tool from callers who should not see it.',
            },
        ],
        fits: [
            'MCP tools are ordinary Prism tools in PHP, so they go straight into a text request. TypeScript and Python carry the protocol rules (trust, pins, gates, result guarding) and take a transport you supply.',
            'The PHP package replaces prism-php/relay and cannot be installed alongside it.',
        ],
        related: ['human-plus', 'harness', 'browser'],
    },
    {
        slug: 'research',
        title: 'Research (Perplexity)',
        category: 'Search and research',
        summary: 'Perplexity embeddings, web search and the long-running Agent API.',
        pitch: [
            'Prism Perplexity provides access to Perplexity embeddings, web search and the long-running Agent API, in PHP, TypeScript and Python.',
        ],
        families: ['perplexity'],
        docs: [{ label: 'Perplexity guide', href: '/docs/packages/perplexity' }],
        capabilities: [
            {
                title: 'Search without an answer',
                body: 'Retrieve web search results without requesting a generated answer, one query or several together.',
                code: {
                    language: 'ts',
                    source: `const results = await search(http, 'what is prism php', {
  max_results: 5,
  search_domain_filter: ['github.com'],
});`,
                },
            },
            {
                title: 'Embeddings',
                body: 'Generate embeddings, including contextualized embeddings that represent chunks of one document using the surrounding document context.',
            },
            {
                title: 'Agent API',
                body: 'Long research tasks run in the background: create starts a run and returns at once, retrieve reads its state, wait polls until it finishes, and cancel stops it.',
            },
            {
                title: 'Never an unfinished run',
                body: 'Waiting returns as soon as the run is terminal. If it is still running after the last attempt, wait throws; it never returns an unfinished run.',
            },
            {
                title: 'Errors inside a 200',
                body: 'Perplexity can report a failure inside an HTTP 200 response. Every call checks the body for an error and throws, so a failure is never returned as an empty result.',
            },
        ],
        fits: [
            'In PHP, the package extends Prism’s existing perplexity provider: text, structured output and streaming keep working through core, and embeddings, search and the Agent API are added to the same provider.',
            'TypeScript and Python expose functions that take an HTTP transport. The package ships one built on the runtime’s own HTTP client.',
        ],
        related: ['providers', 'memory', 'observability'],
    },
    {
        slug: 'observability',
        title: 'Observability',
        category: 'OpenTelemetry tracing',
        summary: 'Generations, steps and tool calls as GenAI-convention OpenTelemetry spans, exported over OTLP.',
        pitch: [
            'A bridge from Prism’s telemetry to GenAI-convention OpenTelemetry spans: a root span per generation, and child spans per step and per tool call.',
            'Export over OTLP to Arize Phoenix, Jaeger, Tempo, Grafana or anything else that speaks OTLP.',
        ],
        families: ['opentelemetry'],
        docs: [{ label: 'OpenTelemetry guide', href: '/docs/packages/opentelemetry' }],
        capabilities: [
            {
                title: 'Spans that follow the conventions',
                body: 'Spans carry both the OpenTelemetry gen_ai.* attributes and the OpenInference attributes Phoenix uses to render LLM traces.',
            },
            {
                title: 'Metadata only, by default',
                body: 'Spans carry model and provider, timing, finish reason, token counts, cost, rate limits and tool names. Prompts, completions and tool arguments are exported only when you turn on content capture.',
            },
            {
                title: 'Zero setup in PHP',
                body: 'In PHP the bridge subscribes to the Laravel events Prism emits; there is nothing to call once Prism telemetry is enabled.',
            },
            {
                title: 'Your SDK, in TypeScript and Python',
                body: 'The subscribers do not import OpenTelemetry. They write through a small tracer interface you connect to your OpenTelemetry SDK, and have no dependencies of their own.',
            },
        ],
        fits: [
            'In PHP, the bridge subscribes to the Laravel events Prism emits. In TypeScript and Python, your application calls the subscriber at generation, step and tool boundaries, and spans are written through a tracer interface you connect to your OpenTelemetry SDK.',
        ],
        related: ['providers', 'harness', 'research'],
    },
    {
        slug: 'acp',
        title: 'Agent Client Protocol',
        category: 'Coding-agent CLIs',
        summary: 'Speak ACP to Claude or Codex through the CLI the user has already signed in to, and get structured state back.',
        pitch: [
            'Speak the Agent Client Protocol to a coding-agent CLI the user has already authenticated.',
            'A client (an editor, a terminal UI, an orchestrator) gets structured state from the agent instead of guessing it from bytes on a pseudo-terminal: session state, streaming output, tool calls with status, token usage and cost.',
        ],
        families: ['acp'],
        docs: [
            { label: 'TypeScript README', href: `${GITHUB}/prism-acp-ts#readme` },
            { label: 'PHP README', href: `${GITHUB}/prism-acp#readme` },
        ],
        capabilities: [
            {
                title: 'The login the user already has',
                body: 'It drives the agent CLI already on the machine, so the user’s existing subscription login is the authentication. In subscription mode the package never reads, holds or sends a credential.',
            },
            {
                title: 'Or an app’s API key',
                body: 'An app that bills per token can hand the drivers an API key instead. Credentials are app or tenant configuration, never something an ACP client sends.',
            },
            {
                title: 'Permission requests',
                body: 'Codex can raise permission requests for the client to answer, and Claude can for the tools you name. Only an explicit allow runs the tool; a deny, a cancel or a missing handler blocks it.',
            },
            {
                title: 'Sessions over a pipe',
                body: 'initialize, session/new, session/load, session/prompt and session/cancel work over a pipe. Several sessions run at once, each with its own agent process.',
                code: {
                    language: 'ts',
                    source: `serve({
  input: process.stdin,
  output: process.stdout,
  driverFactory: (options, events) => new ClaudeDriver(options, events),
  driverCapabilities: CLAUDE_DRIVER_CAPABILITIES,
});`,
                },
            },
            {
                title: 'Contained processes',
                body: 'Each CLI runs under a small supervisor that owns its whole process tree, never through a shell, so ending a session ends every process it started.',
            },
            {
                title: 'No runtime dependencies',
                body: 'The TypeScript package has zero runtime dependencies. The PHP core has no third-party runtime dependencies and does not require Laravel.',
            },
        ],
        fits: [
            'TypeScript is the reference implementation; PHP is a port that runs as an ACP agent command or embeds in an application. The PHP port supports Windows; other platforms are refused before a provider starts.',
            'ACP sits outside the provider interface: instead of calling a provider API with a key, it runs the agent CLI the user is signed in to and translates.',
        ],
        related: ['mcp', 'harness', 'languages'],
    },
    {
        slug: 'languages',
        title: 'Three languages, one contract',
        category: 'Language ports',
        summary: 'Core and the companion families in PHP, TypeScript and Python, held together by one shared conformance corpus.',
        pitch: [
            'Prism has PHP, TypeScript and Python implementations of core and all eight companion package families, including Harness.',
            'Shared conformance fixtures run through all three languages and compare their results.',
        ],
        families: ['core'],
        docs: [
            { label: 'Language ports', href: '/docs/packages/language-ports' },
            { label: 'Building with PHP', href: '/docs/languages/php' },
            { label: 'Building with TypeScript', href: '/docs/languages/typescript' },
            { label: 'Building with Python', href: '/docs/languages/python' },
        ],
        capabilities: [
            {
                title: 'Same capabilities, same names',
                body: 'Both ports implement every capability the PHP reference has. Choose a language once and every docs example follows it.',
            },
            {
                title: 'One corpus, three languages',
                body: 'prism-parity owns shared conformance fixtures covering request construction, response parsing, value-object round trips, coded errors and JSON container identity.',
            },
            {
                title: 'A security corpus per family',
                body: 'Alongside the conformance fixtures, every package family has a security corpus.',
            },
            {
                title: 'Light runtimes',
                body: 'TypeScript runs on Node 20+ with native promises and strict types. Python runs on 3.10+ with zero runtime dependencies.',
            },
            {
                title: 'Agreement, stated precisely',
                body: 'Conformance results establish agreement for the tested cases. They do not imply complete provider coverage or identical framework integrations; each guide names language-specific APIs and unsupported operations.',
            },
        ],
        fits: [
            'PHP and Laravel are the reference. TypeScript and Python packages are configured independently of any framework, and each language installs from its own registry.',
        ],
        extraLinks: [{ label: 'prism-parity on GitHub', href: `${GITHUB}/prism-parity` }],
        related: ['providers', 'harness', 'mcp'],
    },
];

export function featureBySlug(slug: string): Feature | undefined {
    return FEATURES.find((f) => f.slug === slug);
}

/** The feature page for a family, for linking cards elsewhere on the site. */
export function featureHref(slug: string): string {
    return `/features/${slug}`;
}

/** Packages a feature is made of, grouped by language in PHP, TypeScript, Python order. */
export function featurePackages(feature: Feature): Package[] {
    const all = feature.families.flatMap((id) => family(id).packages);

    return LANGUAGE_ORDER.flatMap((lang) => all.filter((p) => p.language === lang));
}

/** Languages a feature exists in. */
export function featureLanguages(feature: Feature): Language[] {
    const langs = new Set(featurePackages(feature).map((p) => p.language));
    return LANGUAGE_ORDER.filter((l) => langs.has(l));
}
