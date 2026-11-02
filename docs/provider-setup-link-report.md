# Provider setup link report

The Provider setup links workflow publishes the `provider-setup-links` artifact,
containing `provider-setup-links.json`, on relevant pull requests and daily on main.
The report is emitted on failure as well as success. A missing report is not a pass.

Schema version1 contains `generatedAt`, overall `status` (`pass` or `fail`),
`checked`, and `rows`. Each row identifies `provider`, `kind`, the public `url`,
and `status`. Successful rows include their classification and HTTP status;
failed rows include a bounded reason. Redirect query strings and response bodies
are not recorded. An authenticated console response is classified as reachable,
not as successful authentication or verified account access.

Public instruction links require success on the original official host. Explicit
`auth-gated` console links must cite their official documented source. These may
return2xx, redirect only to that provider's allowlisted login hosts, or return401/403.
Missing pages, server errors, DNS/transport failures and foreign redirects fail.
Requests never send credentials, cookies or provider keys.

The future watcher consumer must select an actual completed main workflow run,
validate its repository/head identity and artifact provenance, enforce report
freshness, and reject missing, failed or incomplete reports. That consumer is
owned by the watcher refactor; this repository publishes the contract only.
