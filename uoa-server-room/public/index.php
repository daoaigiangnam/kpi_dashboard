<?php
declare(strict_types=1);

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;

require dirname(__DIR__) . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

$secureCookie = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params([
    'httponly' => true,
    'secure' => $secureCookie,
    'samesite' => 'Lax',
]);
session_start();

date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'Asia/Ho_Chi_Minh');

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$page = (string) ($_GET['page'] ?? 'dashboard');
$error = '';

if ($page === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::verify($_POST['_csrf'] ?? null)) {
        $error = 'Phiên gửi biểu mẫu không hợp lệ. Vui lòng tải lại trang.';
    } elseif (Auth::attempt((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''))) {
        header('Location: ?page=dashboard');
        exit;
    } else {
        $error = 'Email hoặc mật khẩu không đúng, hoặc tài khoản đã bị khóa.';
    }
}

if ($page === 'logout' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (Csrf::verify($_POST['_csrf'] ?? null)) {
        Auth::logout();
    }
    header('Location: ?page=login');
    exit;
}

if ($page !== 'login') {
    Auth::requireLogin();
}

$modules = [
    'pl1' => ['title' => 'PL1 – Quyền truy cập', 'table' => 'access_requests', 'status' => 'approval_status', 'description' => 'Yêu cầu cấp mới, thay đổi và thu hồi quyền vào phòng Server.'],
    'pl2' => ['title' => 'PL2 – Nhật ký ra vào', 'table' => 'visits', 'status' => 'approval_status', 'description' => 'Đăng ký ENTRY, phê duyệt, OTP CHECK-IN và CHECK-OUT theo Visit ID.'],
    'pl3' => ['title' => 'PL3 – Kiểm tra phòng Server', 'table' => 'inspections', 'status' => 'uoa_confirmation_status', 'description' => 'Quản lý checklist 12 hạng mục theo ngày, tuần và tháng.'],
    'pl4' => ['title' => 'PL4 – Sự cố / Vi phạm', 'table' => 'incidents', 'status' => 'status', 'description' => 'Tiếp nhận, phân công, khắc phục, xác minh và đóng sự cố.'],
    'pl5' => ['title' => 'PL5 – Công việc / Bảo trì', 'table' => 'work_requests', 'status' => 'approval_status', 'description' => 'Đăng ký công việc, đánh giá ảnh hưởng, phê duyệt và nghiệm thu.'],
];

$counts = [];
if ($page !== 'login') {
    try {
        $pdo = Database::connection();
        foreach ($modules as $key => $module) {
            $counts[$key] = (int) $pdo->query('SELECT COUNT(*) FROM ' . $module['table'])->fetchColumn();
        }
        $queueStats = $pdo->query("SELECT status, COUNT(*) AS total FROM email_queue GROUP BY status")->fetchAll();
        $auditCount = (int) $pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
        $queueStats = [];
        $auditCount = 0;
    }
}

$appName = $_ENV['APP_NAME'] ?? 'UOA Server Room Management';
$user = Auth::user();
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($appName) ?> – Admin</title>
    <link rel="stylesheet" href="assets/admin.css">
</head>
<body>
<?php if ($page === 'login'): ?>
    <main class="login-wrap">
        <section class="login-card">
            <div class="brand-mark">UOA <span>GROUP</span></div>
            <p class="eyebrow">SERVER ROOM MANAGEMENT</p>
            <h1>Đăng nhập Admin</h1>
            <p class="muted">Quản trị tập trung các quy trình PL1–PL5.</p>
            <?php if ($error !== ''): ?><div class="alert danger"><?= e($error) ?></div><?php endif; ?>
            <form method="post" action="?page=login">
                <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
                <label>Email quản trị</label>
                <input type="email" name="email" autocomplete="username" required>
                <label>Mật khẩu</label>
                <input type="password" name="password" autocomplete="current-password" required>
                <button class="button primary full" type="submit">Đăng nhập</button>
            </form>
        </section>
    </main>
<?php else: ?>
    <div class="app-shell">
        <aside class="sidebar">
            <a class="brand" href="?page=dashboard"><span class="brand-icon">U</span><span>UOA SERVER ROOM<small>MANAGEMENT PORTAL</small></span></a>
            <div class="nav-caption">TỔNG QUAN</div>
            <a class="nav-link <?= $page === 'dashboard' ? 'active' : '' ?>" href="?page=dashboard">◫ <span>Dashboard tổng quan</span></a>
            <div class="nav-caption">QUY TRÌNH PHỤ LỤC</div>
            <?php foreach ($modules as $key => $module): ?>
                <a class="nav-link <?= $page === $key ? 'active' : '' ?>" href="?page=<?= e($key) ?>"><span class="nav-code"><?= strtoupper($key) ?></span><span><?= e($module['title']) ?></span></a>
            <?php endforeach; ?>
            <div class="nav-caption">QUẢN TRỊ HỆ THỐNG</div>
            <a class="nav-link" href="?page=email-queue">✉ <span>Email Queue</span></a>
            <a class="nav-link" href="?page=users">♙ <span>Người dùng & phân quyền</span></a>
            <a class="nav-link" href="?page=settings">⚙ <span>Cấu hình hệ thống</span></a>
            <a class="nav-link" href="?page=audit">▤ <span>Audit Log</span></a>
            <div class="sidebar-bottom"><span class="online-dot"></span> Hệ thống quản trị</div>
        </aside>
        <main class="main-area">
            <header class="topbar">
                <div><span class="muted">UOA / Admin /</span> <strong><?= e($modules[$page]['title'] ?? 'Dashboard') ?></strong></div>
                <div class="user-menu"><span class="avatar"><?= e(mb_strtoupper(mb_substr($user['name'] ?? 'A', 0, 1))) ?></span><span><?= e($user['name'] ?? 'Administrator') ?></span>
                    <form method="post" action="?page=logout"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><button class="link-button">Đăng xuất</button></form>
                </div>
            </header>
            <section class="content">
                <?php if ($error !== ''): ?><div class="alert danger"><?= e($error) ?></div><?php endif; ?>
                <?php if ($page === 'dashboard'): ?>
                    <div class="page-heading"><div><p class="eyebrow">CONTROL CENTER</p><h1>Dashboard tổng quan</h1><p class="muted">Tình trạng quản lý và vận hành Phòng Server.</p></div><span class="date-chip"><?= date('d/m/Y') ?></span></div>
                    <div class="stat-grid">
                        <?php foreach ($modules as $key => $module): ?><a class="stat-card" href="?page=<?= e($key) ?>"><span class="stat-label"><?= e($module['title']) ?></span><strong><?= number_format($counts[$key] ?? 0) ?></strong><small>Hồ sơ trong hệ thống</small></a><?php endforeach; ?>
                    </div>
                    <div class="panel-grid">
                        <section class="panel"><div class="panel-heading"><div><h2>Vận hành Email Queue</h2><p class="muted">Theo dõi gửi thư và các lần thử lại.</p></div><a href="?page=email-queue">Quản lý queue →</a></div>
                            <?php if (empty($queueStats)): ?><p class="empty-state">Chưa có dữ liệu Queue hoặc chưa kết nối database.</p><?php else: ?><div class="status-list"><?php foreach ($queueStats as $stat): ?><div><span><?= e($stat['status']) ?></span><strong><?= number_format((int)$stat['total']) ?></strong></div><?php endforeach; ?></div><?php endif; ?>
                        </section>
                        <section class="panel"><div class="panel-heading"><div><h2>Kiểm soát hệ thống</h2><p class="muted">Nhật ký thao tác quản trị.</p></div><a href="?page=audit">Xem Audit Log →</a></div><div class="big-number"><?= number_format($auditCount ?? 0) ?></div><p class="muted">Sự kiện đã được ghi nhận</p></section>
                    </div>
                <?php elseif (isset($modules[$page])): ?>
                    <div class="page-heading"><div><p class="eyebrow">APPENDIX MANAGEMENT</p><h1><?= e($modules[$page]['title']) ?></h1><p class="muted"><?= e($modules[$page]['description']) ?></p></div><span class="date-chip">Tổng hồ sơ: <?= number_format($counts[$page] ?? 0) ?></span></div>
                    <section class="panel"><div class="panel-heading"><div><h2>Dashboard quy trình</h2><p class="muted">Khu vực quản lý hồ sơ, trạng thái và lịch sử.</p></div><button class="button primary" disabled>＋ Tạo hồ sơ (đang triển khai)</button></div>
                        <div class="notice">Khung Dashboard đã được tạo. Bước tiếp theo sẽ bổ sung bảng dữ liệu, bộ lọc, form tạo/sửa và các thao tác nghiệp vụ có kiểm soát cho phân hệ này.</div>
                    </section>
                <?php else: ?>
                    <div class="page-heading"><div><p class="eyebrow">SYSTEM ADMINISTRATION</p><h1><?= e(match($page) {'email-queue'=>'Email Queue','users'=>'Người dùng & phân quyền','settings'=>'Cấu hình hệ thống','audit'=>'Audit Log',default=>'Chức năng'}) ?></h1><p class="muted">Công cụ quản trị và vận hành hệ thống.</p></div></div>
                    <section class="panel"><div class="notice">Phân hệ quản trị này sẽ được triển khai tiếp theo trên nền schema MySQL và worker queue đã thiết kế.</div></section>
                <?php endif; ?>
            </section>
        </main>
    </div>
<?php endif; ?>
</body>
</html>
