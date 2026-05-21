<?php
require_once __DIR__ . '/includes/config.php';
if (isset($_SESSION['restaurant_id'])) { header('Location: dashboard.php'); exit; }
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $indicatif = preg_replace('/[^0-9]/', '', $_POST['indicatif'] ?? '226');
    $telephone = preg_replace('/[^0-9]/', '', $_POST['telephone'] ?? '');
    if (strlen($telephone) >= 8) $telephone = $indicatif . $telephone;
    $password = $_POST['password'] ?? '';
    
    if (empty($telephone) || empty($password)) {
        $error = '<i class="fa-solid fa-triangle-exclamation"></i> Remplissez tous les champs.';
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM restaurants WHERE telephone = ?");
        $stmt->execute([$telephone]);
        $restaurant = $stmt->fetch();
        if ($restaurant && password_verify($password, $restaurant['mot_de_passe'])) {
            if ($restaurant['statut'] === 'suspendu') { $error = '<i class="fa-solid fa-ban"></i> Compte suspendu.'; }
            else { $_SESSION['restaurant_id'] = $restaurant['id']; $_SESSION['restaurant_nom'] = $restaurant['nom_restaurant']; header('Location: dashboard.php'); exit; }
        } else { $error = '<i class="fa-solid fa-triangle-exclamation"></i> Téléphone ou mot de passe incorrect.'; }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Menu QR | NTIC Solution</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root{--o:#FF6B35}*{margin:0;padding:0;box-sizing:border-box}
        body{font-family:-apple-system,BlinkMacSystemFont,sans-serif;background:#F8F9FA;display:flex;justify-content:center;align-items:center;min-height:100vh;padding:20px}
        .box{background:#fff;padding:35px;border-radius:20px;box-shadow:0 8px 40px rgba(0,0,0,.08);width:420px;max-width:100%}
        .logo{text-align:center;margin-bottom:16px}.logo img{width:60px;border-radius:12px}
        .box h1{text-align:center;color:var(--o);margin-bottom:20px;font-size:24px}
        .form-group{margin-bottom:14px}
        label{display:block;font-weight:600;margin-bottom:4px;font-size:12px;color:#555}
        input,select{width:100%;padding:12px 14px;border:2px solid #E2E8F0;border-radius:12px;font-size:14px;font-family:inherit}
        input:focus,select:focus{outline:none;border-color:var(--o)}
        .phone-box{display:flex}
        .phone-box select{width:auto;padding:12px 8px;border:2px solid #E2E8F0;border-right:none;border-radius:12px 0 0 12px;font-weight:700;background:#F1F5F9;font-size:13px}
        .phone-box input{border-radius:0 12px 12px 0}
        .btn{width:100%;padding:15px;background:var(--o);color:#fff;border:none;border-radius:14px;font-size:17px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px}
        .btn-demo{background:#10B981;margin-top:8px}
        .error{background:#FEE2E2;color:#991B1B;padding:12px;border-radius:12px;margin-bottom:16px;font-size:13px;display:flex;align-items:center;gap:8px}
        .link{text-align:center;margin-top:18px;font-size:13px}.link a{color:var(--o);text-decoration:none;font-weight:600}
        .forgot{text-align:right;margin-bottom:14px}.forgot a{font-size:12px;color:var(--o);text-decoration:none}
        .divider{text-align:center;margin:12px 0;color:#999;font-size:11px;position:relative}
        .divider::before,.divider::after{content:'';position:absolute;top:50%;width:35%;height:1px;background:#E2E8F0}
        .divider::before{left:0}.divider::after{right:0}
    </style>
</head>
<body>
    <div class="box">
        <div class="logo"><img src="<?=LOGO_URL?>" alt="Menu QR"></div>
        <h1>Connexion</h1>
        <?php if ($error): ?><div class="error"><?= $error ?></div><?php endif; ?>
        <form method="POST">
            <div class="form-group"><label>📱 Numéro de téléphone</label>
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
                    <input type="tel" name="telephone" placeholder="70 12 34 56" required autofocus>
                </div>
            </div>
            <div class="form-group"><label>🔒 Mot de passe</label><input type="password" name="password" placeholder="Votre mot de passe" required></div>
            <div class="forgot"><a href="mot-de-passe-oublie.php"><i class="fa-solid fa-key"></i> Mot de passe oublié ?</a></div>
            <button type="submit" class="btn"><i class="fa-solid fa-right-to-bracket"></i> Se connecter</button>
        </form>
        <div class="divider">ou</div>
        <form method="POST">
            <input type="hidden" name="indicatif" value="226">
            <input type="hidden" name="telephone" value="00000000">
            <input type="hidden" name="password" value="demo123">
            <button type="submit" class="btn btn-demo"><i class="fa-solid fa-eye"></i> Voir la démo</button>
        </form>
        <p class="link">Pas de compte ? <a href="inscription.php">Inscrivez-vous gratuitement</a><br><a href="index.php">← Retour</a></p>
    </div>
</body>
</html>