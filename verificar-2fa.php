<?php
session_start();
require_once 'config/conexao.php';
require_once 'vendor/autoload.php';

// Verificar se o usuário passou pela primeira etapa do login
if (!isset($_SESSION['temp_usuario_id']) || !isset($_SESSION['aguardando_2fa'])) {
    header('Location: login.php');
    exit;
}

$erro = '';
$tentativas_restantes = 3;

// Verificar tentativas de login
if (!isset($_SESSION['tentativas_2fa'])) {
    $_SESSION['tentativas_2fa'] = 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo = $_POST['codigo_2fa'];
    $usuario_id = $_SESSION['temp_usuario_id'];
    
    // Buscar secret do usuário
    $sql = "SELECT two_fa_secret, nome, email, tipo_usuario FROM usuarios WHERE id = ?";
    $stmt = $conexao->prepare($sql);
    $stmt->execute([$usuario_id]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($usuario) {
        $ga = new \PHPGangsta\GoogleAuthenticator();
        
        // Verificar código (com tolerância de 2 períodos de 30 segundos)
        if ($ga->verifyCode($usuario['two_fa_secret'], $codigo, 2)) {
            // Código válido - completar login
            $_SESSION['usuario_id'] = $usuario_id;
            $_SESSION['usuario_nome'] = $usuario['nome'];
            $_SESSION['usuario_email'] = $usuario['email'];
            $_SESSION['usuario_tipo'] = $usuario['tipo_usuario'];
            
            // Limpar variáveis temporárias
            unset($_SESSION['temp_usuario_id']);
            unset($_SESSION['aguardando_2fa']);
            unset($_SESSION['tentativas_2fa']);
            
            // Registrar log de login bem-sucedido
            $sql = "INSERT INTO logs_auditoria (usuario_id, acao, detalhes, ip_address) 
                    VALUES (?, 'login_2fa_sucesso', 'Login com 2FA realizado com sucesso', ?)";
            $stmt = $conexao->prepare($sql);
            $stmt->execute([$usuario_id, $_SERVER['REMOTE_ADDR']]);
            
            // Redirecionar para dashboard
            header('Location: dashboard.php');
            exit;
            
        } else {
            // Código inválido
            $_SESSION['tentativas_2fa']++;
            $tentativas_restantes = 3 - $_SESSION['tentativas_2fa'];
            
            if ($_SESSION['tentativas_2fa'] >= 3) {
                // Muitas tentativas - cancelar login
                unset($_SESSION['temp_usuario_id']);
                unset($_SESSION['aguardando_2fa']);
                unset($_SESSION['tentativas_2fa']);
                
                // Registrar log de falha
                $sql = "INSERT INTO logs_auditoria (usuario_id, acao, detalhes, ip_address) 
                        VALUES (?, 'login_2fa_bloqueado', 'Muitas tentativas falhadas de 2FA', ?)";
                $stmt = $conexao->prepare($sql);
                $stmt->execute([$usuario_id, $_SERVER['REMOTE_ADDR']]);
                
                header('Location: login.php?erro=muitas_tentativas');
                exit;
            }
            
            $erro = "Código inválido. $tentativas_restantes tentativas restantes.";
        }
    }
}

// Cancelar login
if (isset($_GET['cancelar'])) {
    unset($_SESSION['temp_usuario_id']);
    unset($_SESSION['aguardando_2fa']);
    unset($_SESSION['tentativas_2fa']);
    header('Location: login.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificação de Dois Fatores - Arbitrivm</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #0066cc 0%, #004499 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .verification-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 40px;
            max-width: 400px;
            width: 100%;
        }
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo h1 {
            color: #0066cc;
            font-weight: bold;
        }
        .verification-icon {
            font-size: 4rem;
            color: #0066cc;
            text-align: center;
            margin-bottom: 20px;
        }
        .code-input {
            font-size: 2rem;
            text-align: center;
            letter-spacing: 10px;
            font-weight: bold;
        }
        .help-text {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
            font-size: 0.9rem;
        }
        .btn-verify {
            background: #0066cc;
            color: white;
            font-weight: bold;
            padding: 12px;
            border-radius: 8px;
            border: none;
            width: 100%;
            margin-top: 20px;
            transition: all 0.3s;
        }
        .btn-verify:hover {
            background: #0052a3;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,102,204,0.3);
        }
        .cancel-link {
            text-align: center;
            margin-top: 20px;
        }
        .timer {
            text-align: center;
            color: #6c757d;
            margin-top: 15px;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="verification-container">
        <div class="logo">
            <h1>Arbitrivm</h1>
            <p class="text-muted">Verificação de Segurança</p>
        </div>

        <div class="verification-icon">
            <i class="fas fa-shield-alt"></i>
        </div>

        <h4 class="text-center mb-4">Digite o código de verificação</h4>

        <?php if ($erro): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo $erro; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form method="POST" id="verificationForm">
            <div class="mb-3">
                <input type="text" 
                       class="form-control code-input" 
                       id="codigo_2fa" 
                       name="codigo_2fa" 
                       maxlength="6" 
                       pattern="[0-9]{6}" 
                       placeholder="000000"
                       autofocus
                       required>
            </div>

            <button type="submit" class="btn btn-verify">
                <i class="fas fa-check me-2"></i>Verificar Código
            </button>
        </form>

        <div class="help-text">
            <i class="fas fa-info-circle me-2"></i>
            Abra seu aplicativo autenticador (Google Authenticator, Microsoft Authenticator, etc.) 
            e digite o código de 6 dígitos mostrado.
        </div>

        <div class="timer" id="timer">
            <i class="fas fa-clock me-1"></i>
            <span id="timerText">O código expira em 30 segundos</span>
        </div>

        <div class="cancel-link">
            <a href="?cancelar=true" class="text-muted">
                <i class="fas fa-arrow-left me-1"></i>Cancelar e voltar ao login
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-formatar e auto-submit
        const codeInput = document.getElementById('codigo_2fa');
        const form = document.getElementById('verificationForm');
        
        codeInput.addEventListener('input', function(e) {
            // Remover não-números
            this.value = this.value.replace(/[^0-9]/g, '');
            
            // Auto-submit quando tiver 6 dígitos
            if (this.value.length === 6) {
                form.submit();
            }
        });

        // Timer visual
        let seconds = 30;
        const timerElement = document.getElementById('timerText');
        
        function updateTimer() {
            if (seconds > 0) {
                timerElement.textContent = `O código expira em ${seconds} segundos`;
                seconds--;
                setTimeout(updateTimer, 1000);
            } else {
                timerElement.innerHTML = '<span class="text-warning">Código pode ter expirado. Gere um novo código.</span>';
            }
        }
        
        updateTimer();

        // Focar no input automaticamente
        codeInput.focus();
    </script>
</body>
</html>