# v2026.07.06-ua-config

## Version

- Tag: `v2026.07.06-ua-config`
- Date: 2026-07-06
- Purpose: stable rollback point after moving UA whitelist checks from hardcoded PHP values into the admin system configuration.

## Changes

- Added two admin system config fields:
  - `client_ua_ios`
  - `client_ua`
- `ServerController.php` now reads `client_ua_ios` for iOS UA checks.
- `ClientController.php` and `Client.php` now read `client_ua` for Android, Windows, and macOS UA checks.
- iOS UA matching keeps the existing keyword-contains behavior.
- Android, Windows, and macOS UA matching keeps exact-match behavior.
- Empty UA config means allow all.
- Removed hardcoded UA lists from the checked code paths.
- System config save now only removes legacy UA keys:
  - `client_ua_android`
  - `client_ua_windows`
  - `client_ua_macos`

## Verification

- PHP syntax checks passed for changed PHP files.
- VM was rebuilt from local code as a fresh deployment.
- VM homepage returned HTTP 200.
- VM admin page returned HTTP 200.
- Local code manifest with 406 files was verified on VM with `sha256sum -c`.

## Rollback

Use this tag to return to this known-good version:

```bash
git checkout v2026.07.06-ua-config
```

For branch rollback after a bad update:

```bash
git reset --hard v2026.07.06-ua-config
```
