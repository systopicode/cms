# Block 1: follow-up, items 1–8

Implementation is present; acceptance is not complete. Running-project browser access was previously rejected by automatic approval review. No alternate browser or HTTP client was used to bypass that restriction. Real Safari/iPad checks remain outstanding.

| Nr. | Status | Verification and remaining work |
|---|---|---|
| 1 | BLOCKED | Frontend branding is selected from the validated remembered return destination. Destination tests pass (frontend, CMS, outside installation, absent). Frontend favicon paths were compared with the Stefan project; SVG favicon and 96px PNG variants are supported. Real frontend-to-auth navigation, rendered metadata and logo still need checking. |
| 2 | BLOCKED | Settings/Tools headings are conditional on additional sections; otherwise a nonbreaking-space heading retains height. Users inherit the same spacer. Separator reaches navigation edges. Tools PHP navigation tests pass; visual Settings/Users/Tools comparison remains open. |
| 3 | BLOCKED | Sticky action shadow has a non-scroll-timeline fallback, including schema/remove cells. Horizontal overscroll is disabled without disabling vertical page scroll. Local computed-style test passes; real touch rubber-banding requires iPad. |
| 4 | BLOCKED | Shared completion removes the passkey-signing CSS mode which hid its card. Isolated browser test verifies old lock hidden, welcome/name, destination and Continue link visible. Real passkey sign-in/two-second redirect needs an authenticator. Full PHP passkey suite requires an explicitly isolated fluxo_test database; none was supplied in this run. |
| 5 | BLOCKED | Preview dialog opens after DOM insertion through shared UI initialization, with no dependence on nested inline-script timing. Local browser insertion/open/close test and non-mutating PHP preview action pass. Actual AJAX cleanup click remains open. |
| 6 | BLOCKED | Shared message-container icons use message text colour; coloured containers still distinguish notice/warning/error/success. Isolated computed-style test passes. Final visual icon/colour check with actual system icon assets remains open. |
| 7 | BLOCKED | Remote debugging remains visible to superusers when unavailable, with Environment prerequisite hint. Remote watch/ping actions remain disabled when unavailable. Navigation test and PHP syntax pass; actual relay-configured installation check remains open. |
| 8 | BLOCKED | Floating messages dismiss upwards on desktop click and upward touch gesture; links/buttons stay interactive and contextual messages remain untouched. Isolated browser tests pass for click, pointer gesture and contextual isolation. Real touch gesture and final overlay appearance remain open. |

Regression tests passed: tools-navigation.php, maintenance-view.php, maintenance-actions.php, auth-branding.php, gui-feedback-ui.mjs, block1-followup-ui.mjs. JavaScript syntax and changed PHP syntax checked. No cleanup deletion, account changes, real mail or backup operations were performed in this follow-up.
