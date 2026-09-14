<?php
// আপনার টেলিগ্রাম বট টোকেন
define('BOT_TOKEN', '8913679175:AAGCoNwZIM4tMydV4dEwmW8Cg_qwDyaWcOI');

// আপনার দেওয়া গিটহাব ইউজারনেম এবং রিপোজিটরি
define('GITHUB_TOKEN', 'YOUR_GITHUB_PERSONAL_ACCESS_TOKEN'); // এখানে আপনার গিটহাব টোকেন বসাবেন
define('GITHUB_REPO', 'bds24news2-png/bot_run'); 

define('API_URL', 'https://api.telegram.org/bot' . BOT_TOKEN . '/');

$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (!$update) exit;

$chat_id = $update['message']['chat']['id'] ?? null;
$text = $update['message']['text'] ?? '';
$document = $update['message']['document'] ?? null;
$caption = $update['message']['caption'] ?? ''; 

function send_message($chat_id, $text) {
    file_get_contents(API_URL . "sendMessage?chat_id=" . $chat_id . "&text=" . urlencode($text) . "&parse_mode=HTML");
}

if ($text == '/start') {
    send_message($chat_id, "স্বাগতম! আপনার পাইথন বটের মূল `.py` ফাইলটি এখানে ডকুমেন্ট আকারে পাঠান। সাথে ক্যাপশনে আপনার ইউজারের বটের টেলিগ্রাম টোকেনটি লিখে দিন।");
} 
elseif ($document) {
    $file_name = $document['file_name'];
    $file_id = $document['file_id'];
    $user_bot_token = trim($caption); 

    if (pathinfo($file_name, PATHINFO_EXTENSION) !== 'py') {
        send_message($chat_id, "ভুল ফরম্যাট! শুধুমাত্র `.py` পাইথন ফাইল পাঠান।");
        exit;
    }

    if (empty($user_bot_token)) {
        send_message($chat_id, "ফাইল পাঠানোর সময় ক্যাপশনে ইউজারের বটের টেলিগ্রাম টোকেনটি লিখে দিন!");
        exit;
    }

    $file_info = json_decode(file_get_contents(API_URL . "getFile?file_id=" . $file_id), true);
    if ($file_info['ok']) {
        $file_path = $file_info['result']['file_path'];
        $download_url = "https://api.telegram.org/file/bot" . BOT_TOKEN . "/" . $file_path;
        $python_code = file_get_contents($download_url);

        $final_code = "import os\nimport telebot\n\nTOKEN = '" . $user_bot_token . "'\n" . $python_code;

        $file_path_in_repo = "bot.py";
        $get_file_url = "https://api.github.com/repos/" . GITHUB_REPO . "/contents/" . $file_path_in_repo;
        
        $ch = curl_init($get_file_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: token " . GITHUB_TOKEN,
            "User-Agent: PHP-Script"
        ]);
        $response = curl_exec($ch);
        $result = json_decode($response, true);
        $sha = $result['sha'] ?? null;
        curl_close($ch);

        $data = [
            "message" => "New bot deployed by user " . $chat_id,
            "content" => base64_encode($final_code),
            "branch" => "main"
        ];
        if ($sha) {
            $data["sha"] = $sha;
        }

        $ch = curl_init($get_file_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: token " . GITHUB_TOKEN,
            "User-Agent: PHP-Script",
            "Content-Type: application/json"
        ]);
        $update_response = curl_exec($ch);
        curl_close($ch);

        send_message($chat_id, "আপনার কোড সফলভাবে গিটহবে আপডেট হয়েছে! রেন্ডার (Render) এখন অটোমেটিক বটটি লাইভ করে দিচ্ছে। কয়েক মিনিট অপেক্ষা করুন।");
    } else {
        send_message($chat_id, "ফাইল প্রসেস করতে ব্যর্থ হয়েছে।");
    }
}
?>

