<?php
require_once 'config.php';

// Verificar se há uma sessão ativa
if (isLoggedIn()) {
    // Registrar o logout no log de auditoria
    logActivity('logout', 'Usuário realizou logout');
    
    // Obter ID do usuário antes de destruir a sessão
    $userId = $_SESSION['user_id'] ?? null;
    
    // Atualizar último acesso
    if ($userId) {
        try {
            $db = getDBConnection();
            $stmt = $db->prepare("UPDATE usuarios SET ultimo_acesso = NOW() WHERE id = ?");
            $stmt->execute([$userId]);
        } catch (Exception $e) {
            // Registrar erro mas continuar com logout
            error_log("Erro ao atualizar último acesso durante logout: " . $e->getMessage());
        }
    }
}

// Destruir todas as variáveis de sessão
$_SESSION = array();

// Se desejar destruir a sessão completamente, apague também o cookie de sessão
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destruir a sessão
session_destroy();

// Limpar qualquer cookie de "lembrar-me" se existir
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}

// Redirecionar para a página de login com mensagem de sucesso
header("Location: login.php?logout=success");
exit();
?>