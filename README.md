# ShutterSpeed NZ

A simple, production-ready landing page for a modern service business, built as a static website for easy hosting and fast deployment.

## Project overview

This project is designed to:

- present a professional landing page for the business
- explain the service value clearly
- capture visitor interest with a lead form
- remain lightweight and easy to host on static hosting providers like Hostinger
- avoid unnecessary backend complexity in the initial version

## Tech stack

- HTML5
- CSS3
- Vanilla JavaScript
- No database required for the initial launch

## Project structure

- `index.html` — landing page structure
- `styles.css` — layout, visual styling, responsive design
- `script.js` — form handling and small UI behavior
- `docs/implementation_plan.md` — project plan and milestone tracking

## Local development

Open `index.html` directly in a browser, or serve the folder with a local static server:

```bash
python -m http.server 8000
```

Then visit:

```text
http://localhost:8000
```

## Deployment notes

This project is intentionally static and can be deployed to most static hosting platforms. It is ready for a simple Hostinger deployment workflow without introducing a backend unless required later.

## Next steps

- replace placeholder business copy with final messaging
- connect the contact form to an email or CRM workflow
- add Stripe checkout once pricing and subscription flows are approved
- connect hosting, domain, and DNS for production
