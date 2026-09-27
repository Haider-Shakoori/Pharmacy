# Trial and Subscription Enforcement

Batch 6 makes subscription health an operational access decision.

## Automatic seven-day trial

Creating a new pharmacy from Platform Admin automatically:

1. creates or reuses the TRIAL plan;
2. creates a trial subscription;
3. starts the seven-day countdown immediately;
4. generates the pharmacy license key;
5. shows the plaintext key once to the platform administrator.

The trial currently allows up to 5 pharmacy users, 2 Android devices, and 1 branch. These values are stored in the TRIAL plan and can evolve later.

## Health states

The Platform Admin derives a health state from the tenant, subscription dates/status, and license:

- healthy;
- trial;
- expiring soon;
- no subscription;
- license missing;
- license revoked;
- pharmacy inactive;
- not started;
- suspended;
- expired;
- cancelled.

Only healthy, trial, and expiring-soon states are operational.

## Enforcement

The subscription.operational middleware is fail-closed and returns HTTP 402 when the pharmacy is not operational. It is registered now and will be applied to authenticated pharmacy operations as the pharmacy login/roles layer is introduced in Batch 7.

Android license activation uses the same health service. A trial device lease is capped at the trial end time even if the plan's offline grace period would otherwise be longer.

## Automatic expiry

subscriptions:expire-trials changes elapsed trials to expired and revokes their licenses. The scheduler runs this command hourly.

The health service independently treats an elapsed trial as expired even before the scheduler runs, so scheduler delay cannot grant extra access.
