<?php
/**
 * Statistiques Restaurant - Menu QR
 * Version pro : comptabilité, ventes par période, export
 * NTIC Solution
 */
require_once __DIR__ . '/includes/config.php';
if (!isset($_SESSION['restaurant_id'])) { header('Location: connexion.php'); exit; }

$db = getDB();
$rid = $_SESSION['restaurant_id'];

// Période sélectionnée
$periode = $_GET['periode'] ?? '7j';
switch ($periode) {
    case 'ajd': $dateDebut = date('Y-m-d'); $labelPeriode = "Aujourd'hui"; break;
    case 'hier': $dateDebut = date('Y-m-d', strtotime('-1 day')); $labelPeriode = 'Hier'; break;
    case '7j': $dateDebut = date('Y-m-d', strtotime('-7 days')); $labelPeriode = '7 derniers jours'; break;
    case '30j': $dateDebut = date('Y-m-d', strtotime('-30 days')); $labelPeriode = '30 derniers jours'; break;
    case 'mois': $dateDebut = date('Y-m-01'); $labelPeriode = 'Ce mois'; break;
    case 'semaine': $dateDebut = date('Y-m-d', strtotime('monday this week')); $labelPeriode = 'Cette semaine'; break;
    default: $dateDebut = date('Y-m-d', strtotime('-7 days')); $labelPeriode = '7 derniers jours';
}

// Stats générales
$nbPlats = $db->query("SELECT COUNT(*) FROM plats WHERE restaurant_id=$rid")->fetchColumn();
$nbScans = $db->query("SELECT nb_scans FROM restaurants WHERE id=$rid")->fetchColumn();
$nbCommandes = $db->query("SELECT COUNT(*) FROM commandes WHERE restaurant_id=$rid")->fetchColumn();
$caTotal = $db->query("SELECT COALESCE(SUM(total),0) FROM commandes WHERE restaurant_id=$rid")->fetchColumn();

// Stats période
$nbCmdPeriode = $db->query("SELECT COUNT(*) FROM commandes WHERE restaurant_id=$rid AND DATE(date_commande) >= '$dateDebut'")->fetchColumn();
$caPeriode = $db->query("SELECT COALESCE(SUM(total),0) FROM commandes WHERE restaurant_id=$rid AND DATE(date_commande) >= '$dateDebut'")->fetchColumn();
$panierMoyen = $nbCmdPeriode > 0 ? round($caPeriode / $nbCmdPeriode) : 0;

// Commandes par statut
$cmdParStatut = $db->query("SELECT statut, COUNT(*) as nb, COALESCE(SUM(total),0) as ca FROM commandes WHERE restaurant_id=$rid AND DATE(date_commande) >= '$dateDebut' GROUP BY statut")->fetchAll();
$cmdLivrees = $db->query("SELECT COALESCE(SUM(total),0) FROM commandes WHERE restaurant_id=$rid AND statut='livree' AND DATE(date_commande) >= '$dateDebut'")->fetchColumn();
$nbLivrees = $db->query("SELECT COUNT(*) FROM commandes WHERE restaurant_id=$rid AND statut='livree' AND DATE(date_commande) >= '$dateDebut'")->fetchColumn();

// Plats les plus vendus (en quantité et CA)
$topPlats = $db->query("SELECT p.nom, p.prix, SUM(cp.quantite) as nb_vendus, SUM(cp.quantite * cp.prix_unitaire) as ca_plat FROM commande_plats cp JOIN plats p ON cp.plat_id=p.id JOIN commandes c ON cp.commande_id=c.id WHERE p.restaurant_id=$rid AND c.statut='livree' AND DATE(c.date_commande) >= '$dateDebut' GROUP BY p.id ORDER BY nb_vendus DESC LIMIT 10")->fetchAll();

// Tous les plats avec leurs stats (même ceux jamais commandés)
$tousPlatsStats = $db->query("SELECT p.nom, p.prix, COALESCE(SUM(cp.quantite),0) as nb_vendus, COALESCE(SUM(cp.quantite * cp.prix_unitaire),0) as ca_plat FROM plats p LEFT JOIN commande_plats cp ON p.id=cp.plat_id LEFT JOIN commandes c ON cp.commande_id=c.id AND c.statut='livree' AND DATE(c.date_commande) >= '$dateDebut' WHERE p.restaurant_id=$rid GROUP BY p.id ORDER BY nb_vendus DESC")->fetchAll();

// Commandes par jour (graphique)
$cmdParJour = $db->query("SELECT DATE(date_commande) as jour, COUNT(*) as nb, COALESCE(SUM(total),0) as ca FROM commandes WHERE restaurant_id=$rid AND date_commande >= '$dateDebut' GROUP BY DATE(date_commande) ORDER BY jour ASC")->fetchAll();

// Taux de conversion
$tauxConversion = $nbScans > 0 ? round(($nbCommandes / $nbScans) * 100) : 0;

// Commandes par mode
$cmdSurPlace = $db->query("SELECT COUNT(*) FROM commandes WHERE restaurant_id=$rid AND mode_commande='sur_place' AND DATE(date_commande) >= '$dateDebut'")->fetchColumn();
$cmdEmporter = $db->query("SELECT COUNT(*) FROM commandes WHERE restaurant_id=$rid AND mode_commande='emporter' AND DATE(date_commande) >= '$dateDebut'")->fetchColumn();

// Commandes par table
$topTables = $db->query("SELECT numero_table, COUNT(*) as nb, COALESCE(SUM(total),0) as ca FROM commandes WHERE restaurant_id=$rid AND DATE(date_commande) >= '$dateDebut' AND numero_table IS NOT NULL GROUP BY numero_table ORDER BY nb DESC LIMIT 5")->fetchAll();

// Heures de pointe
$heuresPointe = $db->query("SELECT HOUR(date_commande) as heure, COUNT(*) as nb FROM commandes WHERE restaurant_id=$rid AND DATE(date_commande) >= '$dateDebut' GROUP BY HOUR(date_commande) ORDER BY nb DESC LIMIT 5")->fetchAll();

$resto = $db->query("SELECT * FROM restaurants WHERE id=$rid")->fetch();

// Export CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="stats-'.e($resto['slug']).'-'.date('Ymd').'.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Plat','Prix unitaire','Quantité vendue','CA généré']);
    foreach ($tousPlatsStats as $p) fputcsv($out, [$p['nom'], $p['prix'], $p['nb_vendus'], $p['ca_plat']]);
    fclose($out); exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistiques - <?=e($resto['nom_restaurant'])?> | Menu QR</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root{--o:#FF6B35;--g:#10B981;--r:#EF4444;--b:#3B82F6;--bg:#F1F5F9;--card:#fff;--text:#1E293B;--sub:#64748B;--border:#E2E8F0}
        @media(prefers-color-scheme:dark){:root{--bg:#0F172A;--card:#1E293B;--text:#F1F5F9;--sub:#94A3B8;--border:#334155}}
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:var(--bg);color:var(--text);font-size:13px;padding:16px}
        .container{max-width:700px;margin:0 auto}
        
        .header{text-align:center;margin-bottom:20px}
        .header img{width:48px;border-radius:12px;margin-bottom:8px}
        .header h1{color:var(--o);font-size:20px;display:flex;align-items:center;justify-content:center;gap:8px}
        .header .periode-badge{display:inline-block;background:#FFF7ED;color:var(--o);padding:4px 12px;border-radius:20px;font-size:11px;font-weight:600;margin-top:4px}
        
        .periodes{display:flex;gap:6px;justify-content:center;flex-wrap:wrap;margin-bottom:16px}
        .periode-btn{padding:6px 14px;border-radius:20px;border:2px solid var(--border);background:var(--card);color:var(--sub);cursor:pointer;font-size:11px;font-weight:600;text-decoration:none;transition:.15s;white-space:nowrap}
        .periode-btn:hover,.periode-btn.active{background:var(--o);color:#fff;border-color:var(--o)}
        
        .stats-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:16px}
        .stat-card{background:var(--card);border-radius:14px;padding:16px;text-align:center;box-shadow:0 1px 3px rgba(0,0,0,.04);transition:.2s}
        .stat-card:hover{transform:translateY(-2px)}
        .stat-card .nb{font-size:22px;font-weight:800;color:var(--o);line-height:1.2}
        .stat-card .nb.green{color:var(--g)}.stat-card .nb.blue{color:var(--b)}
        .stat-card .lb{font-size:10px;color:var(--sub);margin-top:3px;text-transform:uppercase;letter-spacing:.5px}
        
        .row{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px}
        .card{background:var(--card);border-radius:16px;padding:18px;box-shadow:0 1px 3px rgba(0,0,0,.04)}
        .card h3{font-size:14px;font-weight:600;margin-bottom:12px;display:flex;align-items:center;gap:8px;color:var(--o)}
        .card h3 .badge{font-size:10px;background:var(--bg);padding:3px 8px;border-radius:10px;color:var(--sub);font-weight:400;margin-left:auto}
        
        .plat-item{display:grid;grid-template-columns:1fr 60px 70px;align-items:center;padding:7px 0;border-bottom:1px solid var(--border);font-size:12px;gap:8px}
        .plat-item:last-child{border-bottom:none}
        .plat-item .nom{font-weight:500}.plat-item .qte{text-align:center;font-weight:600;color:var(--o)}
        .plat-item .ca{text-align:right;color:var(--g);font-weight:600}
        .plat-header{display:grid;grid-template-columns:1fr 60px 70px;gap:8px;font-size:10px;color:var(--sub);text-transform:uppercase;letter-spacing:.5px;padding-bottom:8px;border-bottom:2px solid var(--border);margin-bottom:4px}
        
        .chart{display:flex;align-items:flex-end;gap:4px;height:100px;padding-top:10px}
        .chart-col{flex:1;text-align:center;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;height:100%}
        .chart-bar{width:100%;max-width:36px;background:var(--o);border-radius:4px 4px 0 0;min-height:4px;position:relative;transition:.3s}
        .chart-val{position:absolute;top:-16px;left:50%;transform:translateX(-50%);font-size:9px;font-weight:700}
        .chart-label{font-size:8px;color:var(--sub);margin-top:4px}
        
        .statut-bar{display:flex;height:8px;border-radius:4px;overflow:hidden;margin-bottom:8px}
        .statut-seg{transition:.3s}
        
        .legend{display:flex;flex-wrap:wrap;gap:8px;font-size:10px;margin-top:8px}
        .legend span{display:flex;align-items:center;gap:4px}
        .legend .dot{width:8px;height:8px;border-radius:2px}
        
        .btn{display:inline-flex;align-items:center;gap:6px;padding:10px 20px;border-radius:10px;text-decoration:none;font-weight:600;font-size:12px;transition:.15s}
        .btn-o{background:var(--o);color:#fff}.btn-out{background:transparent;color:var(--o);border:2px solid var(--o)}
        .btn-g{background:var(--g);color:#fff}
        
        .progress-wrap{margin:8px 0}.progress-label{display:flex;justify-content:space-between;font-size:11px;margin-bottom:3px}
        .progress-bar{background:var(--border);border-radius:6px;height:6px;overflow:hidden}
        .progress-fill{background:var(--o);height:100%;border-radius:6px;transition:.3s}
        
        .actions{display:flex;gap:8px;justify-content:center;margin-top:20px;flex-wrap:wrap}
        
        @media(max-width:500px){.stats-grid{grid-template-columns:repeat(2,1fr)}.row{grid-template-columns:1fr}}
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="<?=LOGO_URL?>" alt="Menu QR">
            <h1><i class="fa-solid fa-chart-pie"></i> <?=e($resto['nom_restaurant'])?></h1>
            <span class="periode-badge"><i class="fa-solid fa-calendar"></i> <?=$labelPeriode?></span>
        </div>
        
        <div class="periodes">
            <a href="?periode=ajd" class="periode-btn <?=$periode=='ajd'?'active':''?>">Aujourd'hui</a>
            <a href="?periode=hier" class="periode-btn <?=$periode=='hier'?'active':''?>">Hier</a>
            <a href="?periode=semaine" class="periode-btn <?=$periode=='semaine'?'active':''?>">Semaine</a>
            <a href="?periode=7j" class="periode-btn <?=$periode=='7j'?'active':''?>">7 jours</a>
            <a href="?periode=30j" class="periode-btn <?=$periode=='30j'?'active':''?>">30 jours</a>
            <a href="?periode=mois" class="periode-btn <?=$periode=='mois'?'active':''?>">Ce mois</a>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card"><div class="nb"><?=number_format($nbScans)?></div><div class="lb">Scans QR</div></div>
            <div class="stat-card"><div class="nb"><?=$nbCmdPeriode?></div><div class="lb">Commandes</div></div>
            <div class="stat-card"><div class="nb"><?=formatPrix($caPeriode)?></div><div class="lb">CA Période</div></div>
            <div class="stat-card"><div class="nb green"><?=formatPrix($cmdLivrees)?></div><div class="lb">CA Livré</div></div>
            <div class="stat-card"><div class="nb"><?=formatPrix($panierMoyen)?></div><div class="lb">Panier moyen</div></div>
            <div class="stat-card"><div class="nb blue"><?=$tauxConversion?>%</div><div class="lb">Conversion</div></div>
        </div>
        
        <div class="row">
            <div class="card">
                <h3>🔥 Top plats vendus <span class="badge"><?=$labelPeriode?></span></h3>
                <?php if(empty($topPlats)):?>
                    <p style="text-align:center;color:var(--sub);padding:20px">Aucune vente sur cette période</p>
                <?php else:?>
                    <div class="plat-header"><span>Plat</span><span style="text-align:center">Qté</span><span style="text-align:right">CA</span></div>
                    <?php foreach($topPlats as $p):?>
                        <div class="plat-item"><span class="nom"><?=e($p['nom'])?></span><span class="qte"><?=$p['nb_vendus']?>×</span><span class="ca"><?=formatPrix($p['ca_plat'])?></span></div>
                    <?php endforeach;?>
                <?php endif;?>
            </div>
            
            <div class="card">
                <h3>📊 Répartition</h3>
                <div style="margin-bottom:8px;font-size:12px;font-weight:600">Modes de commande</div>
                <?php $totalMode = $cmdSurPlace + $cmdEmporter; $pctSP = $totalMode>0?round(($cmdSurPlace/$totalMode)*100):0; $pctAE = $totalMode>0?round(($cmdEmporter/$totalMode)*100):0;?>
                <div class="progress-wrap">
                    <div class="progress-label"><span><i class="fa-solid fa-house"></i> Sur place</span><span><?=$cmdSurPlace?> (<?=$pctSP?>%)</span></div>
                    <div class="progress-bar"><div class="progress-fill" style="width:<?=$pctSP?>%;background:var(--b)"></div></div>
                </div>
                <div class="progress-wrap">
                    <div class="progress-label"><span><i class="fa-solid fa-bag-shopping"></i> À emporter</span><span><?=$cmdEmporter?> (<?=$pctAE?>%)</span></div>
                    <div class="progress-bar"><div class="progress-fill" style="width:<?=$pctAE?>%;background:var(--o)"></div></div>
                </div>
                
                <?php if(!empty($topTables)):?>
                    <div style="margin-top:16px;font-size:12px;font-weight:600;margin-bottom:8px">Tables les plus actives</div>
                    <?php foreach($topTables as $t):?>
                        <div class="progress-wrap">
                            <div class="progress-label"><span><i class="fa-solid fa-chair"></i> Table <?=e($t['numero_table'])?></span><span><?=$t['nb']?> cmd</span></div>
                            <div class="progress-bar"><div class="progress-fill" style="width:<?=min(($t['nb']/max($topTables[0]['nb'],1))*100,100)?>%;background:var(--g)"></div></div>
                        </div>
                    <?php endforeach;?>
                <?php endif;?>
            </div>
        </div>
        
        <div class="card" style="margin-bottom:12px">
            <h3>📅 Commandes par jour <span class="badge"><?=$labelPeriode?></span></h3>
            <?php if(empty($cmdParJour)):?>
                <p style="text-align:center;color:var(--sub);padding:20px">Aucune commande sur cette période</p>
            <?php else:?>
                <div class="chart">
                    <?php $maxC = max(array_column($cmdParJour,'nb')?:[1]); foreach($cmdParJour as $c): $h = ($c['nb']/$maxC)*100;?>
                        <div class="chart-col">
                            <div class="chart-bar" style="height:<?=$h?>%">
                                <?php if($c['nb']>0):?><span class="chart-val"><?=$c['nb']?></span><?php endif;?>
                            </div>
                            <div class="chart-label"><?=date('d/m',strtotime($c['jour']))?></div>
                        </div>
                    <?php endforeach;?>
                </div>
            <?php endif;?>
        </div>
        
        <div class="row">
            <div class="card">
                <h3>📋 Commandes par statut</h3>
                <?php $statusColors = ['en_attente'=>'#F59E0B','confirmee'=>'#3B82F6','en_preparation'=>'#3B82F6','prete'=>'#10B981','livree'=>'#10B981','annulee'=>'#EF4444'];
                $statusIcons = ['en_attente'=>'fa-clock','confirmee'=>'fa-circle-check','en_preparation'=>'fa-fire-burner','prete'=>'fa-bell','livree'=>'fa-motorcycle','annulee'=>'fa-circle-xmark'];
                ?>
                <div class="statut-bar">
                    <?php foreach($cmdParStatut as $s): $pct = $nbCmdPeriode>0?round(($s['nb']/$nbCmdPeriode)*100):0;?>
                        <div class="statut-seg" style="width:<?=$pct?>%;background:<?=$statusColors[$s['statut']]??'#94A3B8'?>" title="<?=$s['statut']?>"></div>
                    <?php endforeach;?>
                </div>
                <div class="legend">
                    <?php foreach($cmdParStatut as $s):?>
                        <span><span class="dot" style="background:<?=$statusColors[$s['statut']]??'#94A3B8'?>"></span> <?=$s['statut']?> (<?=$s['nb']?>)</span>
                    <?php endforeach;?>
                </div>
            </div>
            
            <div class="card">
                <h3>🕐 Heures de pointe</h3>
                <?php if(empty($heuresPointe)):?>
                    <p style="text-align:center;color:var(--sub);padding:20px">Aucune donnée</p>
                <?php else:?>
                    <?php foreach($heuresPointe as $h): $pct = ($h['nb']/max($heuresPointe[0]['nb'],1))*100;?>
                        <div class="progress-wrap">
                            <div class="progress-label"><span><?=$h['heure']?>h</span><span><?=$h['nb']?> cmd</span></div>
                            <div class="progress-bar"><div class="progress-fill" style="width:<?=$pct?>%"></div></div>
                        </div>
                    <?php endforeach;?>
                <?php endif;?>
            </div>
        </div>
        
        <div class="card" style="margin-bottom:12px">
            <h3>📋 Tous les plats <span class="badge">Ventes livrées - <?=$labelPeriode?></span></h3>
            <?php if(empty($tousPlatsStats)):?>
                <p style="text-align:center;color:var(--sub);padding:20px">Aucun plat</p>
            <?php else:?>
                <div class="plat-header"><span>Plat</span><span style="text-align:center">Vendus</span><span style="text-align:right">CA</span></div>
                <?php foreach($tousPlatsStats as $p):?>
                    <div class="plat-item" style="<?=$p['nb_vendus']==0?'opacity:.4':''?>">
                        <span class="nom"><?=e($p['nom'])?></span>
                        <span class="qte"><?=$p['nb_vendus']?>×</span>
                        <span class="ca"><?=formatPrix($p['ca_plat'])?></span>
                    </div>
                <?php endforeach;?>
            <?php endif;?>
        </div>
        
        <div class="actions">
            <a href="?export=csv&periode=<?=$periode?>" class="btn btn-g"><i class="fa-solid fa-file-csv"></i> Exporter CSV</a>
            <a href="dashboard.php" class="btn btn-out"><i class="fa-solid fa-arrow-left"></i> Dashboard</a>
        </div>
    </div>
</body>
</html>