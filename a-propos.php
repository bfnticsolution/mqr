<?php require_once __DIR__ . '/includes/config.php'; ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>À propos - Menu QR | NTIC Solution</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root{--o:#FF6B35;--bg:#F8F9FA;--text:#1E293B;--sub:#64748B}
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:-apple-system,BlinkMacSystemFont,sans-serif;background:var(--bg);color:var(--text)}
        .hero{background:linear-gradient(135deg,var(--o),#e55a2b);color:#fff;text-align:center;padding:60px 20px}
        .hero img{width:80px;border-radius:16px;margin-bottom:16px}
        .hero h1{font-size:32px;margin-bottom:8px}.hero p{opacity:.9}
        .container{max-width:800px;margin:0 auto;padding:40px 20px}
        .card{background:#fff;border-radius:16px;padding:30px;margin-bottom:20px;box-shadow:0 2px 8px rgba(0,0,0,.04)}
        .card h2{color:var(--o);margin-bottom:12px;font-size:20px}
        .card p{line-height:1.8;color:var(--sub)}
        .team{display:flex;gap:20px;flex-wrap:wrap;justify-content:center;margin-top:30px}
        .member{background:#fff;border-radius:16px;padding:30px;text-align:center;width:220px;box-shadow:0 2px 8px rgba(0,0,0,.04)}
        .member .avatar{width:80px;height:80px;border-radius:50%;background:var(--o);color:#fff;display:flex;align-items:center;justify-content:center;font-size:32px;margin:0 auto 16px}
        .member h3{font-size:16px;margin-bottom:4px}.member p{font-size:12px;color:var(--sub)}
        .cta{text-align:center;padding:40px 20px}
        .btn{display:inline-block;padding:14px 30px;background:var(--o);color:#fff;border-radius:25px;text-decoration:none;font-weight:700}
        footer{text-align:center;padding:20px;color:#94A3B8;font-size:13px}footer a{color:var(--o)}
    </style>
</head>
<body>
    <div class="hero">
        <img src="<?=LOGO_URL?>" alt="Menu QR">
        <h1>À propos de Menu QR</h1>
        <p>La carte numérique des restaurants africains</p>
    </div>

    <div class="container">
        <div class="card">
            <h2>Notre histoire</h2>
            <p>Menu QR est né en 2026 à <strong><?=CONTACT_VILLE?>, <?=CONTACT_PAYS?></strong>, d'un constat simple : les restaurants et maquis dépensent des milliers de FCFA dans des menus papier qui se salissent, se déchirent et deviennent obsolètes.</p>
            <p>Développé par <strong>NTIC Solution</strong>, Menu QR est une solution adaptée aux réalités locales : pas d'application à installer, fonctionnement sur tous les téléphones, prix accessible, paiement international.</p>
        </div>

        <div class="card">
            <h2>Notre mission</h2>
            <p>Digitaliser les restaurants africains en leur offrant une solution <strong>simple, économique et efficace</strong> pour présenter leurs menus et recevoir des commandes. Rendre la technologie accessible à tous les restaurateurs, du petit maquis au grand restaurant, partout en Afrique et dans le monde.</p>
        </div>

        <div class="card">
            <h2>Nos valeurs</h2>
            <p>🌍 <strong>Made in Africa</strong> : Conçu et développé en Afrique</p>
            <p>💰 <strong>Accessible</strong> : Gratuit pour commencer, <?=formatPrix(ABONNEMENT_PRIX_FCFA)?>/mois pour tout débloquer</p>
            <p>🚀 <strong>Simple</strong> : Pas d'application, pas de matériel coûteux</p>
            <p>🤝 <strong>Proche</strong> : Support local, formation gratuite</p>
            <p>💳 <strong>Paiement international</strong> : Mobile Money, carte bancaire, PayPal via Chariow</p>
        </div>

        <h2 style="text-align:center;margin-top:40px">L'équipe</h2>
        <div class="team">
            <div class="member">
                <div class="avatar"><i class="fa-solid fa-user-tie"></i></div>
                <h3>Kévin Kiessé SANOU</h3>
                <p>Fondateur & Développeur</p>
                <p>NTIC Solution - <?=CONTACT_VILLE?></p>
            </div>
        </div>

        <h2 style="text-align:center;margin-top:40px">Contact</h2>
        <div class="card" style="text-align:center">
            <p>📞 <strong><?=CONTACT_TEL?></strong></p>
            <p>💬 WhatsApp : <strong><?=CONTACT_WHATSAPP?></strong></p>
            <p>📧 <strong><?=CONTACT_EMAIL?></strong></p>
            <p>📍 <strong><?=CONTACT_VILLE?>, <?=CONTACT_PAYS?></strong></p>
            <p>🌐 <strong><?=SITE_URL?></strong></p>
        </div>
    </div>

    <div class="cta">
        <a href="inscription.php" class="btn"><i class="fa-solid fa-rocket"></i> Rejoindre Menu QR</a>
    </div>

    <footer>
        <p>© 2026 <strong>NTIC Solution</strong> - <?=CONTACT_VILLE?>, <?=CONTACT_PAYS?> 🇧🇫</p>
        <p><a href="index.php">Accueil</a> | <a href="admin.php">Admin</a></p>
    </footer>
</body>
</html>