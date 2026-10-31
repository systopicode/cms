# Block 3 — Shared UI, Navigation, Auth & User (2026-10-30)

No new layout, state or icon system. Saturation uses the registered half droplet.

| Original number | Status | Actual verification |
| --- | --- | --- |
| 8 | PASS | Native modal over User Edit in the running Dev app: footer remains in top dialog, returns to outer dialog and body, actions remain clear. Isolated tests: 1194/1440px flat windows have at least 8px clearance; 390px retains existing mobile feedback. Footer background uses 86% opacity. |
| 9 | PASS | Bold intrinsic label sizing in the existing generated navigation span. Isolated before/after selected measurements retain width and height; Dev Settings labels inspected. No fixed text widths/truncation added. |
| 10 | PASS | Actual loading container spans x=0 to viewport width at 390/1194/1440px; no document horizontal overflow in Dev. No dependency on debug classes. |
| 11 | PASS | Dev Add device → Use code → enter one character → Close: no discard prompt. Isolated test also keeps a separate unsaved page field intact while closing the auth dialog. No submitted pairing request. |
| 21 | PASS | Dev General and Change password collapse/reopen independently. Native details/summary uses shared widget and accordion rules; original forms and validation retained. No password changed. |
| 22 | PASS | User fallback visually inspected in Dev; central local user/monitor duotone SVGs tested separately. Uploaded-file lookup retains precedence. PHP fixture checks separate technical fallback, two layers, no email in URI, no external request. Privacy's technical avatar description updated accordingly. |
| 23 | PASS | Floating host has no padding, shadow belongs to painted message child, last-row bottom margin is zero, background clips to border. Shared CSS rendered in responsive fixture. Real physical Safari/iPad rendering remains an environmental check, not claimed here. |
| 24 | PASS | Presence PHP tests: other user IDs included, same person excluded, old entries expire, navigation/leave clears tab. UI fixture verifies named notice. Two real Dev tabs show neutral same-user message; no script or server error. |
| 25 | PASS | Presence only changes its own notice and only when its text/state changes; no re-render request. UI fixture verifies page changes and hiding notice. Two real tabs exercise presence; no content writes. |
| 26 | PASS | Conflict UI fixture displays supplied author/time, retains unsaved field and exact-version overwrite guard. Content conflict uses fresh database page metadata after rollback; explicitly labeled as latest page update, not field history. For settings without recorded metadata the dialog explicitly says author/time unavailable. Existing protection PHP tests pass. |
| 27 | PASS | Loader fixture: initialization before full image load, missing script, 30-second stalled-load deadline, thrown initialization failure. Failure ends spinner and offers Reload; exceptions remain observable. Dev loader hidden after successful load. |
| 29 | PASS | Autosave/focus regression: caret/focus preservation, concurrent changes, navigation guard, dialog Tab/Escape/opener. Shared fixture normal rendered event leaves input focused; logical dialog replacement no longer focuses unrelated opener/control. Dev nested dialog returns focus to Add passkey. |
| 38 | BLOCKED | Existing shared success template remains without check icon, two-second copy unchanged. No real sign-in was executed for each method (passkey requires interactive authenticator; QR requires a second-device authorization). Full end-to-end method matrix is not claimed. |
| 41 | PASS | Running Dev: Pages → Settings → Pages, browser Back/Forward; main active class matches completed AJAX render. Immediate router change can precede asynchronous response, so verification waits for completion. Found and corrected a stale main-nav DOM layer after redirect: after processing a response, active and aria-current now follow the rendered body module. Rechecked after AJAX completion and Back/Forward; no delayed CSS timer. |

## Verification commands

- `php tests/editor-presence.php`
- `php tests/page-edit-conflict.php`
- `php tests/sidebar-conflict.php`
- `node tests/editor-presence-ui.mjs`
- `node tests/page-edit-conflict-ui.mjs`
- `node tests/block2-shared-ui.mjs`
- `node tests/block3-shared-ui.mjs`
- System: `node tests/gui-feedback-ui.mjs`, `node tests/loader-ready.mjs`, `php tests/portrait-fallback.php`
- Icon builder `--check`, changed PHP/JS syntax, both repositories `git diff --check`.

Browser fixtures use `FLUXO_PLAYWRIGHT_MODULE=/private/tmp/fluxo-ui-check-oct30/node_modules/playwright-core/index.mjs`. They use isolated DOM/test values, not project content. Live verification used the already authorized Dev URL in IAB/Chromium. Physical iPad/Safari and real passkey/QR completion were not available. Existing `passkeys.php` integration suite additionally requires an isolated `FLUXO_TEST_DSN`; no real project DB was substituted.

No new console errors in agent Dev tabs. Existing unrelated warning: duplicates panel refers to old cleanup parent. Checked PHP logs contained only older entries. Agent tabs closed, viewport override reset. No content/media/password/passkey/settings writes, no cleanup or restore performed. Opening the existing Add device UI creates its normal short-lived pairing offer; it was closed without authorization or use.

Block is not fully accepted while 38's real-method verification is outstanding.
