# License and Activation Engine

BusinessOS Pharmacy uses the Laravel platform as the authority for subscription, license, device, and offline-lease state.

## License key security

- Every subscription receives one license record.
- The plaintext key is generated from cryptographically secure random bytes.
- Only SHA-256 of the key is stored in the database.
- The Platform Admin sees plaintext only immediately after generation or regeneration.
- Regenerating a key increments its version and revokes all existing device activations.
- Revoking a license revokes all active device activations.
- Desktop activation uses the key only to establish the initial activation relationship.

## Device activation

POST /api/v1/license/activate accepts:

- license_key;
- device_id (persistent installation UUID);
- platform (android or windows);
- optional device_name;
- optional app_version;
- optional device_model;
- optional os_version;
- optional build_number.

Activation requires an active license, an operational subscription, and capacity under the platform-specific device limit. The same device can refresh its activation idempotently.

## Desktop lease refresh

POST /api/v1/desktop/license/refresh is the Windows desktop renewal path.

The desktop sends its currently signed offline lease as a Bearer token plus its persistent device_id and current device/app metadata. The server verifies the signature even when the old offline window has expired, then re-checks:

- activation is still present and not revoked;
- device ID matches;
- activation belongs to Windows;
- tenant, license ID, and license version still match;
- license is active;
- subscription is operational.

A successful refresh returns a newly signed lease. The desktop therefore does not need to reuse or store the plaintext license key as its permanent authentication mechanism.

## Signed offline lease

A successful activation/refresh returns an Ed25519-signed v1 lease token. Existing mobile verification remains compatible with additional claims.

The signed payload includes:

- purpose = offline_lease;
- entitlement_version;
- tenant, subscription, license, activation, and device identifiers;
- license version;
- platform;
- plan code;
- subscription status and health;
- trial start/expiry when applicable;
- paid subscription expiry when applicable;
- issued/server time;
- offline expiry;
- signed plan entitlements.

Plan features remain present in the outer response for backward compatibility, but offline desktop authorization must trust the signed entitlements rather than unsigned JSON.

Environment variables:

- LICENSE_SIGNING_PRIVATE_KEY_B64
- LICENSE_SIGNING_PUBLIC_KEY_B64

The private key must remain server-side. Desktop/mobile clients may contain only the public verification key.

## Trial authority

Trial start and expiry remain server-owned. A Windows desktop installation must not create or restart a trial locally. The BusinessOS Pharmacy website/platform provisions the trial and license; the desktop activates that issued license.
