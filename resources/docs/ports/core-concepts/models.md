# Model listing

The TypeScript and Python core packages do not list a provider's models. Model listing is part of the PHP package only.

## Choosing a model in the ports

Pass the provider's model identifier when you build a request, as shown in [text generation](/core-concepts/text-generation). Use the identifier exactly as the provider's own documentation or model list gives it.

To show users which models an account can call, read the provider's model-list endpoint from your application. The PHP guide's provider table links each endpoint. Keep the provider credential on the server, and treat a listed model as available to try, not as guaranteed to support every capability.

Select PHP for the `listModels()` API and its provider support table.
