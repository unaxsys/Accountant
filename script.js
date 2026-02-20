const yearEl = document.getElementById('year');
const quoteDialog = document.getElementById('quote-dialog');
const openQuoteButtons = document.querySelectorAll('.quote-open');
const closeQuoteButton = document.getElementById('close-quote');
const quoteForm = document.getElementById('quote-form');
const formNote = document.getElementById('form-note');

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
  formNote.textContent = 'Благодарим! Получихме запитването и ще се свържем с вас до 24 часа.';
  quoteForm.reset();

  setTimeout(() => {
    quoteDialog.close();
    formNote.textContent = '';
  }, 1800);
});
