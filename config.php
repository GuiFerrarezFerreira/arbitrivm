<?php
// config.php - Configurações da aplicação Arbitrivm

// Configurações do banco de dados
define('DB_HOST', 'localhost');
define('DB_NAME', 'arbitrivm_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Configurações da aplicação
define('APP_NAME', 'Arbitrivm');
define('APP_URL', 'http://localhost/arbitrivm');
define('APP_VERSION', '1.0.0');

// Configurações de segurança
define('SECRET_KEY', 'sua_chave_secreta_aqui_32_caracteres');
define('SESSION_LIFETIME', 3600); // 1 hora
define('BCRYPT_COST', 12);

// Configurações de email (ajustar conforme servidor SMTP)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'noreply@arbitrivm.com.br');
define('SMTP_PASS', 'senha_email');
define('SMTP_FROM_NAME', 'Arbitrivm');
define('SMTP_FROM_EMAIL', 'noreply@arbitrivm.com.br');

// Configurações de upload
define('UPLOAD_MAX_SIZE', 10 * 1024 * 1024); // 10MB
define('UPLOAD_PATH', __DIR__ . '/uploads/');
define('ALLOWED_EXTENSIONS', ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'mp4', 'avi']);

// Timezone
date_default_timezone_set('America/Sao_Paulo');

// Iniciar sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Função de conexão com o banco de dados
function getDBConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
        
    } catch (PDOException $e) {
        error_log("Erro de conexão com banco de dados: " . $e->getMessage());
        die("Erro ao conectar com o banco de dados. Por favor, tente novamente mais tarde.");
    }
}

// Funções auxiliares de segurança
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_type']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: " . APP_URL . "/arbitrivm/login.php");
        exit();
    }
}

function requireUserType($types) {
    requireLogin();
    if (!in_array($_SESSION['user_type'], (array)$types)) {
        header("HTTP/1.1 403 Forbidden");
        die("Acesso negado. Você não tem permissão para acessar esta página.");
    }
}

function getUserInfo($userId = null) {
    if ($userId === null && isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
    }
    
    if (!$userId) return null;
    
    $db = getDBConnection();
    $stmt = $db->prepare("
        SELECT u.*, tu.tipo as tipo_usuario 
        FROM usuarios u 
        JOIN tipos_usuario tu ON u.tipo_usuario_id = tu.id 
        WHERE u.id = ?
    ");
    $stmt->execute([$userId]);
    return $stmt->fetch();
}

function logActivity($action, $description = null, $disputaId = null) {
    if (!isLoggedIn()) return;
    
    $db = getDBConnection();
    $stmt = $db->prepare("
        INSERT INTO logs_auditoria (usuario_id, disputa_id, acao, descricao, ip_address, user_agent) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $_SESSION['user_id'],
        $disputaId,
        $action,
        $description,
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);
}

function createNotification($userId, $type, $title, $message = null, $link = null) {
    $db = getDBConnection();
    $stmt = $db->prepare("
        INSERT INTO notificacoes (usuario_id, tipo, titulo, mensagem, link) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    return $stmt->execute([$userId, $type, $title, $message, $link]);
}

function generateCaseCode() {
    $year = date('Y');
    $db = getDBConnection();
    
    // Buscar o último código do ano
    $stmt = $db->prepare("
        SELECT codigo_caso 
        FROM disputas 
        WHERE codigo_caso LIKE ? 
        ORDER BY id DESC 
        LIMIT 1
    ");
    $stmt->execute(["ARB-$year-%"]);
    $lastCase = $stmt->fetch();
    
    if ($lastCase) {
        $lastNumber = intval(substr($lastCase['codigo_caso'], -5));
        $newNumber = $lastNumber + 1;
    } else {
        $newNumber = 1;
    }
    
    return sprintf("ARB-%s-%05d", $year, $newNumber);
}

// Função para enviar emails (implementação básica)
function sendEmail($to, $subject, $body, $isHtml = true) {
    // Implementação com PHPMailer seria ideal
    // Por enquanto, usando mail() básico
    $headers = "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM_EMAIL . ">\r\n";
    $headers .= "Reply-To: " . SMTP_FROM_EMAIL . "\r\n";
    
    if ($isHtml) {
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    }
    
    return mail($to, $subject, $body, $headers);
}

// Função para formatar valores monetários
function formatMoney($value) {
    return 'R$ ' . number_format($value, 2, ',', '.');
}

// Função para formatar datas
function formatDate($date, $format = 'd/m/Y') {
    if (!$date) return '';
    return date($format, strtotime($date));
}

// Função para validar CPF
function validateCPF($cpf) {
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    
    if (strlen($cpf) != 11) return false;
    
    if (preg_match('/(\d)\1{10}/', $cpf)) return false;
    
    for ($t = 9; $t < 11; $t++) {
        for ($d = 0, $c = 0; $c < $t; $c++) {
            $d += $cpf[$c] * (($t + 1) - $c);
        }
        $d = ((10 * $d) % 11) % 10;
        if ($cpf[$c] != $d) return false;
    }
    
    return true;
}

// Função para validar CNPJ
function validateCNPJ($cnpj) {
    $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
    
    if (strlen($cnpj) != 14) return false;
    
    if (preg_match('/(\d)\1{13}/', $cnpj)) return false;
    
    $t = strlen($cnpj) - 2;
    $d = substr($cnpj, $t);
    $d1 = $d[0];
    $d2 = $d[1];
    $calc = 0;
    $seq = array(6,5,4,3,2,9,8,7,6,5,4,3,2);
    
    for ($i = 0; $i < $t; $i++) {
        $calc += $cnpj[$i] * $seq[$i+1];
    }
    
    $calc = $calc % 11;
    $calc = $calc < 2 ? 0 : 11 - $calc;
    
    if ($d1 != $calc) return false;
    
    $calc = 0;
    $t++;
    
    for ($i = 0; $i < $t; $i++) {
        $calc += $cnpj[$i] * $seq[$i];
    }
    
    $calc = $calc % 11;
    $calc = $calc < 2 ? 0 : 11 - $calc;
    
    return $d2 == $calc;
}

// Criar diretório de uploads se não existir
if (!file_exists(UPLOAD_PATH)) {
    mkdir(UPLOAD_PATH, 0777, true);
}
?>