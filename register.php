<?php
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';
    $fullName = $_POST['full_name'] ?? '';

    if ($password !== $confirm) {
        $error = 'رمز عبور و تکرار آن یکسان نیستند.';
    } else {
        $result = registerUser($username, $password, $fullName);
        if ($result['success']) {
            $success = true;
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ثبت‌نام | چت‌بات هوشمند</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-card">
            <h1>ساخت حساب کاربری 🚀</h1>
            <p class="subtitle">در چند ثانیه ثبت‌نام کن و چت رو شروع کن</p>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">ثبت‌نام با موفقیت انجام شد! حالا می‌تونی وارد بشی.</div>
                <a href="login.php" class="btn" style="display:block; text-align:center; text-decoration:none; box-sizing:border-box;">رفتن به صفحه ورود</a>
            <?php else: ?>
                <form method="POST">
                    <div class="form-group">
                        <label>نام کامل (اختیاری)</label>
                        <input type="text" name="full_name">
                    </div>
                    <div class="form-group">
                        <label>نام کاربری</label>
                        <input type="text" name="username" required>
                    </div>
                    <div class="form-group">
                        <label>رمز عبور</label>
                        <input type="password" name="password" required>
                    </div>
                    <div class="form-group">
                        <label>تکرار رمز عبور</label>
                        <input type="password" name="confirm" required>
                    </div>
                    <button type="submit" class="btn">ثبت‌نام</button>
                </form>

                <div class="auth-switch">
                    قبلاً ثبت‌نام کردید؟ <a href="login.php">وارد شوید</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
