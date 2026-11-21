<?php
$isCli = PHP_SAPI === 'cli';
if ($isCli) {
    require_once __DIR__ . '/config/database.php';
} else {
    require_once __DIR__ . '/auth/bootstrap.php';
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, 'Method not allowed.', [], 405);
    if (empty($_SESSION['user_id'])) jsonResponse(false, 'Please sign in first.', [], 401);
}
require_once __DIR__ . '/vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;

$mailConfigPath = getenv('METERLY_MAIL_CONFIG') ?: 'C:/xampp/private/meterly_mail.php';
try {
    if (!is_file($mailConfigPath)) {
        $message = 'Email reminders are not configured. Add the private Gmail settings file before sending reminders.';
        if ($isCli) { fwrite(STDERR, $message . "\n"); exit(1); }
        jsonResponse(false, $message, [], 503);
    }
    $mailConfig = require $mailConfigPath;
    foreach (['host','port','username','password','from_email'] as $key) {
        if (empty($mailConfig[$key])) throw new RuntimeException('Missing Gmail configuration: ' . $key);
    }

    $sql = "SELECT
                b.id AS bill_id,
                b.bill_title,
                b.amount,
                b.due_date,
                u.full_name,
                u.email,
                DATEDIFF(b.due_date, CURDATE()) AS days_remaining
            FROM bills b
            INNER JOIN utility_accounts ua
                ON ua.id = b.utility_account_id
            INNER JOIN users u
                ON u.id = ua.user_id
            LEFT JOIN bill_reminder_preferences brp
                ON brp.bill_id = b.id
                AND brp.user_id = ua.user_id
            WHERE b.status IN ('unpaid', 'overdue')
              AND DATEDIFF(b.due_date, CURDATE()) IN (7, 3, 0)
              AND COALESCE(brp.enabled, 1) = 1";

    $params = [];

    // Browser requests are restricted to the signed-in customer's own bills.
    // CLI runs process all customers' bills, while still respecting preferences.
    if (!$isCli) {
        $sql .= " AND ua.user_id = ?";
        $params[] = (int) $_SESSION['user_id'];
    }

    $sql .= " ORDER BY b.due_date ASC";
    $stmt = $pdo->prepare($sql); $stmt->execute($params); $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $sent = 0; $skipped = 0; $failed = 0;
    foreach ($bills as $bill) {
        $billId = (int)$bill['bill_id'];
        try {
            $days = (int)$bill['days_remaining'];
            $timing = $days === 0 ? 'today' : $days . ' days';
            $check = $pdo->prepare("SELECT id, status FROM reminders WHERE bill_id = ? AND reminder_date = CURDATE() ORDER BY id DESC LIMIT 1");
            $check->execute([$billId]); $existing = $check->fetch(PDO::FETCH_ASSOC);
            if ($existing && in_array($existing['status'], ['sent','cancelled'], true)) { $skipped++; continue; }
            if ($existing) { $reminderId = (int)$existing['id']; }
            else {
                $insert = $pdo->prepare("INSERT INTO reminders (bill_id, reminder_date, message, status) VALUES (?, CURDATE(), ?, 'pending')");
                $insert->execute([$billId, 'Your bill is due ' . $timing . '.']); $reminderId = (int)$pdo->lastInsertId();
            }
            $safeName = htmlspecialchars($bill['full_name'], ENT_QUOTES, 'UTF-8');
            $safeTitle = htmlspecialchars($bill['bill_title'], ENT_QUOTES, 'UTF-8');
            $amount = number_format((float)$bill['amount'], 2);
            $dueDate = date('d M Y', strtotime($bill['due_date']));
            $heading = $days === 0 ? 'Your bill is due today' : 'Your bill is due in ' . $days . ' days';
            $mail = new PHPMailer(true); $mail->isSMTP(); $mail->Host = $mailConfig['host'];
            $mail->SMTPAuth = true; $mail->Username = $mailConfig['username'];
            $mail->Password = preg_replace('/\s+/', '', (string)$mailConfig['password']);
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; $mail->Port = (int)$mailConfig['port'];
            $mail->Timeout = 25; $mail->CharSet = 'UTF-8';
            $mail->setFrom($mailConfig['from_email'], $mailConfig['from_name'] ?? 'Meterly');
            $mail->addAddress($bill['email'], $bill['full_name']); $mail->isHTML(true);
            $mail->Subject = $days === 0 ? 'Your Meterly bill is due today' : 'Meterly bill reminder: Due in ' . $days . ' days';
            $mail->Body = '<div style="font-family:Arial,sans-serif;max-width:560px;margin:auto;color:#18241f;line-height:1.6"><h2 style="color:#2f7654">Meterly Bill Reminder</h2><p>Hello ' . $safeName . ',</p><h3>' . $heading . '</h3><p><strong>Bill:</strong> ' . $safeTitle . '</p><p><strong>Amount:</strong> NGN ' . $amount . '</p><p><strong>Due date:</strong> ' . $dueDate . '</p><p>Please make payment on or before the due date.</p><p>Thank you for using Meterly.</p></div>';
            $mail->AltBody = "Hello {$bill['full_name']},\n{$heading}.\nBill: {$bill['bill_title']}\nAmount: NGN {$amount}\nDue date: {$dueDate}\nPlease make payment on or before the due date.";
            $mail->send();
            $update = $pdo->prepare("UPDATE reminders SET status = 'sent' WHERE id = ?"); $update->execute([$reminderId]);
            $sent++;
        } catch (Throwable $e) {
            error_log('Meterly reminder error for bill #' . $billId . ': ' . $e->getMessage()); $failed++;
        }
    }
    if ($isCli) {
        echo "Reminder process completed.\nSent: {$sent}\nSkipped: {$skipped}\nFailed: {$failed}\n";
    } else {
        jsonResponse(true, $sent ? 'Reminder emails processed.' : 'No new reminder emails were due.', ['sent'=>$sent,'skipped'=>$skipped,'failed'=>$failed]);
    }
} catch (Throwable $e) {
    error_log('Meterly reminder system error: ' . $e->getMessage());
    if ($isCli) { fwrite(STDERR, "Reminder system error. Check the PHP error log.\n"); exit(1); }
    jsonResponse(false, 'The reminder process could not be completed. Check the server error log.', [], 500);
}
