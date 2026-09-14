# Accessibility

The Vue and Svelte chat clients follow the same keyboard and assistive-technology conventions. The simpler XML, JSON, and HTML clients remain covered by the shared automated axe scan.

## Keyboard use

- Use the **Skip to messages** link at the start of the page to move directly to the chat log.
- Press **Enter** in the message field to send a message.
- Press **Shift+Enter** to insert a new line.
- Press **Escape** to clear the current draft and keep focus in the message field.
- Use **Tab** and **Shift+Tab** to move through installation, notification, formatting, send, clear, and logout controls.

All interactive controls display a high-contrast focus outline when reached from the keyboard. When the operating system requests reduced motion, non-essential transitions and spinner animations are reduced to a near-instant duration.

## Screen readers

Each modern chat page exposes one named `main` landmark. The message history uses a polite ARIA log so new messages can be announced without interrupting the user, and each message is a labelled article with a machine-readable timestamp. Loading, connectivity, queue, delivery failure, and form validation updates use status or alert semantics as appropriate.

The message field is associated with its formatting and keyboard help. Formatting buttons are grouped in a named toolbar and have explicit accessible names.

## Verification

Run the frontend static checks and production build before the browser suite:

```shell
corepack pnpm@10.34.5 check
corepack pnpm@10.34.5 build
corepack pnpm@10.34.5 e2e
```

Playwright runs axe on every chat implementation and fails for serious or critical violations. The Vue and Svelte scenarios additionally verify landmarks, the live message log, skip navigation, formatting control names, draft keyboard behavior, and reduced-motion handling.

For a manual audit, test both `/chat/vue` and `/chat/svelte` using only the keyboard and with VoiceOver, NVDA, or another screen reader. Run the current Lighthouse accessibility audit in Chrome DevTools and keep each page at 95 or above; Lighthouse scores supplement, but do not replace, the behavior-focused browser checks.
