<?php
$basePath = dirname(__DIR__);
require_once $basePath . '/config/database.php';

try {
    $pdo = getDBConnection();
} catch (Throwable $e) {
    $pdo = null;
}
?>

<div class="content-card">
    <div class="page-title">Loan Closing</div>

    <div class="form-group">
        <label for="loanId">Loan</label>
        <select id="loanId" name="loan_id" required>
            <option value="">Select Loan</option>
        </select>
        <div id="loanInfo" style="margin-top:8px;color:#555;display:none;">
            <div><strong>Customer:</strong> <span id="infoCustomer">-</span></div>
            <div><strong>Principal:</strong> <span id="infoPrincipal">-</span></div>
            <div><strong>Paid:</strong> <span id="infoPaid">-</span></div>
            <div><strong>Remaining:</strong> <span id="infoRemaining">-</span></div>
        </div>
    </div>

    <form id="loanClosingForm">
        <div class="form-row">
            <div class="form-group">
                <label for="closing_date">Closing Date</label>
                <input type="date" name="closing_date" id="closing_date" required>
            </div>
            <div class="form-group">
                <label for="closing_amount">Closing Amount</label>
                <input type="number" name="closing_amount" id="closing_amount" step="0.01" min="0" required>
                <small id="closeHint" style="color:#666;display:block;margin-top:6px;">Auto-close is allowed only when amount is 0. Check Manual Close to override.</small>
            </div>
        </div>

        <div class="form-group">
            <label style="display:inline-flex;align-items:center;gap:8px;">
                <input type="checkbox" name="manual_close" id="manual_close" value="1">
                <span>Manual Close (early close even if amount > 0)</span>
            </label>
        </div>

        <div class="form-actions">
            <button type="button" class="btn-secondary" onclick="window.history.back()">Cancel</button>
            <button type="submit" class="btn-primary" id="closeBtn" disabled>Close Loan</button>
        </div>
    </form>
 </div>

<script>
(function(){
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

    if (closingDate) {
        closingDate.value = new Date().toISOString().split('T')[0];
    }

    function inr(n){
        if (typeof formatCurrency === 'function') {
            return formatCurrency(n).replace('INR', '₹');
        }
        return '₹' + (parseFloat(n||0).toLocaleString('en-IN', {maximumFractionDigits:2}));
    }

    function updateCloseEnabled(){
        const amt = parseFloat(amountInput.value || '');
        const isZero = !isNaN(amt) && Math.abs(amt) < 0.000001;
        closeBtn.disabled = !(isZero || manualCheckbox.checked);
    }

    function updateInfoFromSelection(){
        const option = loanSelect.options[loanSelect.selectedIndex];
        if (!option || !option.value) {
            infoBox.style.display = 'none';
            amountInput.value = '';
            updateCloseEnabled();
            return;
        }
        const principal = parseFloat(option.getAttribute('data-principal')||'0');
        const paid = parseFloat(option.getAttribute('data-paid')||'0');
        const remaining = Math.max(0, principal - paid);
        infoCustomer.textContent = option.getAttribute('data-customer') || '-';
        infoPrincipal.textContent = inr(principal);
        infoPaid.textContent = inr(paid);
        infoRemaining.textContent = inr(remaining);
        infoBox.style.display = 'block';
        amountInput.value = remaining.toFixed(2);
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
    amountInput.addEventListener('input', updateCloseEnabled);
    manualCheckbox.addEventListener('change', updateCloseEnabled);

    document.getElementById('loanClosingForm').addEventListener('submit', function(e){
        e.preventDefault();
        const loanId = loanSelect.value;
        if (!loanId) { alert('Please select a loan'); return; }
        const fd = new FormData();
        fd.append('loan_id', loanId);
        fd.append('closing_date', closingDate.value);
        fd.append('closing_amount', amountInput.value);
        if (manualCheckbox.checked) fd.append('manual_close', '1');

        fetch('api/loan-closings.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    if (typeof showSuccessMessage === 'function') {
                        showSuccessMessage('Loan closed successfully!');
                    } else {
                        alert('Loan closed successfully!');
                    }
                    window.location.reload();
                } else {
                    alert(res.message || 'Failed to close loan');
                }
            })
            .catch(err => {
                console.error(err);
                alert('An error occurred while closing the loan');
            });
    });

    updateCloseEnabled();
})();
</script>
