# General and concurrent-session corrections — 30 October 2026

## 1. General and password

General is a regular existing widget again. Change password is a closed-by-default `cmsBox` accordion inside it, using the existing form, action, validation and button. It is no longer a second widget. No credentials or account details were changed during verification.

Verification: running DEV profile opened and accordion expanded/collapsed; no browser errors. Isolated layout/interaction checks using the rendered component and existing CSS at 390, 1194 and 1440 px (touch enabled for the first two): pointer and Enter toggles passed, no horizontal overflow. Screenshots inspected. The in-app browser viewport override remained 1280 px, so its screenshot is desktop evidence only. Physical iPad verification was not available.

## 2. Concurrent sessions

A relay invalidation could previously be consumed by any request's header hook, including an action-only presence request that renders nothing. The relay now retains invalidations until a dedicated refresh takes them. Failed batches are returned to the existing queue. Updates arriving during an in-flight refresh remain queued, including another update on the same channel. Refresh waits for outstanding saves/navigation. Gather delay is 150 ms.

The existing advisory presence check now polls visible tabs every two seconds instead of five. Stale responses for a replaced or changed page marker cannot update the new page's notice. An in-progress hide animation is cancelled when the notice is needed again. No parallel state system or editing lock was introduced.

Verification: two real DEV browser tabs on page 1174 both showed the neutral same-user advisory; switching the second tab to Settings removed the first tab's advisory. No page content was changed. Console errors: none; inspected project PHP logs contained no new entries. Relay fixture tests exercise action-only requests, concurrent same-channel updates, failed refresh retry, save-in-flight waiting and project stop/start. PHP and browser conflict tests exercise stale writes, explicit overwrite and third-writer protection. Presence PHP/browser tests cover switching, leaving, expiry and named users.

Limits: saved-change delivery was tested in isolated regression harnesses, not by changing real project content across devices. Without a hub the existing fallback remains advisory/reload-based. This is not character-level collaborative editing: unsaved text is kept locally, and overlapping saves require conflict resolution. Google-Docs-style merging would need a separate design; this correction does not claim to provide it.

## Firefox follow-up — 22:30

- Account hover gear increased from .9rem to 1.35rem. Password accordion summary aligns its title/icon and chevron vertically in the centre. Pointer/Enter, size and overflow checks passed at 390, 1194 and 1440 px; actual Firefox profile visually inspected.
- Actual Firefox console exposed malformed Pages layer HTML caused by output buffering around a nested DomLayer. Removed the competing buffer; presence/no-template notices remain siblings of the preview article, ordered above it.
- Fixed a follow-up regression: `sendCall().done()` does not return chainable failure hooks. Relay refresh now uses the existing `server.call().send()` API, which supports both callbacks. Regression harness updated to use that API.
- Fixed pending presence-hide handling: retain the Animation, not its Promise, so cancellation on a returning peer works. Test now explicitly returns a peer during the hide animation and checks for exceptions. Background windows keep their heartbeat instead of silently disappearing from advisory presence.
- Actual end-to-end DEV test used a newly created, unpublished page **1516**. Two Firefox windows opened it. Meta description `Fluxo live QA – Fenster B` appeared in the other window without reload; the reverse change `Fluxo live QA – Fenster A` also appeared. Presence advisory appeared. Test page was moved to the recoverable trash afterwards; no pre-existing content was edited.
- After test, Firefox console had no application TypeError or malformed-layer error. The existing extension error `No Listener: tabs:outgoing.message.ready` remained and is not an application live-update exception.

The saved-change delivery limitation above is superseded by this real two-window Firefox test. Physical multi-device/iPad testing and character-by-character collaboration remain outside this verification.
