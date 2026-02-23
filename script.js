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

const ORIGIN = window.location.origin;
const apiUrl = (path) => `${ORIGIN}${path.startsWith('/') ? '' : '/'}${path}`;
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

    const sendEndpoint = apiUrl('/send.php');

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

async function loadApprovedReviews(limit = 12) {
  const response = await fetch(`${apiUrl('/reviews.php')}?limit=${encodeURIComponent(limit)}`, {
    method: 'GET',
    cache: 'no-store'
  });
  const data = await response.json().catch(() => null);
  if (!response.ok || !data || !data.ok) {
    throw new Error('Failed to load reviews');
  }

  return Array.isArray(data.reviews) ? data.reviews : [];
}

function renderTestimonialsSlider(reviews) {
  const track = document.getElementById('testimonialsTrack');
  if (!track) {
    return;
  }

  if (!reviews.length) {
    track.innerHTML = '<div class="testimonial-card"><p class="muted">Все още няма отзиви.</p></div>';
    return;
  }

  track.innerHTML = reviews
    .map((review, index) => {
      const rating = Math.max(0, Math.min(5, Number(review.rating) || 0));
      const stars = '★'.repeat(rating) + '☆'.repeat(5 - rating);
      return `
        <div class="testimonial-slide" data-index="${index}">
          <div class="testimonial-card">
            <p class="testimonial-text">&ldquo;${escapeHtml(review.message || '')}&rdquo;</p>
            <div class="testimonial-meta">
              <span class="testimonial-name">${escapeHtml(review.name || 'Анонимен клиент')}</span>
              <span class="testimonial-stars" aria-label="Оценка ${rating} от 5">${stars}</span>
            </div>
          </div>
        </div>
      `;
    })
    .join('');

  const slides = Array.from(track.querySelectorAll('.testimonial-slide'));
  const prevBtn = document.getElementById('testPrev');
  const nextBtn = document.getElementById('testNext');
  if (!slides.length) {
    return;
  }

  let index = 0;
  let timer = null;

  const show = (targetIndex) => {
    index = (targetIndex + slides.length) % slides.length;
    slides.forEach((slide, slideIndex) => {
      slide.classList.toggle('is-active', slideIndex === index);
    });
  };

  const startTimer = () => {
    timer = setInterval(() => show(index + 1), 6000);
  };

  const stopTimer = () => {
    if (timer) {
      clearInterval(timer);
      timer = null;
    }
  };

  prevBtn?.addEventListener('click', () => show(index - 1));
  nextBtn?.addEventListener('click', () => show(index + 1));

  const sliderRoot = track.closest('.testimonial-slider') || track;
  sliderRoot.addEventListener('mouseenter', stopTimer);
  sliderRoot.addEventListener('mouseleave', () => {
    if (!timer) {
      startTimer();
    }
  });

  show(0);
  startTimer();
}

function escapeHtml(str) {
  return String(str).replace(/[&<>"']/g, m => ({
    "&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#039;"
  })[m]);
}

async function wireReviewForm() {
  const form = document.getElementById("reviewForm");
  const msg = document.getElementById("reviewMsg");
  if (!form) return;

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    if (msg) msg.textContent = "Изпращане…";

    const fd = new FormData(form);
    try {
      const res = await fetch(apiUrl('/submit-review.php'), { method: "POST", body: fd });
      const json = await res.json();
      if (!json.ok) throw new Error(json.message || "Грешка");

      form.reset();
      if (msg) msg.textContent = json.message || "Готово!";

      // не презареждаме отзивите, защото е pending (чака одобрение)
    } catch (err) {
      if (msg) msg.textContent = (err && err.message) ? err.message : "Грешка при изпращане.";
    }
  });
}

(async function initTestimonials() {
  try {
    const reviews = await loadApprovedReviews(12);
    reviews.sort((a, b) => Number(b.rating || 0) - Number(a.rating || 0));
    renderTestimonialsSlider(reviews.slice(0, 8));
  } catch (error) {
    const track = document.getElementById('testimonialsTrack');
    if (track) {
      track.innerHTML = '<div class="testimonial-card"><p class="muted">Не успяхме да заредим отзивите.</p></div>';
    }
  }
})();

(function initReviewFormToggle() {
  const button = document.getElementById('toggleReviewForm');
  const wrap = document.getElementById('reviewFormWrap');
  if (!button || !wrap) {
    return;
  }

  const setOpen = (open) => {
    wrap.classList.toggle('is-collapsed', !open);
    button.setAttribute('aria-expanded', open ? 'true' : 'false');

    if (open) {
      setTimeout(() => {
        wrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }, 50);
    }
  };

  button.addEventListener('click', () => {
    const open = button.getAttribute('aria-expanded') !== 'true';
    setOpen(open);
  });

  setOpen(false);
})();

wireReviewForm();
