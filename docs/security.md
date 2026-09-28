# Security Hardening — Batch 27

Batch 27 strengthens the SaaS control plane, tenant web application and mobile
API without removing the local-LAN deployment option.

## Web/session hardening

- session payload encryption is enabled by default;
- production session cookies default to Secure, while local HTTP installations
  can still explicitly run without HTTPS;
- session cookies remain HttpOnly and SameSite=Lax;
- every response receives anti-sniffing, anti-framing, referrer and browser
  capability restriction headers;
- HSTS is emitted only on HTTPS requests.

## Rate limiting

Login limits are keyed by identity as well as source address. Tenant login keys
also include the pharmacy host so one pharmacy cannot consume another
pharmacy's login allowance.

Mobile registration is keyed by device, user identity and source address.
Authenticated mobile API limits are keyed by a SHA-256 digest of the bearer
token instead of IP alone, which avoids penalizing many pharmacy devices behind
the same NAT connection.

## API abuse resistance

JSON API bodies are capped before controller validation. The default ceiling is
256 KiB and can be overridden with `SECURITY_MAX_API_PAYLOAD_BYTES`.

Signed mobile tokens are size-bounded and now require:

- token version 1;
- valid issued and expiry timestamps;
- expiry later than issue time;
- issue time not unreasonably far in the future;
- the existing Ed25519 signature and purpose checks.

These checks complement, rather than replace, activation, tenant, license,
subscription and active-user validation.
