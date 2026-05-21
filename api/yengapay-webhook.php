<?php
require_once __DIR__ . '/../includes/config.php';

// Logger
$logDir = __DIR__ . '/../logs/';
if (!is_dir($logDir)) mkdir($logDir, 0755, true);
file_put_contents($logDir . 'webhook.log', date('Y-m-d H:i:s') . " - Webhook reçu\n", FILE_APPEND);

$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_YENGAPAY_SIGNATURE'] ?? '';

file_put_contents($logDir . 'webhook.log', "Payload: " . $payload . "\n", FILE_APPEND);

// Vérifier la signature
$expectedSignature = hash_hmac('sha256', $payload, YENGAPAY_WEBHOOK_SECRET);

if ($signature !== $expectedSignature && !empty(YENGAPAY_WEBHOOK_SECRET)) {
    file_put_contents($logDir . 'webhook.log', "⚠️ Signature invalide\n", FILE_APPEND);
    http_response_code(403);
    echo json_encode(['error' => 'Signature invalide']);
    exit;
}

$data = json_decode($payload, true);
if (!$data) { http_response_code(400); exit; }

$status = $data['status'] ?? $data['event'] ?? '';
$reference = $data['reference'] ?? '';

file_put_contents($logDir . 'webhook.log', "Status: $status | Reference: $reference\n", FILE_APPEND);

// Si paiement réussi
if (in_array($status, ['COMPLETED', 'SUCCESSFUL', 'PAID', 'completed', 'successful'])) {
    $db = getDB();
    
    // Chercher le paiement par référence
    $stmt = $db->prepare("SELECT * FROM paiements WHERE reference = ?");
    $stmt->execute([$reference]);
    $paiement = $stmt->fetch();
    
    if ($paiement && $paiement['statut'] !== 'complete') {
        // Marquer le paiement comme complété
        $db->prepare("UPDATE paiements SET statut = 'complete', date_paiement = NOW() WHERE id = ?")
           ->execute([$paiement['id']]);
        
        // Activer l'abonnement
        $dateFin = date('Y-m-d', strtotime('+' . ABONNEMENT_DUREE_JOURS . ' days'));
        $db->prepare("UPDATE restaurants SET module = 'pro', date_expiration_module = ? WHERE id = ?")
           ->execute([$dateFin, $paiement['restaurant_id']]);
        
        file_put_contents($logDir . 'webhook.log', "✅ Abonnement activé pour resto #{$paiement['restaurant_id']}\n", FILE_APPEND);
    }
}

http_response_code(200);
echo json_encode(['success' => true]);
?>