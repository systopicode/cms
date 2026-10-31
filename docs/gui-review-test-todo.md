# GUI review: practical test checklist

Changes cover the numbered feedback from the 30 October review. Test with a hard reload first so old scripts/styles are not cached. Test desktop light/dark; the final dedicated mobile pass remains separate.

## Messages and mail (items 1–3, 20)

- Change a field: orange while unsaved, saving spinner, then green; green disappears after about 2.5 seconds.
- Cause a rejected save and a failed action: red outline disappears after about 6 seconds; a detailed message is visible. Field failures say Not saved; action failures say Action failed, never Not saved.
- Check a widget/dialog error stays at that item. A page-level error floats above the content with even surrounding spacing and shadow. Multiple page errors share one host. After the reading interval the message collapses without retained blank space.
- Environment: Send test email with the current failing SMTP settings. Check the reason/server response, no password exposure, correct effective recipient. A successful SMTP acceptance still needs a real inbox/spam check.
- Inspect a delivered subject: `Test mail // Subject [configured separator] installation URL` in test mode; no prefix outside test mode. Dev must always route every recipient, including CC/BCC, to the configured test recipient. Do not test this by mailing real third parties.

## Auth and users (items 6, 13–15, 29–32)

- A signed-in account without a password/key can add its first passkey in both User Single and User Edit. There is no extra password prompt or Cancel footer; X closes it. Creating another key still confirms with an existing key.
- Cancel a passkey ceremony repeatedly: cancellation alone must not consume sign-in failures. Genuine invalid verification remains rate-limited; an existing IP block expires after fifteen minutes and is not silently cleared.
- Both invitation/password-link mail buttons ask for confirmation. Cancel must send nothing.
- Enter an invalid remote code: visible feedback in the remote dialog. Typed/scanned codes must not be appended to the address. Closing a dialog opened from a QR URL removes its screen query so reload does not reopen it.
- Authorize a Screen from another device: its own avatar/name and Screen successfully paired appear on the two-second success card. Check its cleaner robot portrait in User Edit too.
- Signed out title/subtitle are left aligned; countdown and sign-in-again button retain their layout.

## Pages and media (items 4–5, 8–12)

- Open one page in two tabs/devices. Both show the compact blue advisory within the polling interval. Navigate away in one: the remaining advisory disappears. Real conflicting writes still preserve local text and offer review/explicit overwrite.
- Enter Pages through the main menu without an ID, from a page link, and with a direct ID URL. The selected row is revealed smoothly only as needed. No scrolling attempt when the tree fits; live refreshes do not jump an unchanged selection.
- Alias/redirect title controls have the pencil-like transparent hover/focus/keyboard behaviour and open the correct sidebar section.
- File Edit/Replace: Used on cards are aligned and have no hover inset/border. Preview shows a spinner while loading, disappearing on load/failure.
- Image: apply rotation, then colour; reload and confirm both persist. Repeat in reverse, then reflection twice. Switch format: no save outline. Check separate formats and Apply to all on disposable test media.

## Dashboard, settings, backups and tools (items 7, 16–19, 21–28 and status colours)

- Customize dashboard, leave it, return: customization is off; saved layout is retained.
- Settings order: Environment, Global, Media, Tags, Theme; Template is separated. Switch between Pages/Settings/Tools, including remembered child redirects: main selection follows the actual module.
- Media groups help button opens a small explanatory dialog; no history entry is created. Colour contrast uses green checks and yellow attention icons. Check dark mode as well.
- Backup Create/Restore use size 2. Backup-log dialog uses the shared rounded widget/table. Merely inspect Restore; do not run a restore in production for this check.
- Audit checkboxes use shared controls. Toggling selection must not run database actions.
- Website checks, Cleanup and Media repair use their new canonical URLs. Old maintenance bookmarks lead to the right new page; generated in-system links use the new paths.
- Systemcheck: Recheck is beside Download. Test media tools updates its area and retains the reading position. Download standalone checker and run it on the intended host, then remove it; verify project-local tool discovery.
- Cleanup: no decorative widget-header icons. Automatic status and task checks use green/yellow/red semantics. Optional scheduling clearly offers one of two methods, while ordinary visit-based cleanup requires no setup. Do not rotate a live scheduler key just to inspect the UI.
- Visible product name is fluxo. Technical package, database and route identifiers retain their existing names.

## Verification already performed

PHP syntax and JavaScript parsing; full passkey service tests with real OpenSSL/CBOR fixtures in an isolated database (including first-key creation for a QR-only account); local feedback/message lifecycle fixtures; advisory presence and sidebar conflict tests; actual two-tab advisory in Dev; new navigation and systemcheck/media view in Dev; local SMTP sink routing; subject idempotence; image tune composition; scheduler permission/view and runtime capability tests. Real inbox delivery, platform passkey UI, cross-device Screen pairing and production hosting remain practical checks.
