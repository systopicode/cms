# Auth / Pages follow-up — 30 October 2026

This round is implemented. Native iPad/camera acceptance is still open; it is not declared fully accepted.

| Original item | Status | Verification |
| --- | --- | --- |
| 1 | PASS | Failed submitted code is retained by the CMS template. Actual shared card runs at 390/1194/1440 with failed and empty attempted codes: fields visible, scanner hidden, values retained. Auth end-to-end on the running instance was attempted but redirected to dashboard because the browser is already authenticated; existing sessions were not logged out. |
| 2 | PASS | Actual QR parser/submit code runs in an isolated browser fixture: foreign QR rejected; system-shaped code shows “Checking code…” and submits once. No unvalidated success message. Physical camera and expired-token server round-trip were not exercised. |
| 3 | PASS | Computed colors checked in actual shared CSS at all three widths: welcome uses text color; countdown retains muted color. |
| 4 | BLOCKED | Desktop-mode iPad capability hint added to ordinary requests and passkey requests; server uses it only for display name/icon. PHP tests cover Mac Safari, iPad desktop hint, Mobile UA and ignoring hint on Windows. Existing generated device names are corrected on normal touch/reconnect; custom names remain. Native Safari iPad verification unavailable, and the screenshot mentioned in the request was not attached. |
| 5 | PASS | Whole footer animates for 250 ms on no-page → selected-page transition. Fixture verifies no replay on subsequent renders, stored 300px height, reduced motion, desktop/tablet/narrow. Running Pages navigation also checked. |
| 6 | PASS | Existing sidebar handle font increased from .6875rem to .875rem. Running app computed size checked; isolated responsive sidebar checked. |
| 7 | PASS | Presence label restored synchronously after diff render, using its existing revision state. Isolated test covers element replacement and cancelled hide animation. Two real application tabs on QA page 1520: peer description changes arrived without reload; banner stayed 35.4375px and article top stayed 118.5625px. |
| 8 | PASS | Presence/leave and relay refresh calls use the existing request object with background flag; they still participate in in-flight ordering and error handling. Running app showed no loading bar for idle presence polls. Relay delivery/retry tests and shared save-feedback tests pass. |
| 9 | PASS | Closed sidebar no longer keeps a border. Running app measured zero width and zero border; responsive fixture also checks zero width. |
| 10 | PASS | Label save marks the successful own save through the existing own-save handling. Real application QA page: title saved with Enter, no false updated/reload notice after polls; persisted after reload. Field conflict/review and sidebar conflict regression tests pass. |

## Changes and limits

- No new spinner, auth state store, message variant or layout library.
- QR recognition is not key validation. The existing redemption action remains authoritative.
- `X-Fluxo-Device: iPad` is a presentation hint, not an identity or permission signal. It is accepted only for Macintosh user agents and never grants access. Normal Macs are unchanged.
- Advisory labels and expiry stay in the existing per-page revision map. No full preview reload is initiated by the presence poll.
- A temporary unpublished QA page (1520) was created, renamed and given a description. It was then removed to the recoverable trash. Existing pages/content were not edited. Testing tabs were closed and original page 1375 / SEO tab restored.
- Browser console: no new JavaScript error during the two-tab checks. A separate ambiguous-focus diagnostic was observed during navigation around Add Page. Server PHP error log had no new entry from this round.
- Proof screenshot: `/private/tmp/fluxo-pages-round-verified.png`.

## Regression commands

CMS: `tests/auth-pages-followup-ui.mjs`, `tests/editor-presence-ui.mjs`, `tests/block2-shared-ui.mjs`, `tests/editor-presence.php`, `tests/page-edit-conflict.php`, `tests/sidebar-conflict.php`.

System: `tests/device-name.php`, `tests/gui-feedback-ui.mjs`, `tests/auth-mobile-ui.mjs` (signed-in fixture corrected to use the existing auth screen fixture).

Relay: `tests/refresh-delivery.mjs`.

Browser fixtures require `FLUXO_PLAYWRIGHT_MODULE` pointing to the locally available Playwright module.
