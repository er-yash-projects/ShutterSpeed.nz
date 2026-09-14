# Shutter & Speed Photography

Production website for Shutter & Speed Photography — real estate photography, video, drone and twilight shoots in Whangarei, NZ. Live at [shutterandspeed.co.nz](https://shutterandspeed.co.nz).

## Project overview

- Single self-contained landing page with an inline booking form (package selection, contact details, payment method)
- Lead notifications and Stripe payments both deliver an email straight to the business inbox
- No database — leads live only in email, not stored anywhere on the server
- Hosted on Hostinger shared hosting (static HTML + a few small PHP endpoints, no build step, no framework)

## Tech stack

- HTML5 + inline CSS/JS (`index.html` is self-contained — no bundler, no build step)
- PHP 8 endpoints for the two things a static site can't do on its own: sending email and talking to Stripe's secret API
- Stripe Checkout for one-time card payments (not a subscription — each package is a single per-shoot payment)
- PHP's built-in `mail()` for notifications — no SMTP credentials or third-party email API needed

## Project structure

- `index.html` — the entire site: markup, styles, and booking-form logic in one file
- `send-booking.php` — emails a lead notification (and a short customer confirmation) for Bank Transfer bookings
- `create-checkout-session.php` — creates a Stripe Checkout Session server-side for Credit/Debit Card bookings and returns its URL
- `stripe-webhook.php` — verifies Stripe's `checkout.session.completed` webhook signature and emails the lead notification once a card payment is confirmed paid (the source of truth — the browser redirect alone is never trusted)
- `stripe-config.php` — **not committed**, holds the Stripe secret key and webhook signing secret; deployed directly to Hostinger outside git (see `stripe-config.example.php` for the template and where to get each value)
- `.htaccess` — blocks direct HTTP access to dotfiles and `stripe-config.php`
- `docs/implementation_plan.md` — the phased build plan this project followed

## Secrets and configuration

There are exactly two secrets, both Stripe-related, both kept out of git:

| Constant | Where it's used | Where to get it |
|---|---|---|
| `STRIPE_SECRET_KEY` | `create-checkout-session.php` | Stripe Dashboard → Developers → API keys → restricted key scoped to Checkout Sessions (Write) + Products/Prices (Read) |
| `STRIPE_WEBHOOK_SECRET` | `stripe-webhook.php` | Stripe Dashboard → Developers → Webhooks → your endpoint's signing secret |

Both live in `stripe-config.php`, which is `.gitignore`d and uploaded to Hostinger directly (not through git). Copy `stripe-config.example.php` to `stripe-config.php` locally if you need to work on the PHP endpoints, and fill in real test-mode values.

No other secrets exist — there's no database, no third-party email API key, and the destination inbox (`g.kant1998@gmail.com`) and Stripe Price IDs are hardcoded in the PHP files since they aren't sensitive.

## Local development

The PHP endpoints need an actual PHP environment to run (they're not testable by opening `index.html` directly). For frontend-only changes, serve the folder with any static server, e.g.:

```bash
npx http-server -p 8000
```

Then visit `http://localhost:8000`. Booking-form submissions will fail against a static server (no PHP) — that's expected; the error handling is designed to degrade gracefully.

## Deployment

Deployed directly to Hostinger shared hosting via file upload (not a git-based deploy). The live document root is `shutterandspeed.co.nz/public_html`. When changing a PHP endpoint or `index.html`, upload the changed file(s) directly — there's no build step.

## Status

All phases of `docs/implementation_plan.md` through Phase 9 (customer confirmation emails) are complete and live-tested, including a real Stripe test-mode payment end-to-end. Remaining phases: production configuration review (in progress), Hostinger/DNS review, production testing, and replacing any remaining placeholder content.
