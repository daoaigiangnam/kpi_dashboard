<?php
declare(strict_types=1);

use App\Core\Database;
use PDO;
use RuntimeException;
use Throwable;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);
Dotenv\Dotenv::createImmutable($root)->safeLoad();

function report(string $label, bool $ok, string $detail = ''): void
{
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $label . ($detail !== '' ? ': ' . $detail : '') . PHP_EOL;
}

try {
    $pdo = Database::connection();
    $failures = 0;

    $migrationTable = $pdo->query(
        "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'schema_migrations'"
    )->fetchColumn();
    report('Migration repository exists', (int) $migrationTable === 1);
    if ((int) $migrationTable !== 1) {
        exit(1);
    }

    $expected = [
        '001_create_core_entities.sql',
        '002_create_core_relationships.sql',
        '003_create_business_entity_registry.sql',
        '004_create_common_services.sql',
        '005_create_pl1_access_roots.sql',
        '006_create_pl1_history_and_tokens.sql',
        '007_create_pl2_visit_root.sql',
        '008_create_pl2_workflow_children.sql',
    ];
    $appliedRows = $pdo->query('SELECT migration, checksum FROM schema_migrations ORDER BY id')->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach ($expected as $migration) {
        $file = __DIR__ . '/migrations/' . $migration;
        $exists = is_file($file);
        $applied = array_key_exists($migration, $appliedRows);
        $checksumMatches = $exists && $applied && hash_equals((string) $appliedRows[$migration], hash_file('sha256', $file));
        $ok = $exists && $applied && $checksumMatches;
        report('Migration ' . $migration, $ok, !$exists ? 'file missing' : (!$applied ? 'not applied' : ($checksumMatches ? 'checksum OK' : 'checksum mismatch')));
        if (!$ok) {
            $failures++;
        }
    }

    $requiredTables = [
        'users', 'roles', 'permissions', 'server_rooms',
        'user_roles', 'role_permissions', 'room_contacts',
        'business_entities',
        'email_queue', 'email_queue_attempts', 'system_audit_logs',
        'evidence_files', 'entity_evidence_links', 'idempotency_keys',
        'integration_events', 'scheduled_job_runs', 'data_exports',
        'pl1_access_requests', 'pl1_access_records', 'pl1_approval_decisions',
        'pl1_access_record_history', 'pl1_action_tokens',
        'pl2_visits', 'pl2_approval_history', 'pl2_action_tokens',
        'pl2_otp_challenges', 'pl2_otp_attempts', 'pl2_visit_events',
    ];
    $tableCheck = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table'
    );
    foreach ($requiredTables as $table) {
        $tableCheck->execute(['table' => $table]);
        $ok = (int) $tableCheck->fetchColumn() === 1;
        report('Table ' . $table, $ok);
        if (!$ok) {
            $failures++;
        }
    }

    $fkRows = $pdo->query(
        'SELECT TABLE_NAME, CONSTRAINT_NAME, DELETE_RULE, UPDATE_RULE
         FROM information_schema.REFERENTIAL_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA = DATABASE()
         ORDER BY TABLE_NAME, CONSTRAINT_NAME'
    )->fetchAll(PDO::FETCH_ASSOC);
    foreach ($fkRows as $fk) {
        $ok = strtoupper((string) $fk['DELETE_RULE']) === 'RESTRICT'
            && strtoupper((string) $fk['UPDATE_RULE']) === 'RESTRICT';
        report('FK ' . $fk['TABLE_NAME'] . '.' . $fk['CONSTRAINT_NAME'], $ok,
            'DELETE=' . $fk['DELETE_RULE'] . ', UPDATE=' . $fk['UPDATE_RULE']);
        if (!$ok) {
            $failures++;
        }
    }

    report('Global FK policy coverage', count($fkRows) > 0, count($fkRows) . ' foreign keys checked');
    if (count($fkRows) === 0) {
        $failures++;
    }

    echo PHP_EOL . ($failures === 0 ? 'VERIFICATION PASSED' : 'VERIFICATION FAILED: ' . $failures . ' issue(s)') . PHP_EOL;
    exit($failures === 0 ? 0 : 1);
} catch (Throwable $exception) {
    fwrite(STDERR, '[ERROR] ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
