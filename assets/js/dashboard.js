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
        item.addEventListener('click', function() {
            const page = this.getAttribute('data-page');
            if (page) {
                loadPage(page);
                
                // Update active states
                navItems.forEach(nav => nav.classList.remove('active'));
                this.classList.add('active');
            }
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
    
    // Load page content
    function loadPage(page) {
        const url = `pages/${page}.php`;
        console.log('Loading page:', page, 'from URL:', url);
        
        fetch(url)
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

                // Page-specific initializers (scripts in fetched HTML won't execute)
                try {
                    if (page === 'loan-closing') {
                        initLoanClosingPage();
                    }
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
    showModal('addCustomerModal');
}

function addCustomer(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    
    fetch('api/customers.php', {
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
            
            // Reload the page and go to first page to show new customer
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

function changePage(page) {
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
    url.searchParams.set('page', page);
    if (searchValue) {
        url.searchParams.set('search', searchValue);
    }
    window.location.href = url.toString();
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