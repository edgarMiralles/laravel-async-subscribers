<?php

declare(strict_types=1);

namespace EdgarMiralles\AsyncSubscribers;

use EdgarMiralles\AsyncSubscribers\Jobs\RunSubscriber;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Queue\Events\JobQueued;
use Psr\Log\LoggerInterface;

final class SubscriberJobLogger
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly Repository $config,
    ) {}

    public function enqueued(JobQueued $queued): void
    {
        if (! $this->isEnabled() || ! $queued->job instanceof RunSubscriber) {
            return;
        }

        $this->logger->info('Async subscriber enqueued', [
            'subscriber' => $queued->job->subscriber,
            'event' => $queued->job->event,
            'job_uuid' => $queued->payload()['uuid'] ?? null,
            'queue_job_id' => $queued->id,
            'connection' => $queued->connectionName,
            'queue' => $this->queueName($queued->job, $queued->connectionName),
        ]);
    }

    public function consuming(RunSubscriber $job): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $this->logger->info('Async subscriber consuming', $this->contextOf($job));
    }

    public function consumed(RunSubscriber $job, float $durationInMilliseconds): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $this->logger->info('Async subscriber consumed', [
            ...$this->contextOf($job),
            'duration_ms' => $durationInMilliseconds,
        ]);
    }

    /** @return array<string, mixed> */
    private function contextOf(RunSubscriber $job): array
    {
        $connection = $job->job?->getConnectionName();

        return [
            'subscriber' => $job->subscriber,
            'event' => $job->event,
            'job_uuid' => $job->job?->uuid(),
            'queue_job_id' => $job->job?->getJobId(),
            'connection' => $connection,
            'queue' => $this->queueName($job, $connection),
            'attempt' => $job->attempts(),
        ];
    }

    /** The name the job was queued under, not the driver's resolved form (a full URL on SQS). */
    private function queueName(RunSubscriber $job, ?string $connection): ?string
    {
        return $job->queue ?? $this->config->get("queue.connections.{$connection}.queue");
    }

    private function isEnabled(): bool
    {
        return (bool) $this->config->get('async-subscribers.log');
    }
}
