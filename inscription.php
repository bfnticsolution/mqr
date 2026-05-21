<?php
require_once __DIR__ . '/includes/config.php';

if (isset($_SESSION['restaurant_id'])) { header('Location: dashboard.php'); exit; }

$error = '';
$showWelcome = false;
$newSlug = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $indicatif = preg_replace('/[^0-9]/', '', $_POST['indicatif'] ?? '226');
    $telephone = preg_replace('/[^0-9]/', '', $_POST['telephone'] ?? '');
    if (strlen($telephone) >= 8) $telephone = $indicatif . $telephone;
    $nom = trim($_POST['nom'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmation = $_POST['confirmation'] ?? '';
    $question = trim($_POST['question_secrete'] ?? '');
    $reponse = trim($_POST['reponse_secrete'] ?? '');
    
    if (strlen($telephone) < 10 || !preg_match('/^[0-9]{10,15}$/', $telephone)) { $error = '<i class="fa-solid fa-triangle-exclamation"></i> Numéro invalide.'; }
    elseif (empty($nom)) { $error = '<i class="fa-solid fa-triangle-exclamation"></i> Nom obligatoire.'; }
    elseif (strlen($password) < 6) { $error = '<i class="fa-solid fa-triangle-exclamation"></i> 6 caractères minimum.'; }
    elseif ($password !== $confirmation) { $error = '<i class="fa-solid fa-triangle-exclamation"></i> Mots de passe différents.'; }
    elseif (empty($question)) { $error = '<i class="fa-solid fa-triangle-exclamation"></i> Choisissez une question.'; }
    elseif (empty($reponse)) { $error = '<i class="fa-solid fa-triangle-exclamation"></i> Répondez à la question.'; }
    else {
        $db = getDB();
        $stmt = $db->prepare("SELECT id FROM restaurants WHERE telephone = ?");
        $stmt->execute([$telephone]);
        if ($stmt->fetch()) {
            $error = '<i class="fa-solid fa-triangle-exclamation"></i> Ce numéro est déjà inscrit. <a href="connexion.php">Connectez-vous</a>.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $slug = strtolower(preg_replace('/[^a-zA-Z0-9]/', '-', $nom));
            $slug = trim($slug, '-') . '-' . rand(100, 999);
            
            $stmt = $db->prepare("INSERT INTO restaurants (telephone, mot_de_passe, nom_restaurant, slug, telephone_whatsapp, question_secrete, reponse_secrete, logo) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$telephone, $hash, $nom, $slug, $telephone, $question, $reponse, null]);
            
            $_SESSION['restaurant_id'] = $db->lastInsertId();
            $_SESSION['restaurant_nom'] = $nom;
            $newSlug = $slug;
            $showWelcome = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - Menu QR | NTIC Solution</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root{--o:#FF6B35;--bg:#F8F9FA}
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:-apple-system,BlinkMacSystemFont,sans-serif;background:var(--bg);display:flex;justify-content:center;align-items:center;min-height:100vh;padding:20px}
        .box{background:#fff;padding:35px;border-radius:20px;box-shadow:0 8px 40px rgba(0,0,0,.08);width:460px;max-width:100%}
        .logo{text-align:center;margin-bottom:16px}
        .logo img{width:60px;border-radius:12px}
        .box h1{text-align:center;color:var(--o);margin-bottom:4px;font-size:24px}
        .sub{text-align:center;color:#888;margin-bottom:24px;font-size:13px}
        .form-group{margin-bottom:14px}
        label{display:block;font-weight:600;margin-bottom:4px;font-size:12px;color:#555}
        input,select{width:100%;padding:12px 14px;border:2px solid #E2E8F0;border-radius:12px;font-size:14px;font-family:inherit}
        input:focus,select:focus{outline:none;border-color:var(--o)}
        .phone-box{display:flex}
        .phone-box select{width:auto;padding:12px 8px;border:2px solid #E2E8F0;border-right:none;border-radius:12px 0 0 12px;font-weight:700;background:#F1F5F9;font-size:13px}
        .phone-box input{border-radius:0 12px 12px 0}
        .btn{width:100%;padding:15px;background:var(--o);color:#fff;border:none;border-radius:14px;font-size:17px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px}
        .error{background:#FEE2E2;color:#991B1B;padding:12px;border-radius:12px;margin-bottom:16px;font-size:13px;display:flex;align-items:center;gap:8px}
        .error a{color:#991B1B;font-weight:700}
        .info-box{background:#EFF6FF;padding:14px;border-radius:12px;margin:16px 0;font-size:12px;color:#1E40AF;border:1px solid #BFDBFE}
        .link{text-align:center;margin-top:18px;font-size:13px}.link a{color:var(--o);text-decoration:none;font-weight:600}
        .welcome{text-align:center;padding:20px}
        .welcome .icon{font-size:60px;margin-bottom:10px}
        .welcome .slug{background:#F1F5F9;padding:12px;border-radius:10px;font-size:16px;font-weight:700;color:var(--o);margin:16px 0}
    </style>
</head>
<body>
    <div class="box">
        <div class="logo"><img src="<?=LOGO_URL?>" alt="Menu QR"></div>
        
        <?php if ($showWelcome): ?>
            <div class="welcome">
                <div class="icon">🎉</div>
                <h1 style="color:#10B981">Bienvenue sur Menu QR !</h1>
                <p style="color:#666;margin:10px 0">Votre compte est créé. Voici votre lien :</p>
                <div class="slug"><?=SITE_URL?>/<?=$newSlug?></div>
                <p style="font-size:12px;color:#666">Partagez ce lien ou générez votre QR code</p>
                <a href="dashboard.php" class="btn" style="margin-top:12px">Accéder au dashboard</a>
                <a href="dashboard.php?tab=qrcode" class="btn" style="background:transparent;color:var(--o);border:2px solid var(--o);margin-top:8px">Voir mon QR code</a>
            </div>
        <?php else: ?>
            <h1>Inscription</h1>
            <p class="sub">Créez votre menu restaurant en 2 minutes</p>
            
            <?php if ($error): ?><div class="error"><?= $error ?></div><?php endif; ?>
            
            <form method="POST">
                <div class="form-group"><label>🏪 Nom du restaurant *</label><input type="text" name="nom" placeholder="Ex: Maquis Le Gourmet" required autofocus></div>
                <div class="form-group"><label>📱 Numéro de téléphone *</label>
                    <div class="phone-box">
                        <select name="indicatif">
                            <option value="226">+226 🇧🇫</option>
                            <option value="225">+225 🇨🇮</option>
                            <option value="223">+223 🇲🇱</option>
                            <option value="228">+228 🇹🇬</option>
                            <option value="229">+229 🇧🇯</option>
                            <option value="221">+221 🇸🇳</option>
                            <option value="224">+224 🇬🇳</option>
                            <option value="227">+227 🇳🇪</option>
                            <option value="235">+235 🇹🇩</option>
                            <option value="237">+237 🇨🇲</option>
                            <option value="241">+241 🇬🇦</option>
                            <option value="242">+242 🇨🇬</option>
                            <option value="243">+243 🇨🇩</option>
                            <option value="261">+261 🇲🇬</option>
                            <option value="33">+33 🇫🇷</option>
                            <option value="32">+32 🇧🇪</option>
                            <option value="1">+1 🇺🇸</option>
                            <option value="44">+44 🇬🇧</option>
                            <option value="other">Autre</option>
                        </select>
                        <input type="tel" name="telephone" placeholder="70 12 34 56" required>
                    </div>
                </div>
                <div class="form-group"><label>🔒 Mot de passe *</label><input type="password" name="password" placeholder="Minimum 6 caractères" minlength="6" required></div>
                <div class="form-group"><label>🔒 Confirmer *</label><input type="password" name="confirmation" placeholder="Répétez" required></div>
                
                <div class="info-box"><i class="fa-solid fa-shield-halved"></i> Question pour réinitialiser votre mot de passe en cas d'oubli.</div>
                
                <div class="form-group"><label>❓ Question de sécurité *</label>
                    <select name="question_secrete" required><option value="">Choisissez...</option>
                        <option value="Quel est votre plat signature ?">Quel est votre plat signature ?</option>
                        <option value="Quel est votre plat préféré ?">Quel est votre plat préféré ?</option>
                        <option value="Dans quelle ville est votre restaurant ?">Dans quelle ville est votre restaurant ?</option>
                    </select>
                </div>
                <div class="form-group"><label>✍️ Votre réponse *</label><input type="text" name="reponse_secrete" placeholder="Mémorisez-la bien" required></div>
                
                <button type="submit" class="btn"><i class="fa-solid fa-rocket"></i> Créer mon compte gratuitement</button>
            </form>
        <?php endif; ?>
        
        <p class="link">Déjà inscrit ? <a href="connexion.php">Connectez-vous</a><br><a href="index.php">← Retour</a></p>
    </div>
</body>
</html>