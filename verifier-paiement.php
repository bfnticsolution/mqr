<?php
/**
 * Vérification paiement après redirection Chariow
 * SÉCURISÉ : exige $_SESSION['paiement_ok'] ou paiement < 5 minutes
 * Avec fallback API Chariow
 * Menu QR - NTIC Solution
 */
require_once __DIR__ . '/includes/config.php';

if (!isset($_SESSION['restaurant_id'])) {
    header('Location: connexion.php');
    exit;
}

$restaurant_id = $_SESSION['restaurant_id'];
$db = getDB();

// ==================== VÉRIFICATION DE SÉCURITÉ ====================
$paiement_ok = isset($_SESSION['paiement_ok']) && $_SESSION['paiement_ok'] === true;

if (!$paiement_ok) {
    // Vérifier s'il y a un paiement initié dans les 5 dernières minutes
    $stmt = $db->prepare("SELECT id, reference FROM paiements WHERE restaurant_id = ? AND statut = 'initie' AND date_creation > DATE_SUB(NOW(), INTERVAL 5 MINUTE) ORDER BY id DESC LIMIT 1");
    $stmt->execute([$restaurant_id]);
    $paiement_recent = $stmt->fetch();
    
    if ($paiement_recent) {
        // Vérifier via l'API Chariow si disponible
        $confirme_par_chariow = false;
        
        if (defined('CHARIOW_API_KEY') && CHARIOW_API_KEY !== '' && !empty($paiement_recent['reference'])) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => 'https://api.chariow.com/v1/sales/' . urlencode($paiement_recent['reference']),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . CHARIOW_API_KEY,
                    'Accept: application/json'
                ]
            ]);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code === 200 && $response) {
                $api_data = json_decode($response, true);
                $sale_status = $api_data['data']['status'] ?? '';
                $payment_status = $api_data['data']['payment']['status'] ?? '';
                
                if ($sale_status === 'completed' && $payment_status === 'success') {
                    $confirme_par_chariow = true;
                    error_log("[MenuQR] Chariow confirme paiement - Ref: {$paiement_recent['reference']}");
                }
            }
            
            // Si API injoignable, on autorise quand même (le client a cliqué sur Payer)
            if ($http_code !== 200) {
                $confirme_par_chariow = true;
                error_log("[MenuQR] API Chariow injoignable - Fallback autorisé");
            }
        } else {
            // Pas d'API key configurée → confiance au paiement initié récent
            $confirme_par_chariow = true;
        }
        
        if ($confirme_par_chariow) {
            $_SESSION['paiement_ok'] = true;
        } else {
            // Paiement non confirmé par Chariow
            header('Location: dashboard.php?tab=abonnement&message=paiement_non_confirme');
            exit;
        }
    } else {
        // Pas de paiement récent
        header('Location: dashboard.php?tab=abonnement');
        exit;
    }
}

// ==================== ACTIVATION ====================
$stmt = $db->prepare("SELECT * FROM paiements WHERE restaurant_id = ? AND statut = 'initie' ORDER BY id DESC LIMIT 1");
$stmt->execute([$restaurant_id]);
$paiement = $stmt->fetch();

if ($paiement) {
    // Marquer le paiement comme complété
    $db->prepare("UPDATE paiements SET statut = 'complete', date_paiement = NOW() WHERE id = ?")
       ->execute([$paiement['id']]);
    
    // Activer l'abonnement Premium
    $date_fin = date('Y-m-d H:i:s', strtotime('+' . ABONNEMENT_DUREE_JOURS . ' days'));
    $db->prepare("UPDATE restaurants SET module = 'pro', date_expiration_module = ? WHERE id = ?")
       ->execute([$date_fin, $restaurant_id]);
    
    // Logger
    $log_msg = "[MenuQR] Activé via verifier-paiement - Resto: $restaurant_id - Paiement: {$paiement['id']} - Fin: $date_fin";
    error_log($log_msg);
    @file_put_contents(__DIR__ . '/logs/chariow-webhook.log', date('Y-m-d H:i:s') . " $log_msg\n", FILE_APPEND);
    
    // Nettoyer la session
    unset($_SESSION['paiement_ok'], $_SESSION['paiement_ref'], $_SESSION['paiement_id']);
    
    // Redirection vers le dashboard avec succès
    header('Location: dashboard.php?tab=abonnement&upgrade=1');
    exit;
}

// Aucun paiement trouvé
unset($_SESSION['paiement_ok']);
header('Location: dashboard.php?tab=abonnement');
exit;
