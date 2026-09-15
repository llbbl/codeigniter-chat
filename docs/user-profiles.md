# User profiles

Authenticated users can personalize how they appear in the Vue and Svelte chat clients. Profile settings are stored in the `users` table and restored each time the user signs in.

## Profile fields

- `display_name`: optional name shown in chat; the username remains the account and message identity.
- `avatar_path`: server-managed relative path for the user's WebP avatar.
- `theme`: `system`, `light`, or `dark`.
- `notification_prefs`: JSON containing the desktop-notification and sound choices.
- `presence`: `online`, `away`, `busy`, or `offline`.
- `last_seen_at`: set when the user's final WebSocket connection closes.

## HTTP endpoints

All profile routes require an authenticated session. Write routes also use the normal CSRF and write-rate filters.

| Method | Route | Purpose |
| --- | --- | --- |
| `GET` | `/profile` | Render the profile entry page. |
| `POST` | `/profile` | Update profile settings. |
| `POST` | `/profile/avatar` | Upload and replace the current user's avatar. |
| `GET` | `/profile/avatar/{userId}` | Return an authenticated user's avatar. |
| `GET` | `/api/v1/profile` | Return the signed-in user's complete profile. |
| `POST` | `/api/v1/profile` | Update the signed-in user's profile as JSON. |
| `POST` | `/api/v1/profile/avatar` | Upload an avatar from the Vue or Svelte client. |
| `GET` | `/api/v1/profiles?usernames=alice,bob` | Return public presentation fields for message authors. |

Public profile responses deliberately omit email, theme, notification preferences, and password data.

## Avatars

Uploads accept JPEG, PNG, and WebP images up to 2 MB and 1024 by 1024 pixels. The server reads the real image contents, center-crops the image, resizes it to 256 by 256 pixels, and saves it as `writable/uploads/avatars/{userId}.webp`. User-controlled filenames are never used for storage or retrieval.

When a user has no avatar, both modern chat clients render initials derived from the display name or username.

The PHP runtime must provide the GD and fileinfo extensions. They are declared as Composer platform requirements so deployment checks fail clearly when either extension is missing.

## Presence lifecycle

The WebSocket server publishes a complete `presence_state` message whenever an authenticated user connects, changes status, or fully disconnects. A user with several tabs remains present until the final connection closes. At that point the server stores `offline` and updates `last_seen_at`.

Clients may change the active status with:

```json
{
  "type": "presence_update",
  "presence": "away"
}
```

The server derives the user ID from the authenticated connection and ignores invalid states. Presence messages also contain the display name and avatar URL needed by the Vue and Svelte clients.

## Themes and sessions

The selected theme is stored in the database and copied into the authenticated session at login. Server-rendered Vue and Svelte entry pages set the initial `data-theme` value before JavaScript loads, and the profile panels apply changes immediately. Logging out clears the session copy; logging in reads the saved database value again.
