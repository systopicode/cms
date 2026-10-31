# Auth / Preview follow-up — 2026-10-30

Original numbers retained. Real browser access was explicitly reauthorized and worked in this run. No cleanup/delete action was invoked.

| Nr. | Status | Verification |
| --- | --- | --- |
| 1 | BLOCKED | Removed empty subheadings and reduced scanner/form spacing. Real code-login camera-permission fallback visually checked: content follows heading without the large gap. Actual camera stream and physical iPad not tested: camera permission/device unavailable to this run. |
| 2 | PASS | Opened Passkey login, invoked authentication, cancelled with Escape. Heading and lead computed left aligned; visually checked the animation-free state. |
| 3 | PASS | Real password login: captured the two-second success card, avatar/name and footer; no check icon. Redirect returned to CMS. Shared template also covered by regression assertion. Successful hardware Passkey ceremony not performed. |
| 4 | PASS | Real AJAX overall and Email-log row previews opened visibly, desktop and 390px viewport; close button tested. Legacy dialog.cms height:0 was overriding modern lightbox CSS; scoped legacy styles away from .cmslightbox. Regression asserts positive visible section height. Preview Runner tests confirm non-mutation. |
| 5 | BLOCKED | Real image editor opened, image loaded and spinner became hidden. Computed spinner has no background/border/shadow and no ::after; SVG mask is loader-2. Reported yellow centre dot could not be reproduced. Local load/error spinner regression passes. Physical iPad / in-flight visual still outstanding. |

Checks: PHP syntax, maintenance-actions.php, maintenance-view.php, block1-followup-ui.mjs, block1-regressions-ui.mjs, git diff --check passed. Local tests are isolated fixtures, not substitutes for real browser observations.

Browser log inspection found existing Pages panelTree empty-libnode / missing-parent messages during page navigation. No new PHP entries in the two project PHP error logs (last modified before this run). This does not establish that every host-level log is clean. Browser viewport restored. No project content, settings, credentials or passkeys changed; routine logout/login only.

Evidence: /private/tmp/fluxo-cleanup-preview-live.jpg and /private/tmp/fluxo-auth-success-live.jpg.

Block is not fully accepted while 1 and 5 remain blocked.
