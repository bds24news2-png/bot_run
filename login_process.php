<?php
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sessionid = trim($_POST['sessionid'] ?? '');

    if (empty($sessionid)) {
        echo json_encode(['status' => 'error', 'error' => 'Session ID cannot be empty!']);
        exit;
    }

    // ইনস্টাগ্রামের প্রোফাইল এন্ডপয়েন্টে কার্ল (cURL) রিকোয়েস্ট পাঠিয়ে কুকি ভ্যালিডেশন চেক করা
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://i.instagram.com/api/v1/accounts/current_user/?edit=true');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'User-Agent: Instagram 219.0.0.12.117 Android',
        'Cookie: sessionid=' . $sessionid
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $data = json_decode($response, true);
        
        if (isset($data['status']) && $data['status'] === 'ok') {
            // কুকি ভ্যালিড হলে ইউজারের সেশন স্টার্ট করুন
            $_InstaUser = $data['user'];
            $_SESSION['insta_logged_in'] = true;
            $_SESSION['insta_username'] = $_InstaUser['username'];
            $_SESSION['insta_userid'] = $_InstaUser['pk'];
            $_SESSION['insta_sessionid'] = $sessionid;

            echo json_encode([
                'status' => 'success',
                'returnUrl' => 'dashboard.php'
            ]);
            exit;
        }
    }

    echo json_encode(['status' => 'error', 'error' => 'Expired or invalid session ID!']);
    exit;
}
