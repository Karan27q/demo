document.addEventListener('DOMContentLoaded', function() {
    // Sidebar toggle functionality
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    
    if (menuToggle && sidebar) {
        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
        });
    }
    
    // Navigation functionality
    const navItems = document.querySelectorAll('.nav-item, .sub-item');
    const contentArea = document.getElementById('contentArea');
    
    navItems.forEach(item => {
        item.addEventListener('click', function(e) {
            const raw = this.getAttribute('data-page');
            if (!raw) return; // headers like has-submenu
            const page = routePage(raw);
            if (!page) return;
            e.stopPropagation();
            loadPage(page);
            // Update active states
            navItems.forEach(nav => nav.classList.remove('active'));
            this.classList.add('active');
        });
    });
    
    // Submenu toggle functionality
    const hasSubmenuItems = document.querySelectorAll('.has-submenu');
    
    hasSubmenuItems.forEach(item => {
        item.addEventListener('click', function() {
            const submenu = this.nextElementSibling;
            const arrow = this.querySelector('.arrow');
            
            if (submenu && submenu.classList.contains('sub-menu')) {
                submenu.style.display = submenu.style.display === 'block' ? 'none' : 'block';
                
                if (arrow) {
                    arrow.classList.toggle('fa-chevron-right');
                    arrow.classList.toggle('fa-chevron-down');
                }
            }
        });
    });
    
    // Reverse map: convert page filename back to data-page attribute
    function reverseRoutePage(page) {
        switch (page) {
            case 'reports':
                return 'reports';
            default:
                return page; // dashboard, user-access, company, groups, products, customers, loans, bank-pledge, interest, loan-closing, transactions, expense, reports
        }
    }
    
    // Update browser URL without page reload
    function updateURL(pageKey) {
        const baseUrl = window.location.pathname;
        const newUrl = pageKey === 'dashboard' 
            ? baseUrl 
            : `${baseUrl}?page=${encodeURIComponent(pageKey)}`;
        
        // Update URL without reloading page
        window.history.pushState({ page: pageKey }, '', newUrl);
    }
    
    // Load page content
    function loadPage(page, updateUrl = true) {
        // Build URL with query parameters from current window URL
        // Pass through pagination (as 'p') and search parameters
        const currentUrl = new URL(window.location);
        
        // Build the fetch URL - use relative path from current location
        let fetchUrl = `pages/${page}.php`;
        const params = new URLSearchParams();
        
        // Copy relevant query parameters from current URL to the page URL
        // Use 'p' for pagination to avoid conflict with the main 'page' routing parameter
        const pagenum = currentUrl.searchParams.get('p');
        if (pagenum !== null) {
            params.set('p', pagenum);
        }
        const search = currentUrl.searchParams.get('search');
        if (search !== null) {
            params.set('search', search);
        }
        const status = currentUrl.searchParams.get('status');
        if (status !== null) {
            params.set('status', status);
        }
        
        // Add query string if there are parameters
        if (params.toString()) {
            fetchUrl += '?' + params.toString();
        }
        
        console.log('Loading page:', page, 'from URL:', fetchUrl);
        
        fetch(fetchUrl)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Page not found');
                }
                return response.text();
            })
            .then(html => {
                console.log('Page loaded successfully, length:', html.length);
                contentArea.innerHTML = html;
                
                // Update page title
                document.title = `Lakshmi Finance - ${page.charAt(0).toUpperCase() + page.slice(1)}`;
                
                // Update browser URL if requested
                if (updateUrl) {
                    const pageKey = reverseRoutePage(page);
                    updateURL(pageKey);
                }

                // Page-specific initializers (scripts in fetched HTML won't execute)
                try {
                    if (page === 'loan-closing') {
                        initLoanClosingPage();
                    } else if (page === 'reports') {
                        initReportsPage();
                    } else if (page === 'products') {
                        if (typeof initProductSearch === 'function') {
                            initProductSearch();
                        }
                    } else if (page === 'loans') {
                        if (typeof initLoansPage === 'function') {
                            initLoansPage();
                        }
                    } else if (page === 'interest') {
                        if (typeof initInterestPage === 'function') {
                            initInterestPage();
                        }
                    } else if (page === 'customers') {
                        if (typeof initCustomerSearch === 'function') {
                            initCustomerSearch();
                        }
                    } else if (page === 'jewel-recovery') {
                        if (typeof initJewelRecoveryPage === 'function') {
                            initJewelRecoveryPage();
                        }
                    } else if (page === 'dashboard') {
                        // Initialize dashboard when loaded dynamically
                        // Only initialize chart, data is already loaded via PHP
                        setTimeout(() => {
                            if (typeof initDashboard === 'function') {
                                // Only load chart data, not all stats (to avoid duplicate fetching)
                                initDashboard();
                            } else {
                                // Execute inline scripts from the loaded HTML
                                const scripts = contentArea.querySelectorAll('script');
                                scripts.forEach(script => {
                                    if (script.textContent && !script.dataset.executed) {
                                        script.dataset.executed = 'true';
                                        try {
                                            eval(script.textContent);
                                        } catch (e) {
                                            console.error('Error executing dashboard script:', e);
                                        }
                                    }
                                });
                            }
                        }, 100);
                    }
                    
                    // Execute inline scripts from loaded HTML for all pages
                    setTimeout(() => {
                        const scripts = contentArea.querySelectorAll('script');
                        scripts.forEach(script => {
                            if (script.textContent && !script.dataset.executed) {
                                script.dataset.executed = 'true';
                                try {
                                    eval(script.textContent);
                                } catch (e) {
                                    console.error('Error executing injected script:', e);
                                }
                            }
                        });
                    }, 50);
                } catch (e) {
                    console.error('Page init failed:', e);
                }
            })
            .catch(error => {
                console.error('Error loading page:', error);
                contentArea.innerHTML = `
                    <div class="content-card">
                        <h2>Page Not Found</h2>
                        <p>The requested page "${page}" could not be found.</p>
                    </div>
                `;
            });
    }

    // Expose globally so other scripts can navigate
    window.loadPage = loadPage;

    // Map sidebar entries to actual page filenames
    function routePage(key) {
        switch (key) {
            case 'loan-report':
                return 'reports';
            case 'jewel-recovery':
                return 'jewel-recovery';
            case 'user-access':
                return 'user-access';
            case 'company':
                return 'company';
            case 'bank-pledge':
                return 'bank-pledge';
            default:
                return key; // dashboard, customers, loans, closed-loans, interest, loan-closing, transactions, reports, jewel-recovery, user-access, company, bank-pledge, expense
        }
    }
    
    // Check for initial page from URL parameter or window.initialPage
    function getInitialPage() {
        // First check if window.initialPage was set by PHP
        if (window.initialPage) {
            return window.initialPage;
        }
        
        // Otherwise check URL parameters
        const urlParams = new URLSearchParams(window.location.search);
        const pageParam = urlParams.get('page');
        if (pageParam) {
            return pageParam;
        }
        
        return null;
    }
    
    // Load initial page if specified in URL
    const initialPage = getInitialPage();
    if (initialPage && initialPage !== 'dashboard') {
        const page = routePage(initialPage);
        if (page) {
            loadPage(page, false); // Don't update URL, we're already there
            
            // Update active state for the initial page
            const targetNavItem = document.querySelector(`[data-page="${initialPage}"]`);
            if (targetNavItem) {
                navItems.forEach(nav => nav.classList.remove('active'));
                targetNavItem.classList.add('active');
                
                // If it's a submenu item, expand the parent submenu
                const submenu = targetNavItem.closest('.sub-menu');
                if (submenu) {
                    submenu.style.display = 'block';
                    const parentNavItem = submenu.previousElementSibling;
                    if (parentNavItem && parentNavItem.classList.contains('has-submenu')) {
                        const arrow = parentNavItem.querySelector('.arrow');
                        if (arrow) {
                            arrow.classList.remove('fa-chevron-right');
                            arrow.classList.add('fa-chevron-down');
                        }
                    }
                }
            }
        }
    } else {
        // No page parameter, set initial state for dashboard
        window.history.replaceState({ page: 'dashboard' }, '', window.location.pathname);
    }
    
    // Handle browser back/forward buttons
    window.addEventListener('popstate', function(event) {
        const pageKey = event.state ? event.state.page : null;
        if (pageKey) {
            const page = routePage(pageKey);
            if (page) {
                loadPage(page, false); // Don't update URL, we're already there
                
                // Update active state
                const targetNavItem = document.querySelector(`[data-page="${pageKey}"]`);
                if (targetNavItem) {
                    navItems.forEach(nav => nav.classList.remove('active'));
                    targetNavItem.classList.add('active');
                    
                    // If it's a submenu item, expand the parent submenu
                    const submenu = targetNavItem.closest('.sub-menu');
                    if (submenu) {
                        submenu.style.display = 'block';
                        const parentNavItem = submenu.previousElementSibling;
                        if (parentNavItem && parentNavItem.classList.contains('has-submenu')) {
                            const arrow = parentNavItem.querySelector('.arrow');
                            if (arrow) {
                                arrow.classList.remove('fa-chevron-right');
                                arrow.classList.add('fa-chevron-down');
                            }
                        }
                    }
                }
            }
        } else {
            // No state, load dashboard
            loadPage('dashboard', false);
            const dashboardNav = document.querySelector('[data-page="dashboard"]');
            if (dashboardNav) {
                navItems.forEach(nav => nav.classList.remove('active'));
                dashboardNav.classList.add('active');
            }
        }
    });
    
    // Initialize tooltips and other UI elements
    initializeUI();
});

// Build API URL that works when current path is under /pages/ or root
function apiUrl(path) {
    try {
        const p = window.location && window.location.pathname || '';
        const underPages = p.indexOf('/pages/') !== -1;
        return (underPages ? '../' : '') + path;
    } catch (e) {
        return path;
    }
}

function initializeUI() {
    // Add tooltips to action buttons
    const actionButtons = document.querySelectorAll('.action-btn');
    actionButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.stopPropagation();
            // Add dropdown menu or action handling here
        });
    });
    
    // Add search functionality
    const searchInputs = document.querySelectorAll('.search-box input');
    searchInputs.forEach(input => {
        input.addEventListener('input', function() {
            // Add search functionality here
            console.log('Searching for:', this.value);
        });
    });
}

// Global functions for use across pages
function showModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'block';
    }
}

function hideModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
    }
}

function confirmDelete(message, callback) {
    if (confirm(message || 'Are you sure you want to delete this item?')) {
        if (typeof callback === 'function') {
            callback();
        }
    }
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR'
    }).format(amount);
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-GB');
}

// Close modals when clicking outside
window.addEventListener('click', function(event) {
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });
});

// Close modals when pressing Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            if (modal.style.display === 'block') {
                modal.style.display = 'none';
            }
        });
    }
});

// Customer-specific functions
function showAddCustomerModal() {
    // Fetch next customer number
    fetch(apiUrl('api/customers.php?action=get_next_number'))
        .then(response => response.json())
        .then(data => {
            if (data.success && data.customer_no) {
                const customerNoField = document.getElementById('customerNo');
                if (customerNoField) {
                    customerNoField.value = data.customer_no;
                }
            }
        })
        .catch(error => {
            console.error('Error fetching next customer number:', error);
        });
    
    showModal('addCustomerModal');
}

function addCustomer(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    
    fetch(apiUrl('api/customers.php'), {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        console.log('API Response:', data); // Debug log
        
        if (data.success) {
            hideModal('addCustomerModal');
            event.target.reset();
            
            // Update customer dropdowns if they exist
            updateCustomerDropdowns();
            
            // Show success message
            showSuccessMessage('Customer added successfully!');
            
            // Redirect to loans page
            window.location.href = 'http://localhost/demo/dashboard.php?page=loans';
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while adding the customer.');
    });
}

function updateCustomerDropdowns() {
    // Fetch updated customer list
    fetch('api/customers.php?action=get_customers')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.customers) {
                // Update all customer dropdowns on the page
                const customerDropdowns = document.querySelectorAll('select[name="customer_id"]');
                customerDropdowns.forEach(dropdown => {
                    // Store current selection
                    const currentValue = dropdown.value;
                    
                    // Clear existing options except the first one
                    while (dropdown.children.length > 1) {
                        dropdown.removeChild(dropdown.lastChild);
                    }
                    
                    // Add new customer options
                    data.customers.forEach(customer => {
                        const option = document.createElement('option');
                        option.value = customer.id;
                        option.textContent = `${customer.customer_no} - ${customer.name}`;
                        dropdown.appendChild(option);
                    });
                    
                    // Restore selection if it was valid
                    if (currentValue) {
                        dropdown.value = currentValue;
                    }
                });
            }
        })
        .catch(error => {
            console.error('Error updating customer dropdowns:', error);
        });
}

function changePage(pageNum) {
    // Get the current search input based on the page
    const searchInputs = ['customerSearch', 'loanSearch', 'transactionSearch', 'productSearch', 'groupSearch', 'closedLoanSearch'];
    let searchValue = '';
    
    for (const inputId of searchInputs) {
        const input = document.getElementById(inputId);
        if (input) {
            searchValue = input.value;
            break;
        }
    }
    
    const url = new URL(window.location);
    const currentPage = url.searchParams.get('page'); // Get routing page (e.g., 'customers')
    
    // Set pagination parameter as 'p' to avoid conflict with routing 'page'
    url.searchParams.set('p', pageNum);
    
    // Preserve the routing page parameter
    if (currentPage) {
        url.searchParams.set('page', currentPage);
    }
    
    if (searchValue) {
        url.searchParams.set('search', searchValue);
    }
    
    // If we're in an AJAX-loaded page, reload via loadPage instead of full reload
    if (currentPage && typeof window.loadPage === 'function') {
        window.history.pushState({ page: currentPage }, '', url.toString());
        window.loadPage(currentPage, false);
    } else {
        window.location.href = url.toString();
    }
}

function showCustomerActions(customerId) {
    // Implement customer actions dropdown
    console.log('Show actions for customer:', customerId);
}

// Loan-specific functions
function showAddLoanModal() {
    showModal('addLoanModal');
    // Refresh customer dropdown when modal opens
    updateCustomerDropdowns();
}

function addLoan(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    
    fetch('api/loans.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            hideModal('addLoanModal');
            event.target.reset();
            
            // Show success message
            showSuccessMessage('Loan added successfully!');
            
            // Update loan dropdowns in interest and loan closing modals if they exist
            updateLoanDropdowns();
            
            // Reload the page and go to first page to show new loan
            const url = new URL(window.location);
            url.searchParams.delete('page'); // Go to first page
            url.searchParams.delete('search'); // Clear search
            window.location.href = url.toString();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while adding the loan.');
    });
}

// Product-specific functions
function showAddProductModal() {
    showModal('addProductModal');
}

function addProduct(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    
    fetch('api/products.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            hideModal('addProductModal');
            event.target.reset();
            
            // Show success message
            showSuccessMessage('Product added successfully!');
            
            // Reload the page and go to first page to show new product
            const url = new URL(window.location);
            url.searchParams.delete('page'); // Go to first page
            url.searchParams.delete('search'); // Clear search
            window.location.href = url.toString();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while adding the product.');
    });
}

// Group-specific functions
function showAddGroupModal() {
    showModal('addGroupModal');
}

function addGroup(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    
    fetch('api/groups.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            hideModal('addGroupModal');
            event.target.reset();
            
            // Show success message
            showSuccessMessage('Group added successfully!');
            
            // Reload the page and go to first page to show new group
            const url = new URL(window.location);
            url.searchParams.delete('page'); // Go to first page
            url.searchParams.delete('search'); // Clear search
            window.location.href = url.toString();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while adding the group.');
    });
}

// Loan-specific functions
function showAddLoanModal() {
    showModal('addLoanModal');
    // Set default date to today
    document.getElementById('loanDate').value = new Date().toISOString().split('T')[0];
    // Reload customers when opening the modal
    loadLoanCustomers();
}

function addLoan(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    
    fetch('api/loans.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            hideModal('addLoanModal');
            event.target.reset();
            // Reload the page to show new loan
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while adding the loan.');
    });
}

function showLoanActions(loanId) {
    // Implement loan actions dropdown
    console.log('Show actions for loan:', loanId);
}

function openLoanPdf(loanId) {
    if (!loanId) return;
    const url = `api/loan-pdf.php?loan_id=${loanId}`;
    window.open(url, '_blank');
}

// Interest-specific functions
function showAddInterestModal() {
    showModal('addInterestModal');
    // Set default date to today
    document.getElementById('interestDate').value = new Date().toISOString().split('T')[0];
    // Reload active loans when opening the modal
    loadActiveLoans();
}

function addInterest(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    
    fetch('api/interest.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            hideModal('addInterestModal');
            event.target.reset();
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while adding the interest record.');
    });
}

function updateInterestLoanDetails() {
    // This function can be used to update loan details when a loan is selected
    const loanSelect = document.getElementById('loanId');
    const selectedOption = loanSelect.options[loanSelect.selectedIndex];
    
    if (selectedOption.value) {
        // You can add logic here to fetch and display loan details if needed
        console.log('Selected loan:', selectedOption.text);
    }
}

function showInterestActions(interestId) {
    // Implement interest actions dropdown
    console.log('Show actions for interest:', interestId);
}

// Transaction-specific functions
function showAddTransactionModal() {
    showModal('addTransactionModal');
    // Set default date to today
    document.getElementById('transactionDate').value = new Date().toISOString().split('T')[0];
}

function addTransaction(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    
    fetch('api/transactions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            hideModal('addTransactionModal');
            event.target.reset();
            
            // Show success message
            showSuccessMessage('Transaction added successfully!');
            
            // Reload the page and go to first page to show new transaction
            const url = new URL(window.location);
            url.searchParams.delete('page'); // Go to first page
            url.searchParams.delete('search'); // Clear search
            window.location.href = url.toString();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while adding the transaction.');
    });
}

function showTransactionActions(transactionId) {
    // Implement transaction actions dropdown
    console.log('Show actions for transaction:', transactionId);
}

// Interest-specific functions
function showAddInterestModal() {
    showModal('addInterestModal');
    // Set default date to today
    const interestDateField = document.getElementById('interestDate');
    if (interestDateField) {
        interestDateField.value = new Date().toISOString().split('T')[0];
    }
    // Load active loans for dropdown
    loadActiveLoans('loanId');
}

function addInterest(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    
    fetch('api/interest.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        console.log('API Response:', data); // Debug log
        
        if (data.success) {
            hideModal('addInterestModal');
            event.target.reset();
            
            // Show success message
            showSuccessMessage('Interest record added successfully!');
            
            // Reload the page and go to first page to show new interest record
            const url = new URL(window.location);
            url.searchParams.delete('page'); // Go to first page
            url.searchParams.delete('search'); // Clear search
            window.location.href = url.toString();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while adding the interest record.');
    });
}

function showInterestActions(interestId) {
    // Implement interest actions dropdown
    console.log('Show actions for interest:', interestId);
}

// Loan Closing-specific functions
function showAddLoanClosingModal() {
    showModal('addLoanClosingModal');
    // Set default date to today
    const closingDateField = document.getElementById('closingDate');
    if (closingDateField) {
        closingDateField.value = new Date().toISOString().split('T')[0];
    }
    // Load active loans for dropdown
    loadActiveLoans('loanId');
}

function updateLoanDetails() {
    const loanSelect = document.getElementById('loanId');
    const principalAmountField = document.getElementById('principalAmount');
    
    if (loanSelect.value) {
        const selectedOption = loanSelect.options[loanSelect.selectedIndex];
        const principalAmount = selectedOption.getAttribute('data-amount');
        if (principalAmountField) {
            principalAmountField.value = '₹' + parseFloat(principalAmount).toLocaleString('en-IN');
        }
    } else {
        if (principalAmountField) {
            principalAmountField.value = '';
        }
    }
}

function updateInterestLoanDetails() {
    const loanSelect = document.getElementById('loanId');
    // This function can be used for future enhancements in the interest modal
    // For example, to show loan details, interest rate, etc.
    if (loanSelect.value) {
        const selectedOption = loanSelect.options[loanSelect.selectedIndex];
        console.log('Selected loan for interest:', selectedOption.textContent);
    }
}

function addLoanClosing(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    
    fetch('api/loan-closings.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        console.log('API Response:', data); // Debug log
        
        if (data.success) {
            hideModal('addLoanClosingModal');
            event.target.reset();
            document.getElementById('principalAmount').value = '';
            
            // Show success message
            showSuccessMessage('Loan closed successfully!');
            
            // Redirect to closed loans page
            console.log('Redirecting to closed-loans page...');
            loadPage('closed-loans');
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while closing the loan.');
    });
}

function showLoanClosingActions(closingId) {
    // Implement loan closing actions dropdown
    console.log('Show actions for loan closing:', closingId);
}

function showClosedLoanActions(loanId) {
    // Implement closed loan actions dropdown
    console.log('Show actions for closed loan:', loanId);
}

// Function to load active loans for dropdowns
function loadActiveLoans(dropdownId) {
    console.log('Loading active loans for dropdown:', dropdownId);
    fetch(apiUrl('api/loans.php?action=get_active_loans'))
        .then(response => response.json())
        .then(data => {
            console.log('Active loans response:', data);
            if (data.success && data.loans) {
                const dropdown = document.getElementById(dropdownId);
                if (dropdown) {
                    // Store current selection
                    const currentValue = dropdown.value;
                    
                    // Clear existing options except the first one
                    while (dropdown.children.length > 1) {
                        dropdown.removeChild(dropdown.lastChild);
                    }
                    
                    // Add new loan options
                    data.loans.forEach(loan => {
                        const option = document.createElement('option');
                        option.value = loan.id;
                        option.textContent = `${loan.loan_no} - ${loan.customer_name}`;
                        if (loan.principal_amount) {
                            option.setAttribute('data-amount', loan.principal_amount);
                        }
                        dropdown.appendChild(option);
                    });
                    
                    // Restore selection if it was valid
                    if (currentValue) {
                        dropdown.value = currentValue;
                    }
                    
                    console.log(`Loaded ${data.loans.length} active loans into dropdown`);
                } else {
                    console.error('Dropdown element not found:', dropdownId);
                }
            } else {
                console.error('Failed to load active loans:', data.message);
            }
        })
        .catch(error => {
            console.error('Error loading active loans:', error);
        });
}

// Function to update all loan dropdowns on the page
function updateLoanDropdowns() {
    // Update loan dropdowns in interest and loan closing modals
    const loanDropdowns = document.querySelectorAll('select[name="loan_id"]');
    loanDropdowns.forEach(dropdown => {
        if (dropdown.id) {
            loadActiveLoans(dropdown.id);
        }
    });
}

// Success message function
function showSuccessMessage(message) {
    // Create success message element
    const successDiv = document.createElement('div');
    successDiv.className = 'success-message';
    successDiv.textContent = message;
    successDiv.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: #4CAF50;
        color: white;
        padding: 15px 20px;
        border-radius: 5px;
        z-index: 10000;
        box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        animation: slideIn 0.3s ease-out;
    `;
    
    const subItems = document.querySelectorAll('.sub-item');

subItems.forEach(item => {
    item.addEventListener('click', () => {
        const page = item.getAttribute('data-page');
        fetch(`pages/${page}.php`)
            .then(response => response.text())
            .then(html => {
                document.getElementById('contentArea').innerHTML = html;
            });
    });
});

    // Add animation styles
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
    `;
    document.head.appendChild(style);
    
    // Add to page
    document.body.appendChild(successDiv);
    
    // Remove after 3 seconds
    setTimeout(() => {
        successDiv.style.animation = 'slideOut 0.3s ease-in';
        setTimeout(() => {
            if (successDiv.parentNode) {
                successDiv.parentNode.removeChild(successDiv);
            }
        }, 300);
    }, 3000);
} 

// Initializer for loan-closing page (binds events, fetches loans)
function initLoanClosingPage() {
    const loanSelect = document.getElementById('loanId');
    const amountInput = document.getElementById('closing_amount');
    const manualCheckbox = document.getElementById('manual_close');
    const closeBtn = document.getElementById('closeBtn');
    const closingDate = document.getElementById('closing_date');

    const infoBox = document.getElementById('loanInfo');
    const infoCustomer = document.getElementById('infoCustomer');
    const infoPrincipal = document.getElementById('infoPrincipal');
    const infoPaid = document.getElementById('infoPaid');
    const infoRemaining = document.getElementById('infoRemaining');

    if (!loanSelect) return; // not on this page

    if (closingDate) {
        closingDate.value = new Date().toISOString().split('T')[0];
    }

    function inr(n){
        if (typeof formatCurrency === 'function') return formatCurrency(n).replace('INR','₹');
        return '₹' + (parseFloat(n||0).toLocaleString('en-IN', {maximumFractionDigits:2}));
    }

    function updateCloseEnabled(){
        const amt = parseFloat(amountInput && amountInput.value || '');
        const isZero = !isNaN(amt) && Math.abs(amt) < 0.000001;
        if (closeBtn) closeBtn.disabled = !(isZero || (manualCheckbox && manualCheckbox.checked));
    }

    function updateInfoFromSelection(){
        const option = loanSelect.options[loanSelect.selectedIndex];
        if (!option || !option.value) {
            if (infoBox) infoBox.style.display = 'none';
            if (amountInput) amountInput.value = '';
            updateCloseEnabled();
            return;
        }
        const principal = parseFloat(option.getAttribute('data-principal')||'0');
        const paid = parseFloat(option.getAttribute('data-paid')||'0');
        const remaining = Math.max(0, principal - paid);
        if (infoCustomer) infoCustomer.textContent = option.getAttribute('data-customer') || '-';
        if (infoPrincipal) infoPrincipal.textContent = inr(principal);
        if (infoPaid) infoPaid.textContent = inr(paid);
        if (infoRemaining) infoRemaining.textContent = inr(remaining);
        if (infoBox) infoBox.style.display = 'block';
        if (amountInput) amountInput.value = remaining.toFixed(2);
        updateCloseEnabled();
    }

    fetch('api/loans.php?action=get_active_loans')
        .then(r => r.json())
        .then(data => {
            if (!data.success || !Array.isArray(data.loans)) return;
            while (loanSelect.children.length > 1) loanSelect.removeChild(loanSelect.lastChild);
            data.loans.forEach(l => {
                const remaining = Math.max(0, parseFloat(l.principal_amount) - parseFloat(l.total_interest_paid||0));
                const opt = document.createElement('option');
                opt.value = l.id;
                opt.textContent = `${l.loan_no} - ${l.customer_name} (Paid: ${inr(l.total_interest_paid||0)}, Remaining: ${inr(remaining)})`;
                opt.setAttribute('data-customer', l.customer_name);
                opt.setAttribute('data-principal', l.principal_amount);
                opt.setAttribute('data-paid', l.total_interest_paid||0);
                loanSelect.appendChild(opt);
            });
        })
        .catch(console.error);

    loanSelect.addEventListener('change', updateInfoFromSelection);
    if (amountInput) amountInput.addEventListener('input', updateCloseEnabled);
    if (manualCheckbox) manualCheckbox.addEventListener('change', updateCloseEnabled);

    const form = document.getElementById('loanClosingForm');
    if (form) {
        form.addEventListener('submit', function(e){
            e.preventDefault();
            const loanId = loanSelect.value;
            if (!loanId) { alert('Please select a loan'); return; }
            const fd = new FormData();
            if (closingDate) fd.append('closing_date', closingDate.value);
            if (amountInput) fd.append('closing_amount', amountInput.value);
            fd.append('loan_id', loanId);
            if (manualCheckbox && manualCheckbox.checked) fd.append('manual_close', '1');
            fetch(apiUrl('api/loan-closings.php'), { method: 'POST', body: fd })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        if (typeof showSuccessMessage === 'function') showSuccessMessage('Loan closed successfully!');
                        // Navigate to closed loans list
                        if (typeof loadPage === 'function') {
                            loadPage('closed-loans');
                        } else {
                            // Fallback if page opened directly
                            window.location.href = apiUrl('pages/closed-loans.php');
                        }
                    } else {
                        alert(res.message || 'Failed to close loan');
                    }
                })
                .catch(err => { console.error(err); alert('An error occurred while closing the loan'); });
        });
    }

    updateCloseEnabled();
}

// Initialize reports page
function initReportsPage() {
    console.log('Initializing reports page...');
    
    // Note: loadPdfCustomers() is handled by reports.php inline script
    // Only load pledge customers if needed
    // loadPledgeCustomers();
}

// Initialize products page
function initProductsPage() {
    console.log('Initializing products page...');
    
    // Load groups for dropdown
    loadGroups();
    
    // Bind search functionality
    const searchInput = document.getElementById('productSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                const search = this.value;
                const url = new URL(window.location);
                if (search) {
                    url.searchParams.set('search', search);
                } else {
                    url.searchParams.delete('search');
                }
                url.searchParams.delete('page');
                window.location.href = url.toString();
            }, 500);
        });
    }
}

// Initialize loans page
function initLoansPage() {
    console.log('Initializing loans page...');
    
    // Load customers for dropdown
    loadLoanCustomers();
    
    // Bind search functionality
    const searchInput = document.getElementById('loanSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                const search = this.value;
                const status = document.getElementById('statusFilter').value;
                const url = new URL(window.location);
                
                if (search) {
                    url.searchParams.set('search', search);
                } else {
                    url.searchParams.delete('search');
                }
                
                if (status && status !== 'active') {
                    url.searchParams.set('status', status);
                }
                
                url.searchParams.delete('page');
                window.location.href = url.toString();
            }, 500);
        });
    }
    
    // Bind status filter functionality (if not already bound via onchange)
    const statusFilter = document.getElementById('statusFilter');
    if (statusFilter && !statusFilter.hasAttribute('data-handler-bound')) {
        statusFilter.setAttribute('data-handler-bound', 'true');
        statusFilter.addEventListener('change', function() {
            const status = this.value;
            const search = document.getElementById('loanSearch')?.value || '';
            const url = new URL(window.location);
            const currentPage = url.searchParams.get('page'); // 'loans' when loaded via dashboard
            
            // Set status parameter
            if (status && status !== 'active') {
                url.searchParams.set('status', status);
            } else {
                url.searchParams.delete('status');
            }
            
            // Set search parameter
            if (search) {
                url.searchParams.set('search', search);
            } else {
                url.searchParams.delete('search');
            }
            
            // Reset to first page when filtering
            url.searchParams.delete('p');
            
            // If loaded via dashboard (has 'page' parameter), use loadPage
            if (typeof window.loadPage === 'function' && currentPage) {
                window.history.pushState({ page: currentPage }, '', url.toString());
                window.loadPage(currentPage, false);
            } else {
                // Direct page access - full reload
                window.location.href = url.toString();
            }
        });
    }
}

// Initialize interest page
function initInterestPage() {
    console.log('Initializing interest page...');
    
    // Load active loans for dropdown
    loadActiveLoans();
    
    // Bind search functionality
    const searchInput = document.getElementById('interestSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                const search = this.value;
                const url = new URL(window.location);
                if (search) {
                    url.searchParams.set('search', search);
                } else {
                    url.searchParams.delete('search');
                }
                url.searchParams.delete('page');
                window.location.href = url.toString();
            }, 500);
        });
    }
}

// Load groups function
async function loadGroups() {
    try {
        console.log('Loading groups...');
        const response = await fetch('api/groups.php');
        const data = await response.json();
        
        if (data.success && data.groups) {
            const groupSelect = document.getElementById('productGroup');
            if (groupSelect) {
                // Clear existing options except the first one
                groupSelect.innerHTML = '<option value="">Select Group</option>';
                
                // Add groups to dropdown
                data.groups.forEach(group => {
                    const option = document.createElement('option');
                    option.value = group.id;
                    option.textContent = group.name;
                    groupSelect.appendChild(option);
                });
                console.log('Groups loaded successfully:', data.groups.length);
            }
        } else {
            console.error('Failed to load groups:', data.message);
        }
    } catch (error) {
        console.error('Error loading groups:', error);
    }
}

// Load loan customers function
async function loadLoanCustomers() {
    try {
        console.log('Loading loan customers...');
        const response = await fetch('api/customers.php');
        const data = await response.json();
        
        if (data.success && data.customers) {
            const customerSelect = document.getElementById('customerId');
            if (customerSelect) {
                // Clear existing options except the first one
                customerSelect.innerHTML = '<option value="">Select Customer</option>';
                
                // Add customers to dropdown
                data.customers.forEach(customer => {
                    const option = document.createElement('option');
                    option.value = customer.id;
                    option.textContent = `${customer.customer_no} - ${customer.name}`;
                    customerSelect.appendChild(option);
                });
                console.log('Customers loaded successfully:', data.customers.length);
            }
        } else {
            console.error('Failed to load customers:', data.message);
        }
    } catch (error) {
        console.error('Error loading customers:', error);
    }
}

// Load active loans function for interest page
async function loadActiveLoans() {
    try {
        console.log('Loading active loans for interest...');
        const response = await fetch('api/loans.php?action=get_active_loans');
        const data = await response.json();
        
        if (data.success && data.loans) {
            const loanSelect = document.getElementById('loanId');
            if (loanSelect) {
                // Clear existing options except the first one
                loanSelect.innerHTML = '<option value="">Select Loan</option>';
                
                // Add loans to dropdown
                data.loans.forEach(loan => {
                    const option = document.createElement('option');
                    option.value = loan.id;
                    option.textContent = `${loan.loan_no} - ${loan.customer_name}`;
                    loanSelect.appendChild(option);
                });
                console.log('Active loans loaded successfully:', data.loans.length);
            }
        } else {
            console.error('Failed to load active loans:', data.message);
        }
    } catch (error) {
        console.error('Error loading active loans:', error);
    }
}

// Load PDF customers function
async function loadPdfCustomers(){
    try {
        console.log('Loading PDF customers...');
        const resp = await fetch('api/customers.php');
        console.log('PDF Response status:', resp.status);
        const data = await resp.json();
        console.log('PDF Customers data:', data);
        const select = document.getElementById('pdfCustomer');
        const loanSelect = document.getElementById('pdfLoan');
        
        if (!select || !loanSelect) {
            console.error('PDF Select elements not found:', { select, loanSelect });
            return;
        }
        
        select.innerHTML = '<option value="">Select Customer</option>';
        loanSelect.innerHTML = '<option value="">Select Loan</option>';
        
        if (data.success && Array.isArray(data.customers)) {
            console.log('PDF Found customers:', data.customers.length);
            data.customers.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.id;
                opt.textContent = `${c.customer_no} - ${c.name}`;
                select.appendChild(opt);
            });
        } else {
            console.error('PDF No customers found or invalid response:', data);
        }
        // Only attach event listener once
        if (!select.dataset.listenerAttached) {
            select.dataset.listenerAttached = 'true';
            select.addEventListener('change', async function(){
                loanSelect.innerHTML = '<option value="">Select Loan</option>';
                const cid = this.value;
                if (!cid) return;
                try {
                    const r = await fetch(`api/loans.php?action=by_customer&customer_id=${cid}`);
                    const d = await r.json();
                    if (d.success && Array.isArray(d.loans)) {
                        // Show ALL loans (no deduplication - each loan has unique id)
                        // Display format: Loan No (Date) - Status - Principal Amount
                        d.loans.forEach(l => {
                            const opt = document.createElement('option');
                            opt.value = l.id; // Use loan ID (unique)
                            opt.setAttribute('data-loan-id', l.id);
                            opt.setAttribute('data-loan-no', l.loan_no || '');
                            opt.setAttribute('data-loan-date', l.loan_date || '');
                            const date = l.loan_date ? new Date(l.loan_date).toLocaleDateString('en-GB') : '';
                            const principal = l.principal_amount ? `₹${parseFloat(l.principal_amount).toLocaleString('en-IN')}` : '';
                            opt.textContent = `${l.loan_no} (${date}) - ${l.status || 'active'} ${principal ? '- ' + principal : ''}`;
                            loanSelect.appendChild(opt);
                        });
                    }
                } catch (e) { console.error(e); }
            });
        }
    } catch (e) {
        console.error('Failed to load customers', e);
    }
}

// Load pledge customers function
async function loadPledgeCustomers(){
    try {
        console.log('Loading pledge customers...');
        const resp = await fetch('api/customers.php');
        console.log('Response status:', resp.status);
        const data = await resp.json();
        console.log('Customers data:', data);
        const select = document.getElementById('pledgeCustomer');
        const loanSelect = document.getElementById('pledgeLoan');
        
        if (!select || !loanSelect) {
            console.error('Select elements not found:', { select, loanSelect });
            return;
        }
        
        select.innerHTML = '<option value="">Select Customer</option>';
        loanSelect.innerHTML = '<option value="">Select Loan</option>';
        
        if (data.success && Array.isArray(data.customers)) {
            console.log('Found customers:', data.customers.length);
            data.customers.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.id;
                opt.textContent = `${c.customer_no} - ${c.name}`;
                select.appendChild(opt);
            });
        } else {
            console.error('No customers found or invalid response:', data);
        }
        select.addEventListener('change', async function(){
            loanSelect.innerHTML = '<option value="">Select Loan</option>';
            const cid = this.value;
            if (!cid) return;
            try {
                const r = await fetch(`api/loans.php?action=by_customer&customer_id=${cid}`);
                const d = await r.json();
                if (d.success && Array.isArray(d.loans)) {
                    // Show ALL loans (no deduplication - each loan has unique id)
                    // Display format: Loan No (Date) - Status - Principal Amount
                    d.loans.forEach(l => {
                        const opt = document.createElement('option');
                        opt.value = l.id; // Use loan ID (unique)
                        opt.setAttribute('data-loan-id', l.id);
                        opt.setAttribute('data-loan-no', l.loan_no || '');
                        opt.setAttribute('data-loan-date', l.loan_date || '');
                        const date = l.loan_date ? new Date(l.loan_date).toLocaleDateString('en-GB') : '';
                        const principal = l.principal_amount ? `₹${parseFloat(l.principal_amount).toLocaleString('en-IN')}` : '';
                        opt.textContent = `${l.loan_no} (${date}) - ${l.status || 'active'} ${principal ? '- ' + principal : ''}`;
                        loanSelect.appendChild(opt);
                    });
                }
            } catch (e) { console.error(e); }
        });
    } catch (e) {
        console.error('Failed to load customers', e);
    }
}

// View loan PDF function
function viewLoanPdf(){
    const loanSel = document.getElementById('pdfLoan');
    const selectedOption = loanSel.options[loanSel.selectedIndex];
    
    if (!selectedOption || !selectedOption.value) {
        alert('Please select a loan');
        return;
    }
    
    // Use loan_id (unique) as primary identifier, loan_no as fallback
    const loanId = selectedOption.getAttribute('data-loan-id') || selectedOption.value;
    const loanNo = selectedOption.getAttribute('data-loan-no') || '';
    
    const form = document.getElementById('fpdfLoanForm');
    if (form) {
        // Set both loan_id and loan_no for compatibility
        const loanIdInput = document.getElementById('fpdfLoanId');
        const loanNoInput = document.getElementById('fpdfLoanNo');
        if (loanIdInput) loanIdInput.value = loanId;
        if (loanNoInput) loanNoInput.value = loanNo;
        form.submit();
    } else {
        console.error('PDF form not found');
    }
}

// View pledge PDF function
function viewPledgePdf(){
    const loanSel = document.getElementById('pledgeLoan');
    const customerSelect = document.getElementById('pledgeCustomer');
    const selectedOption = loanSel.options[loanSel.selectedIndex];
    
    if (!selectedOption || !selectedOption.value) {
        alert('Please select a loan');
        return;
    }
    
    // Use loan_id (unique) as primary identifier, loan_no as fallback
    const loanId = selectedOption.getAttribute('data-loan-id') || selectedOption.value;
    const loanNo = selectedOption.getAttribute('data-loan-no') || '';
    const loanDate = selectedOption.getAttribute('data-loan-date') || '';
    
    // Get customer ID from customer select if available
    const customerId = customerSelect ? customerSelect.value : '';
    
    const form = document.getElementById('fpdfLoanForm');
    if (form) {
        // Set all form values for accurate loan fetching with customer and date
        const loanIdInput = document.getElementById('fpdfLoanId');
        const loanNoInput = document.getElementById('fpdfLoanNo');
        const customerIdInput = document.getElementById('fpdfCustomerId');
        const loanDateInput = document.getElementById('fpdfLoanDate');
        if (loanIdInput) loanIdInput.value = loanId;
        if (loanNoInput) loanNoInput.value = loanNo;
        if (customerIdInput) customerIdInput.value = customerId;
        if (loanDateInput) loanDateInput.value = loanDate;
        form.submit();
    } else {
        console.error('PDF form not found');
    }
}