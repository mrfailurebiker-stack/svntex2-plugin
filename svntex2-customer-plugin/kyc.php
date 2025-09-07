<?php
// KYC document upload shortcode
function svntex2_kyc_upload() {
    ob_start();
    ?>
    <link rel="stylesheet" href="<?php echo plugin_dir_url(__FILE__); ?>svntex2-ui.css">
    <div class="svntex2-card">
        <div class="svntex2-title">KYC Document Upload</div>
        <form id="svntex2-kyc-form" method="post" enctype="multipart/form-data">
            <label>Aadhaar Number</label>
            <input class="svntex2-input" type="text" name="aadhaar_number" required>
            <label>PAN Number</label>
            <input class="svntex2-input" type="text" name="pan_number" required>
            <label>Bank Name</label>
            <input class="svntex2-input" type="text" name="bank_name" required>
            <label>Bank Account Number</label>
            <input class="svntex2-input" type="text" name="bank_account" required>
            <label>IFSC Code</label>
            <input class="svntex2-input" type="text" name="ifsc_code" required>
            <label>UPI ID (optional)</label>
            <input class="svntex2-input" type="text" name="upi_id">
            <label>Aadhaar Front</label>
            <input class="svntex2-input" type="file" name="aadhaar_front" accept=".jpg,.jpeg,.png,.pdf" required>
            <label>Aadhaar Back</label>
            <input class="svntex2-input" type="file" name="aadhaar_back" accept=".jpg,.jpeg,.png,.pdf" required>
            <label>PAN Card</label>
            <input class="svntex2-input" type="file" name="pan_card" accept=".jpg,.jpeg,.png,.pdf" required>
            <label>Bank Passbook</label>
            <input class="svntex2-input" type="file" name="bank_passbook" accept=".jpg,.jpeg,.png,.pdf" required>
            <button class="svntex2-btn" type="submit">Upload Documents</button>
        </form>
        <div class="svntex2-status" id="kyc-upload-result"></div>
        <div class="kyc-status-display">KYC Status: <strong>Pending</strong></div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('svntex2_kyc', 'svntex2_kyc_upload');
