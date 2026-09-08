<?php
// ─── Configuration ────────────────────────────────────────────────────────────
define('RECIPIENT_EMAIL', 'masteranalytics.india@gmail.com');
define('RECIPIENT_NAME',  'Master Analytics / The XL Academy');
define('SITE_NAME',       'The XL Academy Pune');
define('FALLBACK_CC',     'support@thexlacademy.com');

// ─── Security: only accept POST requests ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

// ─── Helper: sanitize input ───────────────────────────────────────────────────
function clean(string $value): string {
    return htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES, 'UTF-8');
}

// ─── Collect & validate fields ────────────────────────────────────────────────
$name      = clean($_POST['name']      ?? '');
$phone     = clean($_POST['phone']     ?? '');
$email     = clean($_POST['email']     ?? '');
$course    = clean($_POST['course']    ?? '');
$city      = clean($_POST['city']      ?? '');
$page_url  = clean($_POST['page_url']  ?? '');
$submitted_at = date('Y-m-d H:i:s');
$formatted_date = date('d M Y, h:i A');

// Check if request is AJAX / JSON
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
       || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
       || !empty($_POST['is_ajax']);

// Required fields
if (empty($name) || empty($phone) || empty($email)) {
    http_response_code(400);
    $err = ['status' => 'error', 'message' => 'Name, phone and email are required.'];
    if ($isAjax) {
        echo json_encode($err);
    } else {
        echo "<script>alert('Please fill all required fields.'); history.back();</script>";
    }
    exit;
}

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    $err = ['status' => 'error', 'message' => 'Invalid email address.'];
    if ($isAjax) {
        echo json_encode($err);
    } else {
        echo "<script>alert('Invalid email address.'); history.back();</script>";
    }
    exit;
}

// ─── Send data to Google Sheets ───────────────────────────────────────────────
$googleScriptUrl = "https://script.google.com/macros/s/AKfycbzL16YOftt4cROcXEXOmhJ3RjuYkzS4drv5GHPjUG5uH3X-iq0jpg9PckMswEOZrbcW0Q/exec";

$payload = json_encode([
    "name"         => $name,
    "phone"        => $phone,
    "email"        => $email,
    "course"       => $course,
    "city"         => $city,
    "page_url"     => $page_url,
    "timestamp"    => $submitted_at,
    "source"       => "Pune Landing Page"
]);

$sheetSuccess = false;

// 1. Try via cURL with redirect following
if (function_exists('curl_init')) {
    $ch = curl_init($googleScriptUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    $sheetResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode >= 200 && $httpCode < 400) {
        $sheetSuccess = true;
    }
}

// 2. Fallback via file_get_contents stream if cURL is unavailable
if (!$sheetSuccess) {
    $options = [
        'http' => [
            'method'          => 'POST',
            'header'          => "Content-Type: application/json\r\nAccept: application/json\r\n",
            'content'         => $payload,
            'timeout'         => 8,
            'follow_location' => 1,
            'ignore_errors'   => true
        ],
        'ssl' => [
            'verify_peer'      => false,
            'verify_peer_name' => false
        ]
    ];
    $context = stream_context_create($options);
    $streamResp = @file_get_contents($googleScriptUrl, false, $context);
    if ($streamResp !== false) {
        $sheetSuccess = true;
    }
}

// ─── Build Notification Email to Administrator ────────────────────────────────
$subject = "🔥 New Pune LP Lead: {$name} (" . (!empty($course) ? $course : 'Data Science & Analytics') . ")";

$body = "
<!DOCTYPE html>
<html lang='en'>
<head>
  <meta charset='UTF-8'>
  <style>
    body        { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f6fb; margin: 0; padding: 0; }
    .wrapper    { max-width: 600px; margin: 30px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
    .header     { background: linear-gradient(135deg, #1B2B6B, #0F1A42); padding: 30px 36px; }
    .header h1  { color: #fff; margin: 0; font-size: 22px; letter-spacing: 0.5px; }
    .header p   { color: rgba(255,255,255,0.85); margin: 6px 0 0; font-size: 13px; }
    .body       { padding: 32px 36px; }
    .field      { margin-bottom: 18px; }
    .label      { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #1B2B6B; margin-bottom: 4px; }
    .value      { font-size: 15px; color: #0F172A; font-weight: 600; background: #f8faff; border-left: 3px solid #E8470A; padding: 10px 14px; border-radius: 6px; }
    .footer     { background: #0A1628; padding: 18px 36px; text-align: center; color: rgba(255,255,255,0.6); font-size: 12px; }
    .badge      { display: inline-block; background: #E8470A; color: #fff; font-size: 11px; font-weight: 700; padding: 4px 12px; border-radius: 100px; margin-bottom: 20px; }
    .sheet-badge { display: inline-block; background: #10B981; color: #fff; font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 6px; margin-left: 6px; }
  </style>
</head>
<body>
  <div class='wrapper'>
    <div class='header'>
      <h1>📩 New Pune Course Enquiry</h1>
      <p>Received on {$formatted_date} IST</p>
    </div>
    <div class='body'>
      <span class='badge'>New Lead</span>
      " . ($sheetSuccess ? "<span class='sheet-badge'>✓ Saved to Google Sheet</span>" : "") . "

      <div class='field'>
        <div class='label'>Full Name</div>
        <div class='value'>{$name}</div>
      </div>

      <div class='field'>
        <div class='label'>Mobile Number (Click to Call)</div>
        <div class='value'><a href='tel:{$phone}' style='color:#1B2B6B; text-decoration:none;'>📞 {$phone}</a> &nbsp;|&nbsp; <a href='https://wa.me/" . preg_replace('/[^0-9]/', '', $phone) . "' style='color:#25D366; text-decoration:none;'>💬 WhatsApp</a></div>
      </div>

      <div class='field'>
        <div class='label'>Email Address</div>
        <div class='value'><a href='mailto:{$email}' style='color:#1B2B6B;'>{$email}</a></div>
      </div>

      <div class='field'>
        <div class='label'>Course Interested In</div>
        <div class='value'>" . (!empty($course) ? $course : 'Data Analytics & Data Science') . "</div>
      </div>

      <div class='field'>
        <div class='label'>City / Location</div>
        <div class='value'>" . (!empty($city) ? $city : 'Pune') . "</div>
      </div>

      <div class='field'>
        <div class='label'>Landing Page URL</div>
        <div class='value'><a href='{$page_url}' style='color:#E8470A;'>" . (!empty($page_url) ? $page_url : 'Pune Landing Page') . "</a></div>
      </div>
    </div>
    <div class='footer'>
      © " . date('Y') . " " . SITE_NAME . " &nbsp;|&nbsp; Sent to " . RECIPIENT_EMAIL . "
    </div>
  </div>
</body>
</html>
";

// ─── Email headers to Administrator ───────────────────────────────────────────
$to      = RECIPIENT_NAME . ' <' . RECIPIENT_EMAIL . '>';
$headers = implode("\r\n", [
    'MIME-Version: 1.0',
    'Content-Type: text/html; charset=UTF-8',
    'From: ' . SITE_NAME . ' <no-reply@thexlacademy.com>',
    'Reply-To: ' . $name . ' <' . $email . '>',
    'Cc: ' . FALLBACK_CC,
    'X-Mailer: PHP/' . phpversion(),
]);

// Failsafe local backup log on server
$logEntry = "[" . date('Y-m-d H:i:s') . "] Name: {$name} | Phone: {$phone} | Email: {$email} | Course: {$course} | City: {$city}\n";
@file_put_contents(__DIR__ . '/leads_backup.log', $logEntry, FILE_APPEND | LOCK_EX);

// Send email to admin with envelope sender (Hostinger/cPanel standard)
$sent = @mail($to, $subject, $body, $headers, '-f no-reply@thexlacademy.com');
if (!$sent) {
    $sent = @mail($to, $subject, $body, $headers);
}

// ─── Auto-reply to the enquirer ───────────────────────────────────────────────
$autoSubject = "Thank you for your enquiry — " . SITE_NAME;
$autoBody = "
<!DOCTYPE html>
<html lang='en'>
<head>
  <meta charset='UTF-8'>
  <style>
    body       { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f6fb; margin: 0; padding: 0; }
    .wrapper   { max-width: 600px; margin: 30px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
    .header    { background: linear-gradient(135deg, #0A1628, #1B2B6B); padding: 30px 36px; text-align: center; }
    .header h1 { color: #fff; margin: 0; font-size: 22px; }
    .header p  { color: rgba(255,255,255,0.7); margin: 8px 0 0; font-size: 13px; }
    .body      { padding: 32px 36px; color: #444; font-size: 15px; line-height: 1.7; }
    .highlight { background: #fff7f4; border-left: 4px solid #E8470A; padding: 14px 18px; border-radius: 6px; margin: 20px 0; font-weight: 600; color: #1B2B6B; }
    .btn       { display: inline-block; background: linear-gradient(135deg, #E8470A, #ff6b35); color: #fff; text-decoration: none; padding: 14px 32px; border-radius: 50px; font-weight: 700; font-size: 15px; margin: 20px 0; }
    .footer    { background: #0A1628; padding: 18px 36px; text-align: center; color: rgba(255,255,255,0.6); font-size: 12px; }
  </style>
</head>
<body>
  <div class='wrapper'>
    <div class='header'>
      <h1>🎉 Thank You, {$name}!</h1>
      <p>Your enquiry has been received successfully.</p>
    </div>
    <div class='body'>
      <p>Hi <strong>{$name}</strong>,</p>
      <p>Thank you for your interest in <strong>" . SITE_NAME . "</strong>. Our senior career counsellor will connect with you within <strong>2 hours</strong> to guide you on syllabus, upcoming batch timings, and scholarship options.</p>

      <div class='highlight'>
        📚 Course of Interest: " . (!empty($course) ? $course : 'Data Science & Data Analytics') . "<br>
        🏙️ Location / Mode: " . (!empty($city) ? $city : 'Pune (Offline / Online)') . "<br>
        📱 Contact Number: {$phone}
      </div>

      <p>Need instant guidance? Call or WhatsApp our Pune counselling desk:</p>
      <a href='tel:+917011062944' class='btn'>📞 Call: +91 70110 62944</a>

      <p style='color:#888; font-size:13px;'>Monday – Saturday · 9:00 AM to 7:00 PM IST</p>
    </div>
    <div class='footer'>
      © " . date('Y') . " " . SITE_NAME . " &nbsp;|&nbsp; Pune Training Center
    </div>
  </div>
</body>
</html>
";

$autoHeaders = implode("\r\n", [
    'MIME-Version: 1.0',
    'Content-Type: text/html; charset=UTF-8',
    'From: ' . SITE_NAME . ' <no-reply@thexlacademy.com>',
    'Reply-To: ' . RECIPIENT_EMAIL,
    'X-Mailer: PHP/' . phpversion(),
]);

@mail($email, $autoSubject, $autoBody, $autoHeaders);

// ─── Response Handling ────────────────────────────────────────────────────────
if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode([
        'status'   => 'success',
        'message'  => 'Enquiry submitted successfully!',
        'redirect' => 'thankyou.html',
        'sheet'    => $sheetSuccess
    ]);
    exit;
}

// Redirect to Thank You Page
header('Location: thankyou.html');
exit;
