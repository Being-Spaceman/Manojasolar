<?php
header('Content-Type: application/json');

$to = 'contact@manojasolar.in';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'method_not_allowed']);
    exit;
}

$name = trim($_POST['name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$message = trim($_POST['message'] ?? '');

if ($name === '' || $phone === '' || $message === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'missing_fields']);
    exit;
}

if (!preg_match('/^[0-9+ ]{10,15}$/', $phone)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'invalid_phone']);
    exit;
}

function clean_header_value($value) {
    return str_replace(["\r", "\n"], '', $value);
}

$name = clean_header_value($name);
$phone = clean_header_value($phone);

$subject = 'New enquiry from Manojasolar.in';
$body = "Name: $name\nPhone: $phone\nMessage:\n$message\n";

$headers = "From: no-reply@manojasolar.in\r\n";
$headers .= "Reply-To: no-reply@manojasolar.in\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

$sent = @mail($to, $subject, $body, $headers);

if ($sent) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'mail_failed']);
}
