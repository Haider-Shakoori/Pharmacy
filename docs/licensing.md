# License and Activation Engine

Batch 5 adds subscription license generation and Android activation.

## License key security

- Every subscription receives one license record.
- The plaintext key is generated from cryptographically secure random bytes.
- Only SHA-256 of the key is stored in the database.
- The Platform Admin sees plaintext only immediately after generation or regeneration.
- Regenerating a key increments its version and revokes all existing device activations.
- Revoking a license revokes all active device activations.

## Android activation

POST /api/v1/license/activate accepts:

- license_key;
- device_id (UUID generated and persisted by the app installation);
- optional device_name;
- optional app_version.

Activation requires:

- active license;
- active pharmacy tenant;
- active subscription;
- subscription within its configured start/end period;
- available Android device capacity in the assigned plan.

The same device can refresh its activation idempotently.

## Offline lease

A successful activation returns an Ed25519-signed lease token. The Android app will embed only the public verification key; the private signing key remains server-side.

The lease contains tenant, subscription, license version, activation, device, plan, issue time and expiry time. Lease expiry is capped by both the plan's offline grace period and the subscription end date.

Environment variables:

- LICENSE_SIGNING_PRIVATE_KEY_B64
- LICENSE_SIGNING_PUBLIC_KEY_B64

The private key must be a base64-encoded Ed25519 secret key. Production activation will fail closed if a valid signing key is not configured.

## Upcoming Batch 6

The seven-day trial and enforcement layer will add trial status, automatic trial expiry, web access enforcement, and subscription/license health rules without weakening offline verification.
