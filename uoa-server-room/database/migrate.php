<?php
declare(strict_types=1);

/**
 * UOA schema migration runner.
 *
 * Usage:
 *   php database/migrate.php status
 *   php database/migrate.php migrate
 *
 * MySQL DDL may implicitly commit. Each migration is therefore a small,
 * independently tracked unit. Never edit a migration after it has run.
 */
use App\Core\Database;
use PDO;
use RuntimeException;
use Throwable;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);
$dotenv = Dotenv\Dotenv::createImmutable($root);
$dotenv->safeLoad();

function migrationLog(string $message): void
{
    fwrite(STDOUT, '[' . gmdate('Y-m-d H:i:s') . ' UTC] ' . $message . PHP_EOL);
}

function migrationFiles(string $directory): array
{
    $files = glob($directory . '/*.sql') ?: [];
    sort($files, SORT_STRING);
    foreach ($files as $file) {
        if (!preg_match('/^[0-9]{3}_[a-z0-9_]+\.sql$/', basename($file))) {
            throw new RuntimeException('Tên migration không hợp lệ: ' . basename($file));
        }
    }
    return $files;
}

function splitSqlStatements(string $sql): array
{
    // Migration SQL files intentionally prohibit stored procedures and
    // semicolons inside string literals. One statement per terminated block.
    $statements = [];
    foreach (preg_split('/;\s*(?:\R|$)/', trim($sql)) ?: [] as $statement) {
        $statement = trim($statement);
        if ($statement !== '' && !str_starts_with($statement, '--')) {
            $statements[] = $statement;
        }
    }
    return $statements;
}

function ensureMigrationTable(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS schema_migrations (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            migration VARCHAR(190) NOT NULL,
            checksum CHAR(64) NOT NULL,
            batch INT UNSIGNED NOT NULL,
            executed_at DATETIME(3) NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_schema_migrations_name (migration)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"
    );
}

function appliedMigrations(PDO $pdo): array
{
    $rows = $pdo->query('SELECT migration, checksum, batch, executed_at FROM schema_migrations ORDER BY id')
        ->fetchAll(PDO::FETCH_ASSOC);
    $result = [];
    foreach ($rows as $row) {
        $result[$row['migration']] = $row;
    }
    return $result;
}

function assertNoUntrackedTargetTables(PDO $pdo, array $statements, string $migration): void
{
    $targets = [];
    foreach ($statements as $statement) {
        if (preg_match('/^CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([a-zA-Z0-9_]+)`?/i', ltrim($statement), $match)) {
            $targets[] = $match[1];
        }
    }
    if ($targets === []) {
        throw new RuntimeException('Migration không có CREATE TABLE: ' . $migration);
    }

    $check = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table'
    );
    foreach ($targets as $table) {
        $check->execute(['table' => $table]);
        if ((int) $check->fetchColumn() > 0) {
            throw new RuntimeException(
                "Bảng {$table} đã tồn tại nhưng migration {$migration} chưa được ghi nhận. " .
                'Dừng để tránh ghi đè schema legacy hoặc schema chưa được đối chiếu.'
            );
        }
    }
}

$command = $argv[1] ?? 'status';
if (!in_array($command, ['status', 'migrate'], true)) {
    fwrite(STDERR, "Usage: php database/migrate.php [status|migrate]\n");
    exit(2);
}

try {
    $pdo = Database::connection();
    $directory = __DIR__ . '/migrations';
    $files = migrationFiles($directory);

    if ($command === 'status') {
        $exists = $pdo->query(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'schema_migrations'"
        )->fetchColumn();
        $applied = (int) $exists === 1 ? appliedMigrations($pdo) : [];
        foreach ($files as $file) {
            $name = basename($file);
            $checksum = hash_file('sha256', $file);
            if (isset($applied[$name])) {
                if (!hash_equals($applied[$name]['checksum'], $checksum)) {
                    migrationLog("DRIFT: {$name} đã chạy nhưng checksum thay đổi.");
                } else {
                    migrationLog("APPLIED: {$name}");
                }
            } else {
                migrationLog("PENDING: {$name}");
            }
        }
        if ((int) $exists !== 1) {
            migrationLog('Migration repository chưa tồn tại; status không thay đổi Database.');
        }
        exit(0);
    }

    ensureMigrationTable($pdo);
    $applied = appliedMigrations($pdo);
    $lock = $pdo->query("SELECT GET_LOCK('uoa_schema_migrations', 30)")->fetchColumn();
    if ((int) $lock !== 1) {
        throw new RuntimeException('Không lấy được migration lock trong 30 giây.');
    }

    try {
        $batch = (int) $pdo->query('SELECT COALESCE(MAX(batch), 0) + 1 FROM schema_migrations')->fetchColumn();
        foreach ($files as $file) {
            $name = basename($file);
            $checksum = hash_file('sha256', $file);
            if (isset($applied[$name])) {
                if (!hash_equals($applied[$name]['checksum'], $checksum)) {
                    throw new RuntimeException("Checksum migration đã chạy bị thay đổi: {$name}");
                }
                continue;
            }

            $sql = file_get_contents($file);
            if ($sql === false) {
                throw new RuntimeException('Không đọc được migration: ' . $name);
            }
            $statements = splitSqlStatements($sql);
            assertNoUntrackedTargetTables($pdo, $statements, $name);

            migrationLog("RUNNING: {$name}");
            foreach ($statements as $index => $statement) {
                try {
                    $pdo->exec($statement);
                } catch (Throwable $exception) {
                    throw new RuntimeException(
                        "Migration {$name} lỗi tại statement " . ($index + 1) .
                        '. DDL có thể đã implicit commit; không tự chạy lại hoặc DROP bảng. ' .
                        'Cần kiểm tra schema thực tế trước khi forward-fix. Chi tiết: ' . $exception->getMessage(),
                        0,
                        $exception
                    );
                }
            }

            $insert = $pdo->prepare(
                'INSERT INTO schema_migrations (migration, checksum, batch, executed_at) VALUES (:migration, :checksum, :batch, UTC_TIMESTAMP(3))'
            );
            $insert->execute(['migration' => $name, 'checksum' => $checksum, 'batch' => $batch]);
            migrationLog("DONE: {$name}");
        }
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('uoa_schema_migrations')");
    }
} catch (Throwable $exception) {
    migrationLog('ERROR: ' . $exception->getMessage());
    exit(1);
}
