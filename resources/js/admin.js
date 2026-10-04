import * as bootstrap from 'bootstrap';

window.bootstrap = bootstrap;

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => new bootstrap.Tooltip(el));

    // Lightweight client-side sortable tables: add data-sortable to a <table>,
    // then data-sort-key="text|number" on each sortable <th>.
    document.querySelectorAll('table[data-sortable]').forEach((table) => {
        const tbody = table.querySelector('tbody');
        if (!tbody) return;

        table.querySelectorAll('th[data-sort-key]').forEach((th, index) => {
            const headerRow = th.parentElement;
            const colIndex = Array.from(headerRow.children).indexOf(th);

            th.style.cursor = 'pointer';
            th.classList.add('user-select-none');
            if (!th.querySelector('.sort-icon')) {
                th.insertAdjacentHTML('beforeend', ' <i class="bi bi-arrow-down-up sort-icon text-muted"></i>');
            }

            th.addEventListener('click', () => {
                const type = th.dataset.sortKey;
                const currentDir = th.dataset.sortDir === 'asc' ? 'desc' : 'asc';

                headerRow.querySelectorAll('th[data-sort-key]').forEach((other) => {
                    other.dataset.sortDir = '';
                    const icon = other.querySelector('.sort-icon');
                    if (icon) icon.className = 'bi bi-arrow-down-up sort-icon text-muted';
                });
                th.dataset.sortDir = currentDir;
                const icon = th.querySelector('.sort-icon');
                if (icon) icon.className = `bi bi-arrow-${currentDir === 'asc' ? 'up' : 'down'} sort-icon text-primary`;

                const rows = Array.from(tbody.querySelectorAll('tr'));
                rows.sort((a, b) => {
                    const cellA = a.children[colIndex]?.dataset.sortValue ?? a.children[colIndex]?.textContent.trim() ?? '';
                    const cellB = b.children[colIndex]?.dataset.sortValue ?? b.children[colIndex]?.textContent.trim() ?? '';

                    let result;
                    if (type === 'number') {
                        result = (parseFloat(cellA) || 0) - (parseFloat(cellB) || 0);
                    } else {
                        result = cellA.localeCompare(cellB);
                    }

                    return currentDir === 'asc' ? result : -result;
                });

                rows.forEach((row) => tbody.appendChild(row));
            });
        });
    });

    // Simple client-side table filter: <input data-table-filter="#tableId">
    document.querySelectorAll('[data-table-filter]').forEach((input) => {
        const table = document.querySelector(input.dataset.tableFilter);
        if (!table) return;

        input.addEventListener('input', () => {
            const term = input.value.trim().toLowerCase();
            table.querySelectorAll('tbody tr').forEach((row) => {
                row.style.display = row.textContent.toLowerCase().includes(term) ? '' : 'none';
            });
        });
    });
});
