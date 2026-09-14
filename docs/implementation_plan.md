Implementation Plan

Project Goal



Upgrade the existing static website into a simple production-ready landing page with:



Professional landing-page content

Lead/contact form

Lead notifications sent to my inbox

Stripe subscription checkout

Customer confirmation/notification emails where appropriate

Hostinger hosting

Hostinger domain and DNS

No database for lead management initially

Keep the frontend as simple as possible

Avoid introducing a backend unless Stripe or another required feature genuinely needs one



The implementation should be completed incrementally. Each phase must be tested before moving to the next phase.



Phase 1 — Project Audit

Objective



Understand the existing project before making changes.



Tasks

Inspect the current project directory.

Identify all existing files.

Inspect index.html.

Inspect existing CSS and JavaScript files.

Determine whether the website is completely static.

Identify the current layout and styling approach.

Check whether there are existing dependencies.

Check the current Hostinger deployment setup.

Check the available Hostinger MCP capabilities.

Rules

Do not change files.

Do not deploy anything.

Do not change DNS.

Do not create external services.

Deliverable



Produce a short report describing:



Current project structure

Current frontend technology

Existing reusable components/styles

Recommended file structure

Changes required for the next phase

Phase 2 — Landing Page Structure and Placeholder Content

Objective



Create the complete website structure before implementing real functionality.



The website should contain placeholder content where the final business copy is not yet available.



Sections

Hero



Include:



Placeholder headline

Placeholder subheadline

Primary CTA

Secondary CTA if useful



Example:



Your Main Value Proposition



A short description explaining the product or service.



Buttons:



Get Started

Subscribe

Benefits



Create 3–6 placeholder benefits.



Example:



Benefit One

Benefit Two

Benefit Three

How It Works



Create a simple 3-step placeholder section.



Example:



Choose a plan

Complete signup

Start using the service

Pricing



Create placeholder subscription plans.



Example:



Basic



$XX / month



Placeholder description.



Pro



$XX / month



Placeholder description.



Business



$XX / month



Placeholder description.



Do not connect these buttons to Stripe yet.



Lead/Contact Section



Create the visual form only.



Fields:



Name

Email

Phone

Message



Add a placeholder submit button.



Do not connect the form to an email service yet.



FAQ



Add placeholder FAQ questions and answers.



Footer



Include placeholder:



Company name

Email

Copyright

Privacy Policy

Terms

Rules



At this stage:



No backend.

No database.

No Stripe.

No email service.

No API keys.

No external secrets.



Focus entirely on the frontend.



Phase 3 — Frontend Polish

Objective



Make the placeholder website look production-quality before adding functionality.



Tasks

Improve typography.

Improve spacing.

Establish a consistent color system.

Improve buttons and CTA states.

Add responsive mobile layout.

Test tablet layout.

Test desktop layout.

Add hover/focus states.

Add accessible labels.

Add keyboard navigation where appropriate.

Add loading/disabled states where useful.

Optimize images/assets.

Check basic SEO metadata.

SEO



Add:



<title>

Meta description

Open Graph metadata

Favicon

Appropriate heading hierarchy

Semantic HTML

Rules



Do not implement payment or form functionality yet.



Phase 4 — Lead Form UI

Objective



Turn the placeholder contact form into a properly validated frontend form.



Fields

Name — required

Email — required

Phone — optional unless business requirements say otherwise

Message — required

Frontend validation



Implement:



Required-field validation

Email validation

Reasonable length limits

User-friendly error messages

Accessible validation messages

UX



The form should support:



Normal state

Validation error state

Submitting state

Success state

Error state



Example success message:



Thanks! Your message has been received. We'll get back to you soon.



Important



Do not store lead information in a database.



Do not put email-service secrets into frontend JavaScript.



Phase 5 — Connect Lead Form to Email

Objective



Send submitted lead information to my inbox.



Architecture



Use an appropriate form/email service rather than exposing personal email credentials in browser JavaScript.



Flow:



Website



↓



Lead Form



↓



Email/Form Service



↓



My Inbox



Email contents



The notification should contain:



Lead name

Email

Phone

Message

Submission date/time

Website/source if useful



Example subject:



New Website Lead — {Name}



Tasks

Select the simplest suitable service.

Configure the destination email.

Configure spam protection.

Configure allowed origins/domain if supported.

Connect the form.

Test successful submission.

Test invalid submission.

Test service failure.

Test mobile submission.

Security



Never expose:



API secret keys

SMTP passwords

Private API tokens

Other service credentials



in frontend source code.



Phase 6 — Stripe Setup

Objective



Prepare Stripe subscriptions before connecting them to the website.



Tasks



Create the required Stripe products and recurring prices.



Example:



Basic — $XX/month

Pro — $XX/month

Business — $XX/month



The final names/prices should be provided before implementation.



Stripe environment



Start with Stripe test mode.



Do not use production payment credentials during development.



Information to record



For every plan, record:



Product ID

Price ID

Currency

Billing interval

Amount



Do not hardcode secret credentials into the frontend.



Phase 7 — Stripe Checkout

Objective



Allow visitors to subscribe using Stripe Checkout.



Desired flow



Visitor



↓



Selects plan



↓



Stripe Checkout



↓



Completes payment



↓



Stripe confirmation



↓



Returns to website



Requirements

Use Stripe Checkout where appropriate.

Do not build a custom card-payment form unless there is a strong reason.

Do not handle raw card information.

Use Stripe-hosted payment UI where possible.

Clearly show the selected plan and price.

Provide success and cancellation states.

Test cases



Test:



Successful payment

Cancelled checkout

Failed payment

Different subscription plans

Mobile checkout

Returning to the website after checkout



Use Stripe test mode during development.



Phase 8 — Stripe Webhooks

Objective



Reliably receive subscription/payment lifecycle events from Stripe.



A successful redirect alone must NOT be treated as proof that a subscription is active.



Important events



Evaluate the Stripe events required for the business workflow, including events related to:



Checkout completion

Subscription creation

Subscription updates

Subscription cancellation

Payment success

Payment failure

Architecture



Stripe



↓



Webhook endpoint



↓



Verify Stripe signature



↓



Process event



Initial approach



Do not introduce a database unless it is actually required.



If webhook processing requires a server-side component, implement the smallest appropriate server/serverless solution compatible with the hosting environment.



Security

Verify webhook signatures.

Never trust webhook data without verification.

Never expose Stripe secret keys.

Never expose the Stripe webhook signing secret.

Phase 9 — Customer Confirmation Emails

Objective



Provide appropriate confirmation/notification emails.



Lead flow



Visitor submits form



↓



Lead notification



↓



My inbox



Subscription flow



Customer subscribes



↓



Stripe processes subscription



↓



Customer receives appropriate confirmation



Determine whether to use:

Stripe's built-in customer emails

A separate transactional email service

Both



Avoid building a custom email system unless required.



Phase 10 — Production Configuration

Objective



Prepare the application for production.



Environment variables



Identify all required secrets/configuration.



Potential examples:



STRIPE\_SECRET\_KEY=

STRIPE\_WEBHOOK\_SECRET=

STRIPE\_PRICE\_BASIC=

STRIPE\_PRICE\_PRO=

STRIPE\_PRICE\_BUSINESS=

EMAIL\_SERVICE\_API\_KEY=





Only include variables that are actually required by the selected implementation.



Rules

Never commit secrets to Git.

Add secrets to the appropriate Hostinger/server environment.

Create/update .env.example without real secrets.

Ensure .env is ignored by Git.



Example:



.env

.env.local





should not be committed when they contain secrets.



Phase 11 — Hostinger Deployment

Objective



Deploy the completed website to Hostinger.



Before deployment



Verify:



Production build succeeds.

No development secrets are included.

Stripe is configured correctly.

Form is configured correctly.

Production domain is known.

DNS configuration is understood.

HTTPS is enabled.

Error handling works.

Hostinger MCP



Use the Hostinger MCP tools to inspect the available hosting configuration.



Before making infrastructure changes:



Show what will be changed.

Confirm the target hosting/site.

Confirm the target domain.

Confirm whether DNS changes are required.



Do not make destructive changes without explicit approval.



Phase 12 — Domain and DNS

Objective



Ensure the production domain correctly points to the website.



Tasks

Inspect current domain configuration.

Inspect current DNS records.

Identify required records.

Avoid changing unrelated records.

Configure required records only.

Verify DNS propagation.

Verify HTTPS.

Important



Do not delete or replace existing DNS records unless they are known to be unnecessary.



Pay particular attention to:



MX records

TXT records

SPF

DKIM

DMARC

Existing subdomains



These may be required for email services.



Phase 13 — Production Testing

Objective



Test the complete system before announcing the website.



Website

Desktop

Tablet

Mobile

Navigation

Links

CTA buttons

Forms

Accessibility basics

Lead system

Valid submission

Invalid submission

Missing fields

Spam protection

Email delivery

Mobile submission

Stripe

Test subscription

Failed payment

Cancelled checkout

Correct plan

Correct price

Correct currency

Success redirect

Cancel redirect

Webhook delivery

Webhook signature verification

Security



Check for:



Exposed API keys

Exposed Stripe secrets

Client-side secret credentials

Unsafe form handling

Missing HTTPS

Incorrect webhook verification

Phase 14 — Replace Placeholder Content

Objective



Replace all placeholder content with final business content.



Replace:



Company name

Headline

Subheadline

Benefits

Product/service descriptions

Pricing

FAQ

Contact information

Legal links

Privacy policy

Terms

Images

Testimonials if applicable



Search the entire project for:



TODO

PLACEHOLDER

Lorem

XX

YOUR\_

EXAMPLE





No placeholder content should remain in production.



Phase 15 — Final Production Deployment

Objective



Deploy the final approved version.



Process

Run final tests.

Build production version.

Review changed files.

Review environment variables.

Review Stripe configuration.

Review Hostinger configuration.

Review DNS.

Deploy.

Verify production website.

Submit a real lead form.

Verify lead email.

Verify Stripe test/production configuration as appropriate.

Verify webhook processing.

Verify HTTPS.

Verify all critical links.

Guiding Principles

Keep It Simple



Do not introduce:



A database

User authentication

Next.js

A custom backend

A VPS

Complex infrastructure



unless a real requirement makes them necessary.



Security First



Never expose:



Stripe secret keys

Webhook signing secrets

Email service API keys

Hostinger API tokens

Database credentials



in frontend code or Git.



Test Incrementally



Complete and test each phase before beginning the next phase.



No Automatic Infrastructure Changes



Claude Code should inspect and explain infrastructure changes before making them.



Especially require confirmation before:



Changing DNS

Deleting resources

Changing domains

Changing production configuration

Making purchases

Creating paid services

Deploying production changes

Initial Target Architecture



The initial target should remain as lightweight as possible:



&#x20;                   HOSTINGER

&#x20;                      │

&#x20;                      ▼

&#x20;               Static Website

&#x20;                      │

&#x20;            ┌─────────┴─────────┐

&#x20;            │                   │

&#x20;            ▼                   ▼

&#x20;       Lead Form           Subscribe

&#x20;            │                   │

&#x20;            ▼                   ▼

&#x20;     Email/Form Service       Stripe

&#x20;            │                   │

&#x20;            ▼                   ▼

&#x20;        My Inbox          Subscription

&#x20;                                │

&#x20;                                ▼

&#x20;                        Customer confirmation





A database should only be introduced if a future requirement makes it necessary.



A backend/serverless function should only be introduced where it is technically required, particularly for secure Stripe operations or webhook processing.



Success Criteria



The project is complete when:



The website is responsive and production-ready.

The landing page contains final content.

Visitors can submit the lead form.

Lead information arrives in my inbox.

No leads are stored in a database.

Visitors can select a subscription.

Visitors can complete payment through Stripe Checkout.

Stripe subscription events are handled securely where required.

Customer confirmation emails work as intended.

No secrets are exposed in frontend code.

The website works on the production Hostinger domain.

HTTPS works correctly.

DNS is correctly configured.

The complete system has been tested in production.

Implementation Order



Follow this exact order:



Project audit

Landing page structure

Placeholder content

Frontend polish

Lead form UI

Lead email integration

Stripe products/prices

Stripe Checkout

Stripe webhook handling if required

Customer emails

Production configuration

Hostinger deployment

Domain/DNS configuration

Production testing

Replace placeholders

Final production deployment

