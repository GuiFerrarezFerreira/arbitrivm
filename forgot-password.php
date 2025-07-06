<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Por favor, insira um email válido.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id, nome, tipo_usuario FROM usuarios WHERE email = ? AND ativo = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Gerar token único
                $token = bin2hex(random_bytes(32));
                $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Salvar token no banco
                $stmt = $pdo->prepare("INSERT INTO password_resets (user_id, token, expiry) VALUES (?, ?, ?)
                                      ON DUPLICATE KEY UPDATE token = ?, expiry = ?");
                $stmt->execute([$user['id'], $token, $expiry, $token, $expiry]);
                
                // Enviar email
                $resetLink = "https://arbitrivm.com.br/reset-password.php?token=" . $token;
                $subject = "Recuperação de Senha - Arbitrivm";
                $message = "
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
                            <p>Recebemos uma solicitação para redefinir sua senha na plataforma Arbitrivm.</p>
                            <p>Para criar uma nova senha, clique no botão abaixo:</p>
                            <p style='text-align: center;'>
                                <a href='" . $resetLink . "' class='button'>Redefinir Senha</a>
                            </p>
                            <p><small>Este link é válido por 1 hora. Se você não solicitou a redefinição de senha, ignore este email.</small></p>
                            <p><small>Link direto: " . $resetLink . "</small></p>
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
                
                if (mail($email, $subject, $message, $headers)) {
                    $success = 'Email de recuperação enviado! Verifique sua caixa de entrada.';
                } else {
                    $error = 'Erro ao enviar email. Por favor, tente novamente.';
                }
            } else {
                // Por segurança, não informamos se o email existe ou não
                $success = 'Se este email estiver cadastrado, você receberá as instruções de recuperação.';
            }
            
        } catch (PDOException $e) {
            error_log("Erro na recuperação de senha: " . $e->getMessage());
            $error = 'Ocorreu um erro. Por favor, tente novamente.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Senha - Arbitrivm</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f0f2f5;
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .forgot-container {
            max-width: 450px;
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
        .btn-primary {
            background-color: #0066cc;
            border: none;
            padding: 12px;
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
        .form-control:focus {
            border-color: #0066cc;
            box-shadow: 0 0 0 0.2rem rgba(0, 102, 204, 0.25);
        }
        .alert {
            border-radius: 8px;
            border: none;
        }
        .back-link {
            color: #0066cc;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        .back-link:hover {
            color: #0052a3;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="forgot-container">
            <div class="card">
                <div class="card-header">
                    <div class="logo">
                        <i class="fas fa-balance-scale"></i> Arbitrivm
                    </div>
                    <h4 class="mb-0">Recuperar Senha</h4>
                </div>
                <div class="card-body p-4">
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <?php echo htmlspecialchars($error); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i>
                            <?php echo htmlspecialchars($success); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <p class="text-muted mb-4">
                        Digite seu email cadastrado e enviaremos instruções para redefinir sua senha.
                    </p>
                    
                    <form method="POST" action="">
                        <div class="mb-4">
                            <label for="email" class="form-label">
                                <i class="fas fa-envelope me-2"></i>Email
                            </label>
                            <input type="email" 
                                   class="form-control" 
                                   id="email" 
                                   name="email" 
                                   placeholder="seu@email.com"
                                   required 
                                   autofocus>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100 mb-3">
                            <i class="fas fa-paper-plane me-2"></i>Enviar Email de Recuperação
                        </button>
                    </form>
                    
                    <div class="text-center">
                        <a href="login.php" class="back-link">
                            <i class="fas fa-arrow-left me-2"></i>Voltar ao Login
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="text-center mt-4 text-muted">
                <small>
                    <i class="fas fa-shield-alt me-2"></i>
                    Sua segurança é nossa prioridade. Nunca compartilhe sua senha.
                </small>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>