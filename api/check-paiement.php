<?php
/**
 * API vérification statut paiement
 * Appelée par verifier-paiement.php en polling
 * Supporte le paramètre force=1 pour activation manuelle
 */
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');

$reference = $_GET['ref'] ?? '';
$force = isset($_GET['force']) && $_GET['force'] === '1';

if (!$reference) {
    echo json_encode(['error' => 'Référence manquante']);
    exit;
}

$db = getDB();

// Vérifier le paiement
$stmt = $db->prepare("SELECT p.*, r.module, r.date_expiration_module 
                      FROM paiements p 
                      JOIN restaurants r ON p.restaurant_id = r.id 
                      WHERE p.reference = ? 
                      LIMIT 1");
$stmt->execute([$reference]);
$paiement = $stmt->fetch();

if (!$paiement) {
    echo json_encode(['completed' => false, 'error' => 'Paiement non trouvé']);
    exit;
}

// Si force=1 ou si initié, activer maintenant
if ($force || $paiement['statut'] === 'initie') {
    // Mettre à jour le paiement
    $db->prepare("UPDATE paiements SET statut = 'complete', date_paiement = NOW() WHERE id = ?")
       ->execute([$paiement['id']]);

    // Activer l'abonnement
    $date_fin = date('Y-m-d H:i:s', strtotime('+' . ABONNEMENT_DUREE_JOURS . ' days'));
    $db->prepare("UPDATE restaurants SET module = 'pro', date_expiration_module = ? WHERE id = ?")
       ->execute([$date_fin, $paiement['restaurant_id']]);

    // Logger
    $log_msg = "[MenuQR] Activation " . ($force ? "forcée" : "fallback") . 
               " - Ref: $reference - Resto: {$paiement['restaurant_id']}";
    error_log($log_msg);
    @file_put_contents(__DIR__ . '/../logs/chariow-webhook.log', 
                       date('Y-m-d H:i:s') . " $log_msg\n", FILE_APPEND);

    echo json_encode([
        'completed' => true,
        'activated' => true,
        'message' => 'Abonnement activé',
        'date_fin' => $date_fin
    ]);
    exit;
}

// Si déjà complet
if ($paiement['statut'] === 'complete') {
    echo json_encode([
        'completed' => true,
        'activated' => true,
        'message' => 'Déjà activé'
    ]);
    exit;
}

// Sinon, en attente
echo json_encode([
    'completed' => false,
    'status' => $paiement['statut']
]);