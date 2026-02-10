<?php
session_start();
if (!isset($_SESSION['enrollment_complete'])) {
    header("Location: enroll.php?step=1");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enrollment Complete - NovaTech</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container my-5">
        <div class="text-center">
            <div class="display-1">🎉</div>
            <h1>Enrollment Successful!</h1>
            <p class="lead">Welcome to NovaTech FET College!</p>
            <div class="alert alert-success">
                <h4>Your Account Details:</h4>
                <p><strong>Email:</strong> <?= htmlspecialchars($_SESSION['final_data']['email'] ?? 'your email') ?></p>
                <p><strong>Password:</strong> <?= htmlspecialchars($_SESSION['final_data']['password'] ?? 'your password') ?></p>
                <p>You can change your password after logging in.</p>
            </div>
            <a href="index.php" class="btn btn-primary btn-lg">Go to Homepage</a>
            <a href="login.html" class="btn btn-outline-secondary btn-lg">Login Now</a>
        </div>
    </div>
</body>
</html>