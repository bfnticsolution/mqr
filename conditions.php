<?php require_once __DIR__ . '/includes/config.php'; ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conditions d'utilisation - <?=SITE_NAME?> | NTIC Solution</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root{--o:#FF6B35;--bg:#F8F9FA;--text:#1E293B;--sub:#64748B}
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:-apple-system,BlinkMacSystemFont,sans-serif;background:var(--bg);color:var(--text);line-height:1.7}
        .header{background:linear-gradient(135deg,var(--o),#e55a2b);color:#fff;text-align:center;padding:50px 20px}
        .header h1{font-size:28px;margin-bottom:8px}.header p{opacity:.9;font-size:14px}
        .container{max-width:800px;margin:0 auto;padding:40px 20px}
        .card{background:#fff;border-radius:16px;padding:30px;margin-bottom:20px;box-shadow:0 2px 8px rgba(0,0,0,.04)}
        .card h2{color:var(--o);font-size:20px;margin-bottom:12px;padding-bottom:8px;border-bottom:2px solid #F1F5F9}
        .card p{margin-bottom:12px;color:var(--sub)}
        .card ul{margin-left:20px;margin-bottom:12px;color:var(--sub)}
        .card ul li{margin-bottom:6px}
        .back{text-align:center;padding:20px}
        .back a{color:var(--o);text-decoration:none;font-weight:600}
        footer{text-align:center;padding:20px;color:#94A3B8;font-size:12px;background:#0F172A}
        footer a{color:var(--o);text-decoration:none}
    </style>
</head>
<body>
    <div class="header">
        <h1><i class="fa-solid fa-file-contract"></i> Conditions d'utilisation</h1>
        <p>Dernière mise à jour : 19 Mai 2026</p>
    </div>

    <div class="container">
        <div class="card">
            <h2>1. Acceptation des conditions</h2>
            <p>En accédant et en utilisant la plateforme <?=SITE_NAME?> (accessible via <?=SITE_URL?>), vous acceptez d'être lié par les présentes conditions d'utilisation. Si vous n'acceptez pas ces conditions, veuillez ne pas utiliser le service.</p>
            <p><?=SITE_NAME?> est un produit de <strong>NTIC Solution</strong>, entreprise de services numériques basée à <?=CONTACT_VILLE?>, <?=CONTACT_PAYS?>.</p>
        </div>

        <div class="card">
            <h2>2. Description du service</h2>
            <p><?=SITE_NAME?> est une plateforme qui permet aux restaurants, maquis, dibiteries et cafétérias de :</p>
            <ul>
                <li>Créer un menu numérique avec photos, descriptions et prix</li>
                <li>Générer un QR code à imprimer et coller sur les tables</li>
                <li>Recevoir des commandes en ligne de la part des clients</li>
                <li>Gérer et suivre les commandes en temps réel</li>
                <li>Imprimer des tickets de commande</li>
            </ul>
        </div>

        <div class="card">
            <h2>3. Inscription et compte</h2>
            <p>Pour utiliser <?=SITE_NAME?>, le restaurateur doit créer un compte en fournissant :</p>
            <ul>
                <li>Le nom de son restaurant</li>
                <li>Son numéro de téléphone (international)</li>
                <li>Un mot de passe sécurisé</li>
                <li>Une question et réponse de sécurité</li>
            </ul>
            <p>Le restaurateur est responsable de la confidentialité de ses identifiants. Toute activité effectuée depuis son compte est de sa responsabilité.</p>
        </div>

        <div class="card">
            <h2>4. Abonnement et paiement</h2>
            <p><?=SITE_NAME?> propose deux formules :</p>
            <ul>
                <li><strong>Gratuit</strong> : 10 plats, QR code simple, sans photos ni commandes</li>
                <li><strong>Premium</strong> : <?=formatPrix(ABONNEMENT_PRIX_FCFA)?>/mois, <?=ABONNEMENT_PLATS_MAX?> plats, photos, commandes, QR par table</li>
            </ul>
            <p>Le paiement s'effectue par Mobile Money, carte bancaire ou PayPal via la plateforme Chariow. L'abonnement est valable <?=ABONNEMENT_DUREE_JOURS?> jours à compter de la date d'activation. Aucun remboursement n'est effectué pour la période en cours.</p>
        </div>

        <div class="card">
            <h2>5. Obligations du restaurateur</h2>
            <p>Le restaurateur s'engage à :</p>
            <ul>
                <li>Fournir des informations exactes lors de l'inscription</li>
                <li>Ne pas publier de contenu illicite, offensant ou frauduleux</li>
                <li>Respecter les lois en vigueur dans son pays</li>
                <li>Assurer le service des commandes passées via la plateforme</li>
                <li>Être seul responsable du contenu publié sur son menu</li>
            </ul>
        </div>

        <div class="card">
            <h2>6. Protection des données</h2>
            <p><?=SITE_NAME?> collecte uniquement les données nécessaires au fonctionnement du service. Les données des clients finaux (nom, téléphone) sont optionnelles. Aucune donnée n'est vendue, louée ou cédée à des tiers.</p>
            <p>Pour plus de détails, consultez notre <a href="confidentialite.php" style="color:var(--o)">Politique de confidentialité</a>.</p>
        </div>

        <div class="card">
            <h2>7. Propriété intellectuelle</h2>
            <p>La plateforme <?=SITE_NAME?>, son code source, son design, son logo et sa marque sont la propriété exclusive de <strong>NTIC Solution</strong>. Le restaurateur conserve la propriété des contenus qu'il publie (photos, descriptions).</p>
        </div>

        <div class="card">
            <h2>8. Limitation de responsabilité</h2>
            <p>NTIC Solution s'engage à fournir le service avec diligence mais ne peut être tenue responsable des interruptions de service indépendantes de sa volonté (coupure internet, panne serveur, force majeure).</p>
            <p>La responsabilité de NTIC Solution est limitée au montant de l'abonnement payé par le restaurateur au cours des 12 derniers mois.</p>
        </div>

        <div class="card">
            <h2>9. Résiliation</h2>
            <p>Le restaurateur peut supprimer son compte à tout moment en contactant le support. NTIC Solution se réserve le droit de suspendre ou supprimer un compte en cas de violation des présentes conditions.</p>
        </div>

        <div class="card">
            <h2>10. Contact</h2>
            <p>Pour toute question concernant ces conditions :</p>
            <p>
                📞 <?=CONTACT_TEL?><br>
                💬 WhatsApp : <?=CONTACT_WHATSAPP?><br>
                📧 <?=CONTACT_EMAIL?><br>
                📍 NTIC Solution, <?=CONTACT_VILLE?>, <?=CONTACT_PAYS?>
            </p>
        </div>
    </div>

    <div class="back"><a href="index.php"><i class="fa-solid fa-arrow-left"></i> Retour à l'accueil</a></div>

    <footer>
        <p>© 2026 <strong>NTIC Solution</strong> - <?=CONTACT_VILLE?>, <?=CONTACT_PAYS?> 🇧🇫 | <a href="index.php"><?=SITE_NAME?></a></p>
    </footer>
</body>
</html>