<?php
declare(strict_types=1);

session_start();

if (!empty($_SESSION['admin_logged_in'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (
        hash_equals('Administrator', $username) &&
        hash_equals('Administrator@111', $password)
    ) {
        session_regenerate_id(true);

        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = 'Administrator';

        header('Location: index.php');
        exit;
    }

    $error = 'Invalid username or password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Login | SmartPOS</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f3f4f8;
            font-family: Arial, sans-serif;
        }

        .login-card {
            width: 100%;
            max-width: 410px;
            padding: 32px;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, .07);
        }

        .logo {
            width: 54px;
            height: 54px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            color: #fff;
            background: #6366f1;
            border-radius: 12px;
            font-size: 28px;
        }

        .form-control {
            min-height: 46px;
            border-radius: 8px;
        }

        .btn-login {
            min-height: 46px;
            background: #4f46e5;
            color: #fff;
            border: none;
            border-radius: 8px;
        }

        .btn-login:hover {
            background: #4338ca;
            color: #fff;
        }
    </style>
</head>

<body>

<div class="login-card">

    <div class="text-center mb-4">
        <div class="logo">⚡</div>
        <h3 class="fw-bold">SmartPOS</h3>
        <p class="text-secondary">Administrator Login</p>
    </div>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger" role="alert">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="login.php">

        <div class="mb-3">
            <label for="username" class="form-label">
                Username
            </label>

            <input
                type="text"
                id="username"
                name="username"
                class="form-control"
                placeholder="Enter username"
                autocomplete="username"
                required
                autofocus
            >
        </div>

        <div class="mb-4">
            <label for="password" class="form-label">
                Password
            </label>

            <input
                type="password"
                id="password"
                name="password"
                class="form-control"
                placeholder="Enter password"
                autocomplete="current-password"
                required
            >
        </div>

        <button type="submit" class="btn btn-login w-100">
            Login
        </button>

    </form>

    <p class="text-center text-secondary small mt-4 mb-0">
        &copy; <?= date('Y') ?> SmartPOS
    </p>

</div>

</body>
</html>
