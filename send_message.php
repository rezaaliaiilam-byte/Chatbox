<?php
header('Content-Type: application/json; charset=utf-8');

set_exception_handler(function ($e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'خطای سرور: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
});

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/api.php';
require_once __DIR__ . '/../includes/conversations.php';
require_once __DIR__ . '/../includes/AIClient.php';
require_once __DIR__ . '/../includes/jalali.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'لطفاً ابتدا وارد شوید.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'متد نامعتبر.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$userId         = currentUserId();
$conversationId = isset($input['conversation_id']) ? (int)$input['conversation_id'] : null;
$userMessage    = trim($input['message'] ?? '');
$model          = $input['model'] ?? DEFAULT_MODEL;
$provider       = $input['provider'] ?? DEFAULT_PROVIDER;
$imageBase64    = $input['image'] ?? null;
$searchEnabled  = !empty($input['search_enabled']);

if (!in_array($provider, ['openrouter', 'gemini'], true)) {
    $provider = DEFAULT_PROVIDER;
}

if ($userMessage === '' && !$imageBase64) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'پیام نمی‌تواند خالی باشد.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ذخیره تصویر روی دیسک
$imagePath = null;
if ($imageBase64) {
    if (preg_match('/^data:image\/(png|jpe?g|gif|webp);base64,/', $imageBase64, $m)) {
        $ext = $m[1] === 'jpeg' ? 'jpg' : $m[1];
        $rawData = base64_decode(preg_replace('/^data:image\/[^;]+;base64,/', '', $imageBase64));
        if ($rawData && strlen($rawData) <= 10 * 1024 * 1024) {
            $filename = uniqid('img_', true) . '.' . $ext;
            $savePath = __DIR__ . '/../uploads/' . $filename;
            if (file_put_contents($savePath, $rawData)) {
                $imagePath = 'uploads/' . $filename;
            }
        }
    }
}

$isNewConversation = false;
if (!$conversationId) {
    $titleText = $userMessage ?: 'تحلیل تصویر';
    $title = generateTitleFromMessage($titleText);
    $conversationId = createConversation($userId, $title, $model, $provider);
    $isNewConversation = true;
} else {
    $conv = getConversation($conversationId, $userId);
    if (!$conv) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'مکالمه پیدا نشد.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $provider = $conv['provider'] ?? $provider;
}

addMessage($conversationId, 'user', $userMessage ?: '[تصویر]', 0, $imagePath);

$history = getConversationMessages($conversationId);
$history = array_slice($history, -20);

$todayShamsi = getShamsiDate();
$systemPrompt = "تو یک دستیار هوشمند و مفید هستی که به زبان فارسی پاسخ می‌دهی، مگر اینکه کاربر زبان دیگری بخواهد. تاریخ امروز: {$todayShamsi} شمسی.";

$apiMessages = [
    ['role' => 'system', 'content' => $systemPrompt]
];

foreach ($history as $msg) {
    if ($msg['role'] === 'system') continue;

    if ($msg['image_path'] && $msg['role'] === 'user') {
        $imgFullPath = __DIR__ . '/../' . $msg['image_path'];
        if (file_exists($imgFullPath)) {
            $imgData = base64_encode(file_get_contents($imgFullPath));
            $ext = pathinfo($msg['image_path'], PATHINFO_EXTENSION);
            $mime = match($ext) {
                'jpg', 'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'webp' => 'image/webp',
                default => 'image/jpeg',
            };

            $contentParts = [];
            if ($msg['content'] && $msg['content'] !== '[تصویر]') {
                $contentParts[] = ['type' => 'text', 'text' => $msg['content']];
            } else {
                $contentParts[] = ['type' => 'text', 'text' => 'این تصویر را تحلیل کن.'];
            }
            $contentParts[] = [
                'type' => 'image_url',
                'image_url' => ['url' => "data:{$mime};base64,{$imgData}"]
            ];

            $apiMessages[] = ['role' => 'user', 'content' => $contentParts];
        } else {
            $apiMessages[] = ['role' => $msg['role'], 'content' => $msg['content']];
        }
    } else {
        $apiMessages[] = ['role' => $msg['role'], 'content' => $msg['content']];
    }
}

// ابزارها (جستجوی وب)
$tools = [];
if ($searchEnabled && $provider === 'gemini') {
    $tools[] = ['type' => 'function', 'function' => [
        'name' => 'google_search',
        'description' => 'Search the web for current information',
    ]];
}

$client = new AIClient($provider);
$result = $client->chat($apiMessages, $model, DEFAULT_TEMPERATURE, DEFAULT_MAX_TOKENS, $searchEnabled);

if (!$result['success']) {
    echo json_encode([
        'success' => false,
        'error' => $result['error'] ?: 'خطای نامشخص در ارتباط با سرویس هوش مصنوعی.',
        'conversation_id' => $conversationId,
        'is_new_conversation' => $isNewConversation,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$assistantReply = $result['content'] ?? 'متأسفم، پاسخی دریافت نشد.';
$tokensUsed = $result['usage']['total_tokens'] ?? 0;

addMessage($conversationId, 'assistant', $assistantReply, $tokensUsed);

echo json_encode([
    'success' => true,
    'reply' => $assistantReply,
    'conversation_id' => $conversationId,
    'is_new_conversation' => $isNewConversation,
    'usage' => $result['usage'],
], JSON_UNESCAPED_UNICODE);
