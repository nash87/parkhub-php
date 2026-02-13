<?php
/**
 * ParkHub PHP Edition - One-Click Installer
 * Upload this file to your web root and visit it in a browser.
 */

$errors = [];
$warnings = [];
$success = false;
$step = $_POST['step'] ?? ($_GET['step'] ?? 'check');

// Step 1: Check requirements
function checkRequirements(): array {
    $checks = [];

    $checks['PHP Version'] = [
        'required' => '8.2+',
        'current' => PHP_VERSION,
        'ok' => version_compare(PHP_VERSION, '8.2.0', '>='),
    ];

    $extensions = ['pdo', 'pdo_sqlite', 'mbstring', 'openssl', 'tokenizer', 'json', 'curl', 'fileinfo'];
    foreach ($extensions as $ext) {
        $checks["ext-$ext"] = [
            'required' => 'Installed',
            'current' => extension_loaded($ext) ? 'Yes' : 'No',
            'ok' => extension_loaded($ext),
        ];
    }

    $checks['storage writable'] = [
        'required' => 'Writable',
        'current' => is_writable(__DIR__ . '/../storage') ? 'Yes' : 'No',
        'ok' => is_writable(__DIR__ . '/../storage'),
    ];

    $checks['bootstrap/cache writable'] = [
        'required' => 'Writable',
        'current' => is_writable(__DIR__ . '/../bootstrap/cache') ? 'Yes' : 'No',
        'ok' => is_writable(__DIR__ . '/../bootstrap/cache'),
    ];

    return $checks;
}

if ($step === 'install' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbType = $_POST['db_type'] ?? 'sqlite';
    $adminUser = $_POST['admin_username'] ?? 'admin';
    $adminPass = $_POST['admin_password'] ?? '';
    $adminEmail = $_POST['admin_email'] ?? 'admin@example.com';
    $adminName = $_POST['admin_name'] ?? 'Administrator';
    $companyName = $_POST['company_name'] ?? 'My Company';

    // Create .env
    $envContent = "APP_NAME=ParkHub\nAPP_ENV=production\nAPP_DEBUG=false\n";
    $envContent .= "APP_KEY=\nAPP_URL=" . (isset($_SERVER['HTTPS']) ? 'https' : 'http') . "://{$_SERVER['HTTP_HOST']}\n\n";

    if ($dbType === 'sqlite') {
        $envContent .= "DB_CONNECTION=sqlite\nDB_DATABASE=" . realpath(__DIR__ . '/../database') . "/database.sqlite\n";
    } else {
        $envContent .= "DB_CONNECTION=mysql\nDB_HOST=" . ($_POST['db_host'] ?? '127.0.0.1') . "\n";
        $envContent .= "DB_PORT=" . ($_POST['db_port'] ?? '3306') . "\n";
        $envContent .= "DB_DATABASE=" . ($_POST['db_name'] ?? 'parkhub') . "\n";
        $envContent .= "DB_USERNAME=" . ($_POST['db_user'] ?? 'root') . "\n";
        $envContent .= "DB_PASSWORD=" . ($_POST['db_pass'] ?? '') . "\n";
    }

    $envContent .= "\nSESSION_DRIVER=file\nCACHE_STORE=file\nQUEUE_CONNECTION=sync\n";
    file_put_contents(__DIR__ . '/../.env', $envContent);

    // Create SQLite database
    if ($dbType === 'sqlite') {
        $dbPath = realpath(__DIR__ . '/../database') . '/database.sqlite';
        if (!file_exists($dbPath)) touch($dbPath);
    }

    // Run artisan commands
    $artisan = __DIR__ . '/../artisan';
    exec("php $artisan key:generate --force 2>&1", $output);
    exec("php $artisan migrate --force 2>&1", $output);

    // Create admin via API-like call
    exec("php $artisan tinker --execute=\"
        use App\\Models\\User;
        use App\\Models\\Setting;
        use Illuminate\\Support\\Facades\\Hash;
        User::create([
            'username' => '$adminUser',
            'email' => '$adminEmail',
            'password' => Hash::make('$adminPass'),
            'name' => '$adminName',
            'role' => 'admin',
            'is_active' => true,
            'preferences' => json_encode(['language' => 'en', 'theme' => 'system']),
        ]);
        Setting::set('setup_completed', 'true');
        Setting::set('company_name', '$companyName');
        echo 'Done';
    \" 2>&1", $output);

    $success = true;
    $step = 'done';
}

$checks = checkRequirements();
$allOk = !in_array(false, array_column($checks, 'ok'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ParkHub Installer</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #0f172a; color: #e2e8f0; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .container { max-width: 600px; width: 100%; padding: 2rem; }
        h1 { color: #f59e0b; font-size: 1.8rem; margin-bottom: 0.5rem; }
        h2 { color: #f59e0b; font-size: 1.2rem; margin: 1.5rem 0 1rem; }
        .subtitle { color: #94a3b8; margin-bottom: 2rem; }
        .card { background: #1e293b; border-radius: 12px; padding: 1.5rem; margin-bottom: 1rem; }
        .check { display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid #334155; }
        .check:last-child { border: none; }
        .ok { color: #22c55e; }
        .fail { color: #ef4444; }
        input, select { width: 100%; padding: 0.75rem; margin: 0.25rem 0 1rem; background: #0f172a; border: 1px solid #334155; border-radius: 8px; color: #e2e8f0; font-size: 1rem; }
        label { color: #94a3b8; font-size: 0.9rem; }
        button { width: 100%; padding: 0.75rem; background: #f59e0b; color: #0f172a; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; margin-top: 1rem; }
        button:hover { background: #d97706; }
        button:disabled { background: #475569; cursor: not-allowed; }
        .success { background: #166534; padding: 1.5rem; border-radius: 12px; text-align: center; }
        .success h2 { color: #22c55e; }
    </style>
</head>
<body>
<div class="container">
    <h1>🅿️ ParkHub Installer</h1>
    <p class="subtitle">PHP Edition — One-Click Setup</p>

    <?php if ($step === 'done'): ?>
        <div class="success">
            <h2>✅ Installation Complete!</h2>
            <p style="margin-top:1rem;">ParkHub has been installed successfully.</p>
            <p style="margin-top:0.5rem;color:#94a3b8;">You can now <a href="/" style="color:#f59e0b;">open ParkHub</a> and log in.</p>
            <p style="margin-top:1rem;color:#ef4444;font-size:0.85rem;">⚠️ Delete this install.php file for security!</p>
        </div>
    <?php else: ?>
        <div class="card">
            <h2>System Requirements</h2>
            <?php foreach ($checks as $name => $check): ?>
                <div class="check">
                    <span><?= $name ?></span>
                    <span class="<?= $check['ok'] ? 'ok' : 'fail' ?>"><?= $check['current'] ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($allOk): ?>
        <form method="POST" action="?step=install">
            <input type="hidden" name="step" value="install">
            <div class="card">
                <h2>Company</h2>
                <label>Company Name</label>
                <input type="text" name="company_name" value="My Company" required>

                <h2>Database</h2>
                <label>Type</label>
                <select name="db_type" id="dbType" onchange="toggleMysql()">
                    <option value="sqlite">SQLite (recommended)</option>
                    <option value="mysql">MySQL / MariaDB</option>
                </select>
                <div id="mysqlFields" style="display:none">
                    <label>Host</label><input type="text" name="db_host" value="127.0.0.1">
                    <label>Port</label><input type="text" name="db_port" value="3306">
                    <label>Database</label><input type="text" name="db_name" value="parkhub">
                    <label>Username</label><input type="text" name="db_user" value="root">
                    <label>Password</label><input type="password" name="db_pass">
                </div>

                <h2>Admin Account</h2>
                <label>Username</label>
                <input type="text" name="admin_username" value="admin" required>
                <label>Name</label>
                <input type="text" name="admin_name" value="Administrator" required>
                <label>Email</label>
                <input type="email" name="admin_email" value="admin@example.com" required>
                <label>Password</label>
                <input type="password" name="admin_password" required minlength="8" placeholder="Min. 8 characters">
            </div>
            <button type="submit">Install ParkHub</button>
        </form>
        <script>
            function toggleMysql() {
                document.getElementById('mysqlFields').style.display =
                    document.getElementById('dbType').value === 'mysql' ? 'block' : 'none';
            }
        </script>
        <?php else: ?>
            <button disabled>Fix requirements above first</button>
        <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>
