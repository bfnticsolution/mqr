<?php
require_once __DIR__ . '/includes/config.php';

if (isset($_SESSION['restaurant_id'])) { header('Location: dashboard.php'); exit; }

$step = $_GET['step'] ?? 'telephone';
$error = '';
$success = '';

// ÉTAPE 1 : Vérifier le téléphone
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_phone'])) {
    $indicatif = preg_replace('/[^0-9]/', '', $_POST['indicatif'] ?? '226');
    $tel = preg_replace('/[^0-9]/', '', $_POST['telephone'] ?? '');
    if (strlen($tel) >= 8) $tel = $indicatif . $tel;
    
    if (strlen($tel) < 10 || !preg_match('/^[0-9]{10,15}$/', $tel)) {
        $error = 'Numéro invalide.';
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT id, nom_restaurant, question_secrete FROM restaurants WHERE telephone = ?");
        $stmt->execute([$tel]);
        $resto = $stmt->fetch();
        
        if (!$resto) {
            $error = 'Aucun compte avec ce numéro.';
        } else {
            $_SESSION['reset_id'] = $resto['id'];
            $_SESSION['reset_nom'] = $resto['nom_restaurant'];
            $_SESSION['reset_question'] = $resto['question_secrete'];
            header('Location: mot-de-passe-oublie.php?step=question');
            exit;
        }
    }
}

// ÉTAPE 2 : Vérifier la réponse secrète
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_answer'])) {
    $reponse = trim(strtolower($_POST['reponse'] ?? ''));
    $id = $_SESSION['reset_id'] ?? null;
    
    if (!$id) { header('Location: mot-de-passe-oublie.php'); exit; }
    
    $db = getDB();
    $stmt = $db->prepare("SELECT reponse_secrete FROM restaurants WHERE id = ?");
    $stmt->execute([$id]);
    $resto = $stmt->fetch();
    
    if (strtolower($resto['reponse_secrete']) === $reponse) {
        $token = bin2hex(random_bytes(32));
        $expire = date('Y-m-d H:i:s', strtotime('+15 minutes'));
        
        $db->prepare("UPDATE restaurants SET reset_token=?, reset_token_expire=? WHERE id=?")
           ->execute([$token, $expire, $id]);
        
        $_SESSION['reset_token'] = $token;
        header('Location: mot-de-passe-oublie.php?step=new_password');
        exit;
    } else {
        $error = 'Réponse incorrecte.';
    }
}

// ÉTAPE 3 : Nouveau mot de passe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_password'])) {
    $token = $_SESSION['reset_token'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirmation = $_POST['confirmation'] ?? '';
    
    if (strlen($password) < 6) {
        $error = '6 caractères minimum.';
    } elseif ($password !== $confirmation) {
        $error = 'Les mots de passe ne correspondent pas.';
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT id, nom_restaurant FROM restaurants WHERE reset_token=? AND reset_token_expire > NOW()");
        $stmt->execute([$token]);
        $resto = $stmt->fetch();
        
        if (!$resto) {
            $error = 'Lien expiré. Refaites une demande.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $db->prepare("UPDATE restaurants SET mot_de_passe=?, reset_token=NULL, reset_token_expire=NULL WHERE id=?")
               ->execute([$hash, $resto['id']]);
            
            unset($_SESSION['reset_id'], $_SESSION['reset_nom'], $_SESSION['reset_question'], $_SESSION['reset_token']);
            
            $success = 'Mot de passe modifié !';
            $step = 'success';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mot de passe oublié - <?=SITE_NAME?></title>
    <style>
        :root{--o:#FF6B35}
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:Arial,sans-serif;background:#f5f5f5;display:flex;justify-content:center;align-items:center;min-height:100vh;padding:20px}
        .box{background:#fff;padding:35px;border-radius:16px;box-shadow:0 4px 20px rgba(0,0,0,.08);width:420px;max-width:100%}
        .box h1{text-align:center;color:var(--o);margin-bottom:8px;font-size:22px}
        .box .sub{text-align:center;color:#888;margin-bottom:20px;font-size:13px}
        .form-group{margin-bottom:14px}
        label{display:block;font-weight:600;margin-bottom:4px;font-size:13px;color:#555}
        input,select{width:100%;padding:12px;border:2px solid #ddd;border-radius:8px;font-size:15px}
        input:focus,select:focus{outline:none;border-color:var(--o)}
        .phone-box{display:flex}
        .phone-box select{width:auto;padding:12px 8px;border:2px solid #ddd;border-right:none;border-radius:8px 0 0 8px;font-weight:bold;background:#eee;font-size:14px}
        .phone-box input{border-radius:0 8px 8px 0}
        .btn{width:100%;padding:14px;background:var(--o);color:#fff;border:none;border-radius:8px;font-size:16px;font-weight:bold;cursor:pointer}
        .btn:hover{background:#e55a2b}
        .error{background:#FEE;color:#c00;padding:12px;border-radius:8px;margin-bottom:15px;font-size:13px;text-align:center}
        .success{background:#DCFCE7;color:#166534;padding:12px;border-radius:8px;margin-bottom:15px;font-size:13px;text-align:center}
        .success a{color:#166534;font-weight:bold}
        .question-box{background:#FFF7ED;padding:16px;border-radius:10px;margin-bottom:16px;border:2px solid #FED7AA}
        .question-box .q{font-weight:600;color:var(--o);margin-bottom:10px;font-size:14px}
        .steps{display:flex;justify-content:center;gap:20px;margin-bottom:20px;font-size:11px}
        .steps span{color:#ccc}.steps span.active{color:var(--o);font-weight:bold}.steps span.done{color:#22C55E}
        .link{text-align:center;margin-top:15px;font-size:13px}
        .link a{color:var(--o);text-decoration:none}
    </style>
</head>
<body>
    <div class="box">
        <h1>🔑 Mot de passe oublié</h1>

        <?php if ($step === 'success'): ?>
            <div class="success">✅ <?=$success?><br><a href="connexion.php">Se connecter</a></div>

        <?php elseif ($step === 'question'): ?>
            <div class="steps">
                <span class="done">1. Téléphone ✓</span>
                <span class="active">2. Question</span>
                <span>3. Nouveau mdp</span>
            </div>
            <p class="sub">Répondez à votre question de sécurité</p>
            <?php if ($error): ?><div class="error"><?=$error?></div><?php endif; ?>
            <div class="question-box">
                <div class="q">🔐 <?=e($_SESSION['reset_question'] ?? '')?></div>
                <form method="POST">
                    <input type="text" name="reponse" placeholder="Votre réponse" required autofocus>
                    <button type="submit" name="check_answer" class="btn" style="margin-top:10px">✅ Vérifier</button>
                </form>
            </div>

        <?php elseif ($step === 'new_password'): ?>
            <div class="steps">
                <span class="done">1. Téléphone ✓</span>
                <span class="done">2. Question ✓</span>
                <span class="active">3. Nouveau mdp</span>
            </div>
            <p class="sub">Choisissez un nouveau mot de passe</p>
            <?php if ($error): ?><div class="error"><?=$error?></div><?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label>Nouveau mot de passe</label>
                    <input type="password" name="password" placeholder="Min 6 caractères" minlength="6" required autofocus>
                </div>
                <div class="form-group">
                    <label>Confirmer</label>
                    <input type="password" name="confirmation" placeholder="Répétez le mot de passe" required>
                </div>
                <button type="submit" name="new_password" class="btn">💾 Enregistrer</button>
            </form>

        <?php else: ?>
            <div class="steps">
                <span class="active">1. Téléphone</span>
                <span>2. Question</span>
                <span>3. Nouveau mdp</span>
            </div>
            <p class="sub">Entrez votre numéro de téléphone</p>
            <?php if ($error): ?><div class="error"><?=$error?></div><?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label>Numéro de téléphone</label>
                    <div class="phone-box">
                        <select name="indicatif">
                            <option value="226">+226 🇧🇫</option>
                            <option value="225">+225 🇨🇮</option>
                            <option value="223">+223 🇲🇱</option>
                            <option value="228">+228 🇹🇬</option>
                            <option value="229">+229 🇧🇯</option>
                            <option value="221">+221 🇸🇳</option>
                            <option value="33">+33 🇫🇷</option>
                            <option value="1">+1 🇺🇸</option>
                            <option value="44">+44 🇬🇧</option>
                            <option value="other">Autre</option>
                        </select>
                        <input type="tel" name="telephone" placeholder="70 12 34 56" required autofocus>
                    </div>
                </div>
                <button type="submit" name="check_phone" class="btn">📩 Continuer</button>
            </form>
        <?php endif; ?>

        <p class="link"><a href="connexion.php">← Retour connexion</a></p>
    </div>
</body>
</html>