<?php

namespace App\Services\Mobile;

use App\Models\User;
use App\Services\Sales\PosSaleService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class MobileSyncPushService
{
    public function __construct(
        private readonly PosSaleService $sales,
    ) {}

    public function push(
        MobileAccessContext $context,
        array $events,
    ): array {
        $results = [];

        foreach ($events as $event) {
            $results[] = $this->pushEvent($context, $event);
        }

        return $results;
    }

    private function pushEvent(
        MobileAccessContext $context,
        array $event,
    ): array {
        $idempotencyKey = (string) ($event['idempotency_key'] ?? '');
        $eventType = (string) ($event['event_type'] ?? '');
        $payload = is_array($event['payload'] ?? null)
            ? $event['payload']
            : [];

        if ($eventType !== 'sale.completed') {
            return $this->rejected(
                $idempotencyKey,
                'unsupported_event',
                'This synchronization event type is not supported.',
            );
        }

        if (($payload['idempotency_key'] ?? null) !== $idempotencyKey) {
            return $this->rejected(
                $idempotencyKey,
                'idempotency_mismatch',
                'The event and payload idempotency keys do not match.',
            );
        }

        if ((string) ($payload['cashier_user_id'] ?? '') !==
            (string) $context->userId) {
            return $this->rejected(
                $idempotencyKey,
                'cashier_mismatch',
                'The offline sale belongs to a different pharmacy user.',
            );
        }

        try {
            return $context->tenant->run(function () use (
                $context,
                $payload,
                $idempotencyKey,
            ): array {
                $user = User::query()
                    ->whereKey($context->userId)
                    ->where('is_active', true)
                    ->firstOrFail();

                $data = [
                    'stock_location_id' => $payload['stock_location_id'] ?? null,
                    'customer_id' => $payload['customer_id'] ?? null,
                    'idempotency_key' => $idempotencyKey,
                    'notes' => 'Synced from Android offline POS.',
                    'lines' => $payload['lines'] ?? [],
                    'payments' => $payload['payments'] ?? [],
                ];

                Validator::make($data, [
                    'stock_location_id' => ['required', 'string'],
                    'customer_id' => ['nullable', 'string'],
                    'idempotency_key' => ['required', 'string', 'max:191'],
                    'lines' => ['required', 'array', 'min:1', 'max:100'],
                    'lines.*.medicine_id' => ['required', 'string'],
                    'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
                    'lines.*.unit_price' => ['nullable', 'numeric', 'min:0'],
                    'lines.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
                    'payments' => ['required', 'array', 'min:1', 'max:10'],
                    'payments.*.method' => [
                        'required',
                        Rule::in(['cash', 'bank', 'mobile', 'credit']),
                    ],
                    'payments.*.amount' => ['required', 'numeric', 'gt:0'],
                    'payments.*.reference' => ['nullable', 'string', 'max:160'],
                ])->validate();

                $sale = $this->sales->checkout($user, $data);

                return [
                    'idempotency_key' => $idempotencyKey,
                    'status' => 'accepted',
                    'aggregate_type' => 'sale',
                    'local_id' => $payload['local_id'] ?? null,
                    'server_id' => $sale->id,
                    'sale_number' => $sale->sale_number,
                    'server_updated_at' => $sale->updated_at?->toISOString(),
                ];
            });
        } catch (ValidationException $exception) {
            [$code, $message] = $this->validationConflict(
                $exception->errors(),
            );

            return $this->rejected(
                $idempotencyKey,
                $code,
                $message,
            );
        } catch (ModelNotFoundException) {
            return $this->rejected(
                $idempotencyKey,
                'reference_missing',
                'A referenced pharmacy record no longer exists.',
            );
        } catch (Throwable $exception) {
            report($exception);

            return $this->rejected(
                $idempotencyKey,
                'server_error',
                'The server could not process this event.',
                retryable: true,
            );
        }
    }

    private function validationConflict(array $errors): array
    {
        $message = (string) (
            collect($errors)->flatten()->first()
            ?? 'The synchronized sale was rejected.'
        );

        if (array_key_exists('closing', $errors)) {
            return ['daily_closing_conflict', $message];
        }

        $normalized = Str::lower($message);

        if (Str::contains($normalized, [
            'insufficient eligible stock',
            'no sellable priced stock',
        ])) {
            return ['stock_conflict', $message];
        }

        return ['validation_failed', $message];
    }

    private function rejected(
        string $idempotencyKey,
        string $code,
        string $message,
        bool $retryable = false,
    ): array {
        return [
            'idempotency_key' => $idempotencyKey,
            'status' => 'rejected',
            'code' => $code,
            'message' => $message,
            'retryable' => $retryable,
        ];
    }
}
