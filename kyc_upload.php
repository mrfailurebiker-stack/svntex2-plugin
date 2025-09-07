<?php
// kyc_upload.php - Stylish and secure KYC document upload section
session_start();
$customer_id = $_SESSION['customer_id'] ?? 1; // Example
$kyc_status = 'Pending';
$errors = [];
$success = '';
$allowed_types = ['image/jpeg','image/png','application/pdf'];
$max_size = 2 * 1024 * 1024; // 2MB
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach(['aadhaar','pan','bank'] as $doc) {
        if (isset($_FILES[$doc]) && $_FILES[$doc]['error'] === UPLOAD_ERR_OK) {
            $type = $_FILES[$doc]['type'];
            $size = $_FILES[$doc]['size'];
            if (!in_array($type, $allowed_types)) {
                $errors[] = ucfirst($doc) . ' file type not allowed.';
            } elseif ($size > $max_size) {
                $errors[] = ucfirst($doc) . ' file exceeds 2MB.';
            } else {
                $target = "uploads/kyc_{$customer_id}_{$doc}." . pathinfo($_FILES[$doc]['name'], PATHINFO_EXTENSION);
                move_uploaded_file($_FILES[$doc]['tmp_name'], $target);
                $success .= ucfirst($doc) . ' uploaded successfully.<br>';
            }
        }
    }
    if (empty($errors)) {
        $kyc_status = 'Under Review';
        // Simulate approval after upload
        // In production, admin will approve
    }
}
// Simulate approval for demo
if (isset($_GET['approve'])) {
    $kyc_status = 'Approved';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>KYC Document Upload</title>
    <link rel="stylesheet" href="kyc.css">
    <script src="kyc.js"></script>
</head>
<body>
<div class="kyc-container">
    <h2>KYC Document Upload</h2>
    <div class="kyc-status <?php echo strtolower($kyc_status); ?>">Status: <?php echo $kyc_status; ?></div>
    <?php if ($kyc_status !== 'Approved'): ?>
    <form method="post" enctype="multipart/form-data">
        <div class="error" id="kyc-error"><?php echo implode('<br>', $errors); ?></div>
        <div class="success" id="kyc-success"><?php echo $success; ?></div>
        <label>Aadhaar (jpg/png/pdf, max 2MB):
            <input type="file" name="aadhaar" accept=".jpg,.jpeg,.png,.pdf" onchange="previewFile(this,'aadhaar-preview')">
        </label>
        <div class="preview" id="aadhaar-preview"></div>
        <label>PAN (jpg/png/pdf, max 2MB):
            <input type="file" name="pan" accept=".jpg,.jpeg,.png,.pdf" onchange="previewFile(this,'pan-preview')">
        </label>
        <div class="preview" id="pan-preview"></div>
        <label>Bank Document (jpg/png/pdf, max 2MB):
            <input type="file" name="bank" accept=".jpg,.jpeg,.png,.pdf" onchange="previewFile(this,'bank-preview')">
        </label>
        <div class="preview" id="bank-preview"></div>
        <button type="submit">Upload Documents</button>
    </form>
    <?php endif; ?>
    <?php if ($kyc_status !== 'Approved'): ?>
    <div class="withdraw-disabled">Withdrawals are disabled until KYC approval.</div>
    <?php else: ?>
    <div class="withdraw-enabled">Withdrawals enabled.</div>
    <?php endif; ?>
</div>
</body>
</html>
