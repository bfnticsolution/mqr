<?php
// ============================================
// MENU QR - Configuration
// NTIC Solution - Burkina Faso / International
// Version sécurisée - Mai 2026
// ============================================

// Fuseau horaire
date_default_timezone_set('Africa/Ouagadougou');

// Affichage des erreurs (désactivé en production)
ini_set('display_errors', 0);
error_reporting(0);

// Session sécurisée
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Régénérer l'ID de session toutes les 30 minutes
if (!isset($_SESSION['_regenerated'])) {
    session_regenerate_id(true);
    $_SESSION['_regenerated'] = time();
} elseif (time() - $_SESSION['_regenerated'] > 1800) {
    session_regenerate_id(true);
    $_SESSION['_regenerated'] = time();
}

// ============================================
// BASE DE DONNÉES
// ============================================
define('DB_HOST', 'sql300.infinityfree.com');
define('DB_NAME', 'if0_41931124_menu_qr');
define('DB_USER', 'if0_41931124');
define('DB_PASS', 'R2UaCfPqfx');

// ============================================
// SITE
// ============================================
define('SITE_URL', 'https://menuqr.page.gd');
define('SITE_NAME', 'Menu QR');
define('LOGO_URL', 'https://i.ibb.co/27fpVXL9/Add-Text-05-17-04-07-27.png');

// ============================================
// CONTACT NTIC SOLUTION
// ============================================
define('CONTACT_TEL', '+22605585868');
define('CONTACT_WHATSAPP', '+22669696924');
define('CONTACT_EMAIL', 'nticsolution.bf@gmail.com');
define('CONTACT_VILLE', 'Banfora');
define('CONTACT_PAYS', 'Burkina Faso');

// ============================================
// PAIEMENT - CHARIOW (principal)
// ============================================
define('CHARIOW_API_KEY', 'sk_lhqo9gjq_864e5b5df70e322edb7ed043f71042fd');
define('CHARIOW_PRODUCT_ID', 'prd_e2hxknfl');
define('CHARIOW_CHECKOUT_URL', 'https://yszndtjg.mychariow.shop/prd_e2hxknfl/checkout');
define('CHARIOW_WEBHOOK_SECRET', 'menuqr_webhook_2025');

// ============================================
// PAIEMENT - YENGAPAY (backup)
// ============================================
define('YENGAPAY_API_KEY', 'beEYxGEnUxBzU5Gh9QcfUDqg9XV1Hp3k');
define('YENGAPAY_ORG_ID', '10946155');
define('YENGAPAY_PROJECT_ID', '67821');
define('YENGAPAY_WEBHOOK_SECRET', '5154f5b0-75ec-4732-b1dc-eaf4e4ba54b9');
define('YENGAPAY_API_URL', 'https://api.yengapay.com/api/v1');

// ============================================
// ABONNEMENT
// ============================================
define('ABONNEMENT_PRIX_FCFA', 1150);
define('ABONNEMENT_PLATS_MAX_FREE', 10);
define('ABONNEMENT_PLATS_MAX_PREMIUM', 200);
define('ABONNEMENT_DUREE_JOURS', 30);

// ============================================
// DEVISE
// ============================================
define('DEVISE', 'XOF');
define('DEVISE_SYMBOLE', 'FCFA');

// ============================================
// SÉCURITÉ
// ============================================
define('CSRF_TOKEN_LENGTH', 32);
define('RATE_LIMIT_MAX', 10);        // Requêtes max
define('RATE_LIMIT_WINDOW', 60);     // Par minute
define('UPLOAD_MAX_SIZE', 2 * 1024 * 1024); // 2 Mo
define('ALLOWED_EXTENSIONS', ['jpg','jpeg','png','webp']);

// ============================================
// FONCTIONS UTILITAIRES
// ============================================
function getDB() {
    static $db = null;
    if ($db === null) {
        $db = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+00:00'"
            ]
        );
    }
    return $db;
}

function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function formatPrix($prix) {
    return number_format($prix, 0, ',', ' ') . ' ' . DEVISE_SYMBOLE;
}

function logoImg($w = 120) {
    return '<img src="' . LOGO_URL . '" alt="' . SITE_NAME . '" style="width:' . $w . 'px;height:auto">';
}

// ============================================
// SÉCURITÉ - CSRF
// ============================================
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . generateCsrfToken() . '">';
}

// ============================================
// SÉCURITÉ - Rate Limiting
// ============================================
function checkRateLimit($key) {
    $file = sys_get_temp_dir() . '/ratelimit_' . md5($key);
    $now = time();
    
    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true);
        if ($now - $data['start'] > RATE_LIMIT_WINDOW) {
            $data = ['start' => $now, 'count' => 1];
        } elseif ($data['count'] >= RATE_LIMIT_MAX) {
            return false;
        } else {
            $data['count']++;
        }
    } else {
        $data = ['start' => $now, 'count' => 1];
    }
    
    file_put_contents($file, json_encode($data));
    return true;
}

// ============================================
// SÉCURITÉ - Validation upload
// ============================================
function validateUpload($file) {
    if ($file['error'] !== UPLOAD_ERR_OK) return false;
    if ($file['size'] > UPLOAD_MAX_SIZE) return false;
    
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXTENSIONS)) return false;
    
    // Vérifier le type MIME réel
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($mime, $allowedMimes)) return false;
    
    return true;
}

// ============================================
// FONCTION PAIEMENT YENGAPAY (backup)
// ============================================
function yengaPayCreatePayment($montant, $telephone, $description, $reference, $returnUrl) {
    if (!str_starts_with($telephone, '+')) {
        $telephone = '+' . $telephone;
    }
    
    $data = [
        'projectId' => YENGAPAY_PROJECT_ID,
        'orgId' => YENGAPAY_ORG_ID,
        'amount' => $montant,
        'currency' => DEVISE,
        'phone' => $telephone,
        'description' => $description,
        'reference' => $reference,
        'returnUrl' => $returnUrl,
        'cancelUrl' => $returnUrl . '&cancel=1'
    ];
    
    $endpoints = ['/payment-intent', '/direct-payment/init', '/paylink'];
    
    foreach ($endpoints as $ep) {
        $ch = curl_init(YENGAPAY_API_URL . $ep);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . YENGAPAY_API_KEY
            ]
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 || $httpCode === 201) {
            $result = json_decode($response, true);
            if ($result) return $result;
        }
    }
    return null;
}