<?php
/**
 * Administration avancée - Menu QR
 * NTIC Solution
 * Version 2.0 - Professionnelle
 */
require_once __DIR__ . '/includes/config.php';

// === AUTHENTIFICATION ===
define('ADMIN_PASS', 'Anysia@3121');

if (!isset($_SESSION['admin'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
        if ($_POST['pass'] === ADMIN_PASS) {
            $_SESSION['admin'] = true;
            $_SESSION['admin_time'] = time();
            header('Location: admin.php');
            exit;
        }
        $err = '<i class="fa-solid fa-circle-xmark"></i> Mot de passe incorrect.';
    }
    ?>
    <!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Menu QR</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>:root{--o:#FF6B35}*{margin:0;padding:0;box-sizing:border-box}body{font-family:-apple-system,BlinkMacSystemFont,sans-serif;background:#0F172A;display:flex;justify-content:center;align-items:center;min-height:100vh}.box{background:#fff;padding:40px;border-radius:24px;width:400px;max-width:90%;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.3)}.box .logo{font-size:40px;color:var(--o);margin-bottom:12px}.box h1{color:var(--o);margin-bottom:4px;font-size:24px}.box p{color:#94A3B8;margin-bottom:24px;font-size:13px}.box input{width:100%;padding:14px;border:2px solid #E2E8F0;border-radius:14px;margin-bottom:14px;font-size:15px;text-align:center;transition:.2s}.box input:focus{outline:none;border-color:var(--o);box-shadow:0 0 0 3px rgba(255,107,53,.1)}.box button{width:100%;padding:14px;background:var(--o);color:#fff;border:none;border-radius:14px;font-size:16px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:.2s}.box button:hover{background:#e55a2b}.err{background:#FEE2E2;color:#991B1B;padding:12px;border-radius:12px;margin-bottom:14px;font-size:13px;display:flex;align-items:center;gap:8px}.back{margin-top:16px;font-size:13px}.back a{color:var(--o);text-decoration:none}</style>
    </head><body><div class="box"><div class="logo"><i class="fa-solid fa-shield-halved"></i></div><h1>Menu QR</h1><p>Administration NTIC Solution</p><?php if(isset($err)):?><div class="err"><?=$err?></div><?php endif;?><form method="POST"><input type="password" name="pass" placeholder="Mot de passe administrateur" required autofocus><button type="submit" name="login"><i class="fa-solid fa-lock-open"></i> Connexion</button></form><div class="back"><a href="index.php"><i class="fa-solid fa-arrow-left"></i> Retour au site</a></div></div></body></html>
    <?php exit;
}

if (isset($_GET['logout'])) { unset($_SESSION['admin']); header('Location: admin.php'); exit; }

$db = getDB();
$msg = '';
$onglet = $_GET['tab'] ?? 'dashboard';
$page = max(1, intval($_GET['p'] ?? 1));
$parPage = 20;
$offset = ($page - 1) * $parPage;
$tri = $_GET['tri'] ?? 'date_inscription';
$ordre = $_GET['ordre'] ?? 'DESC';
$filtre = $_GET['filtre'] ?? 'tous';

// ==================== ACTIONS ====================

if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    if ($_GET['action'] === 'suspendre') { $db->prepare("UPDATE restaurants SET statut='suspendu' WHERE id=?")->execute([$id]); $msg = 'Restaurant suspendu.'; }
    if ($_GET['action'] === 'activer') { $db->prepare("UPDATE restaurants SET statut='actif' WHERE id=?")->execute([$id]); $msg = 'Restaurant activé.'; }
    if ($_GET['action'] === 'supprimer') {
        $db->prepare("DELETE FROM plats WHERE restaurant_id=?")->execute([$id]);
        $db->prepare("DELETE FROM categories WHERE restaurant_id=?")->execute([$id]);
        $db->prepare("DELETE FROM commandes WHERE restaurant_id=?")->execute([$id]);
        $db->prepare("DELETE FROM tables_qr WHERE restaurant_id=?")->execute([$id]);
        $db->prepare("DELETE FROM restaurants WHERE id=?")->execute([$id]);
        $msg = 'Restaurant supprimé définitivement.';
    }
}

if (isset($_GET['abo']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    if ($_GET['abo'] === 'activer') {
        $dateFin = date('Y-m-d H:i:s', strtotime('+'.ABONNEMENT_DUREE_JOURS.' days'));
        $db->prepare("UPDATE restaurants SET module='pro', date_expiration_module=? WHERE id=?")->execute([$dateFin, $id]);
        $msg = 'Abonnement activé pour '.ABONNEMENT_DUREE_JOURS.' jours.';
    }
    if ($_GET['abo'] === 'desactiver') {
        $db->prepare("UPDATE restaurants SET module='simple', date_expiration_module=NULL WHERE id=?")->execute([$id]);
        $msg = 'Abonnement désactivé.';
    }
}

if (isset($_GET['supprimer_cmd']) && isset($_GET['id'])) {
    $cid = intval($_GET['id']);
    $db->prepare("DELETE FROM commande_plats WHERE commande_id=?")->execute([$cid]);
    $db->prepare("DELETE FROM suivi_commandes WHERE commande_id=?")->execute([$cid]);
    $db->prepare("DELETE FROM commandes WHERE id=?")->execute([$cid]);
    $msg = 'Commande supprimée.';
}

// ==================== EXPORTS ====================

if (isset($_GET['export'])) {
    if ($_GET['export'] === 'restaurants') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="restaurants-menuqr-'.date('Ymd').'.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID','Nom','Téléphone','Module','Statut','Ville','Plats','Scans','Commandes','CA','Date inscription']);
        $restos = $db->query("SELECT r.*, (SELECT COUNT(*) FROM plats WHERE restaurant_id=r.id) as nb_plats, (SELECT COUNT(*) FROM commandes WHERE restaurant_id=r.id) as nb_cmd, (SELECT COALESCE(SUM(total),0) FROM commandes WHERE restaurant_id=r.id) as ca FROM restaurants r ORDER BY r.date_inscription DESC")->fetchAll();
        foreach ($restos as $r) fputcsv($out, [$r['id'],$r['nom_restaurant'],$r['telephone'],$r['module'],$r['statut'],$r['ville'],$r['nb_plats'],$r['nb_scans'],$r['nb_cmd'],$r['ca'],$r['date_inscription']]);
        fclose($out); exit;
    }
    if ($_GET['export'] === 'commandes') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="commandes-menuqr-'.date('Ymd').'.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Code','Date','Restaurant','Table','Client','Mode','Total','Statut']);
        $cmds = $db->query("SELECT c.*, r.nom_restaurant FROM commandes c JOIN restaurants r ON c.restaurant_id=r.id ORDER BY c.date_commande DESC")->fetchAll();
        foreach ($cmds as $c) fputcsv($out, [$c['code_suivi'],$c['date_commande'],$c['nom_restaurant'],$c['numero_table'],$c['nom_client'],$c['mode_commande'],$c['total'],$c['statut']]);
        fclose($out); exit;
    }
    if ($_GET['export'] === 'paiements') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="paiements-menuqr-'.date('Ymd').'.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID','Restaurant','Référence','Montant','Module','Statut','Date']);
        $paiements = $db->query("SELECT p.*, r.nom_restaurant FROM paiements p JOIN restaurants r ON p.restaurant_id=r.id ORDER BY p.date_creation DESC")->fetchAll();
        foreach ($paiements as $p) fputcsv($out, [$p['id'],$p['nom_restaurant'],$p['reference'],$p['montant'],$p['module'],$p['statut'],$p['date_creation']]);
        fclose($out); exit;
    }
    if ($_GET['export'] === 'backup_json') {
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="backup-menuqr-'.date('Ymd').'.json"');
        $data = [
            'restaurants' => $db->query("SELECT * FROM restaurants")->fetchAll(),
            'commandes' => $db->query("SELECT * FROM commandes")->fetchAll(),
            'paiements' => $db->query("SELECT * FROM paiements")->fetchAll(),
            'export_date' => date('Y-m-d H:i:s')
        ];
        echo json_encode($data, JSON_PRETTY_PRINT);
        exit;
    }
}

// ==================== STATISTIQUES ====================
$nbRestos = $db->query("SELECT COUNT(*) FROM restaurants")->fetchColumn();
$nbActifs = $db->query("SELECT COUNT(*) FROM restaurants WHERE statut='actif'")->fetchColumn();
$nbSuspendus = $db->query("SELECT COUNT(*) FROM restaurants WHERE statut='suspendu'")->fetchColumn();
$nbAbonnes = $db->query("SELECT COUNT(*) FROM restaurants WHERE module='pro' AND date_expiration_module > NOW()")->fetchColumn();
$nbCommandes = $db->query("SELECT COUNT(*) FROM commandes")->fetchColumn();
$caTotal = $db->query("SELECT COALESCE(SUM(total),0) FROM commandes")->fetchColumn();
$nbScans = $db->query("SELECT COALESCE(SUM(nb_scans),0) FROM restaurants")->fetchColumn();
$nbPlats = $db->query("SELECT COUNT(*) FROM plats")->fetchColumn();
$cmdAujourdhui = $db->query("SELECT COUNT(*) FROM commandes WHERE DATE(date_commande)=CURDATE()")->fetchColumn();
$caAujourdhui = $db->query("SELECT COALESCE(SUM(total),0) FROM commandes WHERE DATE(date_commande)=CURDATE()")->fetchColumn();
$inscriptionsAujourdhui = $db->query("SELECT COUNT(*) FROM restaurants WHERE DATE(date_inscription)=CURDATE()")->fetchColumn();
$tauxConversion = $nbRestos > 0 ? round(($nbAbonnes / $nbRestos) * 100) : 0;
$nbPaiementsReussis = $db->query("SELECT COUNT(*) FROM paiements WHERE statut='complete'")->fetchColumn();
$caPaiements = $db->query("SELECT COALESCE(SUM(montant),0) FROM paiements WHERE statut='complete'")->fetchColumn();

$cmdParJour = $db->query("SELECT DATE(date_commande) as jour, COUNT(*) as nb, COALESCE(SUM(total),0) as ca FROM commandes WHERE date_commande > DATE_SUB(CURDATE(), INTERVAL 7 DAY) GROUP BY DATE(date_commande) ORDER BY jour DESC")->fetchAll();
$caParJour = $db->query("SELECT DATE(date_commande) as jour, COALESCE(SUM(total),0) as ca FROM commandes WHERE date_commande > DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY DATE(date_commande) ORDER BY jour ASC")->fetchAll();
$modulesStats = $db->query("SELECT module, COUNT(*) as nb FROM restaurants GROUP BY module")->fetchAll();
$topRestos = $db->query("SELECT r.nom_restaurant, r.id, COUNT(c.id) as nb FROM restaurants r LEFT JOIN commandes c ON r.id=c.restaurant_id GROUP BY r.id ORDER BY nb DESC LIMIT 10")->fetchAll();

// ==================== RECHERCHE ET FILTRES ====================
$q = $_GET['q'] ?? '';

$whereResto = "WHERE 1=1";
$paramsResto = [];

if ($q) {
    $whereResto .= " AND (r.nom_restaurant LIKE ? OR r.telephone LIKE ? OR r.ville LIKE ?)";
    $paramsResto = array_merge($paramsResto, ["%$q%", "%$q%", "%$q%"]);
}

switch ($filtre) {
    case 'actifs': $whereResto .= " AND r.statut='actif'"; break;
    case 'suspendus': $whereResto .= " AND r.statut='suspendu'"; break;
    case 'premium': $whereResto .= " AND r.module='pro' AND r.date_expiration_module > NOW()"; break;
    case 'gratuit': $whereResto .= " AND (r.module='simple' OR r.date_expiration_module IS NULL OR r.date_expiration_module <= NOW())"; break;
    case 'aujourdhui': $whereResto .= " AND DATE(r.date_inscription)=CURDATE()"; break;
}

$colsAutorises = ['date_inscription','nom_restaurant','nb_scans','nb_plats','statut','module'];
if (!in_array($tri, $colsAutorises)) $tri = 'date_inscription';
if (!in_array($ordre, ['ASC','DESC'])) $ordre = 'DESC';

$stmt = $db->prepare("SELECT COUNT(*) FROM restaurants r $whereResto");
$stmt->execute($paramsResto);
$totalRestos = $stmt->fetchColumn();
$totalPages = ceil($totalRestos / $parPage);

$stmt = $db->prepare("SELECT r.*, (SELECT COUNT(*) FROM plats WHERE restaurant_id=r.id) as nb_plats FROM restaurants r $whereResto ORDER BY r.$tri $ordre LIMIT $parPage OFFSET $offset");
$stmt->execute($paramsResto);
$restaurants = $stmt->fetchAll();

$commandes = $db->query("SELECT c.*, r.nom_restaurant FROM commandes c JOIN restaurants r ON c.restaurant_id=r.id ORDER BY c.date_commande DESC LIMIT 100")->fetchAll();
$tousPlats = $db->query("SELECT p.*, r.nom_restaurant FROM plats p JOIN restaurants r ON p.restaurant_id=r.id ORDER BY p.date_creation DESC LIMIT 100")->fetchAll();
$abonnes = $db->query("SELECT * FROM restaurants WHERE module='pro' AND date_expiration_module > NOW() ORDER BY date_expiration_module ASC")->fetchAll();
$paiements = $db->query("SELECT p.*, r.nom_restaurant FROM paiements p JOIN restaurants r ON p.restaurant_id=r.id ORDER BY p.date_creation DESC LIMIT 50")->fetchAll();

$modNames = ['simple'=>'Gratuit','pro'=>'Premium'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - Menu QR</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root{--o:#FF6B35;--g:#10B981;--r:#EF4444;--b:#3B82F6;--bg:#F1F5F9;--card:#fff;--text:#1E293B;--sub:#64748B;--border:#E2E8F0;--shadow:0 1px 3px rgba(0,0,0,.04)}
        @media(prefers-color-scheme:dark){:root{--bg:#0F172A;--card:#1E293B;--text:#F1F5F9;--sub:#94A3B8;--border:#334155}.card-header{border-bottom-color:var(--border)}th{background:#1E293B}tr:hover td{background:#263348!important}}
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:var(--bg);color:var(--text);font-size:13px}
        
        .topbar{background:#0F172A;color:#fff;padding:0 20px;height:56px;display:flex;justify-content:space-between;align-items:center;position:sticky;top:0;z-index:100}
        .topbar h1{font-size:16px;font-weight:700}.topbar h1 span{color:var(--o)}
        .topbar-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
        .topbar-actions a,.topbar-actions span{color:#94A3B8;text-decoration:none;font-size:11px;padding:6px 10px;border-radius:8px;transition:.2s;display:flex;align-items:center;gap:4px;white-space:nowrap}
        .topbar-actions a:hover,.topbar-actions a.active{color:#fff;background:rgba(255,255,255,.05)}
        .topbar-actions .sep{color:#334155}
        
        .layout{display:flex;min-height:calc(100vh - 56px)}
        
        .sidebar{width:220px;background:var(--card);border-right:1px solid var(--border);padding:12px 0;flex-shrink:0;overflow-y:auto}
        .sidebar a{display:flex;align-items:center;gap:8px;padding:10px 18px;text-decoration:none;color:var(--sub);font-weight:500;font-size:12px;border-left:3px solid transparent;transition:.15s}
        .sidebar a:hover{background:#F8FAFC;color:var(--text)}
        .sidebar a.active{background:#FFF7ED;color:var(--o);border-left-color:var(--o);font-weight:600}
        .sidebar a .count{background:var(--o);color:#fff;padding:2px 8px;border-radius:10px;font-size:10px;margin-left:auto}
        .sidebar hr{border-color:var(--border);margin:8px 0}
        .sidebar .label{padding:6px 18px;font-size:9px;text-transform:uppercase;letter-spacing:1px;color:var(--sub);font-weight:600}
        
        .main{flex:1;padding:20px;overflow-x:auto;max-width:100%}
        
        .stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(155px,1fr));gap:10px;margin-bottom:20px}
        .stat-card{background:var(--card);border-radius:14px;padding:16px;box-shadow:var(--shadow);display:flex;align-items:center;gap:12px;transition:.2s;cursor:default}
        .stat-card:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(0,0,0,.06)}
        .stat-icon{width:42px;height:42px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
        .stat-icon.o{background:#FFF7ED;color:var(--o)}.stat-icon.g{background:#ECFDF5;color:var(--g)}
        .stat-icon.b{background:#EFF6FF;color:var(--b)}.stat-icon.r{background:#FEF2F2;color:var(--r)}
        .stat-icon.y{background:#FEFCE8;color:#EAB308}
        .stat-info .nb{font-size:20px;font-weight:700;line-height:1.2}.stat-info .lb{font-size:10px;color:var(--sub);text-transform:uppercase;letter-spacing:.5px}
        
        .row{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px}
        .card{background:var(--card);border-radius:16px;box-shadow:var(--shadow);overflow:hidden}
        .card-header{padding:14px 18px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px}
        .card-header h2{font-size:14px;font-weight:600;display:flex;align-items:center;gap:8px}.card-header h2 i{color:var(--o)}
        .card-body{padding:14px 18px;overflow-x:auto}
        
        .alert{padding:12px 16px;border-radius:12px;margin-bottom:16px;font-weight:500;font-size:12px;display:flex;align-items:center;gap:8px}
        .alert-success{background:#ECFDF5;color:#065F46;border:1px solid #A7F3D0}
        
        .filtres{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:14px}
        .filtre-btn{padding:6px 12px;border-radius:20px;border:2px solid var(--border);background:transparent;color:var(--sub);cursor:pointer;font-size:11px;font-weight:600;white-space:nowrap;text-decoration:none;transition:.15s}
        .filtre-btn:hover,.filtre-btn.active{background:var(--o);color:#fff;border-color:var(--o)}
        
        table{width:100%;border-collapse:collapse;font-size:11px}
        th,td{padding:8px;text-align:left;border-bottom:1px solid var(--border)}
        th{background:var(--bg);font-weight:600;color:var(--sub);font-size:10px;text-transform:uppercase;white-space:nowrap}
        th a{color:var(--sub);text-decoration:none;display:flex;align-items:center;gap:4px}
        th a:hover{color:var(--o)}
        tr:hover td{background:#FFF7ED}
        
        .badge{padding:3px 8px;border-radius:10px;font-size:10px;font-weight:600;white-space:nowrap;display:inline-flex;align-items:center;gap:4px}
        .badge-actif{background:#ECFDF5;color:#065F46}.badge-suspendu{background:#FEE2E2;color:#991B1B}
        .badge-abonne{background:#DBEAFE;color:#1E40AF}.badge-gratuit{background:#F1F5F9;color:#64748B}
        .badge-attente{background:#FFF3CD;color:#856404}.badge-confirmee{background:#DBEAFE;color:#1E40AF}.badge-prete{background:#ECFDF5;color:#065F46}
        .badge-sp{background:#DBEAFE;color:#1E40AF}.badge-ae{background:#FEF3C7;color:#92400E}
        
        .btn{display:inline-flex;align-items:center;gap:4px;padding:5px 10px;border-radius:7px;text-decoration:none;font-size:10px;font-weight:600;border:none;cursor:pointer;white-space:nowrap;transition:.15s}
        .btn-g{background:var(--g);color:#fff}.btn-r{background:var(--r);color:#fff}.btn-o{background:var(--o);color:#fff}.btn-b{background:var(--b);color:#fff}.btn-gray{background:#64748B;color:#fff}
        .btn:hover{opacity:.85}
        
        .search{display:flex;gap:8px;margin-bottom:14px}
        .search input{flex:1;padding:10px 14px;border:2px solid var(--border);border-radius:12px;font-size:13px;background:var(--card);color:var(--text);transition:.2s}
        .search input:focus{outline:none;border-color:var(--o);box-shadow:0 0 0 3px rgba(255,107,53,.1)}
        .search button{padding:10px 18px;background:var(--o);color:#fff;border:none;border-radius:12px;cursor:pointer;font-weight:600;display:flex;align-items:center;gap:4px}
        
        .pagination{display:flex;gap:4px;justify-content:center;margin-top:16px;flex-wrap:wrap}
        .pagination a,.pagination span{padding:8px 12px;border-radius:8px;text-decoration:none;font-size:11px;font-weight:600;border:1px solid var(--border);color:var(--text);transition:.15s}
        .pagination a:hover{background:var(--o);color:#fff;border-color:var(--o)}
        .pagination .current{background:var(--o);color:#fff;border-color:var(--o)}
        
        .chart-svg{width:100%;height:200px}
        .progress-bar{background:var(--border);border-radius:6px;height:6px;margin:4px 0;overflow:hidden}
        .progress-fill{background:var(--o);height:100%;border-radius:6px;transition:.3s}
        
        .empty{text-align:center;padding:40px;color:var(--sub)}.empty i{font-size:40px;margin-bottom:8px;color:#D1D5DB}
        .prix{font-weight:700;color:var(--o)}.code{background:var(--bg);padding:2px 6px;border-radius:4px;font-family:monospace;font-size:10px}
        
        .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:200;justify-content:center;align-items:center;padding:20px}
        .modal-overlay.active{display:flex}
        .modal{background:var(--card);border-radius:20px;padding:24px;max-width:500px;width:100%;max-height:80vh;overflow-y:auto}
        .modal-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px}
        .modal-close{background:none;border:none;color:var(--text);cursor:pointer;font-size:20px}
        
        @media(max-width:900px){.row{grid-template-columns:1fr}.sidebar{display:none}.stats-grid{grid-template-columns:repeat(2,1fr)}.topbar-actions{overflow-x:auto}}
    </style>
</head>
<body>
<div class="topbar">
    <h1><i class="fa-solid fa-shield-halved"></i> <span>Menu QR</span> Admin</h1>
    <div class="topbar-actions">
        <a href="?tab=dashboard" class="<?=$onglet=='dashboard'?'active':''?>"><i class="fa-solid fa-chart-pie"></i> Dashboard</a>
        <span class="sep">|</span>
        <a href="?tab=restaurants" class="<?=in_array($onglet,['restaurants'])?'active':''?>"><i class="fa-solid fa-store"></i> Restos</a>
        <a href="?tab=commandes" class="<?=$onglet=='commandes'?'active':''?>"><i class="fa-solid fa-receipt"></i> Commandes</a>
        <a href="?tab=plats" class="<?=$onglet=='plats'?'active':''?>"><i class="fa-solid fa-utensils"></i> Plats</a>
        <a href="?tab=abonnements" class="<?=$onglet=='abonnements'?'active':''?>"><i class="fa-solid fa-crown"></i> Abonnés</a>
        <a href="?tab=paiements" class="<?=$onglet=='paiements'?'active':''?>"><i class="fa-solid fa-credit-card"></i> Paiements</a>
        <span class="sep">|</span>
        <a href="index.php" target="_blank"><i class="fa-solid fa-globe"></i> Site</a>
        <a href="?logout=1" style="color:#FCA5A5"><i class="fa-solid fa-right-from-bracket"></i></a>
    </div>
</div>

<div class="layout">
    <div class="sidebar">
        <div class="label">Principal</div>
        <a href="?tab=dashboard" class="<?=$onglet=='dashboard'?'active':''?>"><i class="fa-solid fa-chart-pie"></i> Tableau de bord</a>
        <a href="?tab=restaurants" class="<?=$onglet=='restaurants'?'active':''?>"><i class="fa-solid fa-store"></i> Restaurants <span class="count"><?=$nbRestos?></span></a>
        <a href="?tab=commandes" class="<?=$onglet=='commandes'?'active':''?>"><i class="fa-solid fa-receipt"></i> Commandes <span class="count"><?=$nbCommandes?></span></a>
        <a href="?tab=plats" class="<?=$onglet=='plats'?'active':''?>"><i class="fa-solid fa-utensils"></i> Tous les plats</a>
        <hr>
        <div class="label">Finances</div>
        <a href="?tab=abonnements" class="<?=$onglet=='abonnements'?'active':''?>"><i class="fa-solid fa-crown"></i> Abonnés <span class="count"><?=$nbAbonnes?></span></a>
        <a href="?tab=paiements" class="<?=$onglet=='paiements'?'active':''?>"><i class="fa-solid fa-credit-card"></i> Paiements</a>
        <hr>
        <div class="label">Exports</div>
        <a href="?export=restaurants"><i class="fa-solid fa-file-csv"></i> Restaurants CSV</a>
        <a href="?export=commandes"><i class="fa-solid fa-file-csv"></i> Commandes CSV</a>
        <a href="?export=paiements"><i class="fa-solid fa-file-csv"></i> Paiements CSV</a>
        <a href="?export=backup_json"><i class="fa-solid fa-database"></i> Backup JSON</a>
    </div>

    <div class="main">
        <?php if($msg):?><div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?=$msg?></div><?php endif;?>

        <!-- DASHBOARD -->
        <?php if($onglet=='dashboard'):?>
            <div class="stats-grid">
                <div class="stat-card"><div class="stat-icon o"><i class="fa-solid fa-store"></i></div><div class="stat-info"><div class="nb"><?=$nbRestos?></div><div class="lb">Restaurants</div></div></div>
                <div class="stat-card"><div class="stat-icon g"><i class="fa-solid fa-circle-check"></i></div><div class="stat-info"><div class="nb"><?=$nbActifs?></div><div class="lb">Actifs</div></div></div>
                <div class="stat-card"><div class="stat-icon r"><i class="fa-solid fa-circle-pause"></i></div><div class="stat-info"><div class="nb"><?=$nbSuspendus?></div><div class="lb">Suspendus</div></div></div>
                <div class="stat-card"><div class="stat-icon b"><i class="fa-solid fa-crown"></i></div><div class="stat-info"><div class="nb"><?=$nbAbonnes?></div><div class="lb">Abonnés</div></div></div>
                <div class="stat-card"><div class="stat-icon o"><i class="fa-solid fa-receipt"></i></div><div class="stat-info"><div class="nb"><?=$nbCommandes?></div><div class="lb">Commandes</div></div></div>
                <div class="stat-card"><div class="stat-icon g"><i class="fa-solid fa-coins"></i></div><div class="stat-info"><div class="nb"><?=formatPrix($caTotal)?></div><div class="lb">CA Commandes</div></div></div>
                <div class="stat-card"><div class="stat-icon y"><i class="fa-solid fa-credit-card"></i></div><div class="stat-info"><div class="nb"><?=formatPrix($caPaiements)?></div><div class="lb">CA Abonnements</div></div></div>
                <div class="stat-card"><div class="stat-icon b"><i class="fa-solid fa-qrcode"></i></div><div class="stat-info"><div class="nb"><?=number_format($nbScans)?></div><div class="lb">Scans QR</div></div></div>
            </div>
            
            <div class="row">
                <div class="card">
                    <div class="card-header"><h2><i class="fa-solid fa-calendar-day"></i> Aujourd'hui</h2></div>
                    <div class="card-body">
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                            <div><div class="lb">Commandes</div><div class="nb"><?=$cmdAujourdhui?></div><div style="font-size:11px;color:var(--sub)"><?=formatPrix($caAujourdhui)?></div></div>
                            <div><div class="lb">Inscriptions</div><div class="nb"><?=$inscriptionsAujourdhui?></div></div>
                            <div><div class="lb">Taux conversion</div><div class="nb"><?=$tauxConversion?>%</div></div>
                            <div><div class="lb">Paiements réussis</div><div class="nb"><?=$nbPaiementsReussis?></div></div>
                        </div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header"><h2><i class="fa-solid fa-chart-line"></i> CA 30 jours (FCFA)</h2></div>
                    <div class="card-body">
                        <svg class="chart-svg" viewBox="0 0 400 200">
                            <?php
                            $maxCA = max(array_column($caParJour,'ca')?:[1]);
                            $points = count($caParJour);
                            $polyline = '';
                            foreach ($caParJour as $i => $c) {
                                $x = 40 + ($i * 320 / max($points-1,1));
                                $y = 180 - (($c['ca'] / $maxCA) * 160);
                                $polyline .= "$x,$y ";
                            }
                            ?>
                            <polyline points="<?=$polyline?>" fill="none" stroke="#FF6B35" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                            <?php foreach ($caParJour as $i => $c): 
                                $x = 40 + ($i * 320 / max($points-1,1));
                                $y = 180 - (($c['ca'] / $maxCA) * 160);
                            ?>
                                <circle cx="<?=$x?>" cy="<?=$y?>" r="3" fill="#FF6B35"/>
                                <text x="<?=$x?>" y="195" text-anchor="middle" font-size="8" fill="var(--sub)"><?=date('d/m',strtotime($c['jour']))?></text>
                            <?php endforeach;?>
                        </svg>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="card">
                    <div class="card-header"><h2><i class="fa-solid fa-layer-group"></i> Modules</h2></div>
                    <div class="card-body">
                        <?php foreach($modulesStats as $m): $pct = $nbRestos>0?round(($m['nb']/$nbRestos)*100):0;?>
                            <div style="margin-bottom:12px">
                                <div style="display:flex;justify-content:space-between;font-size:12px"><span><?=$modNames[$m['module']]??$m['module']?></span><strong><?=$m['nb']?> (<?=$pct?>%)</strong></div>
                                <div class="progress-bar"><div class="progress-fill" style="width:<?=$pct?>%"></div></div>
                            </div>
                        <?php endforeach;?>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header"><h2><i class="fa-solid fa-trophy"></i> Top Restaurants</h2></div>
                    <div class="card-body">
                        <?php foreach($topRestos as $i=>$tr):?>
                            <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--border);font-size:12px">
                                <span>#<?=$i+1?> <strong><?=e($tr['nom_restaurant'])?></strong></span>
                                <span class="badge badge-abonne"><?=$tr['nb']?> cmd</span>
                            </div>
                        <?php endforeach;?>
                    </div>
                </div>
            </div>

        <!-- RESTAURANTS -->
        <?php elseif($onglet=='restaurants'):?>
            <form class="search" method="GET">
                <input type="hidden" name="tab" value="restaurants">
                <input type="text" name="q" value="<?=e($q)?>" placeholder="🔍 Rechercher par nom, téléphone, ville...">
                <button type="submit"><i class="fa-solid fa-magnifying-glass"></i> Rechercher</button>
                <?php if($q):?><a href="?tab=restaurants" class="btn btn-gray"><i class="fa-solid fa-xmark"></i></a><?php endif;?>
            </form>
            
            <div class="filtres">
                <a href="?tab=restaurants" class="filtre-btn <?=$filtre=='tous'?'active':''?>">Tous</a>
                <a href="?tab=restaurants&filtre=actifs" class="filtre-btn <?=$filtre=='actifs'?'active':''?>"><i class="fa-solid fa-circle-check"></i> Actifs</a>
                <a href="?tab=restaurants&filtre=suspendus" class="filtre-btn <?=$filtre=='suspendus'?'active':''?>"><i class="fa-solid fa-circle-pause"></i> Suspendus</a>
                <a href="?tab=restaurants&filtre=premium" class="filtre-btn <?=$filtre=='premium'?'active':''?>"><i class="fa-solid fa-crown"></i> Premium</a>
                <a href="?tab=restaurants&filtre=gratuit" class="filtre-btn <?=$filtre=='gratuit'?'active':''?>">Gratuit</a>
                <a href="?tab=restaurants&filtre=aujourdhui" class="filtre-btn <?=$filtre=='aujourdhui'?'active':''?>">Aujourd'hui</a>
            </div>
            
            <div class="card"><div class="card-header"><h2><i class="fa-solid fa-store"></i> Restaurants (<?=$totalRestos?>)</h2></div><div class="card-body"><table>
                <thead><tr>
                    <th>ID</th>
                    <th><a href="?tab=restaurants&tri=nom_restaurant&ordre=<?=$tri=='nom_restaurant'&&$ordre=='ASC'?'DESC':'ASC'?>">Nom <?php if($tri=='nom_restaurant'):?><i class="fa-solid fa-arrow-<?=$ordre=='ASC'?'up':'down'?>"></i><?php endif;?></a></th>
                    <th>Tél</th><th>Ville</th><th>Module</th>
                    <th><a href="?tab=restaurants&tri=statut&ordre=<?=$tri=='statut'&&$ordre=='ASC'?'DESC':'ASC'?>">Statut</a></th>
                    <th>Plats</th><th>Scans</th><th>Inscrit</th><th>Actions</th>
                </tr></thead><tbody>
                <?php foreach($restaurants as $r): $ab = ($r['module']!=='simple' && $r['date_expiration_module'] && strtotime($r['date_expiration_module'])>time());?>
                    <tr><td>#<?=$r['id']?></td>
                        <td><strong><?=e($r['nom_restaurant'])?></strong><br><small style="color:var(--sub)">/<?=e($r['slug'])?></small></td>
                        <td><?=e($r['telephone'])?></td>
                        <td><?=e($r['ville']??'—')?></td>
                        <td><?php if($ab):?><span class="badge badge-abonne"><i class="fa-solid fa-crown"></i> Premium</span><br><small>Exp: <?=date('d/m/Y',strtotime($r['date_expiration_module']))?></small><?php else:?><span class="badge badge-gratuit">Gratuit</span><?php endif;?></td>
                        <td><span class="badge <?=$r['statut']=='actif'?'badge-actif':'badge-suspendu'?>"><?=$r['statut']?></span></td>
                        <td><?=$r['nb_plats']?></td><td><?=$r['nb_scans']?></td><td><?=date('d/m/Y',strtotime($r['date_inscription']))?></td>
                        <td style="white-space:nowrap">
                            <a href="menu.php?id=<?=$r['id']?>" target="_blank" class="btn btn-o"><i class="fa-solid fa-eye"></i></a>
                            <?php if($ab):?><a href="?tab=restaurants&abo=desactiver&id=<?=$r['id']?>" class="btn btn-gray"><i class="fa-solid fa-crown"></i>❌</a><?php else:?><a href="?tab=restaurants&abo=activer&id=<?=$r['id']?>" class="btn btn-b" onclick="return confirm('Activer Premium 30 jours ?')"><i class="fa-solid fa-crown"></i></a><?php endif;?>
                            <?php if($r['statut']=='actif'):?><a href="?tab=restaurants&action=suspendre&id=<?=$r['id']?>" class="btn btn-r" onclick="return confirm('Suspendre ?')"><i class="fa-solid fa-circle-pause"></i></a><?php else:?><a href="?tab=restaurants&action=activer&id=<?=$r['id']?>" class="btn btn-g"><i class="fa-solid fa-circle-check"></i></a><?php endif;?>
                            <a href="?tab=restaurants&action=supprimer&id=<?=$r['id']?>" class="btn btn-r" onclick="return confirm('Supprimer définitivement ?')"><i class="fa-solid fa-trash-can"></i></a>
                        </td></tr>
                <?php endforeach;?></tbody></table></div>
                <?php if($totalPages>1):?>
                    <div class="pagination">
                        <?php for($i=1;$i<=$totalPages;$i++):?>
                            <a href="?tab=restaurants&p=<?=$i?>&tri=<?=$tri?>&ordre=<?=$ordre?>&filtre=<?=$filtre?>&q=<?=urlencode($q)?>" class="<?=$i==$page?'current':''?>"><?=$i?></a>
                        <?php endfor;?>
                    </div>
                <?php endif;?>
            </div>

        <!-- COMMANDES -->
        <?php elseif($onglet=='commandes'):?>
            <div class="card"><div class="card-header"><h2><i class="fa-solid fa-receipt"></i> Commandes (100 dernières)</h2></div><div class="card-body"><table>
                <thead><tr><th>Code</th><th>Date</th><th>Restaurant</th><th>Table</th><th>Client</th><th>Mode</th><th>Total</th><th>Statut</th><th></th></tr></thead><tbody>
                <?php foreach($commandes as $c):?>
                    <tr><td><span class="code"><?=e($c['code_suivi'])?></span></td><td><?=date('d/m H:i',strtotime($c['date_commande']))?></td><td><?=e($c['nom_restaurant'])?></td>
                        <td><?=e($c['numero_table']??'?')?></td><td><?=e($c['nom_client']??'Anonyme')?></td>
                        <td><span class="badge <?=$c['mode_commande']=='emporter'?'badge-ae':'badge-sp'?>"><i class="fa-solid <?=$c['mode_commande']=='emporter'?'fa-bag-shopping':'fa-house'?>"></i></span></td>
                        <td class="prix"><?=formatPrix($c['total'])?></td><td><span class="badge badge-<?=$c['statut']?>"><?=$c['statut']?></span></td>
                        <td><a href="?tab=commandes&supprimer_cmd=1&id=<?=$c['id']?>" class="btn btn-r" onclick="return confirm('Supprimer ?')"><i class="fa-solid fa-trash-can"></i></a></td></tr>
                <?php endforeach;?></tbody></table></div></div>

        <!-- PLATS -->
        <?php elseif($onglet=='plats'):?>
            <div class="card"><div class="card-header"><h2><i class="fa-solid fa-utensils"></i> Tous les plats (100 derniers)</h2></div><div class="card-body"><table>
                <thead><tr><th>ID</th><th>Photo</th><th>Plat</th><th>Restaurant</th><th>Prix</th><th>Dispo</th><th>Date</th></tr></thead><tbody>
                <?php foreach($tousPlats as $p):?>
                    <tr><td>#<?=$p['id']?></td>
                        <td><?php if($p['photo']):?><img src="public/uploads/plats/<?=e($p['photo'])?>" style="width:36px;height:36px;border-radius:6px;object-fit:cover"><?php else:?><i class="fa-solid fa-utensils" style="color:#D1D5DB"></i><?php endif;?></td>
                        <td><strong><?=e($p['nom'])?></strong></td><td><?=e($p['nom_restaurant'])?></td>
                        <td class="prix"><?=formatPrix($p['prix'])?></td>
                        <td><?=$p['disponible']?'<span class="badge badge-actif">Oui</span>':'<span class="badge badge-suspendu">Non</span>'?></td>
                        <td><?=date('d/m/Y',strtotime($p['date_creation']))?></td></tr>
                <?php endforeach;?></tbody></table></div></div>

        <!-- ABONNEMENTS -->
        <?php elseif($onglet=='abonnements'):?>
            <div class="card"><div class="card-header"><h2><i class="fa-solid fa-crown"></i> Abonnés (<?=$nbAbonnes?>)</h2></div><div class="card-body"><table>
                <thead><tr><th>Restaurant</th><th>Tél</th><th>Expire</th><th>Jours restants</th><th>Action</th></tr></thead><tbody>
                <?php foreach($abonnes as $a): $jr = ceil((strtotime($a['date_expiration_module'])-time())/86400);?>
                    <tr><td><strong><?=e($a['nom_restaurant'])?></strong></td><td><?=e($a['telephone'])?></td><td><?=date('d/m/Y',strtotime($a['date_expiration_module']))?></td>
                        <td><span class="badge <?=$jr<=7?'badge-suspendu':'badge-actif'?>"><?=$jr?> jours</span></td>
                        <td><a href="?tab=abonnements&abo=desactiver&id=<?=$a['id']?>" class="btn btn-r"><i class="fa-solid fa-circle-down"></i> Désactiver</a></td></tr>
                <?php endforeach;?></tbody></table></div></div>

        <!-- PAIEMENTS -->
        <?php elseif($onglet=='paiements'):?>
            <div class="card"><div class="card-header"><h2><i class="fa-solid fa-credit-card"></i> Paiements (50 derniers)</h2></div><div class="card-body"><table>
                <thead><tr><th>ID</th><th>Restaurant</th><th>Référence</th><th>Montant</th><th>Module</th><th>Statut</th><th>Date</th></tr></thead><tbody>
                <?php foreach($paiements as $p):?>
                    <tr><td>#<?=$p['id']?></td><td><?=e($p['nom_restaurant'])?></td><td><span class="code"><?=e($p['reference'])?></span></td>
                        <td class="prix"><?=formatPrix($p['montant'])?></td><td><?=$p['module']?></td>
                        <td><span class="badge <?=$p['statut']=='complete'?'badge-actif':'badge-attente'?>"><?=$p['statut']?></span></td>
                        <td><?=date('d/m/Y',strtotime($p['date_creation']))?></td></tr>
                <?php endforeach;?></tbody></table></div></div>
        <?php endif;?>
    </div>
</div>
</body>
</html>