const yearEl = document.getElementById('year');
const quoteDialog = document.getElementById('quote-dialog');
const openQuoteButtons = document.querySelectorAll('.quote-open');
const closeQuoteButton = document.getElementById('close-quote');
const quoteForm = document.getElementById('quote-form');
const formNote = document.getElementById('form-note');
const menuToggle = document.getElementById('menu-toggle');
const drawerClose = document.getElementById('drawer-close');
const mobileDrawer = document.getElementById('mobile-drawer');
const drawerBackdrop = document.getElementById('drawer-backdrop');
const drawerLinks = document.querySelectorAll('.drawer-nav a');
const faqItems = document.querySelectorAll('.faq-item');
const testimonialText = document.getElementById('testimonial-text');
const testimonialAuthor = document.getElementById('testimonial-author');
const testimonialPrev = document.getElementById('testimonial-prev');
const testimonialNext = document.getElementById('testimonial-next');

const testimonials = [
  {
    text: '"Откакто работим с Магос ЕООД, отчетите ни са винаги навреме, а комуникацията е ясна и бърза."',
    author: '— Управител, търговска компания'
  },
  {
    text: '"Прехвърлихме изцяло ТРЗ и счетоводството към екипа и спестихме часове административна работа месечно."',
    author: '— Собственик, онлайн бизнес'
  },
  {
    text: '"При проверка от институции получихме пълно съдействие и отлично подготвена документация."',
    author: '— Финансов мениджър, производствена фирма'
  }
];

let testimonialIndex = 0;

yearEl.textContent = new Date().getFullYear();

openQuoteButtons.forEach((button) => {
  button.addEventListener('click', () => {
    quoteDialog.showModal();
  });
});

closeQuoteButton.addEventListener('click', () => {
  quoteDialog.close();
});

quoteForm.addEventListener('submit', (event) => {
  event.preventDefault();

  const formData = new FormData(quoteForm);
  const subject = `Запитване за оферта от ${formData.get('name')}`;
  const body = [
    `Име и фамилия: ${formData.get('name')}`,
    `Фирма: ${formData.get('company')}`,
    `Имейл: ${formData.get('email')}`,
    `Телефон: ${formData.get('phone')}`,
    `Услуга: ${formData.get('service')}`,
    '',
    'Описание:',
    `${formData.get('message') || 'Няма добавено описание.'}`
  ].join('\n');

  window.location.href = `mailto:office@magos.bg?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;

  formNote.textContent = 'Отваряме вашия имейл клиент, за да изпратите запитването към office@magos.bg.';
  quoteForm.reset();

  setTimeout(() => {
    quoteDialog.close();
    formNote.textContent = '';
  }, 1800);
});

function openDrawer() {
  mobileDrawer.classList.add('open');
  drawerBackdrop.hidden = false;
  menuToggle.setAttribute('aria-expanded', 'true');
  mobileDrawer.setAttribute('aria-hidden', 'false');
}

function closeDrawer() {
  mobileDrawer.classList.remove('open');
  drawerBackdrop.hidden = true;
  menuToggle.setAttribute('aria-expanded', 'false');
  mobileDrawer.setAttribute('aria-hidden', 'true');
}

menuToggle.addEventListener('click', openDrawer);
drawerClose.addEventListener('click', closeDrawer);
drawerBackdrop.addEventListener('click', closeDrawer);

drawerLinks.forEach((link) => {
  link.addEventListener('click', closeDrawer);
});

faqItems.forEach((item) => {
  const button = item.querySelector('.faq-question');
  button.addEventListener('click', () => {
    item.classList.toggle('active');
  });
});

function renderTestimonial() {
  testimonialText.textContent = testimonials[testimonialIndex].text;
  testimonialAuthor.textContent = testimonials[testimonialIndex].author;
}

testimonialPrev.addEventListener('click', () => {
  testimonialIndex = (testimonialIndex - 1 + testimonials.length) % testimonials.length;
  renderTestimonial();
});

testimonialNext.addEventListener('click', () => {
  testimonialIndex = (testimonialIndex + 1) % testimonials.length;
  renderTestimonial();
});

renderTestimonial();
