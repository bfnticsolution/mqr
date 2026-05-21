<?php
require_once __DIR__ . '/includes/config.php';
if (!isset($_SESSION['restaurant_id'])) { header('Location: connexion.php'); exit; }

$db = getDB();
$rid = $_SESSION['restaurant_id'];
$resto = $db->query("SELECT nom_restaurant FROM restaurants WHERE id=$rid")->fetch();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="commandes-'.date('Y-m-d').'.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, ['Date','Table','Client','Téléphone','Mode','Plats','Total FCFA','Statut']);

$stmt = $db->prepare("SELECT * FROM commandes WHERE restaurant_id=? ORDER BY date_commande DESC");
$stmt->execute([$rid]);

while ($cmd = $stmt->fetch()) {
    $stmt2 = $db->prepare("SELECT cp.*, p.nom FROM commande_plats cp JOIN plats p ON cp.plat_id=p.id WHERE cp.commande_id=?");
    $stmt2->execute([$cmd['id']]);
    $plats = $stmt2->fetchAll();
    $platsStr = '';
    foreach ($plats as $p) { $platsStr .= $p['quantite'].'x '.$p['nom'].', '; }
    
    fputcsv($out, [
        date('d/m/Y H:i', strtotime($cmd['date_commande'])),
        $cmd['numero_table'] ?? 'N/A',
        $cmd['nom_client'] ?? 'Anonyme',
        $cmd['telephone_client'] ?? '-',
        $cmd['mode_commande'] == 'emporter' ? 'À emporter' : 'Sur place',
        trim($platsStr, ', '),
        $cmd['total'],
        $cmd['statut']
    ]);
}
fclose($out);
exit;