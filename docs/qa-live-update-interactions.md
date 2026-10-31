# Live updates: interaction follow-up

The current QR + 1–13 feedback block remains open. This note covers the update investigation only, particularly items 6, 11 and 13; it is not acceptance of that full block.

- Presence uses an action-only background request. It must not rebuild the page. The explicit project URL avoids depending on the selected dialog route.
- A link dialog previously opened before its own field lost focus. Autofocus inside the dialog then triggered the field blur-save; the save response replaced the newly opened dialog. Explicitly blur first, allow its existing microtask handler to enqueue the save, then use the existing server idle queue before opening the dialog.
- Relay invalidations remain queued while a dialog, editor toolbar, text selection, focused field or shared drag is active. Existing close/blur/selection/DOM events resume delivery. There is no periodic render retry for an active interaction. Own-origin filtering, request ordering and failure retry remain intact.
- An unchanged field value is checked under the existing row lock and does not call assign/touch. Conflict checking still rejects a different stale value.
- A fresh body-rendered status footer replaces a footer parked inside an overlay. Nested overlays share one footer.

Verification on 2026-10-30:

| Scope | Status | Actual verification |
| --- | --- | --- |
| Relay delivery | PASS | Node VM tests: pending channels survive active dialog/input/selection; closing releases batch; concurrent invalidations, retry, in-flight save and project stop/start pass. |
| Link dialog ordering | PASS | Unit test validates deferred send and history=false. Running Dev app: reproduced disappearing Insert Link; after explicit blur + idle queue it remained present across repeated presence cycles. No link inserted and no content deliberately changed. |
| Unchanged field | PASS | PHP field-conflict test: unchanged value with stale baseline does not touch timestamp; different stale writes remain rejected. |
| Shared footer | PASS | Isolated Chromium fixture: new body footer during second native modal leaves one footer in the top modal; dirty state remains, close restoration and existing focus checks pass. |
| Native touch / media dragging | BLOCKED | Not yet exercised in this follow-up; the original feedback items require their separate end-to-end pass. |

No broad CSS, toolbar or crop interaction change is accepted by these checks.

## Full interaction follow-up (2026-10-31)

Implemented the QR and 1–13 follow-up plus link-close, initial MML visibility, presence device labels and background scroll preservation. Native iPad acceptance remains open; simulated touch is not a substitute for the device keyboard, camera or gesture stack.

| Original item | Verification |
| --- | --- |
| QR follow-up | Auth fixture validates rejected preflight without navigation; existing instance-scoped, non-consuming code lookup checks expiry. Native camera scan remains unverified. |
| 1 | Running Dev dashboard: long mouse press enters edit without changing column membership or selecting footer text. Responsive drag/hold fixture passes. |
| 2 | Production media template at 390/1024/1194/1440 px: shared compact controls and slider, adjustment values and transformations pass. |
| 3 | Shared dialog fixture: keyboard focus on Download and Close uses the same rounded outline. |
| 4 | Simulated reduced visual viewport retains file-editor height. Real iPad IME remains unverified. |
| 5 | Production crop implementation: synthetic two-touch movement scales both image layers while retaining crop frame. Native pinch remains unverified. |
| 6 | Running Dev: Insert Link stays open across presence updates. |
| 7 | Running Dev File chooser: square icon tile visually checked (103.44 px each side). |
| 8 | Touch fixture: select word, hide toolbar during selection, reopen on selected text. Native selection handles remain a device check. |
| 9 | Fixture checks toolbar near selection and inputmode=none until Type. Real iPad keyboard behaviour remains unverified. |
| 10 | Central editor-undo asset mirrors redo; icon build integrity check passes. |
| 11 | Nested native-modal fixture: one shared status footer after fresh DOM response, correct restoration on close. |
| 12 | Long tree label fixture: edit bounds remain within tree; row/icon dimensions stable. |
| 13 | Production upload-handler fixture moves media using the current insertItemInEditor drop target; live updates defer during media dragging. |
| N1 Link X | Running Dev: repeated open → close → reopen → close succeeds; no link inserted. |
| N2 MML reload | Fixture: initially selected page does not animate; unselected → selected animates once; subsequent selection and reduced motion pass. |
| N3 Names/devices | Running Dev, two tabs: both display account name and Mac / Chrome device label. Same-person/device combinations covered by PHP presence tests. |
| N4 Scroll | Production background-command fixture captures position immediately before DOM update, preserves intervening user scrolling and restores window/container positions. Live content mutation was not exercised against project data. |

PHP presence/conflict tests, relay delivery/toggle tests, upload policy tests, editor link-order test and modified-file syntax checks pass. Dev browser checks produced no JavaScript errors; the existing cleanup/duplicates parent warning is unrelated. No project content, credentials, uploads or mail were deliberately changed during the Dev checks.

## Seven-item touch follow-up (2026-10-31)

This round supersedes the earlier MML reveal animation: the library now appears without sliding in.

| Original item | Actual verification |
| --- | --- |
| 1 Crop in place | Running Dev media editor: crop stays over the existing image, Cancel has an X icon and cancels correctly; no Apply executed. Production crop fixture preserves passed image geometry and pinch behaviour. |
| 2 MML | Responsive fixture at 390/1194/1440 px checks no reveal animation on initial selection, reload or page changes. |
| 3 Selection/clipboard | Production toolbar fixture exercises Copy, Cut and Paste with a mocked clipboard, selection reopening and a narrow wrapping toolbar. Native iPadOS selection menus cannot be guaranteed suppressible; real-device acceptance remains open. |
| 4 File tiles | Running Dev File chooser visually shows white no-thumbnail tiles with icons. PHP production-template fragment checks filename fallback, escaping and icon output for an unlabelled ZIP. |
| 5 Class field | Reproduced disappearing class input in Dev; after deferring editor blur-save while its class input is active, field remains focused and Escape closes it. Touch fixture preserves the form during scroll and applies a class. Real iPad IME remains unverified. |
| 6 Keyboard after move | Production touch media-move fixture checks inputmode=none and removal of typing mode on source/destination. Range restoration no longer focuses touch editors; explicit Type still does. Real-device keyboard acceptance remains open. |
| 7 Presence self-name | Running Dev shows neutral Another session plus device for the current account. PHP presence and browser presence fixtures pass; other known users retain their names. |

Shared blur-save, media controls, nested footer/focus and responsive regression fixtures pass. Modified PHP/JS syntax and diff whitespace checks pass. Final Dev console check has no errors; inspected PHP log has no new entries. No project content was deliberately changed.

## Crop, presence and messages follow-up (2026-10-31)

| Original item | Actual verification |
| --- | --- |
| 1 | Running Dev: petrol scrim, crop in place, hint/Cancel/Apply at top, Cancel closes without saving. Production pointer fixture confirms stationary click saves through updateMediaCrop and unload guard is released after completion. Shared feedback fixture checks unload guard in file edit mode. Native-device gestures remain unverified. |
| 2 | Reload retires the preceding document token; pagehide sends a keepalive departure. New document tokens prevent a late departure from removing a newly loaded document. PHP tests retain real other tabs; browser fixture checks reload retirement and departure payload. Running Dev: no advisory after reload. If departure cannot reach the server, existing 45-second expiry remains the fallback. |
| 3 | Research: WebKit touch-callout and cancelable contextmenu events provide partial suppression; suppressing touchstart/default selection also disrupts selection and editing. No portable complete suppression is promised. Native selection remains available. |
| 4 | Clipboard submenu removed, including its unused handler. Touch buttons reduced from 2.75rem to 2.5rem. Production touch fixture checks selection/class tools/media movement, absence of Clipboard and narrow toolbar bounds. |
| 5 | Touch pointerenter no longer pauses expiry through synthetic hover. Browser clock fixture checks the 20-second reading period and removal on touch; shared feedback fixture checks desktop click and touch swipe dismissal. Dismissal gently shrinks/fades/blurs, with reduced-motion support. Real iPad timeout/animation acceptance remains open. |

No project content was saved during the Dev inspection. Inspected PHP log has no new errors. The Dev console recorded an existing focusPath ambiguity after closing the media dialog; isolated changed-interaction suites report no JavaScript errors. This round does not claim a fix for that separate focus-restoration diagnostic.

## Crop loading and selection toolbar follow-up (2026-10-31)

| Original item | Status and evidence |
| --- | --- |
| 1 Compact editor concept | Proposal only: combine format selection and actions in one compact row; show the adjustment slider on demand. No new layout implemented without agreement. |
| 2 Crop loading | Production crop fixture delays the save response, checks the shared spinner/busy state until preview load, completion cleanup and unload guard release. The image is blurred using the existing theme variables; toolbar actions are disabled while saving. Error/load timeout paths release the overlay and report through standard message markup. No real project crop was saved. |
| 3 Sporadic conflict dialog | BLOCKED: original intermittent idle/two-browser case is not reproduced. A confirmed defensive gap is fixed: a delayed response for a field no longer present cannot open a dialog on a different page. Conflict browser fixture covers this; PHP tests retain real stale-write protection. Dialog wording no longer asserts that a different session caused every mismatch. This is not a claim to resolve all reported false conflicts. |
| 4 Touch selection | Production event fixture uses touch taps: selecting text automatically brings the WYSIWYG toolbar back after selection settles; selected-word actions remain available. Placement prefers below the selection to leave room for the native menu above. Native iPad/Android selection handles and menu stacking remain unverified. |
| 5 Messages | Timeout reduced from 20 to 10 seconds. Browser-clock fixtures check the reading period, smooth disappearance and touch hover not suspending the timer. |

Modified-script syntax and diff whitespace checks pass. No real project content was changed by these tests.

### Crop loading bounds correction

Item 1 of the next follow-up: attach the existing loading layer to the crop frame instead of the outer editor (which has no reliable frame dimensions). Blur only the clipped crop image; raise the crop frame above the translucent drag image during saving. Buttons remain disabled. Production browser fixture changes the crop height to 120px and checks identical frame/overlay bounds, blur inside and no blur outside. Real project crops remain untouched.

### 2026-10-31 — crop frame and compact image editor (items 1, 2)

1. Crop handles now resize the visible frame, with rounded corner brackets and edge grips; dragging inside pans the source image. Fixed pixel formats retain their ratio; free/MP frames can change ratio. On release the frame fits the available image stage with a short reduced-motion-aware transition, so controls never depend on the off-screen source image bounds. Pinch zoom changes the source image, not the frame. Apply/Enter confirms; Escape/Cancel discards. The existing normalized crop endpoint and frame-local loading blur/spinner remain in use.
2. Format selection, adjustment toggles and existing actions share one row. The single active slider uses a second row. Smartphone widths may wrap actions separately. The existing sidebar flag style can collapse metadata to make more image space; the image refits through its existing ResizeObserver. No new library, toolbar action system or persisted layout state was introduced.

Verification:
- `tests/crop-frame-ui.mjs` renders production PHP crop markup and runs production JS with complete system CSS at 1440×900, 1194×700, 1024×600 and 390×844. It exercises frame resizing, fixed ratio, free sizing, pan, synthetic two-touch zoom, reachable handles, normalized save parameters, frame-local loading bounds, preview-load completion, Cancel, Escape and failed save cleanup. Network writes are intercepted in the isolated fixture; no project crop is saved.
- `tests/block2-media-ui.mjs` renders the production image editor at 1440/1194/1024/390 px, checks adjustment selection/retention, rotate/flip/reset, Apply URL, image fit, keyboard-height stability, missing image, toolbar overflow and sidebar collapse/reopen. Screenshots inspected at tablet and narrow sizes. All pass.
- Existing `tests/editor-touch-followup-ui.mjs` media-move/selection regression passes. Crop tests now live in the dedicated fixture instead of duplicated old markup.
- Dev browser: opened media 1515, entered crop, resized frame, moved image and cancelled; checked compact toolbar, slider and sidebar collapse. No project media data changed. No new PHP-log entries; fresh test tab reported no JS errors. The older tab retained the previously known `focusPath` diagnostic.
- Native iPad/Android pinch and OS selection behavior are not verified by these simulated touch tests. Successful real image regeneration/storage was deliberately not exercised against project media.
