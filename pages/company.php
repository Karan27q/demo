<?php
$basePath = dirname(__DIR__);
require_once $basePath . '/config/database.php';

// For now, this is a placeholder for company settings
// You can extend this to store company information in a database table
?>

<div class="dashboard-page">
    <div class="content-card">
        <div class="section-header">
            <h2 class="page-title">
                <i class="fas fa-building"></i> Company Settings
            </h2>
        </div>
        
        <form id="companyForm" onsubmit="updateCompany(event)">
            <div class="form-row">
                <div class="form-group">
                    <label for="companyName">Company Name *</label>
                    <input type="text" id="companyName" name="company_name" required placeholder="Enter company name" value="Lakshmi Finance">
                </div>
                
                <div class="form-group">
                    <label for="companyCode">Company Code</label>
                    <input type="text" id="companyCode" name="company_code" placeholder="Enter company code">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="address">Address</label>
                    <textarea id="address" name="address" rows="3" placeholder="Enter company address"></textarea>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="phone">Phone</label>
                    <input type="text" id="phone" name="phone" placeholder="Enter phone number">
                </div>
                
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" placeholder="Enter email address">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="gstin">GSTIN</label>
                    <input type="text" id="gstin" name="gstin" placeholder="Enter GSTIN number">
                </div>
                
                <div class="form-group">
                    <label for="pan">PAN</label>
                    <input type="text" id="pan" name="pan" placeholder="Enter PAN number">
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save"></i> Save Company Settings
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function updateCompany(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    
    // For now, just show a success message
    // You can implement API call to save company settings
    showSuccessMessage('Company settings saved successfully!');
    
    // Uncomment below when API is ready:
    /*
    fetch(apiUrl('api/company.php'), {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccessMessage('Company settings saved successfully!');
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while saving company settings.');
    });
    */
}
</script>

