<?php
require_once __DIR__ . '/includes/config.php';

$db = getDB();
$stmt = $db->query("SELECT r.*, (SELECT COUNT(*) FROM plats WHERE restaurant_id=r.id) as nb_plats, (SELECT COUNT(*) FROM commandes WHERE restaurant_id=r.id) as nb_cmd FROM restaurants r WHERE r.statut='actif' ORDER BY r.nb_scans DESC, r.date_inscription DESC LIMIT 20");
$restaurants = $stmt->fetchAll();

$nbRestos = $db->query("SELECT COUNT(*) FROM restaurants")->fetchColumn();
$nbPlats = $db->query("SELECT COUNT(*) FROM plats")->fetchColumn();
$nbScans = $db->query("SELECT COALESCE(SUM(nb_scans),0) FROM restaurants")->fetchColumn();
$nbCommandes = $db->query("SELECT COUNT(*) FROM commandes")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#FF6B35">
    <title>Menu QR - Digitalisez votre restaurant | NTIC Solution</title>
    <meta name="description" content="Menu QR par NTIC Solution. Créez votre carte restaurant numérique en 2 minutes. QR code, photos, commandes. Fabriqué en Afrique.">
    <meta property="og:title" content="Menu QR - Carte restaurant numérique">
    <meta property="og:description" content="Digitalisez votre restaurant avec un QR code. Par NTIC Solution.">
    <meta property="og:image" content="<?=LOGO_URL?>">
    <meta property="og:url" content="<?=SITE_URL?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root{--o:#FF6B35;--d:#0F172A;--w:#fff;--g:#E2E8F0;--t:#1E293B;--s:#64748B;--grad:linear-gradient(135deg,#FF6B35,#E85D2C)}
        *{margin:0;padding:0;box-sizing:border-box}
        html{scroll-behavior:smooth}
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;color:var(--t);background:#fff;overflow-x:hidden;line-height:1.6}
        
        nav{position:fixed;top:0;left:0;right:0;z-index:1000;padding:12px 24px;transition:.4s}
        nav.scrolled{background:rgba(255,255,255,.97);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);box-shadow:0 4px 30px rgba(0,0,0,.06);padding:8px 24px}
        .nav-inner{max-width:1200px;margin:0 auto;display:flex;justify-content:space-between;align-items:center}
        .logo-link{display:flex;align-items:center;gap:10px;text-decoration:none}
        .logo-link img{width:40px;height:40px;border-radius:10px;transition:.3s}
        .logo-link span{font-weight:800;font-size:20px;color:#fff;transition:.3s}
        nav.scrolled .logo-link span{color:var(--o)}
        .nav-btns{display:flex;gap:10px;align-items:center}
        .btn{padding:11px 22px;border-radius:30px;font-weight:600;text-decoration:none;font-size:14px;display:inline-flex;align-items:center;gap:6px;transition:.3s;cursor:pointer;border:none;white-space:nowrap}
        .btn-out{background:rgba(255,255,255,.15);color:#fff;border:2px solid rgba(255,255,255,.3)}
        .btn-out:hover{background:rgba(255,255,255,.25);transform:translateY(-2px)}
        nav.scrolled .btn-out{background:transparent;color:var(--o);border-color:var(--o)}
        .btn-o{background:var(--w);color:var(--o);box-shadow:0 4px 20px rgba(0,0,0,.15)}
        .btn-o:hover{transform:translateY(-2px);box-shadow:0 8px 30px rgba(0,0,0,.2)}
        nav.scrolled .btn-o{background:var(--o);color:#fff;box-shadow:0 4px 20px rgba(255,107,53,.3)}
        
        .hero{background:var(--grad);color:#fff;padding:160px 24px 100px;text-align:center;position:relative;overflow:hidden}
        .hero::before{content:'';position:absolute;top:-150px;right:-150px;width:500px;height:500px;background:rgba(255,255,255,.03);border-radius:50%;animation:heroFloat 10s ease-in-out infinite}
        .hero::after{content:'';position:absolute;bottom:-100px;left:-100px;width:400px;height:400px;background:rgba(255,255,255,.03);border-radius:50%;animation:heroFloat 8s ease-in-out infinite reverse}
        @keyframes heroFloat{0%,100%{transform:translate(0,0) scale(1)}50%{transform:translate(40px,-40px) scale(1.1)}}
        .hero-content{position:relative;z-index:2;max-width:700px;margin:0 auto}
        .hero-logo{width:90px;border-radius:20px;margin-bottom:24px;animation:fadeInDown .8s ease}
        @keyframes fadeInDown{from{opacity:0;transform:translateY(-30px)}to{opacity:1;transform:translateY(0)}}
        .hero h1{font-size:clamp(30px,7vw,56px);font-weight:800;line-height:1.1;margin-bottom:20px;animation:fadeInUp .8s ease .1s both}
        .hero h1 .highlight{background:rgba(255,255,255,.2);padding:2px 12px;border-radius:8px;display:inline-block}
        @keyframes fadeInUp{from{opacity:0;transform:translateY(30px)}to{opacity:1;transform:translateY(0)}}
        .hero p{font-size:17px;opacity:.9;max-width:500px;margin:0 auto 32px;animation:fadeInUp .8s ease .2s both}
        .hero-btns{display:flex;gap:12px;justify-content:center;flex-wrap:wrap;animation:fadeInUp .8s ease .3s both}
        .hero-btns .btn{font-size:16px;padding:15px 32px}
        .hero-btns .btn-primary{background:#fff;color:var(--o);font-weight:700}
        .hero-btns .btn-secondary{background:rgba(255,255,255,.1);color:#fff;border:2px solid rgba(255,255,255,.3)}
        .hero-stats{display:flex;gap:30px;justify-content:center;margin-top:50px;flex-wrap:wrap;animation:fadeInUp .8s ease .4s both}
        .hero-stat{text-align:center;background:rgba(255,255,255,.1);padding:16px 24px;border-radius:16px;backdrop-filter:blur(10px);min-width:100px}
        .hero-stat .nb{font-size:28px;font-weight:800}.hero-stat .lb{font-size:10px;opacity:.8;text-transform:uppercase;letter-spacing:1px}
        
        section{padding:80px 24px;max-width:1200px;margin:0 auto}
        .section-label{text-align:center;font-size:12px;text-transform:uppercase;letter-spacing:2px;color:var(--o);font-weight:700;margin-bottom:8px}
        h2{text-align:center;font-size:clamp(24px,5vw,38px);font-weight:800;margin-bottom:12px}
        .section-sub{text-align:center;color:var(--s);max-width:550px;margin:0 auto 50px;font-size:15px}
        
        .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:20px}
        .card{background:#fff;border-radius:20px;padding:32px 24px;text-align:center;box-shadow:0 2px 20px rgba(0,0,0,.04);transition:.4s;border:1px solid #F1F5F9;position:relative;overflow:hidden}
        .card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:var(--grad);transform:scaleX(0);transition:.4s}
        .card:hover{transform:translateY(-8px);box-shadow:0 20px 50px rgba(0,0,0,.08)}.card:hover::before{transform:scaleX(1)}
        .card-icon{width:64px;height:64px;border-radius:18px;display:flex;align-items:center;justify-content:center;font-size:28px;margin:0 auto 16px;transition:.3s}
        .card-icon.orange{background:#FFF7ED;color:var(--o)}.card-icon.green{background:#ECFDF5;color:#10B981}.card-icon.blue{background:#EFF6FF;color:#3B82F6}
        .card:hover .card-icon{transform:scale(1.1) rotate(5deg)}
        .card h3{font-size:17px;margin-bottom:6px}.card p{color:var(--s);font-size:13px;line-height:1.6}
        
        .steps{display:flex;justify-content:center;gap:16px;flex-wrap:wrap;align-items:center}
        .step{background:#fff;border:2px solid #F1F5F9;border-radius:20px;padding:28px 20px;text-align:center;width:220px;transition:.3s;position:relative}
        .step:hover{border-color:var(--o);transform:translateY(-4px);box-shadow:0 12px 30px rgba(255,107,53,.1)}
        .step-num{background:var(--grad);color:#fff;width:44px;height:44px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-weight:800;font-size:18px;box-shadow:0 4px 15px rgba(255,107,53,.3)}
        .step h3{font-size:15px;margin-bottom:4px}.step p{color:var(--s);font-size:12px}
        .step-arrow{font-size:22px;color:#CBD5E1}
        
        .pricing{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px;max-width:700px;margin:0 auto}
        .price-box{background:#fff;border:2px solid #F1F5F9;border-radius:24px;padding:36px 28px;text-align:center;transition:.3s;position:relative}
        .price-box:hover{transform:translateY(-4px);box-shadow:0 20px 50px rgba(0,0,0,.06)}
        .price-box.popular{border-color:var(--o);box-shadow:0 8px 40px rgba(255,107,53,.1);transform:scale(1.03)}
        .popular-badge{position:absolute;top:-15px;left:50%;transform:translateX(-50%);background:var(--grad);color:#fff;padding:7px 20px;border-radius:25px;font-size:11px;font-weight:700;box-shadow:0 4px 15px rgba(255,107,53,.3)}
        .price-box h3{font-size:20px;margin-bottom:8px}
        .price{font-size:38px;font-weight:800;color:var(--o)}.price small{font-size:14px;color:var(--s);font-weight:400}
        .price-box ul{list-style:none;margin:24px 0;font-size:14px;text-align:left}
        .price-box ul li{padding:7px 0;display:flex;align-items:center;gap:8px}
        .price-box ul li i.fa-check{color:#10B981}.price-box ul li i.fa-xmark{color:#EF4444}
        .price-box .btn{display:flex;justify-content:center}
        
        .resto-section{background:#F8FAFC;padding:60px 0;overflow:hidden}
        .resto-scroll{position:relative;padding:20px 0}
        .resto-track{display:flex;gap:20px;animation:scrollLeft 50s linear infinite;width:max-content}
        .resto-track:hover{animation-play-state:paused}
        @keyframes scrollLeft{0%{transform:translateX(0)}100%{transform:translateX(-50%)}}
        .resto-card{background:#fff;border-radius:18px;padding:20px;min-width:200px;box-shadow:0 2px 15px rgba(0,0,0,.04);text-align:center;flex-shrink:0;transition:.3s}
        .resto-card:hover{transform:translateY(-4px);box-shadow:0 10px 30px rgba(0,0,0,.08)}
        .resto-card .r-icon{font-size:36px;margin-bottom:8px}
.resto-card .r-logo{width:56px;height:56px;border-radius:50%;object-fit:cover;margin-bottom:8px;border:3px solid var(--o);background:#FFF7ED}
.resto-card .r-placeholder{width:56px;height:56px;border-radius:50%;margin:0 auto 8px;background:#FFF7ED;display:flex;align-items:center;justify-content:center;font-size:24px;color:var(--o)}
        .resto-card h4{font-size:14px;margin-bottom:4px}.resto-card .ville{font-size:11px;color:var(--s);margin-bottom:8px}
        .resto-card a{display:inline-flex;align-items:center;gap:4px;padding:6px 16px;background:var(--o);color:#fff;border-radius:20px;text-decoration:none;font-size:11px;font-weight:600;transition:.2s}
        .resto-card a:hover{background:#e55a2b}
        
        .cta{background:var(--grad);color:#fff;text-align:center;padding:80px 24px;position:relative;overflow:hidden}
        .cta::before{content:'';position:absolute;top:-60px;right:-60px;width:300px;height:300px;background:rgba(255,255,255,.04);border-radius:50%}
        .cta h2{color:#fff}.cta p{opacity:.9;margin-bottom:30px}
        .cta .btn{background:#fff;color:var(--o);font-size:17px;padding:16px 36px}
        
        footer{background:var(--d);color:#94A3B8;padding:50px 24px 30px}
        .footer-grid{max-width:1200px;margin:0 auto;display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:30px;margin-bottom:30px}
        .footer-col h4{color:#fff;font-size:14px;margin-bottom:16px}
        .footer-col a{display:block;color:#94A3B8;text-decoration:none;font-size:13px;padding:4px 0;transition:.2s}
        .footer-col a:hover{color:var(--o)}
        .footer-bottom{text-align:center;border-top:1px solid #1E293B;padding-top:20px;font-size:12px}
        .footer-bottom img{width:24px;vertical-align:middle;margin-right:6px;border-radius:6px}
        .footer-bottom strong{color:#fff}
        
        @media(max-width:768px){
            nav{padding:10px 16px}nav.scrolled{padding:8px 16px}
            .logo-link span{font-size:16px}.logo-link img{width:32px;height:32px}
            .btn{padding:9px 16px;font-size:12px}
            .hero{padding:130px 16px 70px}.hero h1{font-size:28px}.hero p{font-size:15px}
            .hero-stats{gap:12px}.hero-stat{padding:12px 16px;min-width:80px}.hero-stat .nb{font-size:22px}
            .step-arrow{display:none}.steps{flex-direction:column;align-items:center}
            .price-box.popular{transform:none}
            .resto-card{min-width:160px}
            section{padding:50px 16px}
            .footer-grid{grid-template-columns:1fr 1fr}
        }
        @media(max-width:400px){
            .hero-btns{flex-direction:column;align-items:center}
            .nav-btns .btn-out{display:none}
        }
    </style>
</head>
<body>
    <nav id="navbar">
        <div class="nav-inner">
            <a href="/" class="logo-link">
                <img src="<?=LOGO_URL?>" alt="Menu QR">
                <span>Menu QR</span>
            </a>
            <div class="nav-btns">
                <a href="connexion.php" class="btn btn-out"><i class="fa-solid fa-right-to-bracket"></i> Connexion</a>
                <a href="inscription.php" class="btn btn-o"><i class="fa-solid fa-rocket"></i> Créer mon menu</a>
            </div>
        </div>
    </nav>

    <header class="hero">
        <div class="hero-content">
            <img src="<?=LOGO_URL?>" alt="Menu QR" class="hero-logo">
            <h1>Votre menu restaurant<br>en un <span class="highlight">QR code</span></h1>
            <p>Créez votre carte numérique en 2 minutes. Vos clients scannent, voient les photos et commandent. Zéro application. Fabriqué en Afrique.</p>
            <div class="hero-btns">
                <a href="inscription.php" class="btn btn-primary"><i class="fa-solid fa-rocket"></i> Créer mon menu gratuitement</a>
                <a href="#solutions" class="btn btn-secondary"><i class="fa-solid fa-circle-chevron-down"></i> Découvrir</a>
            </div>
        </div>
        <div class="hero-stats">
            <div class="hero-stat"><div class="nb"><?=$nbRestos?></div><div class="lb">Restaurants</div></div>
            <div class="hero-stat"><div class="nb"><?=$nbPlats?></div><div class="lb">Plats en ligne</div></div>
            <div class="hero-stat"><div class="nb"><?=number_format($nbScans)?></div><div class="lb">Scans QR</div></div>
            <div class="hero-stat"><div class="nb"><?=$nbCommandes?></div><div class="lb">Commandes</div></div>
        </div>
    </header>

   <?php if(!empty($restaurants)):?>
    <div class="resto-section">
       <div class="section-label"><i class="fa-solid fa-store"></i> En activité</div>
        <h2>Ils utilisent déjà Menu QR</h2>
        <p class="section-sub">Rejoignez les restaurateurs qui ont digitalisé leur carte</p>
        <div class="resto-scroll"><div class="resto-track">
            <?php foreach(array_merge($restaurants,$restaurants) as $r):?>
                <div class="resto-card">
                    <?php if($r['logo']):?>
                        <img src="public/uploads/logos/<?=e($r['logo'])?>" alt="<?=e($r['nom_restaurant'])?>" class="r-logo" loading="lazy">
                    <?php else:?>
                        <div class="r-placeholder"><i class="fa-solid fa-store"></i></div>
                    <?php endif;?>
                    <h4><?=e($r['nom_restaurant'])?></h4>
                    <div class="ville"><i class="fa-solid fa-location-dot"></i> <?=e($r['ville']??'Afrique')?></div>
                   
                   
                </div>
            <?php endforeach;?>
        </div></div>
    </div>
    <?php endif;?>

    <section id="solutions">
        <div class="section-label">Le constat</div>
        <h2>Fini les menus papier</h2>
        <p class="section-sub">Coûteux, salissants, obsolètes. Passez au numérique.</p>
        <div class="grid">
            <div class="card">
                <div class="card-icon orange"><i class="fa-solid fa-money-bill-wave"></i></div>
                <h3>Économique</h3>
                <p>5 000 à 15 000 FCFA par impression. Menu QR commence à 0 FCFA.</p>
            </div>
            <div class="card">
                <div class="card-icon green"><i class="fa-solid fa-shield-halved"></i></div>
                <h3>Résistant</h3>
                <p>Un QR code plastifié dure des mois. Fini les taches et déchirures.</p>
            </div>
            <div class="card">
                <div class="card-icon blue"><i class="fa-solid fa-bolt"></i></div>
                <h3>Instantané</h3>
                <p>Un prix change ? Modifiez-le en 30 secondes. Visible immédiatement.</p>
            </div>
        </div>
    </section>

    <section style="background:#F8FAFC;max-width:100%;padding:80px 24px">
        <div style="max-width:1200px;margin:0 auto">
            <div class="section-label">Simple</div>
            <h2>Comment ça marche ?</h2>
            <p class="section-sub">En 3 étapes, votre menu est en ligne.</p>
            <div class="steps">
                <div class="step"><div class="step-num">1</div><h3><i class="fa-solid fa-pen-to-square"></i> Créez</h3><p>Ajoutez vos plats, prix et photos</p></div>
                <div class="step-arrow"><i class="fa-solid fa-chevron-right"></i></div>
                <div class="step"><div class="step-num">2</div><h3><i class="fa-solid fa-print"></i> Imprimez</h3><p>QR code au cybercafé (200 FCFA)</p></div>
                <div class="step-arrow"><i class="fa-solid fa-chevron-right"></i></div>
                <div class="step"><div class="step-num">3</div><h3><i class="fa-solid fa-mobile-screen"></i> Scannez</h3><p>Vos clients voient le menu et commandent</p></div>
            </div>
        </div>
    </section>

    <section>
        <div class="section-label">Tarifs</div>
        <h2>Une formule pour chaque besoin</h2>
        <p class="section-sub">Gratuit pour commencer. Premium pour tout débloquer.</p>
        <div class="pricing">
            <div class="price-box">
                <h3><i class="fa-solid fa-mug-hot"></i> Gratuit</h3>
                <div class="price">0 FCFA</div>
                <ul>
                    <li><i class="fa-solid fa-check"></i> 10 plats</li>
                    <li><i class="fa-solid fa-check"></i> QR code simple</li>
                    <li><i class="fa-solid fa-xmark"></i> Photos</li>
                    <li><i class="fa-solid fa-xmark"></i> Commandes</li>
                </ul>
                <a href="inscription.php" class="btn btn-out">Démarrer gratuitement</a>
            </div>
            <div class="price-box popular">
                <div class="popular-badge"><i class="fa-solid fa-crown"></i> POPULAIRE</div>
                <h3><i class="fa-solid fa-crown"></i> Premium</h3>
                <div class="price">1 150 FCFA<small>/mois</small></div>
                <p style="font-size:10px;color:#64748B;margin-top:-8px">~2,00€ • Conversion automatique</p>
                <ul>
                    <li><i class="fa-solid fa-check"></i> 200 plats</li>
                    <li><i class="fa-solid fa-check"></i> Photos</li>
                    <li><i class="fa-solid fa-check"></i> Commandes en ligne</li>
                    <li><i class="fa-solid fa-check"></i> QR code par table</li>
                </ul>
                <a href="inscription.php" class="btn btn-o">Choisir Premium</a>
            </div>
        </div>
    </section>

    <section style="background:#F8FAFC;max-width:100%;padding:80px 24px">
        <div style="max-width:1200px;margin:0 auto">
            <div class="section-label">Avantages</div>
            <h2>Pourquoi choisir Menu QR ?</h2>
            <p class="section-sub">Conçu et développé en Afrique par NTIC Solution.</p>
            <div class="grid">
                <div class="card"><div class="card-icon orange"><i class="fa-solid fa-mobile-screen-button"></i></div><h3>Zéro application</h3><p>Fonctionne sur tous les téléphones, même les petits smartphones.</p></div>
                <div class="card"><div class="card-icon green"><i class="fa-solid fa-clock"></i></div><h3>Temps réel</h3><p>Modifications instantanées. Commandes notifiées immédiatement.</p></div>
                <div class="card"><div class="card-icon blue"><i class="fa-solid fa-credit-card"></i></div><h3>Paiement international</h3><p>Mobile Money, carte bancaire, PayPal. Paiement accepté dans le monde entier via Chariow.</p></div>
                <div class="card"><div class="card-icon orange"><i class="fa-solid fa-globe"></i></div><h3>Made in Africa</h3><p>Développé en Afrique. Support réactif. Adapté à votre pays.</p></div>
            </div>
        </div>
    </section>

    <div class="cta">
        <div class="section-label" style="color:rgba(255,255,255,.8)">🚀 Lancez-vous</div>
        <h2>Prêt à digitaliser votre restaurant ?</h2>
        <p>Rejoignez les restaurateurs africains qui ont déjà adopté Menu QR.</p>
        <a href="inscription.php" class="btn btn-o"><i class="fa-solid fa-rocket"></i> Je crée mon menu gratuitement</a>
    </div>

    <footer>
        <div class="footer-grid">
            <div class="footer-col">
                <h4><img src="<?=LOGO_URL?>" style="width:20px;vertical-align:middle;border-radius:4px;margin-right:6px">Menu QR</h4>
                <p style="font-size:12px;margin-top:8px">Plateforme de carte restaurant numérique par QR code. Développée par NTIC Solution à Banfora, Burkina Faso.</p>
            </div>
            <div class="footer-col">
                <h4>Navigation</h4>
                <a href="index.php"><i class="fa-solid fa-house"></i> Accueil</a>
                <a href="inscription.php"><i class="fa-solid fa-user-plus"></i> Inscription</a>
                <a href="connexion.php"><i class="fa-solid fa-right-to-bracket"></i> Connexion</a>
                <a href="a-propos.php"><i class="fa-solid fa-circle-info"></i> À propos</a>
            </div>
            <div class="footer-col">
                <h4>Légal</h4>
                <a href="conditions.php">Conditions d'utilisation</a>
                <a href="confidentialite.php">Politique de confidentialité</a>
            </div>
            <div class="footer-col">
                <h4>Contact</h4>
                <p style="font-size:12px">📞 +226 05 58 58 68</p>
                <p style="font-size:12px">💬 WhatsApp : +226 69 69 69 24</p>
                <p style="font-size:12px">📧 nticsolution.bf@gmail.com</p>
                <p style="font-size:12px">📍 Banfora, Burkina Faso 🇧🇫</p>
            </div>
        </div>
        <div class="footer-bottom">
            <img src="<?=LOGO_URL?>" alt="Menu QR"> © 2026 <strong>NTIC Solution</strong> - Tous droits réservés. Made in Africa 🌍
        </div>
    </footer>

    <script>
        const navbar = document.getElementById('navbar');
        window.addEventListener('scroll', function() {
            navbar.classList.toggle('scrolled', window.scrollY > 50);
        });
    </script>
</body>
</html>