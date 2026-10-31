# Request payload projection

Request payload projection is available in the PHP reference only. Prism 0.143.0
introduced `projectPayload()`; 0.144.0 added the optional
`DescribesProjectedPayload` interface and `projectedPayloadFormat()` declaration.
The TypeScript and Python core ports do not expose either API.

## Before sending a request

Validate the prompt, messages, tools and options your application supplies before
calling a generation method. For tests, use the port's [testing
facilities](/core-concepts/testing). Calling text, structured or streaming
generation sends a request; it is not a way to preview the body without dispatch.

The application input is not necessarily the provider's mapped JSON body. Do not
treat a serialized builder or an independently reconstructed request as an exact
payload projection, token count or price estimate.

## When you need the mapped body

Use the PHP reference if your integration requires its supported projection
contract. Select PHP on this page for the reference examples and their limits.

A custom integration in your own application can expose the body it actually
maps, but it must keep that mapping consistent with its sender and be tested
without making a live request. The ports' [custom provider
guide](/advanced/custom-providers) describes their extension points; it does not
provide the PHP projection or format-declaration contract.

Do not infer a wire format from a provider's class name or assume that a format
identifier proves which body will be sent. Treat any captured request body as
sensitive: it can contain prompts and tool content. Avoid logging it, and do not
dispatch a request or execute tools merely to discover its eventual payload.
