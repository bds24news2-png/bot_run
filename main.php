<?php
// আপনার মূল টেলিগ্রাম বট টোকেন (যেটি দিয়ে ইউজাররা কন্ট্রোল করবে)
define('BOT_TOKEN', '8913679175:AAGCoNwZIM4tMydV4dEwmW8Cg_qwDyaWcOI');
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
    send_message($chat_id, "স্বাগতম! আপনার পাইথন বট লাইভ করতে নিচের নিয়মে পাঠান:\n\n1. `.py` ফাইলটি ডকুমেন্ট আকারে আপলোড করুন।\n2. ফাইলের **ক্যাপশনে** নিচের ফরম্যাটে তথ্য দিন:\n<code>[USER_BOT_TOKEN]|[GITHUB_TOKEN]</code>\n\n(উদাহরণ: 123456:ABC-DEF|ghp_YourGitHubToken)");
} 
elseif ($document) {
    $file_name = $document['file_name'];
    $file_id = $document['file_id'];
    
    // ক্যাপশন থেকে ইউজার বট টোকেন এবং গিটহাব টোকেন আলাদা করা (পাইপ | দিয়ে আলাদা থাকবে)
    $parts = explode('|', trim($caption));
    
    if (count($parts) < 2) {
        send_message($chat_id, "ভুল ফরম্যাট! ক্যাপশনে আপনার টেলিগ্রাম বট টোকেন এবং গিটহাব টোকেন এভাবে দিন:\n<code>বট_টোকেন|গিটহাব_টোকেন</code>");
        exit;
    }

    $user_bot_token = trim($parts[0]);
    $user_github_token = trim($parts[1]);

    if (pathinfo($file_name, PATHINFO_EXTENSION) !== 'py') {
        send_message($chat_id, "ভুল ফরম্যাট! শুধুমাত্র `.py` পাইথন ফাইল পাঠান।");
        exit;
    }

    if (empty($user_bot_token) || empty($user_github_token)) {
        send_message($chat_id, "বট টোকেন অথবা গিটহাব টোকেন খালি রাখা যাবে না!");
        exit;
    }

    // টেলিগ্রাম থেকে ফাইল ডাউনলোড করা
    $file_info = json_decode(file_get_contents(API_URL . "getFile?file_id=" . $file_id), true);
    if ($file_info['ok']) {
        $file_path = $file_info['result']['file_path'];
        $download_url = "https://api.telegram.org/file/bot" . BOT_TOKEN . "/" . $file_path;
        $python_code = file_get_contents($download_url);

        $final_code = "import os\nimport telebot\n\nTOKEN = '" . $user_bot_token . "'\n" . $python_code;

        $file_path_in_repo = "bot.py";
        $get_file_url = "https://api.github.com/repos/" . GITHUB_REPO . "/contents/" . $file_path_in_repo;
        
        // ইউজারের দেওয়া গিটহাব টোকেন দিয়ে গিটহবে রিকোয়েস্ট পাঠানো হবে
        $ch = curl_init($get_file_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: token " . $user_github_token,
            "User-Agent: PHP-Script"
        ]);
        $response = curl_exec($ch);
        $result = json_decode($response, true);
        $sha = $result['sha'] ?? null;
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code == 401 || $http_code == 403) {
            send_message($chat_id, "আপনার দেওয়া গিটহাব টোকেনটি সঠিক নয় বা মেয়াদোত্তীর্ণ! দয়া করে সঠিক টোকেন দিন।");
            exit;
        }

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
            "Authorization: token " . $user_github_token,
            "User-Agent: PHP-Script",
            "Content-Type: application/json"
        ]);
        $update_response = curl_exec($ch);
        curl_close($ch);

        send_message($chat_id, "সফল! আপনার কোড গিটহবে আপডেট হয়েছে। রেন্ডার (Render) এখন বটটি লাইভ করে দিচ্ছে।");
    } else {
        send_message($chat_id, "ফাইল প্রসেস করতে ব্যর্থ হয়েছে।");
    }
}
?>
