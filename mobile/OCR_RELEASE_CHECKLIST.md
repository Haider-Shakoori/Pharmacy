# Darmaltoon OCR release gates (development only)

The feature remains offline-first. The pharmacist must review all extracted values before saving. Cloud analysis is optional and must never run without the explicit checkbox consent.

## Backend cloud provider (not configured by default)

Set secrets only on the Laravel server, never in Flutter:

- `MEDICINE_OCR_PROVIDER_DRIVER=openai`
- `MEDICINE_OCR_PROVIDER_ENDPOINT=https://api.openai.com/v1/responses`
- `MEDICINE_OCR_ALLOWED_HOSTS=api.openai.com`
- `MEDICINE_OCR_PROVIDER_TOKEN=<server-side API key>`
- `MEDICINE_OCR_OPENAI_MODEL=gpt-4.1-mini` (or a separately verified vision-capable model)

For a private custom provider, use `MEDICINE_OCR_PROVIDER_DRIVER=custom` and configure a trusted HTTPS endpoint, its exact hostname in `MEDICINE_OCR_ALLOWED_HOSTS`, and a server-side token. It must return `{"suggestions":{...}}`.

**Privacy:** the user explicitly consents to transmission of the label image. The backend sets `store=false` for OpenAI Responses, never writes OCR images or suggestions to the database, and returns whitelisted suggestions only. Review provider-specific data retention policies before enabling cloud analysis. The API rejects unconfigured hosts and IP-literal endpoints.

## Before release

1. Run Laravel tests and Pint, Flutter format/analyzer/widget tests, Android CI and Windows CI.
2. Verify the native iOS plugin compiles on a macOS runner; Linux/Windows checks do not prove Swift compilation.
3. Test real labels on a physical iPhone in English, Dari and Pashto, including rotated/blurry photos, mixed scripts, expiry dates and regional digits.
4. Record which iOS languages Vision actually supports on the target device. If Dari/Pashto are unsupported, integrate a **bundled dedicated offline OCR model**; language identifiers alone do not provide a model.
5. Test that declining consent never calls the cloud, provider errors retain the offline draft, and no medicine is created before pharmacist review.
6. Only then produce the unsigned IPA for Sideloadly. Do not deploy the Laravel endpoint or change production without an explicit release decision.

## Current limitation

The iOS adapter uses Vision with supported language-specific passes and a default pass. A dedicated bundled Persian/Pashto recognizer is **not yet included**, and recognition quality is not verified by synthetic parser tests.
