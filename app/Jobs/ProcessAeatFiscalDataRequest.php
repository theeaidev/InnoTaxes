<?php

namespace App\Jobs;

use App\Services\Aeat\AeatIntegrationException;
use App\Services\Aeat\AeatFiscalDataRequestProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessAeatFiscalDataRequest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $requestId)
    {
    }

    /**
     * Get the backoff delay for retryable failures.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    /**
     * Execute the job.
     */
    public function handle(AeatFiscalDataRequestProcessor $processor): void
    {
        try {
            $processor->process($this->requestId, $this->attempts(), $this->tries);
        } catch (Throwable $throwable) {
            $retryable = ! ($throwable instanceof AeatIntegrationException) || $throwable->retryable();
            $willRetry = $retryable && $this->attempts() < $this->tries;
            $processor->recordFailure($this->requestId, $throwable, $this->attempts(), $this->tries, $willRetry);

            if ($willRetry) {
                throw $throwable;
            }
        }
    }
}
