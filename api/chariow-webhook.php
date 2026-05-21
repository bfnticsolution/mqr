<?php
/**
 * Webhook Chariow - Réception Pulse automatique
 * URL: https://menuqr.page.gd/api/chariow-webhook.php
 * SÉCURISÉ : activation par webhook uniquement
 */
require_once __DIR__ . '/../includes/config.php';

$log_file = __DIR__ . '/../logs/chariow-webhook.log';
$raw_input = file_get_contents('php://input');
$timestamp = date('Y-m-d H:i:s');

@file_put_contents($log_file, "$timestamp [WEBHOOK] Requête reçue\n", FILE_APPEND);
@file_put_contents($log_file, "$timestamp [WEBHOOK] Body: $raw_input\n", FILE_APPEND);

$data = json_decode($raw_input, true);

if (!$data) {
    http_response_code(400);
    @file_put_contents($log_file, "$timestamp [WEBHOOK] ERREUR: JSON invalide\n", FILE_APPEND);
    echo json_encode(['error' => 'JSON invalide']);
    exit;
}

// Extraire les infos de la vente
$sale_data = $data['data'] ?? $data;
$sale_id = $sale_data['id'] ?? '';
$sale_status = $sale_data['status'] ?? '';
$payment_status = $sale_data['payment']['status'] ?? '';

// Chercher la référence dans plusieurs endroits possibles
$reference = $sale_data['ref'] ?? '';
if (empty($reference)) {
    $reference = $sale_data['metadata']['ref'] ?? '';
}
if (empty($reference)) {
    $reference = $sale_data['custom_fields_values']['ref'] ?? '';
}

if (empty($reference)) {
    @file_put_contents($log_file, "$timestamp [WEBHOOK] ERREUR: Référence manquante - SaleID: $sale_id\n", FILE_APPEND);
    http_response_code(400);
    echo json_encode(['error' => 'Référence manquante']);
    exit;
}

// Vérifier que la vente est bien complétée
if ($sale_status !== 'completed' || $payment_status !== 'success') {
    @file_put_contents($log_file, "$timestamp [WEBHOOK] Statut non final - Sale: $sale_status, Payment: $payment_status - Ref: $reference\n", FILE_APPEND);
    echo json_encode(['status' => 'ignored', 'reason' => "sale=$sale_status, payment=$payment_status"]);
    exit;
}

try {
    $db = getDB();

    // Trouver le paiement par référence
    $stmt = $db->prepare("SELECT * FROM paiements WHERE reference = ? LIMIT 1");
    $stmt->execute([$reference]);
    $paiement = $stmt->fetch();

    if (!$paiement) {
        @file_put_contents($log_file, "$timestamp [WEBHOOK] ERREUR: Paiement non trouvé - Ref: $reference\n", FILE_APPEND);
        http_response_code(404);
        echo json_encode(['error' => 'Paiement non trouvé']);
        exit;
    }

    // Si déjà complet, ne rien faire
    if ($paiement['statut'] === 'complete') {
        @file_put_contents($log_file, "$timestamp [WEBHOOK] Déjà complet - Ref: $reference\n", FILE_APPEND);
        echo json_encode(['status' => 'already_complete']);
        exit;
    }

    // Mettre à jour le paiement
    $db->prepare("UPDATE paiements SET statut = 'complete', date_paiement = NOW(), chariow_sale_id = ? WHERE id = ?")
       ->execute([$sale_id, $paiement['id']]);

    // Activer l'abonnement
    $date_fin = date('Y-m-d H:i:s', strtotime('+30 days'));
    $db->prepare("UPDATE restaurants SET module = 'pro', date_expiration_module = ? WHERE id = ?")
       ->execute([$date_fin, $paiement['restaurant_id']]);

    @file_put_contents($log_file, "$timestamp [WEBHOOK] SUCCÈS - Ref: $reference - SaleID: $sale_id - Resto: {$paiement['restaurant_id']} - Fin: $date_fin\n", FILE_APPEND);

    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Abonnement activé']);

} catch (Exception $e) {
    @file_put_contents($log_file, "$timestamp [WEBHOOK] ERREUR DB: " . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur']);
}