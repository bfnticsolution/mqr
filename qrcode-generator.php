<?php
/**
 * Générateur QR Codes - Menu QR
 * NTIC Solution
 */
require_once __DIR__ . '/includes/config.php';
define('FPDF_FONTPATH', __DIR__ . '/lib/fpdf/font/');
require_once __DIR__ . '/lib/fpdf/fpdf.php';

if (!isset($_SESSION['restaurant_id'])) {
    header('Location: connexion.php');
    exit;
}

$db = getDB();
$stmt = $db->prepare("SELECT * FROM restaurants WHERE id = ?");
$stmt->execute([$_SESSION['restaurant_id']]);
$restaurant = $stmt->fetch();

if (!$restaurant) {
    header('Location: connexion.php');
    exit;
}

$action = $_GET['action'] ?? '';

// ============================================
// DOWNLOAD PDF
// ============================================
if ($action === 'download-pdf') {
    $tables = $_GET['tables'] ?? '';
    
    if (empty($tables)) {
        $stmt = $db->prepare("SELECT numero_table FROM tables_qr WHERE restaurant_id = ? ORDER BY numero_table");
        $stmt->execute([$restaurant['id']]);
        $tablesQR = $stmt->fetchAll();
        $tableList = array_column($tablesQR, 'numero_table');
    } else {
        $tableList = explode(',', $tables);
    }
    
    $tableList = array_values(array_filter(array_map('trim', $tableList)));
    
    if (empty($tableList)) {
        header('Location: dashboard.php?tab=qrcode');
        exit;
    }
    
    $dir = __DIR__ . '/public/uploads/qrcodes/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    
    $pdf = new FPDF('P', 'mm', 'A4');
    $pdf->SetAutoPageBreak(false);
    
    $cardW = 97;
    $cardH = 68;
    $marginX = 8;
    $marginY = 8;
    $cols = 2;
    $rows = 4;
    
    $pages = array_chunk($tableList, $cols * $rows);
    
    foreach ($pages as $pageNum => $tablesPage) {
        $pdf->AddPage();
        
        $pdf->SetFont('Helvetica', 'B', 12);
        $pdf->SetTextColor(255, 107, 53);
        $titre = "QR CODES - " . $restaurant['nom_restaurant'];
        $pdf->Cell(0, 6, iconv('UTF-8', 'windows-1252', $titre), 0, 1, 'L');
        $pdf->SetDrawColor(255, 107, 53);
        $pdf->Line(8, $pdf->GetY(), 202, $pdf->GetY());
        $pdf->Ln(4);
        
        foreach ($tablesPage as $i => $table) {
            $col = $i % $cols;
            $row = floor($i / $cols);
            
            $x = $marginX + $col * $cardW;
            $y = $marginY + 16 + $row * $cardH;
            
            $pdf->SetFillColor(255, 250, 247);
            $pdf->Rect($x, $y, $cardW, $cardH, 'DF');
            $pdf->SetDrawColor(255, 107, 53);
            $pdf->Rect($x, $y, $cardW, $cardH, 'D');
            
            $pdf->SetFont('Helvetica', 'B', 7);
            $pdf->SetTextColor(30, 30, 30);
            $nom = mb_strlen($restaurant['nom_restaurant']) > 28 ? mb_substr($restaurant['nom_restaurant'], 0, 26) . '..' : $restaurant['nom_restaurant'];
            $pdf->SetXY($x, $y + 1);
            $pdf->Cell($cardW, 4, iconv('UTF-8', 'windows-1252', $nom), 0, 1, 'C');
            
            $pdf->SetFillColor(255, 107, 53);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFont('Helvetica', 'B', 12);
            $badgeW = 38;
            $badgeX = $x + ($cardW - $badgeW) / 2;
            $pdf->Rect($badgeX, $y + 5.5, $badgeW, 6.5, 'F');
            $pdf->SetXY($badgeX, $y + 5.5);
            $pdf->Cell($badgeW, 6.5, 'TABLE ' . $table, 0, 1, 'C');
            
            $menuUrl = SITE_URL . '/' . $restaurant['slug'] . '?auto=1&table=' . $table;
            $qrFile = $dir . 'tmp-' . $restaurant['id'] . '-' . $table . '.png';
            $qrImageData = @file_get_contents('https://api.qrserver.com/v1/create-qr-code/?size=300x300&color=FF6B35&bgcolor=FFFFFF&data=' . urlencode($menuUrl));
            
            if ($qrImageData) {
                file_put_contents($qrFile, $qrImageData);
                $qrSize = 30;
                $qrX = $x + ($cardW - $qrSize) / 2;
                $qrY = $y + 13;
                $pdf->Image($qrFile, $qrX, $qrY, $qrSize, $qrSize, 'PNG');
                @unlink($qrFile);
            }
            
            $pdf->SetFont('Helvetica', '', 6);
            $pdf->SetTextColor(150, 150, 150);
            $pdf->SetXY($x, $y + $cardH - 8);
            $pdf->Cell($cardW, 3, 'Scannez pour voir le menu', 0, 1, 'C');
            
            $pdf->SetFont('Helvetica', '', 5);
            $pdf->SetTextColor(200, 200, 200);
            $pdf->SetXY($x, $y + $cardH - 4);
            $pdf->Cell($cardW, 3, 'Menu QR', 0, 1, 'C');
        }
        
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetTextColor(180, 180, 180);
        $pdf->SetXY(8, 290);
        $pdf->Cell(0, 4, 'Menu QR - Page ' . ($pageNum + 1), 0, 1, 'C');
    }
    
    $pdf->Output('D', 'QR-Menu-' . $restaurant['slug'] . '.pdf');
    exit;
}

// ============================================
// DOWNLOAD SINGLE PNG
// ============================================
if ($action === 'download-single' && isset($_GET['table'])) {
    $table = $_GET['table'];
    $menuUrl = SITE_URL . '/' . $restaurant['slug'] . '?auto=1&table=' . $table;
    header('Content-Type: image/png');
    header('Content-Disposition: attachment; filename="QR-Table-' . $table . '.png"');
    readfile('https://api.qrserver.com/v1/create-qr-code/?size=500x500&color=FF6B35&data=' . urlencode($menuUrl));
    exit;
}

// ============================================
// GENERATE ALL
// ============================================
if ($action === 'generate-all' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $nb = intval($_POST['nb_tables'] ?? 0);
    if ($nb > 0 && $nb <= 50) {
        $db->prepare("DELETE FROM tables_qr WHERE restaurant_id = ?")->execute([$restaurant['id']]);
        for ($i = 1; $i <= $nb; $i++) {
            $qr = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&color=FF6B35&data=' . urlencode(SITE_URL . '/' . $restaurant['slug'] . '?auto=1&table=' . $i);
            try { $db->prepare("INSERT INTO tables_qr (restaurant_id, numero_table, qr_code_path) VALUES (?,?,?)")->execute([$restaurant['id'], $i, $qr]); } catch (Exception $e) {}
        }
        header('Location: dashboard.php?tab=qrcode&generated=' . $nb);
        exit;
    }
}

header('Location: dashboard.php?tab=qrcode');
exit;