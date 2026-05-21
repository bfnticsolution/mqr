<?php
require_once __DIR__ . '/includes/config.php';

$suivi = $_GET['suivi'] ?? null;

if (!$suivi) {
    header('Location: index.php');
    exit;
}

$db = getDB();
$stmt = $db->prepare("SELECT c.*, r.nom_restaurant, r.telephone_whatsapp FROM commandes c JOIN restaurants r ON c.restaurant_id=r.id WHERE c.code_suivi=?");
$stmt->execute([$suivi]);
$cmd = $stmt->fetch();

if (!$cmd) {
    echo "<div style='text-align:center;padding:80px;font-family:sans-serif;'><h1>🔍 Commande introuvable</h1><p>Vérifiez le code.</p><a href='".SITE_URL."' style='color:#FF6B35'>Retour</a></div>";
    exit;
}

$stmt = $db->prepare("SELECT * FROM suivi_commandes WHERE commande_id=? ORDER BY date_maj DESC");
$stmt->execute([$cmd['id']]);
$historique = $stmt->fetchAll();

$stmt = $db->prepare("SELECT cp.*, p.nom FROM commande_plats cp JOIN plats p ON cp.plat_id=p.id WHERE cp.commande_id=?");
$stmt->execute([$cmd['id']]);
$platsCmd = $stmt->fetchAll();

$statutActuel = $cmd['statut'];
$statusConfig = [
    'en_attente'    => ['icon'=>'fa-clock','text'=>'En attente','sub'=>'Votre commande va être prise en charge','color'=>'#F59E0B','bg'=>'#FFF3CD'],
    'confirmee'     => ['icon'=>'fa-circle-check','text'=>'Confirmée','sub'=>'Le restaurant prépare votre commande','color'=>'#3B82F6','bg'=>'#DBEAFE'],
    'en_preparation'=> ['icon'=>'fa-fire-burner','text'=>'En préparation','sub'=>'Le cuisinier est aux fourneaux','color'=>'#3B82F6','bg'=>'#DBEAFE'],
    'prete'         => ['icon'=>'fa-bell','text'=>'Prête !','sub'=>'Votre commande est prête','color'=>'#10B981','bg'=>'#ECFDF5'],
    'livree'        => ['icon'=>'fa-motorcycle','text'=>'Livrée','sub'=>'Bon appétit !','color'=>'#10B981','bg'=>'#ECFDF5'],
    'annulee'       => ['icon'=>'fa-circle-xmark','text'=>'Annulée','sub'=>'Cette commande a été annulée','color'=>'#EF4444','bg'=>'#FEE2E2'],
];
$currentStatus = $statusConfig[$statutActuel] ?? ['icon'=>'fa-circle','text'=>$statutActuel,'sub'=>'','color'=>'#64748B','bg'=>'#F1F5F9'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#FF6B35">
    <title>Suivi #<?= $suivi ?> - <?= e($cmd['nom_restaurant']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root{--o:#FF6B35;--bg:#F8F9FA;--card:#fff;--text:#1E293B;--sub:#64748B;--border:#E5E7EB}
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:var(--bg);color:var(--text);padding:20px;-webkit-tap-highlight-color:transparent}
        @media (prefers-color-scheme:dark){:root{--bg:#0F172A;--card:#1E293B;--text:#F1F5F9;--sub:#94A3B8;--border:#334155}}
        .container{max-width:500px;margin:0 auto}
        
        .header{text-align:center;padding:20px 0}
        .header h1{font-size:20px;margin-bottom:4px;display:flex;align-items:center;justify-content:center;gap:8px}
        .header h1 i{color:var(--o)}
        .code{font-size:30px;font-weight:800;letter-spacing:6px;color:var(--o);background:#FFF7ED;padding:10px 24px;border-radius:16px;display:inline-block;margin:8px 0;border:2px solid #FED7AA}
        .resto{color:var(--sub);font-size:13px}
        
        .status-card{background:<?=$currentStatus['bg']?>;border-radius:20px;padding:24px;margin:16px 0;text-align:center;transition:.3s}
        .status-card .status-icon{font-size:56px;color:<?=$currentStatus['color']?>;margin-bottom:8px}
        .status-card .status-text{font-size:22px;font-weight:700;color:<?=$currentStatus['color']?>}
        .status-card .status-sub{color:var(--sub);font-size:13px;margin-top:4px}
        
        .card{background:var(--card);border-radius:16px;padding:18px;margin-bottom:14px;box-shadow:0 1px 3px rgba(0,0,0,.04)}
        .card h3{font-size:14px;color:var(--sub);text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px;display:flex;align-items:center;gap:8px}
        .card h3 i{color:var(--o)}
        
        .timeline{position:relative;padding-left:28px}
        .timeline::before{content:'';position:absolute;left:11px;top:0;bottom:0;width:2px;background:var(--border)}
        .timeline-item{position:relative;margin-bottom:16px}
        .timeline-item::before{content:'';position:absolute;left:-22px;top:4px;width:12px;height:12px;border-radius:50%;background:var(--o);border:2px solid #fff;box-shadow:0 0 0 2px var(--o)}
        .timeline-item .t-date{font-size:10px;color:var(--sub)}
        .timeline-item .t-text{font-size:13px;font-weight:500}
        
        .plat-item{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border);font-size:13px}
        .plat-item:last-child{border-bottom:none}
        .total{text-align:right;font-weight:700;font-size:18px;color:var(--o);margin-top:8px;padding-top:8px;border-top:2px solid var(--border)}
        
        .alert-new{background:<?=$currentStatus['bg']?>;color:<?=$currentStatus['color']?>;padding:14px;border-radius:14px;text-align:center;font-weight:600;margin:12px 0;display:none;animation:slideIn .4s ease;border:2px solid <?=$currentStatus['color']?>20}
        @keyframes slideIn{from{transform:translateY(-10px);opacity:0}to{transform:translateY(0);opacity:1}}
        
        .btn{display:flex;align-items:center;justify-content:center;gap:8px;padding:14px;border-radius:14px;font-weight:700;text-decoration:none;margin-top:10px;font-size:15px;transition:.2s}
        .btn-wa{background:#25D366;color:#fff}.btn-o{background:var(--o);color:#fff}.btn-out{background:transparent;color:var(--o);border:2px solid var(--o)}
        
        .save-info{background:#EFF6FF;padding:10px 14px;border-radius:10px;font-size:11px;color:#3B82F6;text-align:center;margin:10px 0;display:flex;align-items:center;gap:8px;justify-content:center}
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fa-solid fa-clipboard-list"></i> Suivi de commande</h1>
            <div class="code"><?= $suivi ?></div>
            <div class="resto"><i class="fa-solid fa-store"></i> <?= e($cmd['nom_restaurant']) ?></div>
            <?php if($cmd['numero_table']):?><div class="resto"><i class="fa-solid fa-chair"></i> Table <?= e($cmd['numero_table']) ?></div><?php endif;?>
        </div>
        
        <div class="alert-new" id="alertNew">🆕 Le statut de votre commande a changé !</div>
        
        <div class="status-card" id="statusCard">
            <div class="status-icon" id="statusIcon"><i class="fa-solid <?=$currentStatus['icon']?>"></i></div>
            <div class="status-text" id="statusText"><?=$currentStatus['text']?></div>
            <div class="status-sub" id="statusSub"><?=$currentStatus['sub']?></div>
        </div>
        
        <div class="card">
            <h3><i class="fa-solid fa-utensils"></i> Votre commande</h3>
            <?php foreach($platsCmd as $p):?>
                <div class="plat-item"><span><?= e($p['nom']) ?> ×<?= $p['quantite'] ?></span><span><?= formatPrix($p['prix_unitaire'] * $p['quantite']) ?></span></div>
            <?php endforeach;?>
            <div class="total"><i class="fa-solid fa-receipt"></i> Total : <?= formatPrix($cmd['total']) ?></div>
            <?php if($cmd['mode_commande']):?>
                <div style="font-size:11px;color:var(--sub);margin-top:6px;text-align:center">
                    <i class="fa-solid <?=$cmd['mode_commande']=='emporter'?'fa-bag-shopping':'fa-house'?>"></i>
                    <?= $cmd['mode_commande'] == 'emporter' ? 'À emporter' : 'Sur place' ?>
                </div>
            <?php endif;?>
        </div>
        
        <div class="card">
            <h3><i class="fa-solid fa-clock-rotate-left"></i> Historique</h3>
            <div class="timeline">
                <?php foreach($historique as $h):?>
                    <div class="timeline-item">
                        <div class="t-date"><?= date('d/m/Y H:i', strtotime($h['date_maj'])) ?></div>
                        <div class="t-text">
                            <?= match($h['statut']) {
                                'en_attente' => '<i class="fa-solid fa-clock"></i> Commande reçue',
                                'confirmee' => '<i class="fa-solid fa-circle-check"></i> Confirmée',
                                'en_preparation' => '<i class="fa-solid fa-fire-burner"></i> En préparation',
                                'prete' => '<i class="fa-solid fa-bell"></i> Prête !',
                                'livree' => '<i class="fa-solid fa-motorcycle"></i> Livrée',
                                'annulee' => '<i class="fa-solid fa-circle-xmark"></i> Annulée',
                                default => $h['statut']
                            } ?>
                            <?php if($h['commentaire']):?><br><small style="color:var(--sub)"><?= e($h['commentaire']) ?></small><?php endif;?>
                        </div>
                    </div>
                <?php endforeach;?>
                <?php if(empty($historique)):?>
                    <div class="timeline-item"><div class="t-date"><?= date('d/m/Y H:i', strtotime($cmd['date_commande'])) ?></div><div class="t-text"><i class="fa-solid fa-paper-plane"></i> Commande passée</div></div>
                <?php endif;?>
            </div>
        </div>
        
        <div class="save-info"><i class="fa-solid fa-mobile-screen"></i> Cette commande est sauvegardée dans votre téléphone.</div>
        
        <?php if($cmd['telephone_whatsapp']):?>
            <a href="https://wa.me/<?= e($cmd['telephone_whatsapp']) ?>?text=Bonjour, je suis ma commande #<?= $suivi ?>" class="btn btn-wa" target="_blank"><i class="fa-brands fa-whatsapp"></i> Contacter le restaurant</a>
        <?php endif;?>
        
        <a href="menu.php?id=<?= $cmd['restaurant_id'] ?>" class="btn btn-o"><i class="fa-solid fa-utensils"></i> Commander à nouveau</a>
        <a href="mes-commandes.php" class="btn btn-out"><i class="fa-solid fa-list"></i> Mes commandes</a>
    </div>
    
    <script>
    var currentStatus='<?=$statutActuel?>',commandeId=<?=$cmd['id']?>,codeSuivi='<?=$suivi?>',restoNom='<?=e($cmd['nom_restaurant'],true)?>';
    var audio=new Audio('https://www.soundjay.com/buttons/sounds/button-09.mp3');
    audio.preload='auto';
    
    function saveToLocal(){var c=JSON.parse(localStorage.getItem('menuqr_commandes')||'[]');var e=c.find(function(x){return x.code===codeSuivi});if(!e){c.push({code:codeSuivi,resto:restoNom,total:<?=$cmd['total']?>,statut:currentStatus,date:'<?=$cmd['date_commande']?>',url:window.location.href});localStorage.setItem('menuqr_commandes',JSON.stringify(c))}}
    function updateLocal(s){var c=JSON.parse(localStorage.getItem('menuqr_commandes')||'[]');var f=c.find(function(x){return x.code===codeSuivi});if(f){f.statut=s;localStorage.setItem('menuqr_commandes',JSON.stringify(c))}}
    function playSound(){audio.play().catch(function(){})}
    function notifyClient(s,t){if('Notification' in window&&Notification.permission==='granted'){var n=new Notification('🍽️ Commande #'+codeSuivi,{body:t,icon:'<?=SITE_URL?>/favicon.ico',tag:'order-update',requireInteraction:true});n.onclick=function(){window.focus()}}}
    function showAlert(t){var a=document.getElementById('alertNew');a.textContent='🆕 '+t;a.style.display='block';setTimeout(function(){a.style.display='none'},5000)}
    function updateDisplay(s){
        var config={'en_attente':{icon:'fa-clock',text:'En attente',sub:'Votre commande va être prise en charge',color:'#F59E0B',bg:'#FFF3CD'},'confirmee':{icon:'fa-circle-check',text:'Confirmée',sub:'Le restaurant prépare votre commande',color:'#3B82F6',bg:'#DBEAFE'},'en_preparation':{icon:'fa-fire-burner',text:'En préparation',sub:'Le cuisinier est aux fourneaux',color:'#3B82F6',bg:'#DBEAFE'},'prete':{icon:'fa-bell',text:'Prête !',sub:'Votre commande est prête',color:'#10B981',bg:'#ECFDF5'},'livree':{icon:'fa-motorcycle',text:'Livrée',sub:'Bon appétit !',color:'#10B981',bg:'#ECFDF5'},'annulee':{icon:'fa-circle-xmark',text:'Annulée',sub:'Cette commande a été annulée',color:'#EF4444',bg:'#FEE2E2'}};
        var c=config[s]||config['en_attente'];
        document.getElementById('statusIcon').innerHTML='<i class="fa-solid '+c.icon+'"></i>';
        document.getElementById('statusText').textContent=c.text;
        document.getElementById('statusSub').textContent=c.sub;
        document.getElementById('statusCard').style.background=c.bg;
        document.getElementById('statusCard').querySelector('.status-icon').style.color=c.color;
        document.getElementById('statusCard').querySelector('.status-text').style.color=c.color;
    }
    function checkUpdates(){
        fetch('<?=SITE_URL?>/api/check-status.php?cmd_id='+commandeId+'&current='+currentStatus)
            .then(function(r){return r.json()}).then(function(d){
                if(d.changed&&d.new_status!==currentStatus){currentStatus=d.new_status;playSound();updateDisplay(currentStatus);showAlert('Votre commande est maintenant : '+currentStatus);notifyClient(currentStatus,'Statut : '+currentStatus);updateLocal(currentStatus);setTimeout(function(){location.reload()},3000)}
            }).catch(function(e){console.log(e)})
    }
    if('Notification' in window&&Notification.permission==='default')Notification.requestPermission();
    saveToLocal();setInterval(checkUpdates,5000);setTimeout(checkUpdates,2000);
    </script>
</body>
</html>