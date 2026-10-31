# Fluxo working blocks

Follow the user's quality gate for every implementation block:

- Keep the original item numbers and recheck every requirement and subpoint.
- Implementation alone is not completion. Exercise changed interactions in the running app where available, and visually inspect desktop, iPad-sized/touch and narrow layouts as applicable.
- Check browser errors, relevant server logs and shared-component regressions. Use disposable fixtures for destructive tests; preserve real project data and settings.
- Fix failures in the same block and rerun affected tests.
- End with one compact PASS / FAIL / BLOCKED entry for every original number, stating the actual verification. Code inspection is not a visual or functional test.
- State precisely which tests could not run and why. Do not call a block complete while anything is failed or blocked, and do not begin the next block before this gate.
- Reuse existing components. Keep it simple.
