<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes commandes - Menu QR</title>
    <style>
        :root{--o:#FF6B35;--bg:#F8F9FA;--card:#fff}
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:var(--bg);padding:16px}
        .container{max-width:500px;margin:0 auto}
        h1{text-align:center;margin:20px 0;color:var(--o)}
        .cmd-card{background:var(--card);border-radius:14px;padding:16px;margin-bottom:12px;box-shadow:0 1px 3px rgba(0,0,0,.04);cursor:pointer;display:flex;justify-content:space-between;align-items:center}
        .cmd-card:hover{box-shadow:0 4px 12px rgba(0,0,0,.08)}
        .cmd-card .code{font-weight:800;font-size:18px;color:var(--o)}
        .cmd-card .resto{font-size:13px;color:#64748B}
        .cmd-card .date{font-size:11px;color:#999}
        .tag{padding:3px 8px;border-radius:8px;font-size:10px;font-weight:700}
        .tag-attente{background:#FFF3CD;color:#856404}.tag-confirmee,.tag-preparation{background:#DBEAFE;color:#1E40AF}.tag-prete,.tag-livree{background:#DCFCE7;color:#166534}
        .empty{text-align:center;padding:60px 20px;color:#999}
        .btn{display:block;text-align:center;padding:14px;background:var(--o);color:#fff;border-radius:12px;text-decoration:none;font-weight:700;margin-top:20px}
    </style>
</head>
<body>
    <div class="container">
        <h1>📋 Mes commandes</h1>
        <div id="commandesList"></div>
        <div class="empty" id="empty" style="display:none">
            <p style="font-size:40px">🛒</p>
            <p>Aucune commande sauvegardée.</p>
            <p style="font-size:13px">Scannez un QR code pour commander !</p>
        </div>
        <a href="index.php" class="btn">← Retour</a>
    </div>
    
    <script>
    var commandes = JSON.parse(localStorage.getItem('menuqr_commandes') || '[]');
    var list = document.getElementById('commandesList');
    var empty = document.getElementById('empty');
    
    if (commandes.length === 0) {
        empty.style.display = 'block';
    } else {
        commandes.reverse().forEach(function(c) {
            var tagClass = 'tag-attente';
            if (c.statut === 'confirmee' || c.statut === 'en_preparation') tagClass = 'tag-confirmee';
            if (c.statut === 'prete' || c.statut === 'livree') tagClass = 'tag-prete';
            
            var card = document.createElement('div');
            card.className = 'cmd-card';
            card.onclick = function() { window.location.href = c.url; };
            card.innerHTML = '<div><div class="code">#' + c.code + '</div><div class="resto">' + c.resto + '</div><div class="date">' + c.date + '</div></div><span class="tag ' + tagClass + '">' + c.statut + '</span>';
            list.appendChild(card);
        });
    }
    </script>
</body>
</html>