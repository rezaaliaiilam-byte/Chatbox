<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/conversations.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'لطفاً ابتدا وارد شوید.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$conversationId = (int)($input['conversation_id'] ?? 0);

if (!$conversationId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'شناسه مکالمه نامعتبر است.']);
    exit;
}

deleteConversation($conversationId, currentUserId());

echo json_encode(['success' => true]);
