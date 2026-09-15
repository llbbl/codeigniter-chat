# Mobile behavior

The Vue (`/chat/vue`) and Svelte (`/chat/svelte`) chat interfaces are designed
for narrow touch devices down to a 320px viewport.

## Viewport and safe areas

Both pages use `width=device-width, initial-scale=1, viewport-fit=cover`. The
viewport remains zoomable; do not add `user-scalable=no` or cap the maximum
scale.

Shared CSS exposes the four `env(safe-area-inset-*)` values as
`--chat-safe-area-*` custom properties. On narrow screens, the header and
content respect the side and top insets, while the sticky message composer adds
the bottom inset to its padding. Browsers without display cutouts resolve these
values to `0px`.

The chat container uses `100dvh` with a `100vh` fallback. The composer sticks to
the bottom of the dynamic viewport, and focusing the message field scrolls the
complete composer into view. This keeps the input and actions reachable when a
mobile browser reduces its visual viewport for the on-screen keyboard.

## Touch and narrow-screen layout

- Visible buttons, form controls, and the logout control have a minimum target
  size of 44 by 44 CSS pixels.
- Buttons and the logout control provide a visible pressed state and use
  `touch-action: manipulation`.
- Header and form actions wrap instead of forcing horizontal scrolling.
- Messages, usernames, Markdown code, and other long unbroken content wrap
  within the message card. Code blocks remain horizontally scrollable as a
  fallback.
- The scrollable message log contains a keyboard-accessible skip link to the
  composer.

## Verification

The shared Playwright chat scenario temporarily uses a 320 by 568 viewport for
both Vue and Svelte. It verifies:

- zoom-safe viewport metadata;
- no horizontal overflow on the document, body, or chat container;
- long username and message wrapping;
- 44 by 44 CSS-pixel minimum touch targets;
- a focused sticky composer that remains inside the dynamic viewport; and
- serious or critical automated accessibility violations via axe-core.

Run the browser suite after building production assets:

```bash
corepack pnpm@10.34.5 build
corepack pnpm@10.34.5 e2e
```

For release checks, run Lighthouse in its mobile mode against both authenticated
pages. Accessibility and best-practices scores must each be at least 95. Record
the tested commit, browser/Lighthouse version, and scores in the release or pull
request notes.
