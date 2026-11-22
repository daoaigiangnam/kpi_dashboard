<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

$host = $_ENV['DB_HOST'] ?? '127.0.0.1';
$port = $_ENV['DB_PORT'] ?? '3306';
$name = $_ENV['DB_DATABASE'] ?? '';
$user = $_ENV['DB_USERNAME'] ?? '';
$pass = $_ENV['DB_PASSWORD'] ?? '';

if ($name === '' || $user === '') {
    fwrite(STDERR, "Missing DB_DATABASE or DB_USERNAME in .env\n");
    exit(1);
}
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This command can only run from CLI.\n");
    exit(1);
}

$email = strtolower(trim((string) readline('Admin email: ')));
$fullName = trim((string) readline('Admin name: '));
$password = (string) readline('Admin password (min 12 chars): ');

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $fullName === '' || strlen($password) < 12) {
    fwrite(STDERR, "Invalid email/name or password shorter than 12 characters.\n");
    exit(1);
}

$pdo = new PDO(
    "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
    $user,
    $pass,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
);

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare(
        "INSERT INTO users (name,email,password_hash,status)
         VALUES (:name,:email,:hash,'ACTIVE')
         ON DUPLICATE KEY UPDATE name=VALUES(name), password_hash=VALUES(password_hash), status='ACTIVE', deleted_at=NULL"
    );
    $stmt->execute([
        'name' => $fullName,
        'email' => $email,
        'hash' => password_hash($password, PASSWORD_DEFAULT),
    ]);

    $userId = (int) $pdo->lastInsertId();
    if ($userId === 0) {
        $find = $pdo->prepare('SELECT id FROM users WHERE email = :email');
        $find->execute(['email' => $email]);
        $userId = (int) $find->fetchColumn();
    }

    $pdo->exec("INSERT IGNORE INTO roles (code,name,description) VALUES ('SUPER_ADMIN','Super Administrator','Toàn quyền quản trị hệ thống')");
    $roleId = (int) $pdo->query("SELECT id FROM roles WHERE code='SUPER_ADMIN'")->fetchColumn();
    $assign = $pdo->prepare('INSERT IGNORE INTO user_roles (user_id,role_id) VALUES (:uid,:rid)');
    $assign->execute(['uid' => $userId, 'rid' => $roleId]);

    $pdo->commit();
    fwrite(STDOUT, "Admin account is ready: {$email}\n");
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, "Could not create admin: " . $e->getMessage() . "\n");
    exit(1);
}
