<?php
/**
 * Dashboard Restaurateur - Menu QR
 * Version Pro sécurisée - NTIC Solution
 */
require_once __DIR__ . '/includes/config.php';

if (!isset($_SESSION['restaurant_id'])) { header('Location: connexion.php'); exit; }

$db = getDB();
$stmt = $db->prepare("SELECT * FROM restaurants WHERE id=?");
$stmt->execute([$_SESSION['restaurant_id']]);
$restaurant = $stmt->fetch();
if (!$restaurant) { session_destroy(); header('Location: connexion.php'); exit; }

// Calculer si l'abonnement est actif
$abonne = false;
if ($restaurant['module'] !== 'simple' && !empty($restaurant['date_expiration_module'])) {
    if (strtotime($restaurant['date_expiration_module']) > time()) {
        $abonne = true;
    } else {
        $db->prepare("UPDATE restaurants SET module='simple', date_expiration_module=NULL WHERE id=?")->execute([$restaurant['id']]);
        $restaurant['module'] = 'simple';
        $restaurant['date_expiration_module'] = null;
    }
}

// Si on vient d'une activation, re-vérifier depuis la base
if (isset($_GET['upgrade']) && $_GET['upgrade'] === '1') {
    $stmt = $db->prepare("SELECT module, date_expiration_module FROM restaurants WHERE id = ?");
    $stmt->execute([$restaurant['id']]);
    $fresh = $stmt->fetch();
    if ($fresh) {
        $restaurant['module'] = $fresh['module'];
        $restaurant['date_expiration_module'] = $fresh['date_expiration_module'];
        $abonne = ($fresh['module'] !== 'simple' && !empty($fresh['date_expiration_module']) && strtotime($fresh['date_expiration_module']) > time());
    }
}

$uploadDir = __DIR__ . '/public/uploads/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
$logoDir = $uploadDir . 'logos/';
if (!is_dir($logoDir)) mkdir($logoDir, 0755, true);
$platsDir = $uploadDir . 'plats/';
if (!is_dir($platsDir)) mkdir($platsDir, 0755, true);

// Photo de profil (Premium uniquement)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_logo'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        header('Location: dashboard.php?tab=infos&erreur=csrf'); exit;
    }
    if ($abonne && !empty($_FILES['logo_file']['name']) && validateUpload($_FILES['logo_file'])) {
        $ext = strtolower(pathinfo($_FILES['logo_file']['name'], PATHINFO_EXTENSION));
        $logoName = 'resto_' . $restaurant['id'] . '_' . time() . '.' . $ext;
        move_uploaded_file($_FILES['logo_file']['tmp_name'], $logoDir . $logoName);
        if ($restaurant['logo'] && file_exists($logoDir . $restaurant['logo'])) unlink($logoDir . $restaurant['logo']);
        $db->prepare("UPDATE restaurants SET logo = ? WHERE id = ?")->execute([$logoName, $restaurant['id']]);
        $restaurant['logo'] = $logoName;
    }
    header('Location: dashboard.php?tab=infos'); exit;
}

// Catégories
$stmt = $db->prepare("SELECT * FROM categories WHERE restaurant_id=? ORDER BY ordre ASC");
$stmt->execute([$restaurant['id']]);
$categories = $stmt->fetchAll();

// Plats
$stmt = $db->prepare("SELECT p.*, c.nom as cat_nom FROM plats p LEFT JOIN categories c ON p.categorie_id=c.id WHERE p.restaurant_id=? ORDER BY p.ordre ASC, p.nom ASC");
$stmt->execute([$restaurant['id']]);
$plats = $stmt->fetchAll();

// Commandes
$stmt = $db->prepare("SELECT * FROM commandes WHERE restaurant_id=? ORDER BY date_commande DESC LIMIT 50");
$stmt->execute([$restaurant['id']]);
$commandes = $stmt->fetchAll();

$lastCmdId = $db->query("SELECT MAX(id) FROM commandes WHERE restaurant_id=".intval($restaurant['id']))->fetchColumn();

$message = '';
if (isset($_GET['welcome'])) $message = '<i class="fa-solid fa-party-horn"></i> Bienvenue ! Commencez par ajouter vos plats.';
if (isset($_GET['ok'])) $message = '<i class="fa-solid fa-circle-check"></i> Plat enregistré !';
if (isset($_GET['edit'])) $message = '<i class="fa-solid fa-pen-to-square"></i> Plat modifié !';
if (isset($_GET['upgrade'])) $message = '<i class="fa-solid fa-crown"></i> Abonnement Premium activé !';
if (isset($_GET['generated'])) $message = '<i class="fa-solid fa-qrcode"></i> ' . $_GET['generated'] . ' QR codes générés !';
if (isset($_GET['erreur']) && $_GET['erreur'] === 'limite') $message = '<i class="fa-solid fa-triangle-exclamation"></i> Limite de '.ABONNEMENT_PLATS_MAX_FREE.' plats atteinte. <a href="?tab=abonnement" style="color:#065F46;font-weight:700;text-decoration:underline">Passez Premium pour '.ABONNEMENT_PLATS_MAX_PREMIUM.' plats →</a>';
if (isset($_GET['erreur']) && $_GET['erreur'] === 'csrf') $message = '<i class="fa-solid fa-triangle-exclamation"></i> Session expirée. Réessayez.';

// AJOUTER/MODIFIER PLAT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_plat'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        header('Location: dashboard.php?tab=menu&erreur=csrf'); exit;
    }
    
    $plat_id = $_POST['plat_id'] ?? null;
    $nom = trim($_POST['nom_plat'] ?? '');
    $prix = intval($_POST['prix'] ?? 0);
    $cat_id = !empty($_POST['categorie_id']) ? intval($_POST['categorie_id']) : null;
    $desc = trim($_POST['description'] ?? '');
    $dispo = isset($_POST['disponible']) ? 1 : 0;
    $ordre = intval($_POST['ordre'] ?? 0);
    $badge = in_array($_POST['badge'] ?? 'aucun', ['aucun','epice','vegetarien','best_seller']) ? $_POST['badge'] : 'aucun';
    $photo = null;
    
    // Vérifier la limite de plats pour les gratuits
    if (!$abonne && !$plat_id) {
        $nbPlatsFree = $db->query("SELECT COUNT(*) FROM plats WHERE restaurant_id=".intval($restaurant['id']))->fetchColumn();
        if ($nbPlatsFree >= ABONNEMENT_PLATS_MAX_FREE) {
            header('Location: dashboard.php?tab=menu&erreur=limite'); exit;
        }
    }
    
    // Avertissement si des plats sont bloqués
if (!$abonne) {
    $nbPlatsTotal = count($plats);
    if ($nbPlatsTotal > ABONNEMENT_PLATS_MAX_FREE) {
        $nbBloques = $nbPlatsTotal - ABONNEMENT_PLATS_MAX_FREE;
        $message = '<i class="fa-solid fa-triangle-exclamation"></i> ' . $nbBloques . ' plat(s) sont grisés car votre abonnement a expiré. <a href="?tab=abonnement" style="color:#065F46;font-weight:700;text-decoration:underline">Réactiver Premium →</a>';
    }
}
    // Upload photo (Premium uniquement)
    if ($abonne && !empty($_FILES['photo']['name']) && validateUpload($_FILES['photo'])) {
        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        if ($plat_id) {
            $stmt = $db->prepare("SELECT photo FROM plats WHERE id=? AND restaurant_id=?");
            $stmt->execute([$plat_id, $restaurant['id']]);
            $old = $stmt->fetch();
            if ($old && $old['photo'] && file_exists($platsDir.$old['photo'])) unlink($platsDir.$old['photo']);
        }
        $photo = 'plat_'.time().'_'.rand(1000,9999).'.'.$ext;
        move_uploaded_file($_FILES['photo']['tmp_name'], $platsDir.$photo);
    }
    
    if ($nom && $prix > 0) {
        if ($plat_id) {
            if ($photo) {
                $db->prepare("UPDATE plats SET nom=?, prix=?, categorie_id=?, description=?, disponible=?, ordre=?, badge=?, photo=? WHERE id=? AND restaurant_id=?")->execute([$nom, $prix, $cat_id, $desc, $dispo, $ordre, $badge, $photo, $plat_id, $restaurant['id']]);
            } else {
                $db->prepare("UPDATE plats SET nom=?, prix=?, categorie_id=?, description=?, disponible=?, ordre=?, badge=? WHERE id=? AND restaurant_id=?")->execute([$nom, $prix, $cat_id, $desc, $dispo, $ordre, $badge, $plat_id, $restaurant['id']]);
            }
            header('Location: dashboard.php?tab=menu&edit=1'); exit;
        } else {
            $db->prepare("INSERT INTO plats (restaurant_id, categorie_id, nom, description, prix, photo, disponible, ordre, badge) VALUES (?,?,?,?,?,?,?,?,?)")->execute([$restaurant['id'], $cat_id, $nom, $desc, $prix, $photo, $dispo, $ordre, $badge]);
            header('Location: dashboard.php?tab=menu&ok=1'); exit;
        }
    }
}

// AJOUTER CATÉGORIE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajouter_categorie'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        header('Location: dashboard.php?tab=menu&erreur=csrf'); exit;
    }
    $nom_cat = trim($_POST['nom_categorie'] ?? '');
    if ($nom_cat) $db->prepare("INSERT INTO categories (restaurant_id, nom) VALUES (?,?)")->execute([$restaurant['id'], $nom_cat]);
    header('Location: dashboard.php?tab=menu'); exit;
}

// SUPPRIMER PLAT
if (isset($_GET['supprimer'])) {
    $stmt = $db->prepare("SELECT photo FROM plats WHERE id=? AND restaurant_id=?");
    $stmt->execute([intval($_GET['supprimer']), $restaurant['id']]);
    $p = $stmt->fetch();
    if ($p && $p['photo'] && file_exists($platsDir.$p['photo'])) unlink($platsDir.$p['photo']);
    $db->prepare("DELETE FROM plats WHERE id=? AND restaurant_id=?")->execute([intval($_GET['supprimer']), $restaurant['id']]);
    header('Location: dashboard.php?tab=menu'); exit;
}

// DISPONIBILITÉ
if (isset($_GET['toggle_dispo'])) {
    $pid = intval($_GET['toggle_dispo']);
    $db->prepare("UPDATE plats SET disponible = 1 - disponible WHERE id=? AND restaurant_id=?")->execute([$pid, $restaurant['id']]);
    header('Location: dashboard.php?tab=menu'); exit;
}

// ORDRE
if (isset($_GET['move'])) {
    $pid = intval($_GET['move']); $dir = $_GET['dir'] ?? 'up';
    $stmt = $db->prepare("SELECT ordre FROM plats WHERE id=? AND restaurant_id=?");
    $stmt->execute([$pid, $restaurant['id']]);
    $current = $stmt->fetch();
    if ($current) {
        $newOrdre = $dir === 'up' ? $current['ordre'] - 1 : $current['ordre'] + 1;
        $db->prepare("UPDATE plats SET ordre=? WHERE id=? AND restaurant_id=?")->execute([$newOrdre, $pid, $restaurant['id']]);
    }
    header('Location: dashboard.php?tab=menu'); exit;
}

// STATUT COMMANDE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['changer_statut'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        header('Location: dashboard.php?tab=commandes&erreur=csrf'); exit;
    }
    $cid = intval($_POST['commande_id'] ?? 0);
    $nv = $_POST['nouveau_statut'] ?? '';
    if ($cid > 0 && in_array($nv, ['confirmee','en_preparation','prete','livree','annulee'])) {
        $db->prepare("UPDATE commandes SET statut=? WHERE id=? AND restaurant_id=?")->execute([$nv, $cid, $restaurant['id']]);
        try { $db->prepare("INSERT INTO suivi_commandes (commande_id, statut) VALUES (?,?)")->execute([$cid, $nv]); } catch (Exception $e) {}
    }
    header('Location: dashboard.php?tab=commandes'); exit;
}

// INFOS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_infos'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        header('Location: dashboard.php?tab=infos&erreur=csrf'); exit;
    }
    $nom = trim($_POST['nom_restaurant'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $adr = trim($_POST['adresse'] ?? '');
    $ouv = $_POST['heure_ouverture'] ?? '07:00';
    $ferm = $_POST['heure_fermeture'] ?? '22:00';
    $wa = preg_replace('/[^0-9]/', '', $_POST['whatsapp'] ?? '');
    $newSlug = trim($_POST['slug'] ?? '');
    if ($newSlug && $newSlug !== $restaurant['slug']) {
        $newSlug = strtolower(preg_replace('/[^a-z0-9-]/', '', $newSlug));
        $newSlug = trim($newSlug, '-');
        if (!empty($newSlug)) {
            $stmt = $db->prepare("SELECT id FROM restaurants WHERE slug = ? AND id != ?");
            $stmt->execute([$newSlug, $restaurant['id']]);
            if ($stmt->fetch()) $newSlug .= '-' . $restaurant['id'];
            $db->prepare("UPDATE restaurants SET slug = ? WHERE id = ?")->execute([$newSlug, $restaurant['id']]);
            $restaurant['slug'] = $newSlug;
        }
    }
    $db->prepare("UPDATE restaurants SET nom_restaurant=?, description=?, adresse=?, heure_ouverture=?, heure_fermeture=?, telephone_whatsapp=?, slug=? WHERE id=?")->execute([$nom, $desc, $adr, $ouv, $ferm, $wa, $restaurant['slug'], $restaurant['id']]);
    $_SESSION['restaurant_nom'] = $nom;
    header('Location: dashboard.php?tab=infos'); exit;
}

$menuUrl = SITE_URL . '/' . $restaurant['slug'] . '?auto=1';
$qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&color=FF6B35&data=' . urlencode($menuUrl);
$tab = $_GET['tab'] ?? 'menu';
$editPlat = null;
if (isset($_GET['edit_plat'])) {
    $stmt = $db->prepare("SELECT * FROM plats WHERE id=? AND restaurant_id=?");
    $stmt->execute([intval($_GET['edit_plat']), $restaurant['id']]);
    $editPlat = $stmt->fetch();
}
$tablesQR = [];
try { $stmt = $db->prepare("SELECT * FROM tables_qr WHERE restaurant_id=? ORDER BY numero_table"); $stmt->execute([$restaurant['id']]); $tablesQR = $stmt->fetchAll(); } catch (Exception $e) {}
$logoResto = ($restaurant['logo'] && $abonne) ? 'public/uploads/logos/' . $restaurant['logo'] : LOGO_URL;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#FF6B35">
    <title><?= e($restaurant['nom_restaurant']) ?> - <?=SITE_NAME?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
    :root {
        --o: #FF6B35;
        --g: #10B981;
        --r: #EF4444;
        --b: #3B82F6;
        --bg: #F1F5F9;
        --card: #fff;
        --text: #1E293B;
        --sub: #64748B;
        --border: #E2E8F0;
        --shadow: 0 1px 3px rgba(0,0,0,.05);
        --input-bg: #fff;
        --hover-bg: #FFF7ED;
        --table-hover: #FFF7ED;
        --badge-gratuit-bg: #F1F5F9;
        --badge-gratuit-text: #64748B;
        --detail-bg: #F8FAFC;
        --qr-card-bg: #FFF7ED;
        --btn-icon-bg: #F1F5F9;
        --btn-icon-color: #64748B;
        --bottom-nav-bg: #fff;
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        background: var(--bg); color: var(--text); font-size: 14px;
        padding-top: 56px; padding-bottom: 72px;
    }

    .topbar {
        position: fixed; top: 0; left: 0; right: 0; height: 56px;
        background: var(--o); color: #fff; display: flex;
        align-items: center; justify-content: space-between;
        padding: 0 16px; z-index: 100; box-shadow: 0 2px 8px rgba(255,107,53,.3);
    }
    .topbar h1 { font-size: 16px; font-weight: 700; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 55%; display: flex; align-items: center; gap: 8px; }
    .topbar h1 img { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; border: 2px solid #fff; }
    .topbar .badge { padding: 4px 10px; border-radius: 12px; font-size: 10px; font-weight: 700; background: rgba(255,255,255,.2); white-space: nowrap; }
    .topbar .badge-on { background: var(--b); box-shadow: 0 2px 8px rgba(59,130,246,.4); }
    .topbar .badge-on i { color: #fff; }
    .topbar a { color: #fff; text-decoration: none; font-size: 14px; padding: 4px; }

    .container { max-width: 680px; margin: 0 auto; padding: 12px 14px; }

    .card { background: var(--card); border-radius: 16px; padding: 18px; margin-bottom: 14px; box-shadow: var(--shadow); }
    .card h2 { font-size: 16px; font-weight: 700; margin-bottom: 14px; display: flex; align-items: center; gap: 8px; }
    .card h2 i { color: var(--o); }

    .alert { padding: 12px 16px; border-radius: 12px; margin-bottom: 14px; font-size: 13px; font-weight: 500; text-align: center; }
    .alert-success { background: #ECFDF5; color: #065F46; border: 1px solid #A7F3D0; }
    .alert-error { background: #FEE2E2; color: #991B1B; border: 1px solid #FECACA; }
    .alert i { margin-right: 6px; }
    .alert a { color: inherit; font-weight: 700; }

    .btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 10px 16px; border-radius: 10px; font-weight: 600; font-size: 13px; border: none; cursor: pointer; text-decoration: none; transition: .15s; white-space: nowrap; }
    .btn-o { background: var(--o); color: #fff; }
    .btn-o:active { background: #e55a2b; }
    .btn-out { background: var(--card); color: var(--o); border: 2px solid var(--o); }
    .btn-r { background: var(--r); color: #fff; padding: 6px 10px; font-size: 11px; border-radius: 8px; }
    .btn-g { background: var(--g); color: #fff; padding: 6px 10px; font-size: 11px; border-radius: 8px; }
    .btn-b { background: var(--b); color: #fff; padding: 6px 10px; font-size: 11px; border-radius: 8px; }
    .btn-block { display: flex; width: 100%; }
    .btn-sm { padding: 6px 10px; font-size: 11px; border-radius: 6px; }
    .btn:disabled { opacity: .5; cursor: not-allowed; }

    .btn-icon { width: 34px; height: 34px; border-radius: 8px; display: flex; align-items: center; justify-content: center; text-decoration: none; font-size: 13px; background: var(--btn-icon-bg); color: var(--btn-icon-color); transition: .15s; }
    .btn-icon:hover { background: #E2E8F0; transform: scale(1.05); }
    .btn-icon-green { background: #ECFDF5; color: #10B981; }
    .btn-icon-green:hover { background: #D1FAE5; }
    .btn-icon-red { background: #FEE2E2; color: #EF4444; }
    .btn-icon-red:hover { background: #FECACA; }
    .btn-icon-blue { background: #EFF6FF; color: #3B82F6; }
    .btn-icon-blue:hover { background: #DBEAFE; }

    .form-group { margin-bottom: 10px; }
    label { display: block; font-weight: 600; margin-bottom: 4px; font-size: 12px; color: var(--sub); }
    label i { color: var(--o); width: 16px; }
    input, select, textarea { width: 100%; padding: 10px 12px; border: 2px solid var(--border); border-radius: 10px; font-size: 14px; font-family: inherit; background: var(--input-bg); color: var(--text); transition: .2s; }
    input:focus, select:focus, textarea:focus { outline: none; border-color: var(--o); box-shadow: 0 0 0 3px rgba(255,107,53,.1); }
    input:disabled, select:disabled, textarea:disabled { background: #F1F5F9; cursor: not-allowed; opacity: .7; }
    .form-row { display: flex; gap: 8px; }
    .form-row > * { flex: 1; }

    .table-wrap { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; font-size: 12px; }
    th, td { padding: 8px 6px; text-align: left; border-bottom: 1px solid var(--border); }
    th { background: var(--bg); font-weight: 600; color: var(--sub); font-size: 10px; text-transform: uppercase; }

    .prix { font-weight: 700; color: var(--o); }
    .photo-thumb { width: 44px; height: 44px; border-radius: 8px; object-fit: cover; }

    .cmd-card { background: var(--card); border-radius: 12px; padding: 14px; margin-bottom: 10px; box-shadow: var(--shadow); border-left: 4px solid var(--o); }

    .tag { display: inline-block; padding: 3px 8px; border-radius: 8px; font-size: 10px; font-weight: 700; }
    .tag-attente { background: #FFF3CD; color: #856404; }
    .tag-confirmee, .tag-preparation { background: #DBEAFE; color: #1E40AF; }
    .tag-prete, .tag-livree { background: #ECFDF5; color: #065F46; }
    .tag-annulee { background: #FEE2E2; color: #991B1B; }
    .tag-sp { background: #DBEAFE; color: #1E40AF; }
    .tag-ae { background: #FEF3C7; color: #92400E; }

    .detail-plats { background: var(--detail-bg); border-radius: 10px; padding: 10px 14px; margin: 8px 0; }
    .detail-plat-item { display: flex; justify-content: space-between; padding: 4px 0; font-size: 13px; border-bottom: 1px dotted var(--border); }
    .detail-plat-item:last-child { border-bottom: none; }

    .qr-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 10px; }
    .qr-card { text-align: center; padding: 12px; border: 2px solid var(--o); border-radius: 14px; background: var(--qr-card-bg); }
    .qr-card img { width: 100px; height: 100px; border-radius: 8px; padding: 4px; background: var(--card); }

    .abo-card { text-align: center; padding: 20px; }
    .abo-card .prix { font-size: 32px; font-weight: 800; color: var(--o); }
    .abo-card ul { list-style: none; margin: 16px 0; text-align: left; }
    .abo-card ul li { padding: 8px 0; font-size: 14px; border-bottom: 1px solid var(--border); }
    .abo-card ul li i { color: var(--g); margin-right: 8px; }

    .logo-upload { display: flex; align-items: center; gap: 16px; margin-bottom: 16px; }
    .logo-upload img { width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 3px solid var(--o); }

    .premium-locked { background: #FFF7ED; padding: 14px; border-radius: 12px; text-align: center; border: 2px dashed var(--o); margin-top: 8px; }
    .premium-locked p { color: var(--o); font-weight: 600; font-size: 13px; }

    .bottom-nav { position: fixed; bottom: 0; left: 0; right: 0; height: 64px; background: var(--bottom-nav-bg); display: flex; align-items: center; justify-content: space-around; z-index: 100; border-top: 1px solid var(--border); padding-bottom: env(safe-area-inset-bottom); box-shadow: 0 -2px 10px rgba(0,0,0,.04); }
    .bottom-nav a { display: flex; flex-direction: column; align-items: center; gap: 3px; text-decoration: none; color: var(--sub); font-size: 10px; font-weight: 500; padding: 6px 10px; border-radius: 12px; transition: .15s; min-width: 56px; }
    .bottom-nav a.active { color: var(--o); font-weight: 700; }
    .bottom-nav a.active .nav-icon { background: #FFF7ED; }
    .bottom-nav .nav-icon { font-size: 18px; width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; border-radius: 50%; transition: .15s; }
    .bottom-nav .notif-dot { position: relative; }
    .bottom-nav .notif-dot::after { content: ''; position: absolute; top: 0; right: 2px; width: 8px; height: 8px; background: var(--r); border-radius: 50%; border: 2px solid #fff; }

    .empty { text-align: center; padding: 40px; color: var(--sub); }
    .empty i { font-size: 48px; margin-bottom: 10px; color: #D1D5DB; }

    .statut-badge { padding: 4px 10px; border-radius: 12px; font-size: 10px; font-weight: 600; }
    .statut-visible { background: #ECFDF5; color: #065F46; }
    .statut-cache { background: #FEE2E2; color: #991B1B; }

    .cat-tag { background: #FFF7ED; color: var(--o); padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; }
    .badge-gratuit { background: var(--badge-gratuit-bg); color: var(--badge-gratuit-text); }

    @media (min-width: 768px) { .container { max-width: 750px; } }

  /* Mode sombre forcé par classe */
body.dark {
    --bg: #0F172A;
    --card: #1E293B;
    --text: #F1F5F9;
    --sub: #94A3B8;
    --border: #334155;
    --input-bg: #1E293B;
    --hover-bg: #263348;
    --table-hover: #263348;
    --badge-gratuit-bg: #334155;
    --badge-gratuit-text: #94A3B8;
    --detail-bg: #1a1f2e;
    --qr-card-bg: #1a1f2e;
    --btn-icon-bg: #263348;
    --btn-icon-color: #94A3B8;
    --bottom-nav-bg: #1E293B;
}

body.dark .card { background: var(--card) !important; }
body.dark .card h2 { color: var(--text) !important; }
body.dark .card p, body.dark .card span, body.dark .card div, body.dark .card li, body.dark .card small, body.dark .card strong, body.dark .card label, body.dark .card td, body.dark .card th {
    color: var(--text) !important;
}
body.dark .card .prix, body.dark .prix { color: var(--o) !important; }
body.dark label { color: var(--sub) !important; }
body.dark input, body.dark select, body.dark textarea { background: var(--input-bg) !important; color: var(--text) !important; border-color: var(--border) !important; }
body.dark input:disabled, body.dark select:disabled, body.dark textarea:disabled { background: #1a1f2e !important; }
body.dark input::placeholder, body.dark textarea::placeholder { color: #64748B !important; }
body.dark th { background: #1E293B !important; color: var(--sub) !important; }
body.dark tr:hover td { background: var(--table-hover) !important; }
body.dark table { background: var(--card) !important; }
body.dark .detail-plats { background: var(--detail-bg) !important; }
body.dark .detail-plat-item { border-bottom-color: var(--border) !important; }
body.dark .cmd-card { background: var(--card) !important; border-left-color: var(--o) !important; }
body.dark .qr-card { background: var(--qr-card-bg) !important; border-color: var(--o) !important; }
body.dark .qr-card img { background: var(--card) !important; }
body.dark .abo-card ul li { border-bottom-color: var(--border) !important; }
body.dark .bottom-nav { background: var(--bottom-nav-bg) !important; border-top-color: var(--border) !important; }
body.dark .bottom-nav a { color: var(--sub) !important; }
body.dark .bottom-nav a.active { color: var(--o) !important; }
body.dark .bottom-nav a.active .nav-icon { background: #3E1F0A !important; }
body.dark .btn-icon { background: var(--btn-icon-bg) !important; color: var(--btn-icon-color) !important; }
body.dark .btn-icon:hover { background: #334155 !important; }
body.dark .btn-icon-green { background: #064E3B !important; color: #6EE7B7 !important; }
body.dark .btn-icon-red { background: #7F1D1D !important; color: #FCA5A5 !important; }
body.dark .btn-icon-blue { background: #1E3A5F !important; color: #93C5FD !important; }
body.dark .btn-out { background: transparent !important; }
body.dark .cat-tag { background: #3E1F0A !important; color: var(--o) !important; }
body.dark .premium-locked { background: #3E1F0A !important; border-color: var(--o) !important; }
body.dark .statut-visible { background: #064E3B !important; color: #6EE7B7 !important; }
body.dark .statut-cache { background: #7F1D1D !important; color: #FCA5A5 !important; }
body.dark .tag-attente { background: #3E2D00 !important; color: #FDE68A !important; }
body.dark .tag-confirmee, body.dark .tag-preparation { background: #1E3A5F !important; color: #93C5FD !important; }
body.dark .tag-prete, body.dark .tag-livree { background: #064E3B !important; color: #6EE7B7 !important; }
body.dark .tag-annulee { background: #7F1D1D !important; color: #FCA5A5 !important; }
body.dark .tag-sp { background: #1E3A5F !important; color: #93C5FD !important; }
body.dark .tag-ae { background: #3E2D00 !important; color: #FDE68A !important; }
body.dark .alert-success { background: #064E3B !important; color: #6EE7B7 !important; border-color: #065F46 !important; }
body.dark .alert-error { background: #7F1D1D !important; color: #FCA5A5 !important; border-color: #991B1B !important; }
body.dark .empty i { color: #334155 !important; }
body.dark .badge-gratuit { background: var(--badge-gratuit-bg) !important; color: var(--badge-gratuit-text) !important; }
body.dark .logo-upload img { border-color: var(--o) !important; }
body.dark #abo-actif { background: #064E3B !important; }
body.dark #abo-titre,
body.dark #abo-expire,
body.dark #abo-date,
body.dark #abo-jours { color: #6EE7B7 !important; }
body.dark #code-label { color: #F1F5F9 !important; }
body.dark #menu-url-box { background: var(--badge-gratuit-bg) !important; }
body.dark #menu-url-text { color: var(--o) !important; }
</style>
</head>
<body>

<div class="topbar">
    <h1>
        <img src="<?=$logoResto?>" alt="Logo">
        <?= e($restaurant['nom_restaurant']) ?>
    </h1>
    <div style="display:flex;align-items:center;gap:8px">
       <a href="#" onclick="toggleDarkMode()" title="Mode sombre" id="darkToggle" style="font-size:16px">
    <i class="fa-solid fa-moon"></i>
</a>
        <a href="stats-resto.php" title="Statistiques"><i class="fa-solid fa-chart-simple"></i></a>
        <span class="badge <?= $abonne ? 'badge-on' : '' ?>"><i class="fa-solid <?= $abonne ? 'fa-circle-check' : 'fa-mug-hot' ?>"></i> <?= $abonne ? 'Verified' : 'Gratuit' ?></span>
        <a href="deconnexion.php" title="Déconnexion"><i class="fa-solid fa-right-from-bracket"></i></a>
    </div>
</div>

<div class="container">
    <?php if($message):?><div class="alert alert-success"><?=$message?></div><?php endif;?>

    <!-- ===== MENU ===== -->
    <?php if($tab=='menu'):?>
        <?php if($editPlat):?>
            <div class="card">
                <h2><i class="fa-solid fa-pen-to-square"></i> Modifier le plat</h2>
                <form method="POST" enctype="multipart/form-data">
                    <?=csrfField()?>
                    <input type="hidden" name="plat_id" value="<?=$editPlat['id']?>">
                    <div class="form-group"><label><i class="fa-solid fa-utensils"></i> Nom</label><input type="text" name="nom_plat" value="<?=e($editPlat['nom'])?>" required></div>
                    <div class="form-row">
                        <div class="form-group"><label><i class="fa-solid fa-tag"></i> Prix</label><input type="number" name="prix" value="<?=$editPlat['prix']?>" required min="100"></div>
                        <div class="form-group"><label><i class="fa-solid fa-folder"></i> Catégorie</label>
                            <select name="categorie_id"><option value="">Sans catégorie</option>
                                <?php foreach($categories as $c):?><option value="<?=$c['id']?>" <?=$editPlat['categorie_id']==$c['id']?'selected':''?>><?=e($c['nom'])?></option><?php endforeach;?>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label><i class="fa-solid fa-sort"></i> Ordre</label><input type="number" name="ordre" value="<?=$editPlat['ordre']?>"></div>
                        <div class="form-group"><label><i class="fa-solid fa-medal"></i> Badge</label>
                            <select name="badge">
                                <option value="aucun" <?=$editPlat['badge']=='aucun'?'selected':''?>>Aucun</option>
                                <option value="epice" <?=$editPlat['badge']=='epice'?'selected':''?>>🌶️ Épicé</option>
                                <option value="vegetarien" <?=$editPlat['badge']=='vegetarien'?'selected':''?>>🥬 Végétarien</option>
                                <option value="best_seller" <?=$editPlat['badge']=='best_seller'?'selected':''?>>⭐ Best-seller</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group"><label><i class="fa-solid fa-align-left"></i> Description</label><textarea name="description" rows="2"><?=e($editPlat['description'])?></textarea></div>
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px">
                        <label style="margin:0;display:flex;align-items:center;gap:6px;cursor:pointer">
                            <input type="checkbox" name="disponible" <?=$editPlat['disponible']?'checked':''?>> <i class="fa-solid fa-eye"></i> Disponible
                        </label>
                    </div>
                    <?php if($abonne):?>
                        <div class="form-group"><label><i class="fa-solid fa-camera"></i> Photo</label><input type="file" name="photo" accept="image/jpeg,image/png,image/webp"></div>
                    <?php else:?>
                        <div class="form-group" style="opacity:.7">
                            <label><i class="fa-solid fa-camera"></i> Photo <span style="font-size:10px;color:var(--o);font-weight:600">(Premium)</span></label>
                            <input type="file" disabled style="background:#F1F5F9;cursor:not-allowed">
                        </div>
                    <?php endif;?>
                    <div style="display:flex;gap:8px">
                        <button name="save_plat" class="btn btn-o btn-block"><i class="fa-solid fa-floppy-disk"></i> Enregistrer</button>
                        <a href="?tab=menu" class="btn btn-out" style="flex:1">Annuler</a>
                    </div>
                </form>
            </div>
        <?php else:?>
            <div class="card">
                <h2><i class="fa-solid fa-folder-tree"></i> Catégories</h2>
                <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px">
                    <?php foreach($categories as $c):?>
                        <span class="cat-tag"><i class="fa-solid fa-folder"></i> <?=e($c['nom'])?></span>
                    <?php endforeach;?>
                    <?php if(empty($categories)):?><span style="color:var(--sub);font-size:12px">Aucune catégorie</span><?php endif;?>
                </div>
                <form method="POST" style="display:flex;gap:6px">
                    <?=csrfField()?>
                    <input type="text" name="nom_categorie" placeholder="Nouvelle catégorie" required style="flex:1">
                    <button name="ajouter_categorie" class="btn btn-o"><i class="fa-solid fa-plus"></i></button>
                </form>
            </div>

            <div class="card">
                <h2><i class="fa-solid fa-circle-plus"></i> Ajouter un plat</h2>
                <form method="POST" enctype="multipart/form-data">
                    <?=csrfField()?>
                    <div class="form-group"><input type="text" name="nom_plat" placeholder="Nom du plat *" required></div>
                    <div class="form-row">
                        <div class="form-group"><input type="number" name="prix" placeholder="Prix (<?=DEVISE_SYMBOLE?>) *" required min="100"></div>
                        <div class="form-group">
                            <select name="categorie_id"><option value="">Sans catégorie</option>
                                <?php foreach($categories as $c):?><option value="<?=$c['id']?>"><?=e($c['nom'])?></option><?php endforeach;?>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><textarea name="description" placeholder="Description" rows="2"></textarea></div>
                        <div class="form-group"><input type="number" name="ordre" value="0" placeholder="Ordre"></div>
                    </div>
                    <?php if($abonne):?>
                        <div class="form-group"><label><i class="fa-solid fa-camera"></i> Photo</label><input type="file" name="photo" accept="image/jpeg,image/png,image/webp"></div>
                    <?php else:?>
                        <div class="form-group" style="opacity:.7">
                            <label><i class="fa-solid fa-camera"></i> Photo <span style="font-size:10px;color:var(--o);font-weight:600">(Premium)</span></label>
                            <input type="file" disabled style="background:#F1F5F9;cursor:not-allowed">
                            <p style="font-size:10px;color:var(--sub);margin-top:4px"><i class="fa-solid fa-crown"></i> Passez Premium pour ajouter des photos</p>
                        </div>
                    <?php endif;?>
                    <button name="save_plat" class="btn btn-o btn-block"><i class="fa-solid fa-check"></i> Ajouter le plat</button>
                </form>
            </div>

            <div class="card" style="padding:0;overflow:hidden">
                <div style="padding:18px;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--border)">
                    <h2 style="margin:0"><i class="fa-solid fa-list-ul"></i> Mes plats <span style="font-weight:400;color:var(--sub);font-size:13px">(<?=count($plats)?>/<?= $abonne ? ABONNEMENT_PLATS_MAX_PREMIUM : ABONNEMENT_PLATS_MAX_FREE ?>)</span></h2>
                </div>
                <?php if(empty($plats)):?>
                    <div class="empty"><i class="fa-solid fa-plate-wheat"></i><p>Ajoutez votre premier plat !</p></div>
                <?php else:?>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th></th><th>Plat</th><th>Prix</th><th>Statut</th><th>Actions</th></tr></thead>
                            <tbody>
                                <?php foreach($plats as $p):?>
                                    <tr style="<?= !$p['disponible'] ? 'opacity:.5;background:#FEF2F2' : ($depasseLimite ? 'opacity:.5;background:#FFF7ED' : '') ?>">
        <td>
            <?php if($p['photo']):?>
                <img src="public/uploads/plats/<?=e($p['photo'])?>" class="photo-thumb" style="<?= $depasseLimite ? 'filter:grayscale(80%)' : '' ?>">
            <?php else:?>
                <i class="fa-solid fa-utensils" style="color:#D1D5DB;font-size:20px"></i>
            <?php endif;?>
        </td>
        <td>
            <strong><?=e($p['nom'])?></strong>
            <?php if($p['badge']=='epice'):?>🌶️<?php elseif($p['badge']=='vegetarien'):?>🥬<?php elseif($p['badge']=='best_seller'):?>⭐<?php endif;?>
            <?php if($depasseLimite):?>
                <span style="font-size:9px;background:#FEF3C7;color:#92400E;padding:2px 6px;border-radius:8px;margin-left:4px"><i class="fa-solid fa-lock"></i> Bloqué</span>
            <?php endif;?>
            <br><small style="color:var(--sub)"><?=e($p['cat_nom']??'')?></small>
        </td>
        <td class="prix"><?=formatPrix($p['prix'])?></td>
        <td>
            <?php if($depasseLimite):?>
                <span class="statut-badge statut-cache"><i class="fa-solid fa-lock"></i> Premium requis</span>
            <?php else:?>
                <span class="statut-badge <?=$p['disponible']?'statut-visible':'statut-cache'?>"><i class="fa-solid <?=$p['disponible']?'fa-eye':'fa-eye-slash'?>"></i> <?=$p['disponible']?'Visible':'Caché'?></span>
            <?php endif;?>
        </td>
        <td>
            <div style="display:flex;gap:4px">
                <a href="?tab=menu&move=<?=$p['id']?>&dir=up" class="btn-icon" title="Monter"><i class="fa-solid fa-chevron-up"></i></a>
                <a href="?tab=menu&move=<?=$p['id']?>&dir=down" class="btn-icon" title="Descendre"><i class="fa-solid fa-chevron-down"></i></a>
                <?php if(!$depasseLimite):?>
                    <a href="?tab=menu&toggle_dispo=<?=$p['id']?>" class="btn-icon <?=$p['disponible']?'btn-icon-green':'btn-icon-red'?>" title="Visibilité"><i class="fa-solid <?=$p['disponible']?'fa-eye':'fa-eye-slash'?>"></i></a>
                <?php endif;?>
                <a href="?tab=menu&edit_plat=<?=$p['id']?>" class="btn-icon btn-icon-blue" title="Modifier"><i class="fa-solid fa-pen-to-square"></i></a>
                <a href="?tab=menu&supprimer=<?=$p['id']?>" class="btn-icon btn-icon-red" title="Supprimer" onclick="return confirm('Supprimer ?')"><i class="fa-solid fa-trash-can"></i></a>
            </div>
        </td>
    </tr>
<?php endforeach;?>
                            </tbody>
                        </table>
                    </div>
                <?php endif;?>
            </div>
        <?php endif;?>

    <!-- ===== QR CODE ===== -->
    <?php elseif($tab=='qrcode'):?>
        <div class="card">
            <h2><i class="fa-solid fa-qrcode"></i> QR Code principal</h2>
            <div style="text-align:center">
                <div style="position:relative;display:inline-block">
                    <img src="<?=$qrUrl?>" style="width:180px;height:180px;border:3px solid #FF6B35;border-radius:14px;padding:5px;background:#fff">
                    <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;border-radius:50%;width:50px;height:50px;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px rgba(0,0,0,.2)">
                        <img src="<?=LOGO_URL?>" style="width:40px;height:40px;border-radius:50%">
                    </div>
                </div>
            </div>
            <div style="background:#F1F5F9;padding:10px;border-radius:8px;word-break:break-all;font-size:11px;text-align:center;margin:8px 0" id="menu-url-box">
    <i class="fa-solid fa-link"></i> <span id="menu-url-text"><?=$menuUrl?></span>
</div>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <a href="<?=$qrUrl?>" download class="btn btn-o" style="flex:1"><i class="fa-solid fa-download"></i> PNG</a>
                <a href="qrcode-generator.php?action=download-pdf&tables=1" class="btn btn-out" style="flex:1"><i class="fa-solid fa-file-pdf"></i> PDF A4</a>
            </div>
        </div>
        <div class="card">
            <h2><i class="fa-solid fa-chair"></i> QR Codes par table</h2>
            <?php if(!empty($tablesQR)):?>
                <div class="qr-grid">
                    <?php foreach($tablesQR as $t):?>
                        <div class="qr-card"><img src="<?=$t['qr_code_path']?>" alt="Table <?=$t['numero_table']?>"><p style="font-weight:700;margin-top:4px;font-size:12px"><i class="fa-solid fa-chair"></i> Table <?=$t['numero_table']?></p>
                            <div style="display:flex;gap:4px;justify-content:center;margin-top:4px">
                                <a href="<?=$t['qr_code_path']?>" download class="btn btn-o btn-sm"><i class="fa-solid fa-download"></i></a>
                                <a href="qrcode-generator.php?action=download-pdf&tables=<?=$t['numero_table']?>" class="btn btn-out btn-sm"><i class="fa-solid fa-file-pdf"></i></a>
                            </div>
                        </div>
                    <?php endforeach;?>
                </div>
                <div style="margin-top:12px">
                    <a href="qrcode-generator.php?action=download-pdf&tables=<?=implode(',',array_column($tablesQR,'numero_table'))?>" class="btn btn-o btn-block"><i class="fa-solid fa-file-pdf"></i> Télécharger TOUS en PDF (<?=count($tablesQR)?> pages)</a>
                </div>
            <?php else:?>
                <div class="empty"><i class="fa-solid fa-chair"></i><p>Aucun QR code par table.</p></div>
            <?php endif;?>
            <?php if($abonne):?>
            <form method="POST" action="qrcode-generator.php?action=generate-all" style="margin-top:12px;display:flex;gap:8px;align-items:end">
                <?=csrfField()?>
                <div class="form-group" style="flex:1"><label><i class="fa-solid fa-hashtag"></i> Nombre de tables</label><input type="number" name="nb_tables" min="1" max="50" placeholder="Ex: 15" required></div>
                <button type="submit" class="btn btn-o"><i class="fa-solid fa-wand-magic-sparkles"></i> Générer</button>
            </form>
            <?php else:?>
            <div class="premium-locked">
                <p style="font-size:24px;margin-bottom:4px">🪑</p>
                <p><i class="fa-solid fa-crown"></i> QR codes par table = Premium</p>
                <p style="font-size:11px;color:var(--sub);margin:6px 0">Chaque table a son propre QR code.</p>
                <a href="?tab=abonnement" class="btn btn-o btn-sm"><i class="fa-solid fa-arrow-up"></i> Passez Premium</a>
            </div>
            <?php endif;?>
        </div>
        <div class="card">
            <h2><i class="fa-solid fa-code"></i> Badge pour votre site</h2>
            <p style="font-size:12px;color:var(--sub);margin-bottom:8px">Copiez ce code pour intégrer un bouton Menu QR sur votre site :</p>
            <textarea readonly style="width:100%;height:70px;padding:10px;border-radius:8px;border:2px solid var(--border);font-size:11px;font-family:monospace" onclick="this.select()">&lt;a href="<?=SITE_URL?>/<?=$restaurant['slug']?>" target="_blank" style="display:inline-block;background:#FF6B35;color:#fff;padding:10px 20px;border-radius:25px;text-decoration:none;font-weight:bold"&gt;📱 Voir notre menu QR&lt;/a&gt;</textarea>
        </div>

   <!-- ===== COMMANDES ===== -->
<?php elseif($tab=='commandes'):?>
    <div class="card">
        <h2><i class="fa-solid fa-receipt"></i> Commandes</h2>
        
        <form method="GET" style="display:flex;gap:8px;margin-bottom:12px">
            <input type="hidden" name="tab" value="commandes">
            <input type="text" name="search_cmd" placeholder="🔍 Rechercher par code de suivi..." 
                   value="<?=e($_GET['search_cmd'] ?? '')?>" 
                   style="flex:1;padding:10px 14px;border:2px solid var(--border);border-radius:10px;font-size:13px">
            <button type="submit" class="btn btn-o"><i class="fa-solid fa-magnifying-glass"></i></button>
            <?php if(isset($_GET['search_cmd']) && $_GET['search_cmd'] !== ''):?>
                <a href="?tab=commandes" class="btn btn-out"><i class="fa-solid fa-xmark"></i></a>
            <?php endif;?>
        </form>
        
        <div style="display:flex;gap:8px;margin-bottom:12px">
            <a href="export-commandes.php" class="btn btn-sm btn-out"><i class="fa-solid fa-file-csv"></i> Exporter</a>
        </div>
        
        <?php
        $searchCmd = $_GET['search_cmd'] ?? '';
        if ($searchCmd !== '') {
            $stmt = $db->prepare("SELECT * FROM commandes WHERE restaurant_id=? AND code_suivi LIKE ? ORDER BY date_commande DESC LIMIT 50");
            $stmt->execute([$restaurant['id'], "%$searchCmd%"]);
            $commandes = $stmt->fetchAll();
        }
        ?>
        
        <?php if(empty($commandes)):?>
            <div class="empty"><i class="fa-solid fa-inbox"></i><p>Aucune commande trouvée.</p></div>
        <?php else:?>
            <?php foreach($commandes as $cmd): 
                $stmt2 = $db->prepare("SELECT cp.*, p.nom FROM commande_plats cp JOIN plats p ON cp.plat_id=p.id WHERE cp.commande_id=?");
                $stmt2->execute([$cmd['id']]);
                $platsCommande = $stmt2->fetchAll();
            ?>
                <div class="cmd-card">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:6px;margin-bottom:6px">
                        <div>
                            <strong><i class="fa-solid fa-chair"></i> Table <?=e($cmd['numero_table']??'?')?></strong>
                            <span class="tag tag-sp"><i class="fa-solid <?=$cmd['mode_commande']=='emporter'?'fa-bag-shopping':'fa-house'?>"></i> <?=$cmd['mode_commande']=='emporter'?'À emporter':'Sur place'?></span>
                            <br><small style="font-family:monospace;background:var(--badge-gratuit-bg);color:var(--o);padding:2px 6px;border-radius:4px;font-weight:600">#<?=e($cmd['code_suivi'])?></small>
                            <?php if($cmd['nom_client']):?><br><small><i class="fa-solid fa-user"></i> <?=e($cmd['nom_client'])?></small><?php endif;?>
                        </div>
                        <span class="tag tag-<?=$cmd['statut']?>"><?=$cmd['statut']?></span>
                    </div>
                    <div class="detail-plats">
                        <?php foreach($platsCommande as $pc):?>
                            <div class="detail-plat-item"><span><?=e($pc['nom'])?> ×<?=$pc['quantite']?></span><span style="font-weight:600"><?=formatPrix($pc['prix_unitaire']*$pc['quantite'])?></span></div>
                        <?php endforeach;?>
                    </div>
                    <div style="font-size:12px;color:var(--sub);margin-bottom:6px">
                        <?=date('d/m/Y H:i',strtotime($cmd['date_commande']))?> | <strong class="prix">Total : <?=formatPrix($cmd['total'])?></strong>
                        <?php if($cmd['note']):?><br><i class="fa-solid fa-note-sticky"></i> <?=e($cmd['note'])?><?php endif;?>
                    </div>
                    
                    <?php if($cmd['statut']!='annulee'): ?>
                        <?php if($cmd['statut']!='livree'): ?>
                            <form method="POST" style="display:flex;gap:6px;align-items:end">
                                <?=csrfField()?>
                                <input type="hidden" name="commande_id" value="<?=$cmd['id']?>">
                                <select name="nouveau_statut" style="flex:1;padding:8px;border:2px solid var(--border);border-radius:8px;font-size:12px">
                                    <option value="">Changer statut...</option>
                                    <?php if($cmd['statut']=='en_attente'):?><option value="confirmee">✅ Confirmer</option><?php endif;?>
                                    <option value="en_preparation">👨‍🍳 En préparation</option>
                                    <option value="prete">🍽️ Prête</option>
                                    <option value="livree">🛵 Livrée</option>
                                    <option value="annulee">❌ Annuler</option>
                                </select>
                                <button name="changer_statut" class="btn btn-o" style="padding:8px 12px;font-size:11px"><i class="fa-solid fa-check"></i> OK</button>
                            </form>
                        <?php endif;?>
                        <div style="margin-top:6px">
                            <a href="imprimer-commande.php?id=<?=$cmd['id']?>" target="_blank" class="btn btn-sm btn-out">
                                <i class="fa-solid fa-print"></i> Imprimer le reçu
                            </a>
                        </div>
                    <?php endif;?>
                </div>
            <?php endforeach;?>
        <?php endif;?>
    </div>

    <!-- ===== ABONNEMENT ===== -->
    <?php elseif($tab=='abonnement'):?>
        <?php $show_success = isset($_GET['upgrade']) && $_GET['upgrade'] === '1' && $abonne; ?>
        
        <div class="card abo-card">
            <h2><i class="fa-solid fa-crown" style="color:#F59E0B"></i> Abonnement Premium</h2>
            
            <?php if ($show_success): ?>
                <div style="background:#ECFDF5;border:2px solid #10B981;border-radius:16px;padding:30px;text-align:center;margin:16px 0;">
                    <div style="font-size:60px;color:#10B981;margin-bottom:12px;"><i class="fa-solid fa-circle-check"></i></div>
                    <h3 style="color:#065F46;margin-bottom:8px;font-size:20px;">Abonnement Premium activé !</h3>
                    <p style="color:#047857;margin-bottom:4px;"><i class="fa-solid fa-calendar-check"></i> Actif jusqu'au <strong><?= date('d/m/Y à H:i', strtotime($restaurant['date_expiration_module'])) ?></strong></p>
                    <p style="color:#64748B;font-size:13px;margin-bottom:20px;">(<?= ceil((strtotime($restaurant['date_expiration_module']) - time()) / 86400) ?> jours)</p>
                    <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;">
                        <a href="?tab=menu" class="btn btn-o"><i class="fa-solid fa-utensils"></i> Ajouter des plats</a>
                        <a href="menu.php?slug=<?= urlencode($restaurant['slug']) ?>" target="_blank" class="btn" style="background:#1E293B;color:#fff;padding:10px 16px;border-radius:10px;text-decoration:none;font-weight:600;font-size:13px;"><i class="fa-solid fa-qrcode"></i> Voir mon menu</a>
                        <a href="?tab=qrcode" class="btn btn-out"><i class="fa-solid fa-print"></i> QR Codes</a>
                    </div>
                </div>
            <?php elseif ($abonne): ?>
               <div style="background:#ECFDF5;padding:20px;border-radius:14px;margin:16px 0" id="abo-actif">
    <p style="font-size:18px;color:#065F46;font-weight:700" id="abo-titre"><i class="fa-solid fa-circle-check"></i> Abonnement actif</p>
    <p style="color:#065F46" id="abo-expire">Expire le <strong style="color:#065F46" id="abo-date"><?= date('d/m/Y', strtotime($restaurant['date_expiration_module'])) ?></strong></p>
    <p style="font-size:13px;color:#065F46" id="abo-jours">(<?= ceil((strtotime($restaurant['date_expiration_module']) - time()) / 86400) ?> jours restants)</p>
</div>
            <?php else: ?>
                <div class="prix"><?=formatPrix(ABONNEMENT_PRIX_FCFA)?><small style="font-size:14px;color:var(--sub);font-weight:400">/mois</small></div>
                <ul>
                    <li><i class="fa-solid fa-check-circle"></i> Photos des plats</li>
                    <li><i class="fa-solid fa-check-circle"></i> Commandes en ligne</li>
                    <li><i class="fa-solid fa-check-circle"></i> QR Code par table</li>
                    <li><i class="fa-solid fa-check-circle"></i> <?=ABONNEMENT_PLATS_MAX_PREMIUM?> plats</li>
                    <li><i class="fa-solid fa-check-circle"></i> Statistiques</li>
                    <li><i class="fa-solid fa-check-circle"></i> Badge Verified</li>
                </ul>
                <a href="payer.php" class="btn btn-o btn-block" style="padding:14px;font-size:16px"><i class="fa-solid fa-credit-card"></i> Payer <?=formatPrix(ABONNEMENT_PRIX_FCFA)?></a>
                <p style="font-size:11px;color:var(--sub);margin-top:8px"><i class="fa-solid fa-globe"></i> Mobile Money, Carte bancaire, PayPal via Chariow</p>
            <?php endif; ?>
        </div>
        <div class="card">
            <h2><i class="fa-solid fa-gift"></i> Parrainage</h2>
           <p style="font-size:13px;color:var(--sub);margin-bottom:10px">Parrainez un restaurant et gagnez <strong>1 mois gratuit</strong> ! Votre code :</p>
            <div style="background:#F1F5F9;padding:12px;border-radius:10px;font-size:13px;text-align:center">
        	<strong style="color:#FF6B35!important;font-size:18px;letter-spacing:2px"><?= strtoupper(substr($restaurant['slug'], 0, 6)) ?></strong>
            </div>
            <p style="font-size:11px;color:var(--sub);margin-top:8px">Lien : <strong><?= SITE_URL ?>/inscription.php?parrain=<?= $restaurant['slug'] ?></strong></p>
        </div>

    <!-- ===== INFOS ===== -->
    <?php elseif($tab=='infos'):?>
        <div class="card">
            <h2><i class="fa-solid fa-camera"></i> Photo de profil</h2>
            <form method="POST" enctype="multipart/form-data">
                <?=csrfField()?>
                <div class="logo-upload">
                    <img src="<?=$logoResto?>" alt="Logo">
                    <div>
                        <?php if($abonne):?>
                            <input type="file" name="logo_file" accept="image/jpeg,image/png,image/webp" style="margin-bottom:8px">
                        <?php else:?>
                            <div style="opacity:.7">
                                <input type="file" disabled style="margin-bottom:8px;background:#F1F5F9;cursor:not-allowed">
                                <p style="font-size:10px;color:var(--o)"><i class="fa-solid fa-crown"></i> Premium requis pour personnaliser votre photo</p>
                            </div>
                        <?php endif;?>
                        <button type="submit" name="update_logo" class="btn btn-o btn-sm" <?= !$abonne ? 'disabled' : '' ?>><i class="fa-solid fa-upload"></i> Mettre à jour</button>
                    </div>
                </div>
            </form>
        </div>
        <div class="card">
            <h2><i class="fa-solid fa-gear"></i> Paramètres</h2>
            <form method="POST">
                <?=csrfField()?>
                <div class="form-group"><label><i class="fa-solid fa-store"></i> Nom</label><input type="text" name="nom_restaurant" value="<?=e($restaurant['nom_restaurant'])?>" required></div>
                <div class="form-group"><label><i class="fa-solid fa-align-left"></i> Description</label><textarea name="description" rows="2"><?=e($restaurant['description']??'')?></textarea></div>
                <div class="form-group"><label><i class="fa-solid fa-location-dot"></i> Adresse</label><input type="text" name="adresse" value="<?=e($restaurant['adresse']??'')?>"></div>
                <div class="form-row">
                    <div class="form-group"><label><i class="fa-solid fa-clock"></i> Ouverture</label><input type="time" name="heure_ouverture" value="<?=substr($restaurant['heure_ouverture']??'07:00',0,5)?>"></div>
                    <div class="form-group"><label><i class="fa-solid fa-clock"></i> Fermeture</label><input type="time" name="heure_fermeture" value="<?=substr($restaurant['heure_fermeture']??'22:00',0,5)?>"></div>
                </div>
                <div class="form-group"><label><i class="fa-brands fa-whatsapp"></i> WhatsApp</label><input type="text" name="whatsapp" value="<?=$restaurant['telephone_whatsapp']?preg_replace('/^226/','',$restaurant['telephone_whatsapp']):''?>" placeholder="70 12 34 56"></div>
                <div class="form-group">
                    <label><i class="fa-solid fa-link"></i> URL personnalisée</label>
                    <div style="display:flex;align-items:center;gap:0;background:#F1F5F9;border-radius:10px;overflow:hidden;border:2px solid var(--border)">
                       <span style="background:var(--border);padding:10px 12px;font-size:13px;color:var(--sub)"><?=SITE_URL?>/</span>
                        <input type="text" name="slug" value="<?=e($restaurant['slug'])?>" style="border:none;background:transparent;padding:10px;font-weight:600;color:var(--o);flex:1" pattern="[a-z0-9-]+">
                    </div>
                </div>
                <button name="update_infos" class="btn btn-o btn-block"><i class="fa-solid fa-floppy-disk"></i> Enregistrer</button>
            </form>
        </div>
    <?php endif;?>
</div>

<div class="bottom-nav">
    <a href="?tab=menu" class="<?=$tab=='menu'?'active':''?>"><span class="nav-icon"><i class="fa-solid fa-utensils"></i></span><span>Menu</span></a>
    <a href="?tab=qrcode" class="<?=$tab=='qrcode'?'active':''?>"><span class="nav-icon"><i class="fa-solid fa-qrcode"></i></span><span>QR Code</span></a>
    <a href="?tab=commandes" class="<?=$tab=='commandes'?'active':''?> <?=!empty($commandes)&&$commandes[0]['statut']=='en_attente'?'notif-dot':''?>"><span class="nav-icon"><i class="fa-solid fa-receipt"></i></span><span>Commandes</span></a>
    <a href="?tab=abonnement" class="<?=$tab=='abonnement'?'active':''?>"><span class="nav-icon"><i class="fa-solid fa-crown"></i></span><span>Premium</span></a>
    <a href="?tab=infos" class="<?=$tab=='infos'?'active':''?>"><span class="nav-icon"><i class="fa-solid fa-gear"></i></span><span>Infos</span></a>
</div>

<script>
(function() {
    var lastCmdId = <?=$lastCmdId ?: 0?>;
    var audio = null;
    
    function initAudio() {
        if (!audio) {
            audio = new Audio('https://www.soundjay.com/buttons/sounds/button-09.mp3');
            audio.preload = 'auto';
        }
    }
    
    function playAlert() {
        if (!audio) return;
        audio.currentTime = 0;
        audio.play().then(function() {
            setTimeout(function() { audio.currentTime = 0; audio.play(); }, 400);
            setTimeout(function() { audio.currentTime = 0; audio.play(); }, 800);
        }).catch(function() {});
    }

    function notify(title, body) {
        if ('Notification' in window && Notification.permission === 'granted') {
            var n = new Notification(title, {
                body: body,
                icon: '<?=LOGO_URL?>',
                tag: 'cmd-' + Date.now(),
                requireInteraction: true,
                vibrate: [200, 100, 200]
            });
            n.onclick = function() { window.focus(); window.location.href = 'dashboard.php?tab=commandes'; n.close(); };
        }
    }

    if ('Notification' in window && Notification.permission === 'default') {
        document.addEventListener('click', function askNotif() {
            Notification.requestPermission();
            document.removeEventListener('click', askNotif);
        }, { once: true });
    }

    function checkCommands() {
        fetch('<?=SITE_URL?>/api/check-commandes.php?rid=<?=$restaurant['id']?>&last=' + lastCmdId + '&_=' + Date.now(), { cache: 'no-store' })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.new_commands > 0) {
                    lastCmdId = d.last_id;
                    playAlert();
                    notify('🛒 ' + d.new_commands + ' nouvelle(s) commande(s) !', 'Cliquez pour voir les détails');
                    showBanner(d.new_commands);
                    var tab = document.querySelector('a[href="?tab=commandes"]');
                    if (tab) tab.classList.add('notif-dot');
                    if (window.location.href.indexOf('tab=commandes') !== -1) {
                        setTimeout(function() { location.reload(); }, 2000);
                    }
                }
            }).catch(function(e) { console.log('Polling:', e); });
    }

    function showBanner(count) {
        var old = document.getElementById('cmdBanner');
        if (old) old.remove();
        var banner = document.createElement('div');
        banner.id = 'cmdBanner';
        banner.style.cssText = 'position:fixed;top:60px;left:16px;right:16px;background:#10B981;color:#fff;padding:14px 16px;border-radius:12px;z-index:200;font-weight:600;font-size:14px;cursor:pointer;box-shadow:0 4px 20px rgba(16,185,129,.4);animation:slideDown .3s ease;display:flex;align-items:center;gap:10px';
        banner.innerHTML = '🛒 <strong>' + count + ' nouvelle(s) commande(s) !</strong> <span style="flex:1"></span> <a href="?tab=commandes" style="color:#fff;font-weight:700">Voir →</a>';
        banner.onclick = function() { window.location.href = 'dashboard.php?tab=commandes'; };
        document.body.appendChild(banner);
        var style = document.createElement('style');
        style.textContent = '@keyframes slideDown{from{transform:translateY(-100%);opacity:0}to{transform:translateY(0);opacity:1}}';
        document.head.appendChild(style);
        setTimeout(function() { if (banner.parentNode) { banner.style.opacity='0';banner.style.transition='opacity .5s';setTimeout(function(){if(banner.parentNode)banner.remove()},500); } }, 8000);
    }

    window.activerKiosque = function() {
        if (confirm('Mode Kiosque ? Plein écran + rafraîchissement auto.')) {
            if (document.documentElement.requestFullscreen) document.documentElement.requestFullscreen();
            setInterval(function() { location.reload(); }, 60000);
        }
    };

    initAudio();
    document.addEventListener('click', function() { initAudio(); }, { once: true });
    setInterval(checkCommands, 10000);
    setTimeout(checkCommands, 3000);
})();
    function toggleDarkMode() {
    document.body.classList.toggle('dark');
    var icon = document.getElementById('darkToggle').querySelector('i');
    if (document.body.classList.contains('dark')) {
        icon.className = 'fa-solid fa-sun';
        localStorage.setItem('darkMode', '1');
    } else {
        icon.className = 'fa-solid fa-moon';
        localStorage.setItem('darkMode', '0');
    }
}

// Appliquer au chargement
if (localStorage.getItem('darkMode') === '1') {
    document.body.classList.add('dark');
    document.getElementById('darkToggle').querySelector('i').className = 'fa-solid fa-sun';
}
</script>
</body>
</html>