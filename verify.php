<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

$status = '';
$message = '';
$verified = false;

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    try {
        // Buscar usuário com este token
        $stmt = $pdo->prepare("SELECT id, nome, email, tipo_usuario FROM usuarios 
                              WHERE verification_token = ? AND email_verified = 0");
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Atualizar status de verificação
            $stmt = $pdo->prepare("UPDATE usuarios 
                                  SET email_verified = 1, 
                                      verification_token = NULL,
                                      ativo = 1,
                                      data_verificacao = NOW()
                                  WHERE id = ?");
            $stmt->execute([$user['id']]);
            
            $verified = true;
            $status = 'success';
            $message = 'Email verificado com sucesso! Sua conta está ativa.';
            
            // Se for empresa (imobiliária/condomínio), criar registro na tabela empresas
            if ($user['tipo_usuario'] === 'empresa') {
                $stmt = $pdo->prepare("INSERT IGNORE INTO empresas (user_id, status) VALUES (?, 'pendente')");
                $stmt->execute([$user['id']]);
            }
            
            // Log de atividade
            $stmt = $pdo->prepare("INSERT INTO atividades_log (user_id, acao, detalhes) 
                                  VALUES (?, 'email_verificado', 'Email verificado com sucesso')");
            $stmt->execute([$user['id']]);
            
        } else {
            // Verificar se já foi verificado
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE verification_token = ? AND email_verified = 1");
            $stmt->execute([$token]);
            
            if ($stmt->fetch()) {
                $status = 'warning';
                $message = 'Este email já foi verificado anteriormente.';
            } else {
                $status = 'error';
                $message = 'Token de verificação inválido ou expirado.';
            }
        }
        
    } catch (PDOException $e) {
        error_log("Erro na verificação de email: " . $e->getMessage());
        $status = 'error';
        $message = 'Ocorreu um erro ao verificar seu email. Por favor, contate o suporte.';
    }
} else {
    $status = 'error';
    $message = 'Link de verificação inválido.';
}

// Função para reenviar email de verificação
if (isset($_POST['resend_email']) && isset($_POST['email'])) {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    
    try {
        $stmt = $pdo->prepare("SELECT id, nome, verification_token FROM usuarios 
                              WHERE email = ? AND email_verified = 0");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Gerar novo token se necessário
            if (!$user['verification_token']) {
                $newToken = bin2hex(random_bytes(32));
                $stmt = $pdo->prepare("UPDATE usuarios SET verification_token = ? WHERE id = ?");
                $stmt->execute([$newToken, $user['id']]);
                $user['verification_token'] = $newToken;
            }
            
            // Reenviar email
            $verifyLink = "https://arbitrivm.com.br/verify.php?token=" . $user['verification_token'];
            $subject = "Verificação de Email - Arbitrivm";
            $emailMessage = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background-color: #0066cc; color: white; padding: 20px; text-align: center; }
                    .content { padding: 30px; background-color: #f9f9f9; }
                    .button { background-color: #0066cc; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 20px 0; }
                    .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>Arbitrivm</h1>
                    </div>
                    <div class='content'>
                        <h2>Olá " . htmlspecialchars($user['nome']) . ",</h2>
                        <p>Bem-vindo à Arbitrivm! Para ativar sua conta, precisamos verificar seu email.</p>
                        <p>Por favor, clique no botão abaixo para confirmar seu endereço de email:</p>
                        <p style='text-align: center;'>
                            <a href='" . $verifyLink . "' class='button'>Verificar Email</a>
                        </p>
                        <p><small>Se o botão não funcionar, copie e cole este link no seu navegador:<br>" . $verifyLink . "</small></p>
                    </div>
                    <div class='footer'>
                        <p>© 2025 Arbitrivm - Plataforma de Resolução de Disputas</p>
                    </div>
                </div>
            </body>
            </html>
            ";
            
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= 'From: Arbitrivm <noreply@arbitrivm.com.br>' . "\r\n";
            
            if (mail($email, $subject, $emailMessage, $headers)) {
                $status = 'success';
                $message = 'Email de verificação reenviado com sucesso!';
            } else {
                $status = 'error';
                $message = 'Erro ao enviar email. Por favor, tente novamente.';
            }
        } else {
            $status = 'warning';
            $message = 'Email não encontrado ou já verificado.';
        }
    } catch (PDOException $e) {
        error_log("Erro ao reenviar email: " . $e->getMessage());
        $status = 'error';
        $message = 'Ocorreu um erro. Por favor, tente novamente.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificação de Email - Arbitrivm</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f0f2f5;
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .verify-container {
            max-width: 500px;
            margin: 0 auto;
        }
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .card-header {
            background-color: #0066cc;
            color: white;
            border-radius: 12px 12px 0 0 !important;
            padding: 30px;
            text-align: center;
        }
        .logo {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .status-icon {
            font-size: 4rem;
            margin-bottom: 20px;
        }
        .status-success { color: #28a745; }
        .status-warning { color: #ffc107; }
        .status-error { color: #dc3545; }
        .btn-primary {
            background-color: #0066cc;
            border: none;
            padding: 12px 30px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            background-color: #0052a3;
            transform: translateY(-1px);
        }
        .form-control {
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #ddd;
        }
        .resend-form {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }
        .progress {
            height: 30px;
            border-radius: 15px;
            margin-top: 20px;
        }
        .progress-bar {
            border-radius: 15px;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="verify-container">
            <div class="card">
                <div class="card-header">
                    <div class="logo">
                        <i class="fas fa-balance-scale"></i> Arbitrivm
                    </div>
                    <h4 class="mb-0">Verificação de Email</h4>
                </div>
                <div class="card-body p-4 text-center">
                    <?php if ($status === 'success'): ?>
                        <div class="status-icon status-success">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h5 class="mb-3 text-success">Verificação Concluída!</h5>
                        <p class="text-muted mb-4"><?php echo htmlspecialchars($message); ?></p>
                        
                        <?php if ($verified): ?>
                            <div class="progress mb-4">
                                <div class="progress-bar bg-success progress-bar-striped progress-bar-animated" 
                                     role="progressbar" 
                                     style="width: 100%">
                                    Conta Ativada
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <a href="login.php" class="btn btn-primary">
                            <i class="fas fa-sign-in-alt me-2"></i>Fazer Login
                        </a>
                        
                    <?php elseif ($status === 'warning'): ?>
                        <div class="status-icon status-warning">
                            <i class="fas fa-exclamation-circle"></i>
                        </div>
                        <h5 class="mb-3 text-warning">Atenção</h5>
                        <p class="text-muted mb-4"><?php echo htmlspecialchars($message); ?></p>
                        <a href="login.php" class="btn btn-primary">
                            <i class="fas fa-sign-in-alt me-2"></i>Acessar Conta
                        </a>
                        
                    <?php else: ?>
                        <div class="status-icon status-error">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <h5 class="mb-3 text-danger">Erro na Verificação</h5>
                        <p class="text-muted mb-4"><?php echo htmlspecialchars($message); ?></p>
                        
                        <div class="resend-form">
                            <p class="mb-3">Precisa de um novo email de verificação?</p>
                            <form method="POST" action="">
                                <div class="mb-3">
                                    <input type="email" 
                                           class="form-control" 
                                           name="email" 
                                           placeholder="Digite seu email"
                                           required>
                                </div>
                                <button type="submit" name="resend_email" class="btn btn-primary btn-sm">
                                    <i class="fas fa-paper-plane me-2"></i>Reenviar Email
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="text-center mt-4">
                <p class="text-muted mb-2">
                    <i class="fas fa-info-circle me-2"></i>
                    Precisa de ajuda? 
                </p>
                <a href="suporte.php" class="text-decoration-none">
                    Contate nosso suporte
                </a>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <?php if ($verified): ?>
    <script>
        // Redirecionar automaticamente após 5 segundos
        setTimeout(function() {
            window.location.href = 'login.php';
        }, 5000);
    </script>
    <?php endif; ?>
</body>
</html>