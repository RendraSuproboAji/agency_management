// Konfirmasi sebelum submit form yang merusak data.
document.addEventListener('submit', (event) => {
    const message = event.target.dataset.confirm;

    if (message && !window.confirm(message)) {
        event.preventDefault();
    }
});

// Buka/tutup sidebar di layar kecil.
document.querySelector('[data-drawer-toggle]')?.addEventListener('click', () => {
    document.querySelector('[data-drawer]')?.classList.toggle('open');
});
