<?php
// ─── Configuration ────────────────────────────────────────────────────────────
define('RECIPIENT_EMAIL', 'support@thexlacademy.com');
define('RECIPIENT_NAME',  'The XL Academy');
define('SITE_NAME',       'The XL Academy');

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
$name    = clean($_POST['name']    ?? '');
$phone   = clean($_POST['phone']   ?? '');
$email   = clean($_POST['email']   ?? '');
$course  = clean($_POST['course']  ?? '');
$city    = clean($_POST['city']    ?? '');
$page_url = clean($_POST['page_url'] ?? '');

// Required fields
if (empty($name) || empty($phone) || empty($email)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Name, phone and email are required.']);
    exit;
}

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid email address.']);
    exit;
}


// ─── Send data to Google Sheets ───────────────────────────────────────────────
$googleScriptUrl = "https://script.google.com/macros/s/AKfycbzL16YOftt4cROcXEXOmhJ3RjuYkzS4drv5GHPjUG5uH3X-iq0jpg9PckMswEOZrbcW0Q/exec";

$payload = json_encode([
    "name"     => $name,
    "phone"    => $phone,
    "email"    => $email,
    "course"   => $course,
    "city"     => $city,
    "page_url" => $page_url
]);

$ch = curl_init($googleScriptUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

$response = curl_exec($ch);
curl_close($ch);


// ─── Build the email ──────────────────────────────────────────────────────────
$subject = "New Pune LP Enquiry from {$name} — " . SITE_NAME;

$body = "
<!DOCTYPE html>
<html lang='en'>
<head>
  <meta charset='UTF-8'>
  <style>
    body        { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f6fb; margin: 0; padding: 0; }
    .wrapper    { max-width: 600px; margin: 30px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
    .header     { background: linear-gradient(135deg, #E8470A, #F5A623); padding: 30px 36px; }
    .header h1  { color: #fff; margin: 0; font-size: 22px; letter-spacing: 0.5px; }
    .header p   { color: rgba(255,255,255,0.85); margin: 6px 0 0; font-size: 13px; }
    .body       { padding: 32px 36px; }
    .field      { margin-bottom: 20px; }
    .label      { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #E8470A; margin-bottom: 4px; }
    .value      { font-size: 15px; color: #1A3A6B; font-weight: 600; background: #f8faff; border-left: 3px solid #E8470A; padding: 10px 14px; border-radius: 6px; }
    .footer     { background: #0D1B35; padding: 18px 36px; text-align: center; color: rgba(255,255,255,0.5); font-size: 12px; }
    .badge      { display: inline-block; background: #E8470A; color: #fff; font-size: 11px; font-weight: 700; padding: 4px 12px; border-radius: 100px; margin-bottom: 20px; }
  </style>
</head>
<body>
  <div class='wrapper'>
    <div class='header'>
      <h1>📩 New Course Enquiry</h1>
      <p>Received on " . date('d M Y, h:i A') . " IST</p>
    </div>
    <div class='body'>
      <span class='badge'>Lead Details</span>

      <div class='field'>
        <div class='label'>Full Name</div>
        <div class='value'>{$name}</div>
      </div>

      <div class='field'>
        <div class='label'>Mobile Number</div>
        <div class='value'>{$phone}</div>
      </div>

      <div class='field'>
        <div class='label'>Email Address</div>
        <div class='value'>{$email}</div>
      </div>

      <div class='field'>
        <div class='label'>Course Interested In</div>
        <div class='value'>" . (!empty($course) ? $course : '—') . "</div>
      </div>

      <div class='field'>
        <div class='label'>City</div>
        <div class='value'>" . (!empty($city) ? $city : '—') . "</div>
      </div>

      <div class='field'>
        <div class='label'>Page URL</div>
        <div class='value'><a href='{$page_url}' style='color:#E8470A;'>" . (!empty($page_url) ? $page_url : '—') . "</a></div>
      </div>
    </div>
    <div class='footer'>
      © " . date('Y') . " " . SITE_NAME . " &nbsp;|&nbsp; This is an automated enquiry notification.
    </div>
  </div>
</body>
</html>
";

// ─── Email headers ────────────────────────────────────────────────────────────
$to      = RECIPIENT_NAME . ' <' . RECIPIENT_EMAIL . '>';
$headers = implode("\r\n", [
    'MIME-Version: 1.0',
    'Content-Type: text/html; charset=UTF-8',
    'From: ' . SITE_NAME . ' <no-reply@thexlacademy.com>',
    'Reply-To: ' . $name . ' <' . $email . '>',
    'X-Mailer: PHP/' . phpversion(),
]);

// ─── Send email ───────────────────────────────────────────────────────────────
$sent = mail($to, $subject, $body, $headers);

// ─── Auto-reply to the enquirer ───────────────────────────────────────────────
if ($sent) {
    $autoSubject = "Thank you for your enquiry — " . SITE_NAME;
    $autoBody = "
<!DOCTYPE html>
<html lang='en'>
<head>
  <meta charset='UTF-8'>
  <style>
    body       { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f6fb; margin: 0; padding: 0; }
    .wrapper   { max-width: 600px; margin: 30px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
    .header    { background: linear-gradient(135deg, #0D1B35, #1A3A6B); padding: 30px 36px; text-align: center; }
    .header h1 { color: #fff; margin: 0; font-size: 22px; }
    .header p  { color: rgba(255,255,255,0.7); margin: 8px 0 0; font-size: 13px; }
    .body      { padding: 32px 36px; color: #444; font-size: 15px; line-height: 1.7; }
    .highlight { background: #fff7f4; border-left: 4px solid #E8470A; padding: 14px 18px; border-radius: 6px; margin: 20px 0; font-weight: 600; color: #1A3A6B; }
    .btn       { display: inline-block; background: linear-gradient(135deg, #E8470A, #ff6b35); color: #fff; text-decoration: none; padding: 14px 32px; border-radius: 50px; font-weight: 700; font-size: 15px; margin: 20px 0; }
    .footer    { background: #0D1B35; padding: 18px 36px; text-align: center; color: rgba(255,255,255,0.5); font-size: 12px; }
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
      <p>Thank you for reaching out to <strong>" . SITE_NAME . "</strong>. We have received your enquiry and our career counsellor will call you within <strong>2 hours</strong>.</p>

      <div class='highlight'>
        📚 Course of Interest: " . (!empty($course) ? $course : 'Not specified') . "<br>
        🏙️ City: " . (!empty($city) ? $city : 'Not specified') . "<br>
        📱 We'll call you on: {$phone}
      </div>

      <p>In the meantime, feel free to call or WhatsApp us directly:</p>
      <a href='tel:+917428703467' class='btn'>📞 Call: +91 74287 03467</a>

      <p style='color:#888; font-size:13px;'>Mon–Sat · 9 AM to 7 PM IST</p>
    </div>
    <div class='footer'>
      © " . date('Y') . " " . SITE_NAME . " &nbsp;|&nbsp; Dwarka Sec-13, New Delhi – 110078
    </div>
  </div>
</body>
</html>
";

    $autoHeaders = implode("\r\n", [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . SITE_NAME . ' <no-reply@thexlacademy.com>',
        'Reply-To: support@thexlacademy.com',
        'X-Mailer: PHP/' . phpversion(),
    ]);

    mail($email, $autoSubject, $autoBody, $autoHeaders);
}

// ─── Response ─────────────────────────────────────────────────────────────────
if ($sent) {
    // Redirect to thank you page
    header('Location: thankyou.html');
    exit;
} else {
    // Redirect back with error flag
    header('Location: index.html');
    exit;
}
