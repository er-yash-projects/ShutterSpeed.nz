const yearEl = document.getElementById('year');
if (yearEl) {
  yearEl.textContent = new Date().getFullYear();
}

const form = document.getElementById('contact-form');
const status = document.querySelector('.form-status');

if (form) {
  form.addEventListener('submit', (event) => {
    event.preventDefault();

    const formData = new FormData(form);
    const name = (formData.get('name') || '').toString().trim();

    status.textContent = name
      ? `Thanks, ${name}. Your inquiry has been received and we’ll be in touch soon.`
      : 'Thanks. Your inquiry has been received and we’ll be in touch soon.';

    form.reset();
  });
}
