# Human-in-the-loop

The TypeScript and Python core packages do not expose the PHP reference's resumable tool-confirmation API. Their separate **Harness packages do support durable approvals**: a run pauses for a decision and resumes after the application records it. See [Harness approvals](/harness/modes#approvals).

## Approval in an application

Keep pending actions in application-owned state, present the exact action to the human, and check the returned decision against that pending action before executing it. An agent must not be able to invoke its own confirmation channel.

[Human+](/packages/human-plus) supplies shared human/agent surfaces in all three languages. That guide includes the appropriate installation and connection examples for the selected language. [Harness modes](/harness/modes) describe the separate tool-permission boundary.

## Core limitation

A core response requiring automatic tool execution raises `tool_loop_not_supported`.
Use Harness for managed execution and approval workflows. Select PHP for the
core package's approval API examples.
