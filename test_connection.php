
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>EduLoka System Check</h1>";
echo "<hr>";

// 1. Check PHP version
echo "<h2>1. PHP Version</h2>";
echo "Current: " . PHP_VERSION . " (Required: 7.4+)<br>";
echo (version_compare(PHP_VERSION, '7.4.0') >= 0) ? "✅ OK<br>" : "❌ FAILED<br>";

// 2. Check required extensions
echo "<h2>2. PHP Extensions</h2>";
$required_extensions = ['pdo', 'pdo_pgsql', 'mbstring', 'json', 'fileinfo'];
foreach ($required_extensions as $ext) {
    $loaded = extension_loaded($ext);
    echo "$ext: " . ($loaded ? "✅ Loaded" : "❌ Missing") . "<br>";
}

// 3. Check database connection
echo "<h2>3. Database Connection</h2>";
try {
    require_once __DIR__ . '/config/database.php';
    echo "✅ Database connected successfully<br>";
    
    // Test query
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $result = $stmt->fetch();
    echo "✅ Database query successful - Found {$result['count']} users<br>";
} catch (Exception $e) {
    echo "❌ Database error: " . htmlspecialchars($e->getMessage()) . "<br>";
}

// 4. Check file permissions
echo "<h2>4. File Permissions</h2>";
$dirs_to_check = [
    'assets/uploads',
    'assets/uploads/profiles',
    'assets/uploads/logos',
    'uploads/files',
    'uploads/submissions'
];

foreach ($dirs_to_check as $dir) {
    if (is_dir(__DIR__ . '/' . $dir)) {
        $writable = is_writable(__DIR__ . '/' . $dir);
        echo "$dir: " . ($writable ? "✅ Writable" : "❌ Not writable") . "<br>";
    } else {
        echo "$dir: ⚠️ Directory not found<br>";
    }
}

// 5. Check critical files
echo "<h2>5. Critical Files</h2>";
$critical_files = [
    'config/config.php',
    'config/database.php',
    'config/lang_id.php',
    'config/lang_en.php',
    'components/header.php',
    'components/footer.php',
    'index.php',
    'login.php'
];

foreach ($critical_files as $file) {
    $exists = file_exists(__DIR__ . '/' . $file);
    echo "$file: " . ($exists ? "✅ Exists" : "❌ Missing") . "<br>";
}

echo "<hr>";
echo "<h2>Test Complete</h2>";
echo "<p>If all checks pass, visit <a href='/index.php'>index.php</a> to continue.</p>";
