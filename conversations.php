<?php
/**
 * توابع کمکی برای مدیریت مکالمات و پیام‌ها
 */

require_once __DIR__ . '/../config/database.php';

function createConversation($userId, $title = 'گفتگوی جدید', $model = DEFAULT_MODEL, $provider = DEFAULT_PROVIDER) {
    $pdo = getDB();
    $stmt = $pdo->prepare('INSERT INTO conversations (user_id, title, model, provider) VALUES (?, ?, ?, ?)');
    $stmt->execute([$userId, $title, $model, $provider]);
    return $pdo->lastInsertId();
}

function getUserConversations($userId) {
    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT id, title, model, created_at, updated_at FROM conversations WHERE user_id = ? ORDER BY updated_at DESC');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function getConversation($conversationId, $userId) {
    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT * FROM conversations WHERE id = ? AND user_id = ?');
    $stmt->execute([$conversationId, $userId]);
    return $stmt->fetch();
}

function getConversationMessages($conversationId) {
    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT role, content, image_path, created_at FROM messages WHERE conversation_id = ? ORDER BY id ASC');
    $stmt->execute([$conversationId]);
    return $stmt->fetchAll();
}

function addMessage($conversationId, $role, $content, $tokensUsed = 0, $imagePath = null) {
    $pdo = getDB();
    $stmt = $pdo->prepare('INSERT INTO messages (conversation_id, role, content, image_path, tokens_used) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$conversationId, $role, $content, $imagePath, $tokensUsed]);

    // آپدیت زمان آخرین ویرایش مکالمه
    $update = $pdo->prepare('UPDATE conversations SET updated_at = NOW() WHERE id = ?');
    $update->execute([$conversationId]);

    return $pdo->lastInsertId();
}

function updateConversationTitle($conversationId, $userId, $title) {
    $pdo = getDB();
    $stmt = $pdo->prepare('UPDATE conversations SET title = ? WHERE id = ? AND user_id = ?');
    $stmt->execute([$title, $conversationId, $userId]);
}

function deleteConversation($conversationId, $userId) {
    $pdo = getDB();
    $stmt = $pdo->prepare('DELETE FROM conversations WHERE id = ? AND user_id = ?');
    $stmt->execute([$conversationId, $userId]);
}

/**
 * ساخت یک عنوان کوتاه از اولین پیام کاربر (برای نمایش در لیست مکالمات)
 */
function generateTitleFromMessage($message) {
    $title = mb_substr(trim($message), 0, 40, 'UTF-8');
    if (mb_strlen(trim($message), 'UTF-8') > 40) {
        $title .= '...';
    }
    return $title ?: 'گفتگوی جدید';
}
