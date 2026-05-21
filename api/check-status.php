<?php
/**
 * API - Vérification changement de statut (Version Pro)
 * Appelée par suivi.php en polling (toutes les 5 secondes)
 * Retourne le nouveau statut + infos complémentaires
 * Menu QR - NTIC Solution
 */
require_once __DIR__ . '/../includes/config.php';

$cmd_id = intval($_GET['cmd_id'] ?? 0);
$current = $_GET['current'] ?? '';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('Access-Control-Allow-Origin: *');

if ($cmd_id === 0) {
    echo json_encode([
        'success' => false,
        'changed' => false,
        'error' => 'ID commande manquant'
    ]);
    exit;
}

$db = getDB();

// Récupérer le statut actuel + infos commande
$stmt = $db->prepare("SELECT c.statut, c.total, c.numero_table, c.mode_commande, c.date_commande, r.nom_restaurant, r.telephone_whatsapp FROM commandes c JOIN restaurants r ON c.restaurant_id = r.id WHERE c.id = ?");
$stmt->execute([$cmd_id]);
$cmd = $stmt->fetch();

if (!$cmd) {
    echo json_encode([
        'success' => false,
        'changed' => false,
        'error' => 'Commande introuvable'
    ]);
    exit;
}

// Définir les infos selon le statut
$statusInfo = [
    'en_attente'    => ['icon' => 'fa-clock', 'text' => 'En attente', 'sub' => 'Votre commande va être prise en charge', 'color' => '#F59E0B', 'bg' => '#FFF3CD'],
    'confirmee'     => ['icon' => 'fa-circle-check', 'text' => 'Confirmée', 'sub' => 'Le restaurant prépare votre commande', 'color' => '#3B82F6', 'bg' => '#DBEAFE'],
    'en_preparation'=> ['icon' => 'fa-fire-burner', 'text' => 'En préparation', 'sub' => 'Le cuisinier est aux fourneaux', 'color' => '#3B82F6', 'bg' => '#DBEAFE'],
    'prete'         => ['icon' => 'fa-bell', 'text' => 'Prête !', 'sub' => 'Votre commande est prête', 'color' => '#10B981', 'bg' => '#ECFDF5'],
    'livree'        => ['icon' => 'fa-motorcycle', 'text' => 'Livrée', 'sub' => 'Bon appétit !', 'color' => '#10B981', 'bg' => '#ECFDF5'],
    'annulee'       => ['icon' => 'fa-circle-xmark', 'text' => 'Annulée', 'sub' => 'Cette commande a été annulée', 'color' => '#EF4444', 'bg' => '#FEE2E2'],
];

$newStatus = $cmd['statut'];
$changed = ($newStatus !== $current);

$response = [
    'success' => true,
    'changed' => $changed,
    'current_status' => $current,
    'new_status' => $newStatus,
    'server_time' => date('Y-m-d H:i:s')
];

// Si le statut a changé, ajouter les infos complètes
if ($changed) {
    $info = $statusInfo[$newStatus] ?? ['icon' => 'fa-circle', 'text' => $newStatus, 'sub' => '', 'color' => '#64748B', 'bg' => '#F1F5F9'];
    
    $response['status_info'] = $info;
    $response['commande'] = [
        'total' => (int)$cmd['total'],
        'total_formatted' => formatPrix($cmd['total']),
        'table' => $cmd['numero_table'],
        'mode' => $cmd['mode_commande'] == 'emporter' ? 'À emporter' : 'Sur place',
        'restaurant' => $cmd['nom_restaurant'],
        'date' => date('d/m/Y H:i', strtotime($cmd['date_commande']))
    ];
    
    // Message WhatsApp si le statut est "prête" ou "livrée"
    if (in_array($newStatus, ['prete', 'livree']) && !empty($cmd['telephone_whatsapp'])) {
        $response['whatsapp'] = [
            'number' => $cmd['telephone_whatsapp'],
            'message' => "Bonjour, ma commande #{$cmd_id} est marquée comme « {$newStatus} ». Merci !"
        ];
    }
    
    // Notification push
    $response['notification'] = [
        'title' => '🍽️ Commande #' . $cmd_id,
        'body' => $info['text'] . ' — ' . $info['sub'],
        'icon' => SITE_URL . '/favicon.ico'
    ];
}

echo json_encode($response);
exit;