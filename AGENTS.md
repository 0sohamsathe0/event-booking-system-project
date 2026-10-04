# Event Booking System — Project Instructions

Read this file before making changes in this repository.

## Scope and safety

- Use Core PHP, MySQL, HTML5, CSS3, and vanilla JavaScript only unless the user explicitly approves another dependency.
- Preserve existing routes, authentication, authorization, database access, validation, event/ticket/booking logic, Razorpay integration, form actions, and feature behavior during visual work.
- Make only necessary, scoped changes. Do not inspect or modify folders outside `D:\Event Booking System` unless a required development tool must be invoked.
- Never store plaintext passwords. Continue using PHP password hashing and verification.
- Update `README.md` briefly after completing a development phase or meaningful project feature.

## Persistent visual direction

The interface is a premium editorial event platform: dark, cinematic, confident, minimal, image-aware, and typography-led. It must not look like a generic Bootstrap or SaaS dashboard.

- Use shared CSS tokens. Base surfaces are warm near-black (`#080808`, `#0d0d0d`, `#111111`, `#161616`) with warm white (`#f5f3ef`) and restrained gray text.
- Public/editorial accent is warm orange (`#d9783d`, hover `#e98a4c`) and must be used sparingly.
- Role accents: attendee blue `#3fa7d6`, organizer orange `#d9783d`, platform admin violet `#8b7cf6`.
- Role themes must use CSS variables (`--role-accent`, `--role-accent-soft`) rather than duplicated component styles. Always show the textual role label as well as the color.
- Prefer strong typography, asymmetric editorial composition, generous spacing, fine separators, photography-first event presentation, and small radii (generally 2–8px).
- Avoid excessive rounded cards, shadows, pills, gradients, borders, glassmorphism, animations, and decorative images.
- Do not generate or download decorative images. Use organizer-uploaded posters; use a restrained CSS placeholder when none exists.
- Use system fonts by default for performance. A restrained system serif may be used for editorial supporting copy; never use serif typography in forms or dense dashboard UI.
- Buttons are compact and purposeful. Forms, tables, alerts, modals, navigation, empty states, and dashboard metrics must share the same visual language.
- Dashboards share one shell and component system. Attendee feels personal/discovery-led; organizer operational/creative; admin authoritative and slightly denser.
- Desktop sidebar becomes an accessible mobile drawer. Maintain visible focus states, keyboard support, sufficient contrast, touch-friendly controls, semantic status colors, and `prefers-reduced-motion`.
- Design responsively from the start and verify at approximately 375, 430, 768, 1024, and 1440 pixel widths. Mobile layouts must recompose rather than merely shrink.
- Functionality, usability, responsive behavior, consistency, and accessibility take priority over decoration.

