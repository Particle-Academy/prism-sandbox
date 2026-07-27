<?php

namespace App\Http\Controllers\Webhooks;

use App\Models\Contributor;
use App\Support\ContributorResolver;
use App\Support\XpAwarder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LaravelFunLab\Facades\LFL;
use Throwable;

/**
 * Awards contribution XP from GitHub webhook events. Authenticated by the
 * X-Hub-Signature-256 HMAC (not CSRF — the route is CSRF-exempt), scoped to the
 * configured org, and idempotent via {@see XpAwarder}.
 *
 * There are no site accounts: the contributor is resolved (and created on first
 * sight) directly from the GitHub identity in each payload.
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
            'pull_request_review' => $this->handleReview($payload),
            'pull_request' => $this->handlePullRequest($payload),
            'star' => $this->handleStar($payload),
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
        $fullName = (string) data_get($payload, 'repository.full_name', '');

        if ($fullName === '') {
            return false;
        }

        // Explicit repo allowlist wins when configured; otherwise any org repo.
        /** @var list<string> $repos */
        $repos = (array) config('services.github.repos', []);

        if ($repos !== []) {
            return in_array($fullName, $repos, true);
        }

        $org = (string) config('services.github.org');

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
            $this->contributor(data_get($payload, 'issue.user')),
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
            $this->contributor(data_get($payload, 'comment.user')),
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
            $this->contributor(data_get($payload, 'comment.user')),
            'review-comment:'.data_get($payload, 'comment.id'),
            data_get($payload, 'comment.html_url'),
        );
    }

    protected function awardComment(?Contributor $contributor, string $dedupKey, ?string $url): bool
    {
        return XpAwarder::award(
            $contributor,
            'pr-comment-xp',
            $this->xp('pr-comment-xp'),
            'Commented on a pull request'.($url !== null ? ' '.$url : ''),
            $dedupKey,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function handleReview(array $payload): bool
    {
        if (data_get($payload, 'action') !== 'submitted') {
            return false;
        }

        $url = data_get($payload, 'review.html_url');

        return XpAwarder::award(
            $this->contributor(data_get($payload, 'review.user')),
            'pr-review-xp',
            $this->xp('pr-review-xp'),
            'Reviewed a pull request'.($url !== null ? ' '.$url : ''),
            'review:'.data_get($payload, 'review.id'),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function handleStar(array $payload): bool
    {
        if (data_get($payload, 'action') !== 'created') {
            return false;
        }

        $sender = data_get($payload, 'sender');

        return XpAwarder::award(
            $this->contributor($sender),
            'star-xp',
            $this->xp('star-xp'),
            'Starred the repository',
            'star:'.data_get($sender, 'id'),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function handlePullRequest(array $payload): bool
    {
        $action = data_get($payload, 'action');
        $contributor = $this->contributor(data_get($payload, 'pull_request.user'));
        $nodeId = (string) data_get($payload, 'pull_request.node_id');
        $url = data_get($payload, 'pull_request.html_url');

        if ($action === 'opened') {
            return XpAwarder::award($contributor, 'pr-submitted-xp', $this->xp('pr-submitted-xp'), 'Opened pull request '.$url, 'pr-opened:'.$nodeId);
        }

        if ($action === 'closed' && data_get($payload, 'pull_request.merged') === true) {
            $awarded = XpAwarder::award($contributor, 'pr-accepted-xp', $this->xp('pr-accepted-xp'), 'Merged pull request '.$url, 'pr-merged:'.$nodeId);

            if ($awarded && $contributor instanceof Contributor && ! $contributor->hasAchievement('first-pr')) {
                try {
                    LFL::grant('first-pr')->to($contributor)->because('First merged pull request')->save();
                } catch (Throwable) {
                    // best-effort
                }
            }

            return $awarded;
        }

        return false;
    }

    /**
     * Resolve (creating on first sight) a Contributor from a GitHub user object
     * in the payload. Skips bots and anything missing an id or login.
     */
    protected function contributor(mixed $githubUser): ?Contributor
    {
        return app(ContributorResolver::class)->resolve($githubUser);
    }

    protected function xp(string $metric): int
    {
        return (int) config("gamification.metrics.$metric.xp", 0);
    }
}
