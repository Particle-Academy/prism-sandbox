# Companion packages

Prism provides a common API for model requests. Its companion packages add
agent sessions, storage, retrieval, external tools, browser automation and
observability. All nine package families, including core, have PHP, TypeScript
and Python implementations.

## Available packages

| Package | Purpose |
| --- | --- |
| [Harness](/packages/harness) | Durable sessions, threads, modes, approvals and subagents |
| [MCP](/packages/mcp) | Connect to MCP servers with explicit tool trust policies |
| [Workspace](/packages/workspace) | Session-scoped file storage with path validation |
| [Memory](/packages/memory) | Store context and retrieve relevant records |
| [Human+](/packages/human-plus) | Participate in shared Fancy application surfaces |
| [Browser](/packages/browser) | Browser automation with host and action policies |
| [Perplexity](/packages/perplexity) | Perplexity search, embeddings and Agent API operations |
| [OpenTelemetry](/packages/opentelemetry) | Export generation and tool activity as telemetry spans |

Each guide includes installation commands and examples for the selected
language. PHP framework integrations may use Laravel; TypeScript and Python
packages are configured independently.

## Versions and compatibility

Packages are versioned independently. Pin dependencies and review their release
notes before upgrading. Pre-1.0 minor releases may include API changes.

Package availability does not imply identical provider coverage or every
framework integration. See [Language ports](/packages/language-ports) for
coverage and each package guide for specific limitations.

## Workflow orchestration

Use a workflow engine when your application needs dependency graphs,
checkpoints or durable workflow pauses. The PHP Harness provides an integration
with Fancy's workflow tools. Harness itself manages agent sessions and runs.

## Migrating from Relay

The PHP MCP package replaces `prism-php/relay` and cannot be installed alongside
it. Review the [MCP trust policy](/packages/mcp#trust-policy) before migrating;
server connections require explicit tool trust configuration.

[Bedrock](https://github.com/prism-php/bedrock) is a separate provider
integration maintained by the upstream project.
