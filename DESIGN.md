# QRIS Lab Design System

## 0. Research Log

- Embedded refs: shortlisted Stripe, Revolut, dan Linear → picked taste-skill + Stripe karena aplikasi ini adalah dashboard pembayaran operasional yang membutuhkan hierarki jelas, materi permukaan halus, dan status transaksi yang mudah dipindai.
- Lazyweb: 2 queries, 4 screens viewed (PayPal, Stripe Dashboard, Checkout.com, Gr4vy) → adopted a compact developer dashboard grammar, restrained account summary, and a transaction receipt as the focal object.
- StyleGallery: adopted the split-screen pattern with document-owned scrolling and a responsive auto-fit grid.
- Interaction catalog: reviewed button and switch patterns from beui.dev → adopted immediate pressed feedback, a native checkbox-backed switch, and reduced-motion fallbacks.
- Imagen drafts: skipped — no image-generation backend was exposed in this session.
- UI/UX database: validated persistent labels, inline errors, loading feedback, ARIA live status, semantic state colors, and 44px touch targets.

## 1. Atmosphere & Identity

QRIS Lab feels like a calm payment operations desk: precise enough for a developer, reassuring enough for a merchant, and never visually noisy. Its signature is the “live receipt”: a softly elevated QR panel whose amount, expiry, provider, and payment state update in place, giving the user one stable object to follow from creation through settlement.

## 2. Color

### Palette

| Role | Token | Light | Dark | Usage |
|---|---|---|---|---|
| Canvas | --canvas | #F4F7FC | #080D1A | Page background |
| Canvas glow | --canvas-glow | #E8E7FF | #171837 | Atmospheric light |
| Surface/primary | --surface-primary | #FFFFFF | #10182A | Cards and controls |
| Surface/secondary | --surface-secondary | #F8FAFD | #151F34 | Grouped fields and receipt wells |
| Surface/elevated | --surface-elevated | #FFFFFF | #1C2942 | Elevated receipt and menus |
| Text/primary | --text-primary | #071B33 | #F5F7FC | Headlines and body |
| Text/secondary | --text-secondary | #50647B | #A9B8CF | Supporting copy |
| Text/tertiary | --text-tertiary | #71839A | #8190A8 | Metadata and placeholders |
| Border/default | --border-default | #DCE5F0 | #2B3952 | Controls and card edges |
| Border/subtle | --border-subtle | #EAF0F6 | #202D45 | Dividers |
| Accent/primary | --accent-primary | #5B47F6 | #8B7CFF | Primary actions and focus |
| Accent/hover | --accent-hover | #4634D2 | #A397FF | Hovered primary actions |
| Accent/soft | --accent-soft | #ECE9FF | #28264F | Selected washes and ambient tint |
| Accent/ink | --accent-ink | #3F2CC8 | #C5BEFF | Accent text |
| Success | --status-success | #0F8A4B | #55D790 | Paid state |
| Success soft | --status-success-soft | #E8F7EF | #123C2A | Paid surface |
| Warning | --status-warning | #A46100 | #FFBF5B | Pending/expiry state |
| Warning soft | --status-warning-soft | #FFF5E2 | #3A2A12 | Pending surface |
| Error | --status-error | #C33445 | #FF7887 | Errors |
| Error soft | --status-error-soft | #FDECEF | #421C25 | Error surface |
| Info | --status-info | #2367D1 | #70A7FF | Informational state |
| Info soft | --status-info-soft | #EAF2FF | #182E50 | Informational surface |
| On accent | --on-accent | #FFFFFF | #0A0D18 | Text on accent controls |
| Backdrop | --backdrop | rgba(7, 27, 51, 0.52) | rgba(0, 0, 0, 0.66) | Overlays if introduced |

### Rules

- Accent is reserved for the main generation action, focus rings, active toggles, and quiet emphasis.
- Payment states always combine color with an icon and explicit text.
- No decorative side stripes, accent card outlines, or rainbow gradients.

## 3. Typography

### Scale

| Level | Size | Weight | Line Height | Tracking | Usage |
|---|---|---|---|---|---|
| Display | clamp(2rem, 5vw, 3.5rem) | 720 | 1.05 | -0.04em | Intro title |
| H1 | clamp(1.75rem, 3vw, 2.5rem) | 700 | 1.12 | -0.03em | Main screen title |
| H2 | 1.375rem | 680 | 1.25 | -0.02em | Panel titles |
| H3 | 1rem | 650 | 1.4 | -0.01em | Group titles |
| Body/lg | 1.0625rem | 450 | 1.65 | 0 | Intro and primary guidance |
| Body | 0.9375rem | 450 | 1.6 | Default desktop text |
| Body/sm | 0.875rem | 450 | 1.5 | Supporting detail |
| Caption | 0.75rem | 600 | 1.4 | Metadata |
| Overline | 0.6875rem | 700 | 1.3 | Uppercase section labels |
| Amount | clamp(1.75rem, 4vw, 2.5rem) | 740 | 1.1 | Payment amount |

### Font Stack

- Primary: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif.
- Mono: "SFMono-Regular", Consolas, "Liberation Mono", monospace.

### Rules

- Financial figures use tabular numerals.
- On phone, body copy is at least 17px and secondary copy at least 15px.
- Labels stay visible; placeholders only demonstrate the expected format.

## 4. Spacing & Layout

### Base Unit

All spacing derives from a 4px base.

| Token | Value | Usage |
|---|---|---|
| --space-1 | 0.25rem | Icon detail |
| --space-2 | 0.5rem | Tight inline groups |
| --space-3 | 0.75rem | Compact field groups |
| --space-4 | 1rem | Standard gap and control padding |
| --space-5 | 1.25rem | Comfortable grouping |
| --space-6 | 1.5rem | Card padding |
| --space-8 | 2rem | Panel groups |
| --space-10 | 2.5rem | Section rhythm |
| --space-12 | 3rem | Major sections |
| --space-16 | 4rem | Intro spacing |

### Grid

- Maximum content width: 1180px.
- Main app grid: repeat(auto-fit, minmax(min(20rem, 100%), 1fr)).
- Desktop columns favor configuration at 0.92fr and receipt at 1.08fr.
- Page gutters: clamp(1rem, 4vw, 2rem).
- Breakpoints: compact below 720px; wide at 960px.
- The document owns vertical scrolling; no panel creates its own vertical scroll area.

### Rules

- Related fields sit in stacks; actions use clusters that wrap safely.
- The configuration panel precedes the receipt in reading order and on small screens.
- Long IDs and QR payloads wrap or scroll horizontally only inside dedicated code wells.

## 5. Components

### Top Bar

- **Structure**: header > brand cluster + connection badge.
- **Variants**: disconnected, connected.
- **Spacing**: --space-3, --space-4.
- **States**: sticky resting state; badge updates after token actions.
- **Accessibility**: landmark header; status text is explicit.
- **Motion**: badge cross-fades using standard timing; reduced motion keeps a simple opacity change.
- **Layout**: cluster; document is the scroll owner.

### Panel

- **Structure**: section > panel header + body groups.
- **Variants**: configuration, receipt, showcase.
- **Spacing**: --space-5 through --space-8.
- **States**: default, loading, error, success.
- **Accessibility**: semantic heading relationships and visible focus within controls.
- **Motion**: subtle opacity/translate entry only on first render; cross-fade under reduced motion.
- **Layout**: stack; never owns page scrolling.

### Field

- **Structure**: label + optional hint + input/select + inline message.
- **Variants**: text, password with reveal, currency, select.
- **Spacing**: --space-2 and --space-3.
- **States**: default, hover, focus, disabled, invalid, loading.
- **Accessibility**: persistent label, described-by hint/error, correct inputmode, 44px minimum control height.
- **Motion**: focus and validation colors transition with micro timing.
- **Layout**: stack.

### Button

- **Structure**: button > optional icon + label + optional activity indicator.
- **Variants**: primary, secondary, quiet, danger, icon-only.
- **Spacing**: --space-3 and --space-4.
- **States**: default, hover, pressed, focus-visible, disabled, loading.
- **Accessibility**: outcome verb labels, 44px minimum target, aria-label for icon-only buttons.
- **Motion**: same-frame pressed translation; opacity-only loading indicator; reduced motion removes translation.
- **Layout**: cluster.

### Switch Row

- **Structure**: checkbox-backed switch + label + explanatory text.
- **Variants**: off, on, disabled.
- **Spacing**: --space-3 and --space-4.
- **States**: hover, pressed, focus-visible, checked, disabled.
- **Accessibility**: native checkbox semantics; label states what turning it on does; track position and check glyph distinguish state beyond color.
- **Motion**: thumb transform uses standard timing; reduced motion shortens to a cross-fade-like snap.
- **Layout**: cluster with flexible text.

### Status Chip

- **Structure**: icon + status label.
- **Variants**: neutral, pending, paid, expired, error, checking.
- **Spacing**: --space-2 and --space-3.
- **States**: live text updates; checking uses a bounded spinner.
- **Accessibility**: status is announced through the receipt live region; color is never the only cue.
- **Motion**: status replacement cross-fades.
- **Layout**: cluster.

### Receipt

- **Structure**: status header + amount + QR well + metadata rows + actions + details disclosure.
- **Variants**: empty, loading, pending, paid, expired, error.
- **Spacing**: --space-4 through --space-8.
- **States**: all variants retain stable geometry where practical; errors appear next to the receipt action.
- **Accessibility**: named live region, QR image alt text, local date formatting, selectable IDs, keyboard-operable disclosure.
- **Motion**: QR and result content cross-fade; paid state uses one short confirmation lift with a reduced-motion fade.
- **Layout**: stack; document owns scrolling.

### Inline Notice

- **Structure**: icon + title/copy.
- **Variants**: info, warning, error, success.
- **Spacing**: --space-3 and --space-4.
- **States**: enters on async outcomes and remains until superseded.
- **Accessibility**: role=status for routine changes, role=alert for errors.
- **Motion**: opacity transition only.
- **Layout**: cluster.

## 6. Motion & Interaction

### Timing

| Type | Duration | Easing | Usage |
|---|---|---|---|
| Micro | 120ms | ease-out | Press, focus, hover |
| Standard | 220ms | ease-in-out | Switch, status replacement |
| Emphasis | 480ms | cubic-bezier(0.16, 1, 0.3, 1) | Initial panel arrival, paid confirmation |

### Feedback Rules

- Buttons acknowledge presses in the same frame and disable immediately during their own request.
- Loading copy appears immediately; a visible spinner is delayed until a request lasts longer than one second.
- Polling uses recursive setTimeout after each completed request, so checks never overlap.
- Turning polling off, paying, expiring, resetting, or disconnecting cancels the next timer.
- Only transform, opacity, and filter animate. Reduced motion replaces positional motion with a short fade.

## 7. Depth & Surface

### Strategy

Mixed tonal shift, neutral hairline borders, and layered blue-violet shadows.

| Level | Value | Usage |
|---|---|---|
| Subtle | 0 1px 2px rgba(7, 27, 51, 0.04) | Controls and compact surfaces |
| Default | 0 12px 34px rgba(51, 58, 112, 0.10), 0 2px 8px rgba(7, 27, 51, 0.05) | Main panels |
| Prominent | 0 30px 80px rgba(70, 52, 210, 0.15), 0 8px 24px rgba(7, 27, 51, 0.08) | Live receipt focal object |

### Radius

| Token | Value | Usage |
|---|---|---|
| --radius-sm | 0.625rem | Inputs and chips |
| --radius-md | 0.875rem | Notices and grouped wells |
| --radius-lg | 1.25rem | Main panels |
| --radius-xl | 1.75rem | Receipt focal surface |
| --radius-full | 9999px | Pills and switch tracks |

### Rules

- Inner radii shrink with their inset to maintain concentric corners.
- The receipt receives the strongest shadow and scale; other cards stay quiet.
- Sticky surfaces use opaque or blurred backgrounds that preserve contrast.

## 8. Accessibility Constraints & Accepted Debt

### Constraints

- WCAG 2.2 AA: 4.5:1 body contrast, 3:1 large text and control edges.
- Visible focus on every interactive element; complete keyboard access in reading order.
- Minimum touch target 44×44px with sufficient separation.
- System light/dark mode and prefers-reduced-motion are respected.
- No state relies on color alone. Async and payment status use ARIA live regions.
- Browser zoom to 200% remains usable without clipped primary content.

### Accepted Debt

| Item | Location | Why accepted | Owner / Exit |
|---|---|---|---|
| No real paid transaction can be exercised without a user-owned API token and actual payment | End-to-end payment state | External credential and financial action are outside the demo build | Exit when the owner supplies a sandbox token or performs a controlled payment |
