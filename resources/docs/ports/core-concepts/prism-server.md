# Prism Server

Prism Server is the PHP package's Laravel HTTP integration. The TypeScript and Python core packages do not ship its routes, Artisan commands or `config/prism.php` settings.

## Calling Prism from a server

Use your Node.js or Python framework to own the HTTP endpoint and call the ordinary [text-generation API](/core-concepts/text-generation) inside it. That guide contains runnable request examples for both ports.

Your endpoint must enforce authentication, request limits and a bounded provider budget. Keep provider credentials in the server environment. For incremental responses, adapt the [stream iterator](/core-concepts/streaming-output) to the streaming response supported by your framework.

Select PHP to see the built-in Laravel Prism Server setup.
