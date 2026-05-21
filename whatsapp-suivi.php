<?php
/**
 * Partage WhatsApp - Suivi de commande
 * Menu QR - NTIC Solution
 */
require_once __DIR__ . '/includes/config.php';

$suivi = $_GET['suivi'] ?? '';
if (!$suivi) { header('Location: index.php'); exit; }

$db = getDB();
$stmt = $db->prepare("SELECT c.*, r.nom_restaurant, r.telephone_whatsapp FROM commandes c JOIN restaurants r ON c.restaurant_id = r.id WHERE c.code_suivi = ?");
$stmt->execute([$suivi]);
$cmd = $stmt->fetch();

if (!$cmd) { header('Location: index.php'); exit; }

$stmt = $db->prepare("SELECT cp.*, p.nom FROM commande_plats cp JOIN plats p ON cp.plat_id = p.id WHERE cp.commande_id = ?");
$stmt->execute([$cmd['id']]);
$plats = $stmt->fetchAll();

// Construire le message WhatsApp
$msg  = "🍽️ *Suivi de commande - " . $cmd['nom_restaurant'] . "*\n\n";
$msg .= "🔍 Code : *" . $suivi . "*\n";
$msg .= "🪑 Table : *" . ($cmd['numero_table'] ?? 'N/A') . "*\n";
$msg .= "📦 Mode : *" . ($cmd['mode_commande'] == 'emporter' ? 'À emporter' : 'Sur place') . "*\n";
$msg .= "📅 Date : *" . date('d/m/Y H:i', strtotime($cmd['date_commande'])) . "*\n\n";
$msg .= "*Plats commandés :*\n";
foreach ($plats as $p) {
    $msg .= "• " . $p['quantite'] . "× " . $p['nom'] . " - " . formatPrix($p['prix_unitaire'] * $p['quantite']) . "\n";
}
$msg .= "\n💰 *Total : " . formatPrix($cmd['total']) . "*\n";
$msg .= "\n📋 Statut : *" . $cmd['statut'] . "*\n";
$msg .= "\nSuivez votre commande : " . SITE_URL . "/suivi.php?suivi=" . $suivi;

// Numéro WhatsApp du restaurant (ou fallback sans numéro)
$waNumber = $cmd['telephone_whatsapp'] ?? '';

if (!empty($waNumber)) {
    // Avec numéro du restaurant → message envoyé au resto
    $waUrl = "https://wa.me/" . $waNumber . "?text=" . urlencode($msg);
} else {
    // Sans numéro → partage via l'API WhatsApp (le client choisit le contact)
    $waUrl = "https://api.whatsapp.com/send?text=" . urlencode($msg);
}

// Rediriger vers WhatsApp
header('Location: ' . $waUrl);
exit;