<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/api.php';
require_once __DIR__ . '/includes/conversations.php';

requireLogin();

$userId = currentUserId();
$conversations = getUserConversations($userId);

$activeConvId = isset($_GET['c']) ? (int)$_GET['c'] : null;
$activeConv = null;
$messages = [];

if ($activeConvId) {
    $activeConv = getConversation($activeConvId, $userId);
    if ($activeConv) {
        $messages = getConversationMessages($activeConvId);
    } else {
        $activeConvId = null;
    }
}

// مدل‌ها به تفکیک هر سرویس (provider)
$modelsByProvider = [
    'openrouter' => [
        'openrouter/auto' => 'Auto (رایگان)',
        'google/gemma-4-31b-it:free' => 'Gemma 4 31B (رایگان)',
    ],
    'gemini' => [
        'gemini-2.5-flash-lite' => 'Gemini 2.5 Flash-Lite (سریع)',
        'gemini-2.5-flash' => 'Gemini 2.5 Flash',
        'gemini-3.5-flash' => 'Gemini 3.5 Flash (جدید)',
        'gemini-3.1-flash-lite' => 'Gemini 3.1 Flash-Lite',
        'gemini-3-flash-preview' => 'Gemini 3 Flash Preview',
        'gemini-2.5-pro' => 'Gemini 2.5 Pro (محدود)',
        'gemini-3.1-pro-preview' => 'Gemini 3.1 Pro Preview (محدود)',
    ],
];

$activeProvider = $activeConv['provider'] ?? DEFAULT_PROVIDER;
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>چت‌بات هوشمند</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="chat-app">
        <!-- نوار کناری -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="app-title">💬 چت‌بات هوشمند</div>
                <button class="new-chat-btn" id="newChatBtn">+ گفتگوی جدید</button>
            </div>
            <div class="conversation-list" id="conversationList">
                <?php foreach ($conversations as $conv): ?>
                    <a href="index.php?c=<?= $conv['id'] ?>"
                       class="conversation-item <?= ($activeConvId == $conv['id']) ? 'active' : '' ?>"
                       data-id="<?= $conv['id'] ?>">
                        <span class="conv-title"><?= htmlspecialchars($conv['title']) ?></span>
                        <button class="del-btn" data-id="<?= $conv['id'] ?>" title="حذف گفتگو">🗑</button>
                    </a>
                <?php endforeach; ?>
            </div>
            <div class="sidebar-footer">
                <span>👤 <?= htmlspecialchars(currentUsername()) ?></span>
                <a href="logout.php">خروج</a>
            </div>
        </aside>

        <!-- بخش اصلی چت -->
        <main class="chat-main">
            <div class="chat-topbar">
                <span style="font-size:14px; color:var(--text-secondary);">
                    <?= $activeConv ? htmlspecialchars($activeConv['title']) : 'گفتگوی جدید' ?>
                </span>
                <div style="display:flex; gap:8px;">
                    <select id="providerSelect">
                        <option value="openrouter" <?= $activeProvider === 'openrouter' ? 'selected' : '' ?>>OpenRouter</option>
                        <option value="gemini" <?= $activeProvider === 'gemini' ? 'selected' : '' ?>>Gemini (مستقیم)</option>
                    </select>
                    <select id="modelSelect">
                        <?php foreach ($modelsByProvider[$activeProvider] as $key => $label): ?>
                            <option value="<?= $key ?>" <?= ($activeConv && $activeConv['model'] === $key) ? 'selected' : '' ?>>
                                <?= $label ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <?php if (empty($messages)): ?>
                <div class="empty-state" id="emptyState">
                    <h2>سلام! چطور می‌تونم کمکت کنم؟ 🤖</h2>
                    <p>یک پیام بنویس تا گفتگو رو شروع کنیم</p>
                </div>
            <?php endif; ?>

            <div class="messages-container" id="messagesContainer" style="<?= empty($messages) ? 'display:none;' : '' ?>">
                <?php foreach ($messages as $msg): ?>
                    <div class="message-row <?= $msg['role'] ?>">
                        <div class="bubble"><?php
                            if ($msg['image_path'] && $msg['role'] === 'user') {
                                echo '<img class="msg-image" src="' . htmlspecialchars($msg['image_path']) . '" alt="تصویر">';
                            }
                            echo htmlspecialchars($msg['content']);
                        ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="chat-input-area">
                <div class="image-preview-area" id="imagePreviewArea">
                    <div class="image-preview-wrapper">
                        <img id="imagePreview" src="" alt="پیش‌نمایش">
                        <button class="image-preview-remove" id="imageRemoveBtn" type="button">&times;</button>
                    </div>
                </div>
                <div class="chat-input-box">
                    <input type="file" id="imageFileInput" accept="image/png,image/jpeg,image/gif,image/webp" style="display:none">
                    <button class="tool-btn" id="uploadBtn" type="button" title="آپلود تصویر">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
                    </button>
                    <button class="tool-btn" id="searchToggleBtn" type="button" title="جستجوی وب">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                    </button>
                    <textarea id="messageInput" placeholder="پیام خود را بنویسید..." rows="1"></textarea>
                    <button class="send-btn" id="sendBtn">&#10148;</button>
                </div>
            </div>
        </main>
    </div>

    <script>
        window.CHAT_STATE = {
            conversationId: <?= $activeConvId ? (int)$activeConvId : 'null' ?>,
            hasMessages: <?= empty($messages) ? 'false' : 'true' ?>,
            activeProvider: <?= json_encode($activeProvider) ?>,
            modelsByProvider: <?= json_encode($modelsByProvider, JSON_UNESCAPED_UNICODE) ?>
        };
    </script>
    <script src="assets/js/chat.js"></script>
</body>
</html>
