<?php

namespace App\Services\Mobile;

use App\Models\Customer;
use App\Models\Medicine;
use App\Models\StockLocation;
use App\Models\User;
use App\Services\Sales\PosSaleService;
use App\Services\Sync\SyncAccessContext;
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
        SyncAccessContext $context,
        array $events,
    ): array {
        $results = [];

        foreach ($events as $event) {
            $results[] = $this->pushEvent($context, $event);
        }

        return $results;
    }

    private function pushEvent(
        SyncAccessContext $context,
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

                $resolved = $this->resolveReferences($payload);

                $data = [
                    'stock_location_id' => $resolved['stock_location_id'],
                    'customer_id' => $resolved['customer_id'],
                    '_allow_inactive_references' => true,
                    'idempotency_key' => $idempotencyKey,
                    'notes' => 'Synced from an offline pharmacy client.',
                    'prescription_reference' => $payload['prescription_reference'] ?? null,
                    'prescriber_name' => $payload['prescriber_name'] ?? null,
                    'prescription_date' => $payload['prescription_date'] ?? null,
                    'lines' => $resolved['lines'],
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
        } catch (ModelNotFoundException $exception) {
            $model = class_basename($exception->getModel());
            $ids = implode(', ', array_map(
                static fn (mixed $id): string => (string) $id,
                $exception->getIds(),
            ));
            $reference = $ids !== '' ? " ({$ids})" : '';

            return $this->rejected(
                $idempotencyKey,
                'reference_missing',
                "{$model} reference{$reference} could not be resolved for this historical sale.",
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

    private function resolveReferences(array $payload): array
    {
        $locationId = trim((string) ($payload['stock_location_id'] ?? ''));
        $location = $locationId === ''
            ? null
            : StockLocation::query()
                ->whereKey($locationId)
                ->first();

        if ($location === null) {
            $locationCode = trim((string) ($payload['stock_location_code'] ?? ''));
            if ($locationCode !== '') {
                $location = StockLocation::query()
                    ->where('code', $locationCode)
                    ->first();
            }
        }

        if ($location === null) {
            throw (new ModelNotFoundException)->setModel(
                StockLocation::class,
                [$locationId],
            );
        }

        $customerId = trim((string) ($payload['customer_id'] ?? ''));
        $customer = null;

        if ($customerId !== '') {
            $customer = Customer::query()
                ->whereKey($customerId)
                ->first();

            if ($customer === null) {
                $email = trim((string) ($payload['customer_email'] ?? ''));
                $phone = trim((string) ($payload['customer_phone'] ?? ''));

                if ($email !== '') {
                    $customer = Customer::query()
                            ->whereRaw('LOWER(email) = ?', [Str::lower($email)])
                        ->first();
                }

                if ($customer === null && $phone !== '') {
                    $matches = Customer::query()
                            ->where('phone', $phone)
                        ->limit(2)
                        ->get();

                    if ($matches->count() === 1) {
                        $customer = $matches->first();
                    }
                }

                if ($customer === null) {
                    throw (new ModelNotFoundException)->setModel(
                        Customer::class,
                        [$customerId],
                    );
                }
            }
        }

        $lines = [];
        foreach (($payload['lines'] ?? []) as $line) {
            if (! is_array($line)) {
                $lines[] = $line;

                continue;
            }

            $medicineId = trim((string) ($line['medicine_id'] ?? ''));
            $medicine = $medicineId === ''
                ? null
                : Medicine::query()
                    ->whereKey($medicineId)
                    ->first();

            if ($medicine === null) {
                $medicineCode = trim((string) ($line['medicine_code'] ?? ''));
                if ($medicineCode !== '') {
                    $medicine = Medicine::query()
                            ->where('medicine_code', $medicineCode)
                        ->first();
                }
            }

            if ($medicine === null) {
                throw (new ModelNotFoundException)->setModel(
                    Medicine::class,
                    [$medicineId],
                );
            }

            $line['medicine_id'] = (string) $medicine->id;
            $lines[] = $line;
        }

        return [
            'stock_location_id' => (string) $location->id,
            'customer_id' => $customer?->id,
            'lines' => $lines,
        ];
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
