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

// Tabel item penawaran: tambah dan hapus baris tanpa memuat ulang halaman.
const itemsTable = document.querySelector('[data-items] tbody');

document.querySelector('[data-add-row]')?.addEventListener('click', () => {
    const last = itemsTable.rows[itemsTable.rows.length - 1];
    const row = last.cloneNode(true);
    const index = itemsTable.rows.length;

    row.querySelectorAll('input').forEach((input) => {
        input.name = input.name.replace(/items\[\d+\]/, `items[${index}]`);
        input.value = input.type === 'number' && input.name.endsWith('[qty]') ? '1' : '';
    });

    itemsTable.appendChild(row);
});

document.querySelector('[data-items]')?.addEventListener('click', (event) => {
    if (event.target.matches('[data-remove-row]') && itemsTable.rows.length > 1) {
        event.target.closest('tr').remove();
    }
});
