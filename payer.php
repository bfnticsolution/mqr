<?php
/**
 * Page de paiement Premium - Menu QR
 * Redirection vers Chariow Checkout
 * NTIC Solution
 */
require_once __DIR__ . '/includes/config.php';

if (!isset($_SESSION['restaurant_id'])) {
    header('Location: connexion.php');
    exit;
}

$db = getDB();
$restaurant_id = $_SESSION['restaurant_id'];

// Récupérer les infos du restaurant
$stmt = $db->prepare("SELECT nom_restaurant, telephone, module, date_expiration_module FROM restaurants WHERE id = ?");
$stmt->execute([$restaurant_id]);
$restaurant = $stmt->fetch();

// Si déjà Premium et non expiré
if ($restaurant['module'] === 'pro' && strtotime($restaurant['date_expiration_module']) > time()) {
    header('Location: dashboard.php?tab=abonnement&message=deja_premium');
    exit;
}

// Générer une référence unique
$reference = 'MENUQR-' . strtoupper(substr(uniqid(), -8)) . '-' . $restaurant_id;
$montant = ABONNEMENT_PRIX_FCFA;

// Insérer le paiement en base (statut initié)
$stmt = $db->prepare("INSERT INTO paiements (restaurant_id, reference, montant, module, statut, date_creation) VALUES (?, ?, ?, 'pro', 'initie', NOW())");
$stmt->execute([$restaurant_id, $reference, $montant]);
$paiement_id = $db->lastInsertId();

// Marquer la session : ce restaurant est en cours de paiement légitime
$_SESSION['paiement_ok'] = true;
$_SESSION['paiement_ref'] = $reference;
$_SESSION['paiement_id'] = $paiement_id;

// URL de redirection après paiement
$redirect_url = SITE_URL . '/verifier-paiement.php';

// Construire l'URL Chariow
$chariow_url = CHARIOW_CHECKOUT_URL . '?' . http_build_query([
    'ref' => $reference,
    'amount' => $montant,
    'currency' => DEVISE,
    'redirect_url' => $redirect_url,
    'customer_email' => $restaurant['telephone'] . '@menuqr.bf',
    'customer_name' => $restaurant['nom_restaurant'],
    'metadata' => json_encode([
        'restaurant_id' => $restaurant_id,
        'type' => 'abonnement_premium'
    ])
]);

// Logger
error_log("[MenuQR] Paiement initié - Ref: $reference - Resto: $restaurant_id");

// Redirection vers Chariow
header('Location: ' . $chariow_url);
exit;