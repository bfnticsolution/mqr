<?php
require_once __DIR__ . '/includes/config.php';

if (!isset($_SESSION['restaurant_id'])) {
    header('Location: connexion.php');
    exit;
}

$db = getDB();
$cmd_id = intval($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT c.*, r.nom_restaurant, r.adresse, r.telephone, r.telephone_whatsapp FROM commandes c JOIN restaurants r ON c.restaurant_id = r.id WHERE c.id = ? AND c.restaurant_id = ?");
$stmt->execute([$cmd_id, $_SESSION['restaurant_id']]);
$cmd = $stmt->fetch();

if (!$cmd) {
    echo "<p style='text-align:center;padding:40px'>Commande introuvable.</p>";
    exit;
}

$stmt = $db->prepare("SELECT cp.*, p.nom FROM commande_plats cp JOIN plats p ON cp.plat_id = p.id WHERE cp.commande_id = ?");
$stmt->execute([$cmd_id]);
$plats = $stmt->fetchAll();

// Calculer le nombre de caractères max par ligne (police monospace)
$lineWidth = 42;
$totalFormatted = formatPrix($cmd['total']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket #<?= e($cmd['code_suivi']) ?></title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        
        @media print {
            body{width:80mm;margin:0;padding:0;font-size:10px}
            .no-print{display:none!important}
            @page{margin:0;size:80mm 297mm}
        }
        
        @media screen {
            body{background:#e0e0e0;display:flex;justify-content:center;padding:20px;font-family:'Courier New',monospace}
            .ticket{background:#fff;width:80mm;box-shadow:2px 2px 10px rgba(0,0,0,.15);padding:8px;font-size:10px}
        }
        
        .ticket{font-family:'Courier New',monospace;line-height:1.4;color:#000}
        .ticket .center{text-align:center}
        .ticket .right{text-align:right}
        .ticket .bold{font-weight:bold}
        .ticket .line{border-top:1px dashed #000;margin:6px 0}
        .ticket .line-solid{border-top:1px solid #000;margin:6px 0}
        .ticket .cut-line{text-align:center;margin:8px 0;letter-spacing:3px;font-size:8px;color:#999}
        
        .no-print{text-align:center;margin-top:20px;font-family:Arial,sans-serif}
        .no-print button{padding:14px 30px;background:#FF6B35;color:#fff;border:none;border-radius:12px;font-size:16px;font-weight:700;cursor:pointer;margin:5px}
        .no-print button:hover{background:#e55a2b}
        .no-print button i{margin-right:6px}
        .no-print a{display:block;margin-top:12px;color:#FF6B35;text-decoration:none;font-size:13px}
    </style>
</head>
<body>
    <div>
        <!-- TICKET -->
        <div class="ticket" id="ticket">
            <!-- En-tête -->
            <div class="center bold" style="font-size:14px"><?= e(mb_strtoupper($cmd['nom_restaurant'])) ?></div>
            <?php if($cmd['adresse']):?><div class="center" style="font-size:9px"><?= e($cmd['adresse']) ?></div><?php endif;?>
           <div class="center" style="font-size:9px">Tél: <?= e($cmd['telephone']) ?></div>
            <div class="line"></div>
            
            <!-- Infos commande -->
            <div>CMD: <span class="bold">#<?= e($cmd['code_suivi']) ?></span></div>
            <div>DATE: <?= date('d/m/Y H:i', strtotime($cmd['date_commande'])) ?></div>
            <div>TABLE: <span class="bold"><?= e($cmd['numero_table'] ?? 'N/A') ?></span></div>
            <div>MODE: <?= $cmd['mode_commande'] == 'emporter' ? 'A EMPORTER' : 'SUR PLACE' ?></div>
            <?php if($cmd['nom_client']):?><div>CLIENT: <?= e($cmd['nom_client']) ?></div><?php endif;?>
            <?php if($cmd['telephone_client']):?><div>TEL: <?= e($cmd['telephone_client']) ?></div><?php endif;?>
            <div class="line"></div>
            
            <!-- Plats -->
            <table style="width:100%;border-collapse:collapse">
                <thead><tr style="border-bottom:1px solid #000"><td style="width:20px">QT</td><td>PLAT</td><td class="right">PRIX</td></tr></thead>
                <tbody>
                    <?php foreach($plats as $p): 
                        $sousTotal = $p['prix_unitaire'] * $p['quantite'];
                        $nomPlat = mb_strlen($p['nom']) > 22 ? mb_substr($p['nom'], 0, 20) . '..' : $p['nom'];
                    ?>
                        <tr>
                            <td><?= $p['quantite'] ?></td>
                            <td><?= e($nomPlat) ?></td>
                            <td class="right"><?= formatPrix($sousTotal) ?></td>
                        </tr>
                        <?php if($p['quantite'] > 1):?>
                            <tr><td></td><td style="font-size:8px;color:#666">(<?= formatPrix($p['prix_unitaire']) ?> x<?= $p['quantite'] ?>)</td><td></td></tr>
                        <?php endif;?>
                    <?php endforeach;?>
                </tbody>
            </table>
            <div class="line-solid"></div>
            
            <!-- Total -->
            <div style="font-size:14px" class="bold right">TOTAL: <?= $totalFormatted ?></div>
            <div style="font-size:8px;color:#666" class="right">(TTC - TVA non applicable, art.293B CGI)</div>
            
            <!-- Note -->
            <?php if($cmd['note']):?>
                <div class="line"></div>
                <div style="font-size:9px;font-style:italic">📝 <?= e($cmd['note']) ?></div>
            <?php endif;?>
            
            <!-- Pied -->
            <div class="line"></div>
            <div class="center" style="font-size:9px">Commande via Menu QR</div>
            <div class="center" style="font-size:9px">menuqr.page.gd</div>
            <div class="center" style="font-size:8px"><?= date('d/m/Y H:i:s') ?></div>
            
            <!-- Ligne de découpe -->
            <div class="cut-line">- - - - - - - - - coupez ici - - - - - - - - -</div>
        </div>
        
        <!-- Boutons -->
        <div class="no-print">
            <button onclick="imprimer()"><i class="fa-solid fa-print"></i> Imprimer (thermique 80mm)</button>
            <button onclick="imprimerA4()"><i class="fa-solid fa-file"></i> Imprimer (A4 standard)</button>
            <br>
            <a href="dashboard.php?tab=commandes">← Retour aux commandes</a>
        </div>
    </div>
    
    <script>
        function imprimer() {
            window.print();
        }
        
        function imprimerA4() {
            var ticket = document.getElementById('ticket');
            ticket.style.width = '210mm';
            ticket.style.fontSize = '14px';
            ticket.style.padding = '20px';
            window.print();
            setTimeout(function() {
                ticket.style.width = '80mm';
                ticket.style.fontSize = '10px';
                ticket.style.padding = '8px';
            }, 500);
        }
        
        <?php if(isset($_GET['auto'])):?>
            window.onload = function() { window.print(); }
        <?php endif;?>
    </script>
</body>
</html>