<?php
/**
 * API - Vérification nouvelles commandes (Version Pro)
 * Appelée par le dashboard en polling (toutes les 10 secondes)
 * Retourne : nouvelles commandes, stats du jour, alertes
 * Menu QR - NTIC Solution
 */
require_once __DIR__ . '/../includes/config.php';

$rid = intval($_GET['rid'] ?? 0);
$last = intval($_GET['last'] ?? 0);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if ($rid === 0) {
    echo json_encode([
        'success' => false,
        'error' => 'Restaurant ID manquant'
    ]);
    exit;
}

$db = getDB();

// ==================== 1. NOUVELLES COMMANDES ====================
$stmt = $db->prepare("SELECT id, code_suivi, numero_table, nom_client, telephone_client, mode_commande, note, total, statut, date_commande FROM commandes WHERE restaurant_id = ? AND id > ? ORDER BY id ASC LIMIT 10");
$stmt->execute([$rid, $last]);
$newCommands = $stmt->fetchAll();
$newCount = count($newCommands);
$newLastId = $newCount > 0 ? max(array_column($newCommands, 'id')) : $last;

// ==================== 2. STATS DU JOUR ====================
$stmt = $db->prepare("SELECT COUNT(*) as nb, COALESCE(SUM(total),0) as ca, COUNT(DISTINCT numero_table) as tables_servies FROM commandes WHERE restaurant_id = ? AND DATE(date_commande) = CURDATE()");
$stmt->execute([$rid]);
$todayStats = $stmt->fetch();

// ==================== 3. COMMANDES EN ATTENTE ====================
$stmt = $db->prepare("SELECT COUNT(*) as nb FROM commandes WHERE restaurant_id = ? AND statut = 'en_attente'");
$stmt->execute([$rid]);
$enAttente = $stmt->fetchColumn();

// ==================== 4. COMMANDES EN COURS (confirmée + préparation) ====================
$stmt = $db->prepare("SELECT COUNT(*) as nb FROM commandes WHERE restaurant_id = ? AND statut IN ('confirmee','en_preparation')");
$stmt->execute([$rid]);
$enCours = $stmt->fetchColumn();

// ==================== 5. TOTAL COMMANDES ====================
$stmt = $db->prepare("SELECT COUNT(*) FROM commandes WHERE restaurant_id = ?");
$stmt->execute([$rid]);
$totalCommandes = $stmt->fetchColumn();

// ==================== RÉPONSE ====================
echo json_encode([
    'success' => true,
    'server_time' => date('Y-m-d H:i:s'),
    
    // Nouvelles commandes
    'new_commands' => $newCount,
    'last_id' => (int)$newLastId,
    'commands' => $newCommands,
    
    // Stats du jour
    'today' => [
        'commandes' => (int)($todayStats['nb'] ?? 0),
        'ca' => (int)($todayStats['ca'] ?? 0),
        'ca_formatted' => formatPrix($todayStats['ca'] ?? 0),
        'tables_servies' => (int)($todayStats['tables_servies'] ?? 0)
    ],
    
    // Compteurs
    'en_attente' => (int)$enAttente,
    'en_cours' => (int)$enCours,
    'total_commandes' => (int)$totalCommandes,
    
    // Alerte (pour bandeau)
    'alert' => $newCount > 0 ? [
        'type' => 'new_commands',
        'message' => $newCount . ' nouvelle(s) commande(s) !',
        'count' => $newCount
    ] : null
]);
exit;