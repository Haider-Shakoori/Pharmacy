# Pharmacy Settings

Batch 8 introduces tenant-managed profile, localization, and operational settings.

## Localization

A pharmacy can configure its default English, Dari, or Pashto locale and timezone. User session language overrides the pharmacy default for that session.

Currency remains controlled by the tenant/platform record and is displayed read-only to pharmacy staff.

## Daily Closing policy

The following policy is now centrally configurable:

- business-day rollover time;
- opening cash source: prior closing carry-forward or manual;
- counted-cash requirement;
- AFN variance threshold at which a note is required;
- whether reopening a finalized day is permitted at all;
- whether the prior day must be closed before the next day begins;
- whether online sales are blocked after a business date is finalized;
- whether to warn about unsynced Android devices before closing.

The authorization layer remains independent: even when reopening is enabled in settings, the user must also have daily_closing.reopen permission.

## Business date

BusinessDateResolver converts a timestamp into the pharmacy's business date using the pharmacy timezone and rollover time. For example, with a 03:00 rollover, a sale at 02:15 local time still belongs to the previous business date.

This resolver is intended to be the common source for Web POS, Android sync, reports, accounting, shifts, and Daily Closing.
