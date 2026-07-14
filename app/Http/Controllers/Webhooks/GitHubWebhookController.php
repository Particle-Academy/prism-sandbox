<?php

namespace App\Http\Controllers\Webhooks;

use App\Models\User;
use App\Support\XpAwarder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LaravelFunLab\Facades\LFL;
use Throwable;

/**
 * Awards contribution XP from GitHub webhook events. Authenticated by the
 * X-Hub-Signature-256 HMAC (not CSRF — the route is CSRF-exempt), scoped to the
 * configured org, and idempotent via {@see XpAwarder}.
 */
class GitHubWebhookController
{
    public function __invoke(Request $request): JsonResponse
    {
        $secret = (string) config('services.github.webhook_secret');

        if ($secret === '') {
            return response()->json(['message' => 'Webhook not configured'], 503);
        }

        if (! $this->signatureValid($request, $secret)) {
            return response()->json(['message' => 'Invalid signature'], 403);
        }

        /** @var array<string, mixed> $payload */
        $payload = (array) $request->json()->all();

        if (! $this->inScope($payload)) {
            return response()->json(['message' => 'Ignored (out of scope)']);
        }

        $awarded = match ((string) $request->header('X-GitHub-Event')) {
            'issues' => $this->handleIssueOpened($payload),
            'issue_comment' => $this->handlePrComment($payload),
            'pull_request_review_comment' => $this->handleReviewComment($payload),
            'pull_request' => $this->handlePullRequest($payload),
            default => false,
        };

        return response()->json(['awarded' => $awarded]);
    }

    protected function signatureValid(Request $request, string $secret): bool
    {
        $signature = (string) $request->header('X-Hub-Signature-256');

        if ($signature === '') {
            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function inScope(array $payload): bool
    {
        $org = (string) config('services.github.org');
        $fullName = (string) data_get($payload, 'repository.full_name', '');

        return $org !== '' && str_starts_with($fullName, $org.'/');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function handleIssueOpened(array $payload): bool
    {
        // Only real issues — PRs also arrive as "issues" but carry a pull_request key.
        if (data_get($payload, 'action') !== 'opened' || data_get($payload, 'issue.pull_request') !== null) {
            return false;
        }

        return XpAwarder::award(
            $this->resolveUser(data_get($payload, 'issue.user.login')),
            'issue-xp',
            $this->xp('issue-xp'),
            'Opened issue '.data_get($payload, 'issue.html_url'),
            'issue:'.data_get($payload, 'issue.node_id'),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function handlePrComment(array $payload): bool
    {
        // issue_comment fires for both issues and PRs; only PR comments count.
        if (data_get($payload, 'action') !== 'created' || data_get($payload, 'issue.pull_request') === null) {
            return false;
        }

        return $this->awardComment(
            $this->resolveUser(data_get($payload, 'comment.user.login')),
            'comment:'.data_get($payload, 'comment.id'),
            data_get($payload, 'comment.html_url'),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function handleReviewComment(array $payload): bool
    {
        if (data_get($payload, 'action') !== 'created') {
            return false;
        }

        return $this->awardComment(
            $this->resolveUser(data_get($payload, 'comment.user.login')),
            'review-comment:'.data_get($payload, 'comment.id'),
            data_get($payload, 'comment.html_url'),
        );
    }

    protected function awardComment(?User $user, string $dedupKey, ?string $url): bool
    {
        return XpAwarder::award(
            $user,
            'pr-comment-xp',
            $this->xp('pr-comment-xp'),
            'Commented on a pull request'.($url !== null ? ' '.$url : ''),
            $dedupKey,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function handlePullRequest(array $payload): bool
    {
        $action = data_get($payload, 'action');
        $user = $this->resolveUser(data_get($payload, 'pull_request.user.login'));
        $nodeId = (string) data_get($payload, 'pull_request.node_id');
        $url = data_get($payload, 'pull_request.html_url');

        if ($action === 'opened') {
            return XpAwarder::award($user, 'pr-submitted-xp', $this->xp('pr-submitted-xp'), 'Opened pull request '.$url, 'pr-opened:'.$nodeId);
        }

        if ($action === 'closed' && data_get($payload, 'pull_request.merged') === true) {
            $awarded = XpAwarder::award($user, 'pr-accepted-xp', $this->xp('pr-accepted-xp'), 'Merged pull request '.$url, 'pr-merged:'.$nodeId);

            if ($awarded && $user instanceof User && ! $user->hasAchievement('first-pr')) {
                try {
                    LFL::grant('first-pr')->to($user)->because('First merged pull request')->save();
                } catch (Throwable) {
                    // best-effort
                }
            }

            return $awarded;
        }

        return false;
    }

    protected function resolveUser(mixed $login): ?User
    {
        if (! is_string($login) || $login === '') {
            return null;
        }

        return User::where('github_username', $login)->first();
    }

    protected function xp(string $metric): int
    {
        return (int) config("gamification.metrics.$metric.xp", 0);
    }
}
