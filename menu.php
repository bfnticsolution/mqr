<?php
/**
 * Menu public (côté client) - Menu QR
 * Version Pro sécurisée - NTIC Solution
 */
require_once __DIR__ . '/includes/config.php';

$id = intval($_GET['id'] ?? 0);
$slug = $_GET['slug'] ?? null;
$table = $_GET['table'] ?? null;

$db = getDB();

if ($slug) {
    $stmt = $db->prepare("SELECT * FROM restaurants WHERE slug = ? AND statut = 'actif'");
    $stmt->execute([$slug]);
    $restaurant = $stmt->fetch();
} elseif ($id > 0) {
    $stmt = $db->prepare("SELECT * FROM restaurants WHERE id = ? AND statut = 'actif'");
    $stmt->execute([$id]);
    $restaurant = $stmt->fetch();
} else {
    header('Location: index.php');
    exit;
}

if (!$restaurant) {
    echo "<div style='text-align:center;padding:80px;font-family:sans-serif;'><h1>😕 Restaurant introuvable</h1><p>Ce restaurant n'existe pas ou a été désactivé.</p><a href='".SITE_URL."' style='color:#FF6B35'>Retour à l'accueil</a></div>";
    exit;
}

$id = $restaurant['id'];
$abonne = ($restaurant['module'] !== 'simple' && $restaurant['date_expiration_module'] && strtotime($restaurant['date_expiration_module']) > time());

// Incrémenter les scans
$db->prepare("UPDATE restaurants SET nb_scans=nb_scans+1 WHERE id=?")->execute([$id]);

// Enregistrer la visite
try {
    $stmt = $db->prepare("INSERT INTO visites_menu (restaurant_id, ip, user_agent) VALUES (?, ?, ?)");
    $stmt->execute([$id, $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null]);
} catch (Exception $e) {}

$isAutoRedirect = isset($_GET['auto']) && $_GET['auto'] == '1';
$isFirstVisit = !isset($_COOKIE['menuqr_visited']);
if (!isset($_COOKIE['menuqr_visited'])) setcookie('menuqr_visited', '1', time() + 86400 * 365, '/', '', true, false);
$showSplash = $isAutoRedirect || $isFirstVisit;

$cleanUrl = SITE_URL . '/' . $restaurant['slug'];
if ($table) $cleanUrl .= '?table=' . $table;
$menuUrl = $cleanUrl;

// Logo du restaurant (Premium + logo uploadé) ou logo plateforme
$logoMenu = ($abonne && $restaurant['logo']) ? 'public/uploads/logos/' . $restaurant['logo'] : LOGO_URL;

// Catégories
$stmt = $db->prepare("SELECT * FROM categories WHERE restaurant_id=? ORDER BY ordre");
$stmt->execute([$id]);
$categories = $stmt->fetchAll();

// Récupérer TOUS les plats disponibles (même au-delà de la limite gratuite)
$stmt = $db->prepare("SELECT p.*, c.nom as cat_nom FROM plats p LEFT JOIN categories c ON p.categorie_id=c.id WHERE p.restaurant_id=? AND p.disponible=1 ORDER BY p.ordre ASC, p.nom ASC");
$stmt->execute([$id]);
$plats = $stmt->fetchAll();

$platsParCat = [];
foreach ($plats as $p) { $cat = $p['cat_nom'] ?: 'À la carte'; $platsParCat[$cat][] = $p; }

$cmdOk = false; $cmdSuivi = ''; $cmdTable = ''; $cmdTotal = 0;

// Traitement de la commande
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['commander'])) {
    $clientKey = 'order_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    if (!checkRateLimit($clientKey)) {
        $cmdOk = false;
        $cmdError = 'Trop de commandes. Veuillez patienter.';
    } else {
        $nom = trim(strip_tags($_POST['nom'] ?? ''));
        $tel = trim(strip_tags($_POST['tel'] ?? ''));
        $mode = in_array($_POST['mode'] ?? 'sur_place', ['sur_place','emporter']) ? $_POST['mode'] : 'sur_place';
        $note = trim(strip_tags($_POST['note'] ?? ''));
        $panier = json_decode($_POST['panier_data'] ?? '[]', true);
        
        if (!empty($panier)) {
            $total = 0;
            foreach ($panier as $item) $total += intval($item['prix']) * intval($item['quantite']);
            $code = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
            
            $stmt = $db->prepare("INSERT INTO commandes (code_suivi, restaurant_id, numero_table, nom_client, telephone_client, mode_commande, note, total) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->execute([$code, $id, $table, $nom?:null, $tel?:null, $mode, $note?:null, $total]);
            $cmdId = $db->lastInsertId();
            
            $stmt = $db->prepare("INSERT INTO commande_plats (commande_id, plat_id, quantite, prix_unitaire) VALUES (?,?,?,?)");
            foreach ($panier as $item) {
                $stmt->execute([$cmdId, intval($item['id']), intval($item['quantite']), intval($item['prix'])]);
            }
            
            try { $db->prepare("INSERT INTO suivi_commandes (commande_id, statut) VALUES (?,'en_attente')")->execute([$cmdId]); } catch (Exception $e) {}
            
            $cmdOk = true; $cmdSuivi = $code; $cmdTable = $table; $cmdTotal = $total;
        }
    }
}
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
        :root{--o:#FF6B35;--b:#3B82F6;--bg:#F8F9FA;--card:#fff;--text:#1E293B;--sub:#64748B;--border:#E5E7EB;--shadow:0 1px 3px rgba(0,0,0,.04)}
        @media(prefers-color-scheme:dark){:root{--bg:#0F172A;--card:#1E293B;--text:#F1F5F9;--sub:#94A3B8;--border:#334155}.header{background:linear-gradient(135deg,#1a1a2e,#16213e)!important}}
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:var(--bg);color:var(--text);padding-bottom:90px;-webkit-tap-highlight-color:transparent}
        
        .header{background:linear-gradient(135deg,#FF6B35,#E85D2C);color:#fff;padding:40px 20px 30px;text-align:center;position:relative;overflow:hidden}
        .header::after{content:'';position:absolute;bottom:-20px;left:-10%;right:-10%;height:40px;background:var(--bg);border-radius:50% 50% 0 0}
        .header h1{font-size:22px;font-weight:800;position:relative;z-index:1;display:flex;align-items:center;justify-content:center;gap:10px;flex-wrap:wrap}
        .header h1 img{width:44px;height:44px;border-radius:50%;object-fit:cover;border:2px solid rgba(255,255,255,.4);flex-shrink:0}
        .verified-badge{display:inline-flex;align-items:center;gap:3px;background:var(--b);color:#fff;padding:3px 8px;border-radius:12px;font-size:9px;font-weight:600;animation:fadeIn .3s ease;box-shadow:0 2px 8px rgba(59,130,246,.3);white-space:nowrap}
        @keyframes fadeIn{from{opacity:0;transform:scale(.9)}to{opacity:1;transform:scale(1)}}
        .header .infos{font-size:13px;opacity:.9;margin-top:6px;position:relative;z-index:1}
        .header .table-tag{display:inline-block;background:rgba(255,255,255,.25);padding:8px 20px;border-radius:25px;font-weight:700;margin-top:10px;font-size:15px;position:relative;z-index:1}
        
        .search-bar{padding:10px 16px;background:var(--card);position:sticky;top:0;z-index:20;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:8px}
        .search-bar .search-icon{color:var(--sub);font-size:14px;flex-shrink:0}
        .search-bar input{flex:1;padding:10px 14px;border:2px solid var(--border);border-radius:25px;font-size:14px;background:var(--bg);color:var(--text)}
        .search-bar input:focus{outline:none;border-color:var(--o)}
        
        .cats{background:var(--card);padding:8px 12px;display:flex;gap:6px;overflow-x:auto;border-bottom:1px solid var(--border)}
        .cat-btn{padding:7px 16px;border-radius:20px;border:2px solid var(--border);background:transparent;color:var(--text);cursor:pointer;white-space:nowrap;font-size:12px;font-weight:600;display:flex;align-items:center;gap:4px;transition:.15s}
        .cat-btn.active{background:var(--o);color:#fff;border-color:var(--o)}
        
        .container{max-width:600px;margin:0 auto;padding:16px}
        .section{margin-bottom:20px}.section-title{font-size:17px;font-weight:700;margin-bottom:12px;display:flex;align-items:center;gap:8px}
        .section-title i{color:var(--o)}.section-title::after{content:'';flex:1;height:2px;background:var(--border)}
        
        .plat{background:var(--card);border-radius:16px;margin-bottom:10px;box-shadow:var(--shadow);overflow:hidden;animation:fadeInUp .3s ease}
        @keyframes fadeInUp{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}
        .plat-inner{display:flex;padding:14px;gap:14px;align-items:center}
        .plat-img{width:85px;height:85px;border-radius:12px;object-fit:cover;background:#E2E8F0;flex-shrink:0;cursor:pointer}
        .plat-img-placeholder{width:85px;height:85px;border-radius:12px;background:var(--bg);flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:28px;color:#D1D5DB}
        .plat-info{flex:1;min-width:0}.plat-info h3{font-size:15px;font-weight:600}
        .plat-info .desc{font-size:12px;color:var(--sub);margin:4px 0;line-height:1.4}
        .plat-bottom{display:flex;justify-content:space-between;align-items:center;margin-top:8px}
        .plat-prix{font-weight:700;color:var(--o);font-size:17px}
        .btn-add{background:var(--o);color:#fff;border:none;padding:9px 18px;border-radius:20px;cursor:pointer;font-weight:600;font-size:13px;display:flex;align-items:center;gap:4px;transition:.15s}
        .btn-add:active{transform:scale(.95)}
        .btn-add-locked{background:#94A3B8;color:#fff;border:none;padding:9px 18px;border-radius:20px;cursor:not-allowed;font-weight:600;font-size:13px;display:flex;align-items:center;gap:4px}
        
        .panier-bar{position:fixed;bottom:0;left:0;right:0;background:var(--card);border-top:2px solid var(--o);padding:14px 20px;display:flex;justify-content:space-between;align-items:center;cursor:pointer;z-index:50;box-shadow:0 -4px 20px rgba(0,0,0,.1)}
        .panier-count{background:var(--o);color:#fff;padding:5px 14px;border-radius:20px;font-weight:700;font-size:13px}
        
        .modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:100;justify-content:center;align-items:flex-end}
        .modal.active{display:flex}
        .modal-content{background:var(--card);color:var(--text);border-radius:24px 24px 0 0;width:100%;max-width:500px;max-height:85vh;overflow-y:auto;padding:24px}
        .modal-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px}
        .modal-head h2{font-size:18px;display:flex;align-items:center;gap:8px}.modal-head h2 i{color:var(--o)}
        .modal-close{background:none;border:none;font-size:24px;cursor:pointer;color:var(--text)}
        
        .panier-item{display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:1px solid var(--border)}
        .qte-ctrl{display:flex;align-items:center;gap:6px}
        .qte-btn{background:var(--border);border:none;width:28px;height:28px;border-radius:50%;cursor:pointer;font-weight:700;color:var(--text)}
        .qte-value{font-weight:600;min-width:20px;text-align:center}
        .btn-remove{background:none;border:none;color:#EF4444;cursor:pointer;font-size:16px;padding:4px}
        
        .panier-total{font-size:22px;font-weight:700;text-align:right;padding:16px 0;border-top:2px solid var(--border);margin-top:12px;display:flex;justify-content:space-between}
        
        .form-group{margin-bottom:12px}
        .form-group label{display:block;font-weight:600;margin-bottom:4px;font-size:12px;color:var(--sub)}
        .form-group label i{color:var(--o);width:16px}
        .form-group input,.form-group textarea{width:100%;padding:11px 14px;border:2px solid var(--border);border-radius:12px;font-size:14px;font-family:inherit;background:var(--bg);color:var(--text)}
        .form-group input:focus,.form-group textarea:focus{outline:none;border-color:var(--o)}
        .radio-group{display:flex;gap:8px}
        .radio-option{flex:1;text-align:center;padding:12px;border:2px solid var(--border);border-radius:12px;cursor:pointer;font-weight:600;font-size:13px;display:flex;align-items:center;justify-content:center;gap:6px;transition:.15s}
        .radio-option.selected{border-color:var(--o);background:#FFF7ED;color:var(--o)}
        .radio-option input{display:none}
        
        .btn-cmd{width:100%;padding:15px;background:var(--o);color:#fff;border:none;border-radius:14px;font-size:17px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:.15s}
        .btn-cmd:active{transform:scale(.98)}
        
        .premium-locked{background:#FFF7ED;padding:16px;border-radius:14px;text-align:center;border:2px dashed var(--o);margin-top:8px}
        .premium-locked .icon{font-size:24px;margin-bottom:4px}
        .premium-locked .title{color:var(--o);font-weight:700;font-size:14px}
        .premium-locked .sub{font-size:12px;color:var(--sub);margin:6px 0}
        
        .success-page{text-align:center;padding:50px 20px;background:var(--bg);min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center}
        .success-icon{font-size:80px;animation:bounce .6s ease;color:#10B981}
        @keyframes bounce{0%,100%{transform:scale(1)}50%{transform:scale(1.2)}}
        .success-code{font-size:32px;font-weight:800;letter-spacing:6px;background:#ECFDF5;color:#065F46;padding:14px 28px;border-radius:16px;display:inline-block;margin:12px 0;border:2px solid #A7F3D0}
        .btn{display:inline-flex;align-items:center;gap:8px;padding:14px 28px;border-radius:14px;font-weight:700;text-decoration:none;margin:6px;font-size:15px;transition:.2s}
        .btn-o{background:var(--o);color:#fff}.btn-out{background:transparent;color:var(--o);border:2px solid var(--o)}
        .btn-wa{background:#25D366;color:#fff}
        
        .fullscreen-img{display:none;position:fixed;inset:0;background:rgba(0,0,0,.95);z-index:300;align-items:center;justify-content:center;padding:20px}
        .fullscreen-img.active{display:flex}
        .fullscreen-img img{max-width:95%;max-height:95%;object-fit:contain;border-radius:12px}
        .fullscreen-img .close-btn{position:absolute;top:20px;right:20px;color:#fff;font-size:30px;cursor:pointer;background:rgba(255,255,255,.1);width:44px;height:44px;border-radius:50%;display:flex;align-items:center;justify-content:center}
        
        .vide{text-align:center;padding:50px;color:var(--sub)}.vide i{font-size:60px;margin-bottom:12px;color:#D1D5DB}
        .footer{text-align:center;padding:20px;color:var(--sub);font-size:11px;border-top:1px solid var(--border);margin-top:20px}
        
        .splash{position:fixed;inset:0;background:linear-gradient(135deg,#FF6B35,#E85D2C);z-index:999;display:flex;align-items:center;justify-content:center;flex-direction:column;color:#fff;transition:opacity .5s}
        .splash .s-icon{font-size:60px;animation:splashBounce 1s ease infinite}
        @keyframes splashBounce{0%,100%{transform:translateY(0)}50%{transform:translateY(-20px)}}
        .splash h1{font-size:24px;margin-top:16px;font-weight:800}
        .splash .s-loader{width:50px;height:3px;background:rgba(255,255,255,.3);border-radius:2px;margin-top:16px;overflow:hidden}
        .splash .s-loader div{width:100%;height:100%;background:#fff;border-radius:2px;animation:loading 1.5s ease infinite}
        @keyframes loading{0%{transform:translateX(-100%)}100%{transform:translateX(100%)}}
    </style>
</head>
<body>

<?php if ($cmdOk): ?>
    <div class="success-page">
        <div class="success-icon"><i class="fa-solid fa-circle-check"></i></div>
        <h1 style="color:#10B981;margin-top:10px">Commande envoyée !</h1>
        <p style="color:var(--sub);margin:8px 0">Votre code de suivi :</p>
        <div class="success-code"><?= $cmdSuivi ?></div>
        <?php if ($cmdTable): ?><p style="font-size:16px;font-weight:600"><i class="fa-solid fa-chair"></i> Table <?= e($cmdTable) ?></p><?php endif; ?>
        <p style="color:var(--sub);font-size:13px;margin:8px 0">💰 Total : <strong><?= formatPrix($cmdTotal) ?></strong></p>
        
        <?php if ($restaurant['telephone_whatsapp']): ?>
            <a href="https://wa.me/<?= e($restaurant['telephone_whatsapp']) ?>?text=Bonjour,%20je%20viens%20de%20commander.%0ACode%20:%20<?= $cmdSuivi ?>%0ATable%20:%20<?= urlencode($cmdTable ?? 'N/A') ?>%0ATotal%20:%20<?= urlencode(formatPrix($cmdTotal)) ?>" target="_blank" class="btn btn-wa">
                <i class="fa-brands fa-whatsapp"></i> Contacter le restaurant
            </a>
        <?php endif; ?>
        
        <a href="suivi.php?suivi=<?= $cmdSuivi ?>" class="btn btn-o"><i class="fa-solid fa-magnifying-glass"></i> Suivre ma commande</a>
        <a href="<?= $menuUrl ?>" class="btn btn-out"><i class="fa-solid fa-utensils"></i> Commander autre chose</a>
    </div>
<?php else: ?>
    <?php if ($showSplash): ?>
    <div class="splash" id="splashScreen">
        <div class="s-icon">🍽️</div>
        <h1><?= e($restaurant['nom_restaurant']) ?></h1>
        <p style="font-size:13px;opacity:.9;margin-top:4px">Chargement du menu...</p>
        <div class="s-loader"><div></div></div>
    </div>
    <script>setTimeout(function(){var s=document.getElementById('splashScreen');if(s){s.style.opacity='0';setTimeout(function(){s.remove()},500)}},1800);</script>
    <?php endif; ?>

    <div class="header">
        <h1>
            <img src="<?=$logoMenu?>" alt="<?=e($restaurant['nom_restaurant'])?>">
            <span><?= e($restaurant['nom_restaurant']) ?></span>
            <?php if($abonne):?>
                <span class="verified-badge"><i class="fa-solid fa-circle-check"></i> Verified</span>
            <?php endif;?>
        </h1>
        <?php if ($restaurant['description']): ?><p class="infos"><?= e($restaurant['description']) ?></p><?php endif; ?>
        <?php if ($restaurant['adresse'] || $restaurant['heure_ouverture']): ?>
            <p class="infos">
                <?php if ($restaurant['adresse']): ?><i class="fa-solid fa-location-dot"></i> <?= e($restaurant['adresse']) ?> · <?php endif; ?>
                <i class="fa-solid fa-clock"></i> <?= substr($restaurant['heure_ouverture']??'07:00',0,5) ?>-<?= substr($restaurant['heure_fermeture']??'22:00',0,5) ?>
            </p>
        <?php endif; ?>
        <?php if ($table): ?><div class="table-tag"><i class="fa-solid fa-chair"></i> Table <?= e($table) ?></div><?php endif; ?>
    </div>

    <div class="search-bar">
        <i class="fa-solid fa-magnifying-glass search-icon"></i>
        <input type="text" id="searchInput" placeholder="Rechercher un plat..." oninput="rechercher()">
    </div>

    <?php if (count($categories) > 1): ?>
    <div class="cats">
        <button class="cat-btn active" onclick="filtrer('all')"><i class="fa-solid fa-grid-2"></i> Tout</button>
        <?php foreach ($categories as $c): ?>
            <button class="cat-btn" onclick="filtrer('<?= e($c['nom']) ?>')"><i class="fa-solid fa-folder"></i> <?= e($c['nom']) ?></button>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="container">
        <?php if (empty($plats)): ?>
            <div class="vide"><i class="fa-solid fa-plate-wheat"></i><p>Aucun plat disponible pour le moment.</p></div>
        <?php else: ?>
            <?php 
            $indexGlobal = 0;
            foreach ($platsParCat as $catNom => $platsCat): ?>
                <div class="section" data-cat="<?= e($catNom) ?>">
                    <div class="section-title"><i class="fa-solid fa-folder"></i> <?= e($catNom) ?></div>
                    <?php foreach ($platsCat as $p): 
                        $indexGlobal++;
                        $depasseLimite = !$abonne && $indexGlobal > ABONNEMENT_PLATS_MAX_FREE;
                    ?>
                        <div class="plat" data-nom="<?= e(strtolower($p['nom'])) ?>" style="<?= $depasseLimite ? 'opacity:.5;filter:grayscale(80%)' : '' ?>">
                            <div class="plat-inner">
                                <?php if ($p['photo']): ?>
                                    <img src="public/uploads/plats/<?= e($p['photo']) ?>" class="plat-img" alt="<?= e($p['nom']) ?>" onclick="openFullscreen(this.src)" loading="lazy" style="<?= $depasseLimite ? 'filter:grayscale(100%)' : '' ?>">
                                <?php else: ?>
                                    <div class="plat-img-placeholder"><i class="fa-solid fa-utensils"></i></div>
                                <?php endif; ?>
                                <div class="plat-info">
                                    <h3>
                                        <?= e($p['nom']) ?>
                                        <?php if($p['badge']=='epice'):?>🌶️<?php elseif($p['badge']=='vegetarien'):?>🥬<?php elseif($p['badge']=='best_seller'):?>⭐<?php endif;?>
                                        <?php if($depasseLimite):?>
                                            <span style="font-size:9px;background:#FEE2E2;color:#991B1B;padding:2px 6px;border-radius:8px;margin-left:4px"><i class="fa-solid fa-lock"></i> Premium</span>
                                        <?php endif;?>
                                    </h3>
                                    <?php if ($p['description']): ?><p class="desc"><?= e($p['description']) ?></p><?php endif; ?>
                                    <div class="plat-bottom">
                                        <span class="plat-prix"><?= formatPrix($p['prix']) ?></span>
                                        <?php if($depasseLimite):?>
                                            <button class="btn-add-locked" disabled>
                                                <i class="fa-solid fa-lock"></i> Premium requis
                                            </button>
                                        <?php else:?>
                                            <button class="btn-add" onclick="ajouter(<?= $p['id'] ?>,'<?= e($p['nom'], true) ?>',<?= $p['prix'] ?>)">
                                                <i class="fa-solid fa-plus"></i> Ajouter
                                            </button>
                                        <?php endif;?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php if($abonne):?>
    <div class="panier-bar" onclick="ouvrirPanier()">
        <span style="font-weight:600;display:flex;align-items:center;gap:8px"><i class="fa-solid fa-basket-shopping"></i> Ma commande</span>
        <span class="panier-count" id="panierCount">0</span>
    </div>
    <?php endif;?>
    
    <div class="modal" id="modalPanier">
        <div class="modal-content">
            <div class="modal-head"><h2><i class="fa-solid fa-basket-shopping"></i> Votre commande</h2><button class="modal-close" onclick="fermerPanier()"><i class="fa-solid fa-xmark"></i></button></div>
            <div id="panierItems"><p style="text-align:center;color:var(--sub);padding:20px">Panier vide</p></div>
            <div class="panier-total" id="panierTotal" style="display:none"><span>Total</span><span id="totalPrix">0</span></div>
            <?php if($abonne):?>
            <form method="POST" id="formCmd" style="display:none" onsubmit="return preparerCommande()">
                <input type="hidden" name="commander" value="1"><input type="hidden" name="panier_data" id="panierData">
                <div class="form-group"><label><i class="fa-solid fa-utensils"></i> Mode</label><div class="radio-group">
                    <label class="radio-option selected" onclick="selectMode(this)"><input type="radio" name="mode" value="sur_place" checked><i class="fa-solid fa-house"></i> Sur place</label>
                    <label class="radio-option" onclick="selectMode(this)"><input type="radio" name="mode" value="emporter"><i class="fa-solid fa-bag-shopping"></i> À emporter</label>
                </div></div>
                <div class="form-group"><label><i class="fa-solid fa-user"></i> Nom <small>(optionnel)</small></label><input type="text" name="nom" placeholder="Ex: Jean" maxlength="100"></div>
                <div class="form-group"><label><i class="fa-solid fa-phone"></i> Téléphone <small>(optionnel)</small></label><input type="tel" name="tel" placeholder="Pour être contacté" maxlength="20"></div>
                <div class="form-group"><label><i class="fa-solid fa-note-sticky"></i> Note</label><textarea name="note" rows="2" placeholder="Sans piment, bien cuit..." maxlength="300"></textarea></div>
                <button type="submit" class="btn-cmd"><i class="fa-solid fa-paper-plane"></i> Confirmer la commande</button>
            </form>
            <?php else:?>
            <div id="formCmd" style="display:none">
                <div class="premium-locked">
                    <div class="icon">🔒</div>
                    <div class="title">Commandes désactivées</div>
                    <p class="sub">L'abonnement Premium de ce restaurant a expiré. Les commandes en ligne sont temporairement indisponibles.</p>
                    <p style="font-size:11px;color:var(--sub)">Vous pouvez toujours consulter le menu et commander directement au restaurant.</p>
                </div>
            </div>
            <?php endif;?>
        </div>
    </div>

    <div class="fullscreen-img" id="fullscreenImg" onclick="closeFullscreen()">
        <span class="close-btn"><i class="fa-solid fa-xmark"></i></span>
        <img src="" alt="Photo" id="fullscreenImgSrc">
    </div>

    <div class="footer">Propulsé par <i class="fa-solid fa-qrcode"></i> <strong><?=SITE_NAME?></strong> · <?=SITE_URL?>/<?=e($restaurant['slug'])?></div>
<?php endif; ?>

<script>
var panier = {};
var favoris = JSON.parse(localStorage.getItem('menuqr_favoris') || '{}');

function ajouter(id, nom, prix) {
    id = parseInt(id);
    prix = parseInt(prix);
    if (panier[id]) panier[id].quantite++;
    else panier[id] = {id, nom, prix, quantite: 1};
    update();
}

function changer(id, d) {
    id = parseInt(id);
    if (panier[id]) {
        panier[id].quantite += d;
        if (panier[id].quantite <= 0) delete panier[id];
    }
    update();
}

function update() {
    var h = '', t = 0, n = 0;
    for (var k in panier) {
        var v = panier[k];
        t += v.prix * v.quantite;
        n += v.quantite;
        h += '<div class="panier-item"><div><div style="font-weight:600">' + v.nom + '</div><div style="font-size:12px;color:var(--sub)">' + v.prix.toLocaleString() + ' <?=DEVISE_SYMBOLE?> × ' + v.quantite + '</div></div><div style="display:flex;align-items:center;gap:8px"><div class="qte-ctrl"><button class="qte-btn" onclick="event.stopPropagation();changer(' + k + ',-1)"><i class="fa-solid fa-minus"></i></button><span class="qte-value">' + v.quantite + '</span><button class="qte-btn" onclick="event.stopPropagation();changer(' + k + ',1)"><i class="fa-solid fa-plus"></i></button></div><button class="btn-remove" onclick="event.stopPropagation();delete panier[' + k + '];update()"><i class="fa-solid fa-trash-can"></i></button></div></div>';
    }
    document.getElementById('panierItems').innerHTML = n === 0 ? '<p style="text-align:center;color:var(--sub);padding:20px">Panier vide</p>' : h;
    document.getElementById('panierTotal').style.display = n === 0 ? 'none' : 'flex';
    document.getElementById('totalPrix').textContent = t.toLocaleString() + ' <?=DEVISE_SYMBOLE?>';
    document.getElementById('formCmd').style.display = n === 0 ? 'none' : 'block';
    if (document.getElementById('panierCount')) document.getElementById('panierCount').textContent = n;
}

function preparerCommande() {
    var d = [];
    for (var k in panier) d.push(panier[k]);
    document.getElementById('panierData').value = JSON.stringify(d);
    return true;
}

function ouvrirPanier() { document.getElementById('modalPanier').classList.add('active'); }
function fermerPanier() { document.getElementById('modalPanier').classList.remove('active'); }
document.getElementById('modalPanier').addEventListener('click', function(e) { if (e.target === this) fermerPanier(); });

function selectMode(el) {
    document.querySelectorAll('.radio-option').forEach(r => r.classList.remove('selected'));
    el.classList.add('selected');
}

function filtrer(cat) {
    document.querySelectorAll('.cat-btn').forEach(b => b.classList.remove('active'));
    event.target.classList.add('active');
    document.querySelectorAll('.section').forEach(s => s.style.display = (cat === 'all' || s.dataset.cat === cat) ? 'block' : 'none');
}

function rechercher() {
    var q = document.getElementById('searchInput').value.toLowerCase();
    document.querySelectorAll('.plat').forEach(p => {
        p.style.display = p.dataset.nom.includes(q) ? 'block' : 'none';
    });
}

function openFullscreen(src) {
    document.getElementById('fullscreenImgSrc').src = src;
    document.getElementById('fullscreenImg').classList.add('active');
}

function closeFullscreen() { document.getElementById('fullscreenImg').classList.remove('active'); }

update();
</script>
</body>
</html>