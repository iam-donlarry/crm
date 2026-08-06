/**
 * Global CRM Table Pagination Engine
 * 25 Rows per page default with live search & responsive navigation
 */
function initTablePagination(tableId, paginationContainerId, searchInputId, pageSize = 25) {
    const table = document.getElementById(tableId);
    if (!table) return;

    const tbody = table.querySelector('tbody');
    if (!tbody) return;

    const allRows = Array.from(tbody.querySelectorAll('tr')).filter(r => !r.querySelector('td[colspan]'));
    const paginationContainer = document.getElementById(paginationContainerId);
    const searchInput = document.getElementById(searchInputId);

    let currentPage = 1;
    let filteredRows = [...allRows];

    function renderPage(page) {
        currentPage = page;
        const totalRows = filteredRows.length;
        const totalPages = Math.ceil(totalRows / pageSize) || 1;

        if (currentPage > totalPages) currentPage = totalPages;
        if (currentPage < 1) currentPage = 1;

        const startIdx = (currentPage - 1) * pageSize;
        const endIdx = startIdx + pageSize;

        // Hide all rows, show current page rows
        allRows.forEach(row => row.style.display = 'none');
        filteredRows.slice(startIdx, endIdx).forEach(row => row.style.display = '');

        // Render Pagination Controls
        if (!paginationContainer) return;

        const fromNum = totalRows > 0 ? startIdx + 1 : 0;
        const toNum = Math.min(endIdx, totalRows);

        let html = `
            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-center gap-3 w-100">
                <div class="text-muted small" style="font-family: 'Outfit', sans-serif;">
                    Showing <span class="fw-semibold text-dark">${fromNum}</span> to <span class="fw-semibold text-dark">${toNum}</span> of <span class="fw-semibold text-dark">${totalRows}</span> entries
                </div>
                <nav aria-label="Table navigation">
                    <ul class="pagination pagination-sm mb-0 gap-1 align-items-center" style="font-family: 'Outfit', sans-serif;">
                        <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
                            <button type="button" class="btn btn-sm btn-light border px-2.5 rounded-2 d-flex align-items-center gap-1" ${currentPage === 1 ? 'disabled' : ''} onclick="changeTablePage('${tableId}', ${currentPage - 1})">
                                <i data-lucide="chevron-left" class="lucide-icon" style="width:14px; height:14px;"></i> Prev
                            </button>
                        </li>
        `;

        for (let p = 1; p <= totalPages; p++) {
            if (totalPages > 7 && Math.abs(p - currentPage) > 2 && p !== 1 && p !== totalPages) {
                if (p === 2 && currentPage > 4) html += `<li class="page-item disabled"><span class="px-2 text-muted">...</span></li>`;
                if (p === totalPages - 1 && currentPage < totalPages - 3) html += `<li class="page-item disabled"><span class="px-2 text-muted">...</span></li>`;
                continue;
            }
            const activeClass = (p === currentPage) ? 'btn-primary text-white fw-bold shadow-sm' : 'btn-light border text-dark';
            html += `
                <li class="page-item">
                    <button type="button" class="btn btn-sm ${activeClass} px-3 rounded-2" onclick="changeTablePage('${tableId}', ${p})">${p}</button>
                </li>
            `;
        }

        html += `
                        <li class="page-item ${currentPage === totalPages || totalPages === 0 ? 'disabled' : ''}">
                            <button type="button" class="btn btn-sm btn-light border px-2.5 rounded-2 d-flex align-items-center gap-1" ${currentPage === totalPages || totalPages === 0 ? 'disabled' : ''} onclick="changeTablePage('${tableId}', ${currentPage + 1})">
                                Next <i data-lucide="chevron-right" class="lucide-icon" style="width:14px; height:14px;"></i>
                            </button>
                        </li>
                    </ul>
                </nav>
            </div>
        `;

        paginationContainer.innerHTML = html;
        if (window.lucide) lucide.createIcons();
    }

    function applyFilter() {
        const query = (searchInput ? searchInput.value : '').toLowerCase().trim();
        if (!query) {
            filteredRows = [...allRows];
        } else {
            filteredRows = allRows.filter(r => r.textContent.toLowerCase().includes(query));
        }
        renderPage(1);
    }

    if (searchInput) {
        searchInput.addEventListener('keyup', applyFilter);
        searchInput.addEventListener('change', applyFilter);
        searchInput.addEventListener('input', applyFilter);
    }

    // Attach reference for changeTablePage function
    window[`_paginate_${tableId}`] = renderPage;

    // Initial render
    renderPage(1);
}

function changeTablePage(tableId, page) {
    if (typeof window[`_paginate_${tableId}`] === 'function') {
        window[`_paginate_${tableId}`](page);
    }
}
