<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$auth = new Auth();
$database = new Database();
$db = $database->getConnection();

// Redirect if not admin
if (!$auth->isAdmin()) {
    header('Location: ../login.php');
    exit();
}

$success_message = '';
$error_message = '';

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tab = $_POST['tab'] ?? 'wallet';
    
    switch ($tab) {
        case 'wallet':
            $settings = [
                'min_deposit' => floatval($_POST['min_deposit']),
                'min_withdrawal' => floatval($_POST['min_withdrawal']),
                'min_points_withdrawal' => intval($_POST['min_points_withdrawal']),
                'points_to_usd_rate' => floatval($_POST['points_to_usd_rate']),
                'referral_percentage' => floatval($_POST['referral_percentage']),
                'withdrawal_fee_percentage' => floatval($_POST['withdrawal_fee_percentage']),
                'faucetpay_merchant_username' => trim($_POST['faucetpay_merchant_username']),
                'faucetpay_api_key' => trim($_POST['faucetpay_api_key']),
                'bitpay_api_token' => trim($_POST['bitpay_api_token']),
                'bitpay_environment' => $_POST['bitpay_environment'],
                'bitpay_webhook_secret' => trim($_POST['bitpay_webhook_secret'])
            ];
            
            foreach ($settings as $key => $value) {
                $update_query = "UPDATE wallet_settings SET {$key} = :value WHERE id = 1";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->bindParam(':value', $value);
                $update_stmt->execute();
            }
            
            $success_message = 'Wallet settings updated successfully!';
            break;
            
        case 'redirect':
            $countdown_seconds = intval($_POST['countdown_seconds']);
            $redirect_message = trim($_POST['redirect_message']);
            $show_site_preview = isset($_POST['show_site_preview']) ? 1 : 0;
            
            $update_query = "UPDATE redirect_settings SET 
                            countdown_seconds = :countdown_seconds,
                            redirect_message = :redirect_message,
                            show_site_preview = :show_site_preview
                            WHERE id = 1";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':countdown_seconds', $countdown_seconds);
            $update_stmt->bindParam(':redirect_message', $redirect_message);
            $update_stmt->bindParam(':show_site_preview', $show_site_preview);
            $update_stmt->execute();
            
            $success_message = 'Redirect settings updated successfully!';
            break;
            
        case 'upload':
            $max_file_size = intval($_POST['max_file_size']) * 1024 * 1024; // Convert MB to bytes
            $allowed_types = implode(',', $_POST['allowed_types'] ?? []);
            
            // Update or insert upload settings
            $update_query = "INSERT INTO site_upload_settings (id, max_file_size, allowed_types) 
                            VALUES (1, :max_file_size, :allowed_types)
                            ON DUPLICATE KEY UPDATE 
                            max_file_size = :max_file_size, 
                            allowed_types = :allowed_types";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':max_file_size', $max_file_size);
            $update_stmt->bindParam(':allowed_types', $allowed_types);
            $update_stmt->execute();
            
            $success_message = 'Upload settings updated successfully!';
            break;
    }
}

// Get current settings
$wallet_settings_query = "SELECT * FROM wallet_settings WHERE id = 1";
$wallet_settings_stmt = $db->prepare($wallet_settings_query);
$wallet_settings_stmt->execute();
$wallet_settings = $wallet_settings_stmt->fetch(PDO::FETCH_ASSOC);

$redirect_settings_query = "SELECT * FROM redirect_settings WHERE id = 1";
$redirect_settings_stmt = $db->prepare($redirect_settings_query);
$redirect_settings_stmt->execute();
$redirect_settings = $redirect_settings_stmt->fetch(PDO::FETCH_ASSOC);

$upload_settings_query = "SELECT * FROM site_upload_settings WHERE id = 1";
$upload_settings_stmt = $db->prepare($upload_settings_query);
$upload_settings_stmt->execute();
$upload_settings = $upload_settings_stmt->fetch(PDO::FETCH_ASSOC);

$page_title = 'Settings - Admin Panel';
include 'includes/admin_header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/admin_sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Settings</h1>
            </div>

            <?php if ($success_message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <!-- Settings Tabs -->
            <ul class="nav nav-tabs" id="settingsTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="wallet-tab" data-bs-toggle="tab" data-bs-target="#wallet" type="button">
                        <i class="fas fa-wallet"></i> Wallet Settings
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="redirect-tab" data-bs-toggle="tab" data-bs-target="#redirect" type="button">
                        <i class="fas fa-arrow-up-right-from-square"></i> Redirect Settings
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="upload-tab" data-bs-toggle="tab" data-bs-target="#upload" type="button">
                        <i class="fas fa-upload"></i> Upload Settings
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button">
                        <i class="fas fa-cog"></i> General Settings
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="settingsTabContent">
                <!-- Wallet Settings -->
                <div class="tab-pane fade show active" id="wallet" role="tabpanel">
                    <div class="card mt-3">
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="tab" value="wallet">
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <h5>Deposit Settings</h5>
                                        <div class="mb-3">
                                            <label class="form-label">Minimum Deposit (USD)</label>
                                            <input type="number" name="min_deposit" class="form-control" 
                                                   value="<?php echo $wallet_settings['min_deposit']; ?>" 
                                                   step="0.0001" min="0" required>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">FaucetPay Merchant Username</label>
                                            <input type="text" name="faucetpay_merchant_username" class="form-control" 
                                                   value="<?php echo htmlspecialchars($wallet_settings['faucetpay_merchant_username']); ?>"
                                                   placeholder="Your FaucetPay username">
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">FaucetPay API Key</label>
                                            <input type="password" name="faucetpay_api_key" class="form-control" 
                                                   value="<?php echo htmlspecialchars($wallet_settings['faucetpay_api_key']); ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <h5>Withdrawal Settings</h5>
                                        <div class="mb-3">
                                            <label class="form-label">Minimum Withdrawal (USD)</label>
                                            <input type="number" name="min_withdrawal" class="form-control" 
                                                   value="<?php echo $wallet_settings['min_withdrawal']; ?>" 
                                                   step="0.0001" min="0" required>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Minimum Points for Withdrawal</label>
                                            <input type="number" name="min_points_withdrawal" class="form-control" 
                                                   value="<?php echo $wallet_settings['min_points_withdrawal']; ?>" 
                                                   min="1" required>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Withdrawal Fee (%)</label>
                                            <input type="number" name="withdrawal_fee_percentage" class="form-control" 
                                                   value="<?php echo $wallet_settings['withdrawal_fee_percentage']; ?>" 
                                                   step="0.01" min="0" max="100" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <h5>Points System</h5>
                                        <div class="mb-3">
                                            <label class="form-label">Points to USD Rate</label>
                                            <input type="number" name="points_to_usd_rate" class="form-control" 
                                                   value="<?php echo $wallet_settings['points_to_usd_rate']; ?>" 
                                                   step="0.000001" min="0" required>
                                            <small class="form-text text-muted">How much USD each point is worth</small>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <h5>Referral System</h5>
                                        <div class="mb-3">
                                            <label class="form-label">Referral Percentage (%)</label>
                                            <input type="number" name="referral_percentage" class="form-control" 
                                                   value="<?php echo $wallet_settings['referral_percentage']; ?>" 
                                                   step="0.01" min="0" max="100" required>
                                            <small class="form-text text-muted">Percentage of points referrers earn from referrals</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">Save Wallet Settings</button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Redirect Settings -->
                <div class="tab-pane fade" id="redirect" role="tabpanel">
                    <div class="card mt-3">
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="tab" value="redirect">
                                
                                <div class="mb-3">
                                    <label class="form-label">Countdown Duration (seconds)</label>
                                    <input type="number" name="countdown_seconds" class="form-control" 
                                           value="<?php echo $redirect_settings['countdown_seconds']; ?>" 
                                           min="1" max="60" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Redirect Message</label>
                                    <textarea name="redirect_message" class="form-control" rows="3" required><?php echo htmlspecialchars($redirect_settings['redirect_message']); ?></textarea>
                                    <small class="form-text text-muted">Use {seconds} as placeholder for countdown</small>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input type="checkbox" name="show_site_preview" class="form-check-input" 
                                               <?php echo $redirect_settings['show_site_preview'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label">Show Site Preview</label>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">Save Redirect Settings</button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Upload Settings -->
                <div class="tab-pane fade" id="upload" role="tabpanel">
                    <div class="card mt-3">
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="tab" value="upload">
                                
                                <div class="mb-3">
                                    <label class="form-label">Maximum File Size (MB)</label>
                                    <input type="number" name="max_file_size" class="form-control" 
                                           value="<?php echo ($upload_settings['max_file_size'] ?? 2097152) / 1024 / 1024; ?>" 
                                           min="1" max="10" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Allowed File Types</label>
                                    <?php 
                                    $allowed_types = explode(',', $upload_settings['allowed_types'] ?? 'image/jpeg,image/png,image/gif');
                                    ?>
                                    <div class="form-check">
                                        <input type="checkbox" name="allowed_types[]" value="image/jpeg" class="form-check-input" 
                                               <?php echo in_array('image/jpeg', $allowed_types) ? 'checked' : ''; ?>>
                                        <label class="form-check-label">JPEG</label>
                                    </div>
                                    <div class="form-check">
                                        <input type="checkbox" name="allowed_types[]" value="image/png" class="form-check-input" 
                                               <?php echo in_array('image/png', $allowed_types) ? 'checked' : ''; ?>>
                                        <label class="form-check-label">PNG</label>
                                    </div>
                                    <div class="form-check">
                                        <input type="checkbox" name="allowed_types[]" value="image/gif" class="form-check-input" 
                                               <?php echo in_array('image/gif', $allowed_types) ? 'checked' : ''; ?>>
                                        <label class="form-check-label">GIF</label>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">Save Upload Settings</button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- General Settings -->
                <div class="tab-pane fade" id="general" role="tabpanel">
                    <div class="card mt-3">
                        <div class="card-body">
                            <h5>Site Information</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Site Name:</strong> <?php echo SITE_NAME; ?></p>
                                    <p><strong>Site URL:</strong> <?php echo SITE_URL; ?></p>
                                    <p><strong>Admin Email:</strong> <?php echo ADMIN_EMAIL; ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Auto Approve Sites:</strong> <?php echo AUTO_APPROVE_SITES ? 'Yes' : 'No'; ?></p>
                                    <p><strong>Email Verification:</strong> <?php echo REQUIRE_EMAIL_VERIFICATION ? 'Required' : 'Optional'; ?></p>
                                    <p><strong>Credits System:</strong> <?php echo ENABLE_CREDITS_SYSTEM ? 'Enabled' : 'Disabled'; ?></p>
                                </div>
                            </div>
                            
                            <div class="alert alert-info">
                                <strong>Note:</strong> To modify these settings, edit the <code>config/config.php</code> file directly.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Edit Currency Modal -->
<div class="modal fade" id="editCurrencyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Currency Settings</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="editCurrencyForm">
                <input type="hidden" name="action" value="update_currency">
                <input type="hidden" name="currency_id" id="editCurrencyId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Currency</label>
                        <input type="text" id="editCurrencyName" class="form-control" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Withdrawal Method</label>
                        <select name="withdrawal_method" id="editWithdrawalMethod" class="form-select" required>
                            <option value="faucetpay">FaucetPay Only</option>
                            <option value="direct_wallet">Direct Wallet Only</option>
                            <option value="both">Both Methods</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Minimum Amount (USD)</label>
                        <input type="number" name="min_amount" id="editMinAmount" class="form-control" 
                               step="0.0001" min="0" required>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" name="is_active" id="editIsActive" class="form-check-input">
                            <label class="form-check-label">Active</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Currency</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editCurrency(currency) {
    document.getElementById('editCurrencyId').value = currency.id;
    document.getElementById('editCurrencyName').value = currency.currency_name + ' (' + currency.currency_code + ')';
    document.getElementById('editWithdrawalMethod').value = currency.withdrawal_method;
    document.getElementById('editMinAmount').value = currency.min_amount;
    document.getElementById('editIsActive').checked = currency.is_active == 1;
    
    const modal = new bootstrap.Modal(document.getElementById('editCurrencyModal'));
    modal.show();
}
</script>

<?php include 'includes/admin_footer.php'; ?>
