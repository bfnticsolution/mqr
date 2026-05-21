<?php require_once __DIR__ . '/includes/config.php'; ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Politique de confidentialité - <?=SITE_NAME?> | NTIC Solution</title>
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
        <h1><i class="fa-solid fa-shield-halved"></i> Politique de confidentialité</h1>
        <p>Dernière mise à jour : 19 Mai 2026</p>
    </div>

    <div class="container">
        <div class="card">
            <h2>1. Introduction</h2>
            <p>La présente politique de confidentialité explique comment <strong>NTIC Solution</strong> collecte, utilise et protège les données personnelles dans le cadre du service <strong><?=SITE_NAME?></strong>.</p>
            <p>Nous nous engageons à respecter la confidentialité de vos données conformément aux lois en vigueur.</p>
        </div>

        <div class="card">
            <h2>2. Données collectées</h2>
            <p><strong>Données du restaurateur :</strong></p>
            <ul>
                <li>Numéro de téléphone (obligatoire - identifiant de connexion)</li>
                <li>Nom du restaurant (obligatoire)</li>
                <li>Adresse du restaurant (optionnelle)</li>
                <li>Horaires d'ouverture (optionnels)</li>
                <li>Numéro WhatsApp (optionnel)</li>
                <li>Photo de profil du restaurant (optionnelle)</li>
                <li>Question et réponse de sécurité (obligatoires - récupération de compte)</li>
            </ul>
            <p><strong>Données des clients finaux :</strong></p>
            <ul>
                <li>Nom (optionnel)</li>
                <li>Numéro de téléphone (optionnel)</li>
                <li>Note de commande (optionnelle)</li>
            </ul>
            <p><strong>Aucune donnée bancaire</strong> n'est stockée sur nos serveurs. Les paiements sont traités par Chariow.</p>
        </div>

        <div class="card">
            <h2>3. Finalité de la collecte</h2>
            <p>Les données sont collectées pour :</p>
            <ul>
                <li>Créer et gérer le compte restaurateur</li>
                <li>Permettre la connexion sécurisée</li>
                <li>Afficher le menu du restaurant aux clients</li>
                <li>Transmettre les commandes au restaurateur</li>
                <li>Permettre la récupération du mot de passe</li>
                <li>Améliorer le service et produire des statistiques anonymes</li>
            </ul>
        </div>

        <div class="card">
            <h2>4. Base légale</h2>
            <p>Le traitement des données repose sur :</p>
            <ul>
                <li><strong>L'exécution du contrat</strong> : les données sont nécessaires pour fournir le service <?=SITE_NAME?></li>
                <li><strong>Le consentement</strong> : pour les données optionnelles</li>
                <li><strong>L'intérêt légitime</strong> : pour l'amélioration du service</li>
            </ul>
        </div>

        <div class="card">
            <h2>5. Conservation des données</h2>
            <p>Les données sont conservées pendant toute la durée d'utilisation du service. En cas de suppression du compte, les données sont définitivement effacées dans un délai de 30 jours.</p>
            <p>Les commandes sont conservées à des fins comptables pendant la durée légale applicable.</p>
        </div>

        <div class="card">
            <h2>6. Partage des données</h2>
            <p>Les données ne sont <strong>jamais vendues, louées ou cédées</strong> à des tiers.</p>
            <p>Elles peuvent être partagées uniquement dans les cas suivants :</p>
            <ul>
                <li>Avec le restaurateur (commandes de ses clients)</li>
                <li>Avec les autorités compétentes sur demande légale</li>
                <li>Avec nos sous-traitants techniques (hébergement) dans le cadre strict du service</li>
            </ul>
        </div>

        <div class="card">
            <h2>7. Sécurité</h2>
            <p>Nous mettons en œuvre des mesures techniques et organisationnelles pour protéger vos données :</p>
            <ul>
                <li>Mots de passe cryptés (bcrypt)</li>
                <li>Connexion sécurisée HTTPS (SSL/TLS)</li>
                <li>Accès restreint aux données</li>
                <li>Sessions sécurisées</li>
            </ul>
        </div>

        <div class="card">
            <h2>8. Cookies</h2>
            <p><?=SITE_NAME?> utilise des cookies strictement nécessaires au fonctionnement :</p>
            <ul>
                <li>Cookie de session (connexion)</li>
                <li>Cookie de première visite (affichage du splash screen)</li>
            </ul>
            <p>Aucun cookie publicitaire ou de tracking n'est utilisé.</p>
        </div>

        <div class="card">
            <h2>9. Vos droits</h2>
            <p>Conformément à la réglementation, vous disposez des droits suivants :</p>
            <ul>
                <li><strong>Droit d'accès</strong> : consulter vos données</li>
                <li><strong>Droit de rectification</strong> : modifier vos données</li>
                <li><strong>Droit de suppression</strong> : supprimer votre compte</li>
                <li><strong>Droit d'opposition</strong> : vous opposer au traitement</li>
            </ul>
            <p>Pour exercer ces droits, contactez-nous à <strong><?=CONTACT_EMAIL?></strong></p>
        </div>

        <div class="card">
            <h2>10. Modifications</h2>
            <p>Cette politique peut être modifiée à tout moment. Les modifications seront publiées sur cette page et, si elles sont substantielles, notifiées aux utilisateurs.</p>
        </div>

        <div class="card">
            <h2>11. Contact</h2>
            <p>Pour toute question relative à la confidentialité :</p>
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