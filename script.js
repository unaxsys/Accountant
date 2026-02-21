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
    author: 'Христо Иванов — Управител, търговска компания'
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

function formatIsoDateToBg(isoDate) {
  if (!isoDate || typeof isoDate !== 'string') {
    return '';
  }

  const [year, month, day] = isoDate.split('-');
  if (!year || !month || !day) {
    return isoDate;
  }

  return `${day.padStart(2, '0')}.${month.padStart(2, '0')}.${year}`;
}

window.formatIsoDateToBg = formatIsoDateToBg;

if (yearEl) {
  yearEl.textContent = new Date().getFullYear();
}

openQuoteButtons.forEach((button) => {
  button.addEventListener('click', () => {
    if (quoteDialog && typeof quoteDialog.showModal === 'function') {
      quoteDialog.showModal();
    }
  });
});

if (closeQuoteButton && quoteDialog) {
  closeQuoteButton.addEventListener('click', () => {
    quoteDialog.close();
  });
}

if (quoteForm && formNote) {
  quoteForm.addEventListener('submit', async (event) => {
    event.preventDefault();

    formNote.textContent = 'Изпращане...';

    const sendEndpoint = window.location.pathname.includes('/statii/') ? '../send.php' : 'send.php';

    try {
      const response = await fetch(sendEndpoint, {
        method: 'POST',
        body: new FormData(quoteForm)
      });

      const result = await response.json();
      formNote.textContent = result.message || 'Възникна непредвидена грешка.';

      if (response.ok && result.ok) {
        quoteForm.reset();
        setTimeout(() => {
          if (quoteDialog) {
            quoteDialog.close();
          }
        }, 1800);
      }
    } catch (error) {
      formNote.textContent = 'Няма връзка със сървъра. Моля, опитайте отново.';
    }
  });
}

function openDrawer() {
  if (!mobileDrawer || !drawerBackdrop || !menuToggle) {
    return;
  }

  mobileDrawer.classList.add('open');
  drawerBackdrop.hidden = false;
  menuToggle.setAttribute('aria-expanded', 'true');
  mobileDrawer.setAttribute('aria-hidden', 'false');
}

function closeDrawer() {
  if (!mobileDrawer || !drawerBackdrop || !menuToggle) {
    return;
  }

  mobileDrawer.classList.remove('open');
  drawerBackdrop.hidden = true;
  menuToggle.setAttribute('aria-expanded', 'false');
  mobileDrawer.setAttribute('aria-hidden', 'true');
}

if (menuToggle) {
  menuToggle.addEventListener('click', openDrawer);
}

if (drawerClose) {
  drawerClose.addEventListener('click', closeDrawer);
}

if (drawerBackdrop) {
  drawerBackdrop.addEventListener('click', closeDrawer);
}

drawerLinks.forEach((link) => {
  link.addEventListener('click', closeDrawer);
});

faqItems.forEach((item) => {
  const button = item.querySelector('.faq-question');
  if (!button) {
    return;
  }

  button.addEventListener('click', () => {
    item.classList.toggle('active');
  });
});

function renderTestimonial() {
  if (!testimonialText || !testimonialAuthor) {
    return;
  }

  testimonialText.textContent = testimonials[testimonialIndex].text;
  testimonialAuthor.textContent = testimonials[testimonialIndex].author;
}

if (testimonialPrev) {
  testimonialPrev.addEventListener('click', () => {
    testimonialIndex = (testimonialIndex - 1 + testimonials.length) % testimonials.length;
    renderTestimonial();
  });
}

if (testimonialNext) {
  testimonialNext.addEventListener('click', () => {
    testimonialIndex = (testimonialIndex + 1) % testimonials.length;
    renderTestimonial();
  });
}

if (testimonialText && testimonialAuthor) {
  renderTestimonial();
}
