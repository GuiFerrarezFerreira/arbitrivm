<?php
session_start();
require_once 'config/conexao.php';
require_once 'vendor/autoload.php'; // Para usar a biblioteca de 2FA

// Verificar se usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$mensagem = '';
$erro = '';

// Buscar status atual do 2FA
$sql = "SELECT two_fa_enabled, two_fa_secret FROM usuarios WHERE id = ?";
$stmt = $conexao->prepare($sql);
$stmt->execute([$usuario_id]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

// Processar ativação/desativação do 2FA
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['ativar_2fa'])) {
        // Gerar novo secret
        $ga = new \PHPGangsta\GoogleAuthenticator();
        $secret = $ga->createSecret();
        
        // Salvar temporariamente o secret
        $_SESSION['temp_2fa_secret'] = $secret;
        $_SESSION['mostrar_qr'] = true;
        
    } elseif (isset($_POST['confirmar_2fa'])) {
        // Verificar código antes de ativar
        $codigo = $_POST['codigo_verificacao'];
        $ga = new \PHPGangsta\GoogleAuthenticator();
        $secret = $_SESSION['temp_2fa_secret'];
        
        if ($ga->verifyCode($secret, $codigo, 2)) {
            // Código válido, ativar 2FA
            $sql = "UPDATE usuarios SET two_fa_enabled = 1, two_fa_secret = ? WHERE id = ?";
            $stmt = $conexao->prepare($sql);
            $stmt->execute([$secret, $usuario_id]);
            
            unset($_SESSION['temp_2fa_secret']);
            unset($_SESSION['mostrar_qr']);
            
            $mensagem = "Autenticação de dois fatores ativada com sucesso!";
            $usuario['two_fa_enabled'] = 1;
        } else {
            $erro = "Código inválido. Por favor, tente novamente.";
        }
        
    } elseif (isset($_POST['desativar_2fa'])) {
        // Verificar senha antes de desativar
        $senha = $_POST['senha_confirmacao'];
        
        $sql = "SELECT senha FROM usuarios WHERE id = ?";
        $stmt = $conexao->prepare($sql);
        $stmt->execute([$usuario_id]);
        $usuario_dados = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (password_verify($senha, $usuario_dados['senha'])) {
            // Desativar 2FA
            $sql = "UPDATE usuarios SET two_fa_enabled = 0, two_fa_secret = NULL WHERE id = ?";
            $stmt = $conexao->prepare($sql);
            $stmt->execute([$usuario_id]);
            
            $mensagem = "Autenticação de dois fatores desativada com sucesso!";
            $usuario['two_fa_enabled'] = 0;
        } else {
            $erro = "Senha incorreta. A desativação foi cancelada.";
        }
    }
}

// Gerar QR Code se necessário
$qrCodeUrl = '';
if (isset($_SESSION['mostrar_qr']) && $_SESSION['mostrar_qr']) {
    $ga = new \PHPGangsta\GoogleAuthenticator();
    $secret = $_SESSION['temp_2fa_secret'];
    
    // Buscar email do usuário
    $sql = "SELECT email FROM usuarios WHERE id = ?";
    $stmt = $conexao->prepare($sql);
    $stmt->execute([$usuario_id]);
    $email = $stmt->fetchColumn();
    
    $qrCodeUrl = $ga->getQRCodeGoogleUrl('Arbitrivm', $email, $secret);
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurar Autenticação de Dois Fatores - Arbitrivm</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .card-2fa {
            max-width: 600px;
            margin: 0 auto;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .qr-container {
            text-align: center;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 10px;
            margin: 20px 0;
        }
        .status-badge {
            font-size: 1.1rem;
            padding: 8px 16px;
        }
        .instruction-box {
            background-color: #e8f4fd;
            border-left: 4px solid #0066cc;
            padding: 15px;
            margin: 15px 0;
        }
        .security-icon {
            font-size: 3rem;
            color: #28a745;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container my-5">
        <div class="row">
            <div class="col-md-12">
                <div class="card card-2fa">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <i class="fas fa-shield-alt me-2"></i>
                            Autenticação de Dois Fatores (2FA)
                        </h4>
                    </div>
                    <div class="card-body">
                        <?php if ($mensagem): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle me-2"></i>
                                <?php echo $mensagem; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if ($erro): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <?php echo $erro; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <div class="text-center mb-4">
                            <i class="fas fa-mobile-alt security-icon"></i>
                            <h5 class="mt-3">Status Atual</h5>
                            <?php if ($usuario['two_fa_enabled']): ?>
                                <span class="badge status-badge bg-success">
                                    <i class="fas fa-check me-1"></i> Ativado
                                </span>
                            <?php else: ?>
                                <span class="badge status-badge bg-secondary">
                                    <i class="fas fa-times me-1"></i> Desativado
                                </span>
                            <?php endif; ?>
                        </div>

                        <?php if (!$usuario['two_fa_enabled'] && !isset($_SESSION['mostrar_qr'])): ?>
                            <!-- Formulário para ativar 2FA -->
                            <div class="instruction-box">
                                <h6><i class="fas fa-info-circle me-2"></i>O que é 2FA?</h6>
                                <p class="mb-0">A autenticação de dois fatores adiciona uma camada extra de segurança à sua conta. 
                                Além da senha, você precisará inserir um código gerado pelo seu smartphone para fazer login.</p>
                            </div>

                            <form method="POST">
                                <button type="submit" name="ativar_2fa" class="btn btn-success btn-lg w-100">
                                    <i class="fas fa-lock me-2"></i>Ativar Autenticação de Dois Fatores
                                </button>
                            </form>

                        <?php elseif (isset($_SESSION['mostrar_qr'])): ?>
                            <!-- Mostrar QR Code e instruções -->
                            <div class="instruction-box">
                                <h6><i class="fas fa-mobile-alt me-2"></i>Configure seu aplicativo autenticador</h6>
                                <ol class="mb-0">
                                    <li>Instale um aplicativo autenticador (Google Authenticator, Microsoft Authenticator, etc.)</li>
                                    <li>Escaneie o código QR abaixo com o aplicativo</li>
                                    <li>Digite o código de 6 dígitos gerado pelo aplicativo</li>
                                </ol>
                            </div>

                            <div class="qr-container">
                                <img src="<?php echo $qrCodeUrl; ?>" alt="QR Code para 2FA" class="img-fluid">
                                <p class="mt-3 text-muted">
                                    <small>Não consegue escanear? Use este código: 
                                    <code><?php echo $_SESSION['temp_2fa_secret']; ?></code></small>
                                </p>
                            </div>

                            <form method="POST">
                                <div class="mb-3">
                                    <label for="codigo_verificacao" class="form-label">
                                        <i class="fas fa-key me-1"></i>Código de Verificação
                                    </label>
                                    <input type="text" 
                                           class="form-control form-control-lg text-center" 
                                           id="codigo_verificacao" 
                                           name="codigo_verificacao" 
                                           maxlength="6" 
                                           pattern="[0-9]{6}" 
                                           placeholder="000000"
                                           required>
                                    <div class="form-text">Digite o código de 6 dígitos do seu aplicativo</div>
                                </div>
                                <button type="submit" name="confirmar_2fa" class="btn btn-primary btn-lg w-100">
                                    <i class="fas fa-check me-2"></i>Confirmar e Ativar 2FA
                                </button>
                            </form>

                        <?php else: ?>
                            <!-- Opção para desativar 2FA -->
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Atenção:</strong> Desativar a autenticação de dois fatores tornará sua conta menos segura.
                            </div>

                            <form method="POST" onsubmit="return confirm('Tem certeza que deseja desativar a autenticação de dois fatores?');">
                                <div class="mb-3">
                                    <label for="senha_confirmacao" class="form-label">
                                        <i class="fas fa-lock me-1"></i>Confirme sua senha para desativar
                                    </label>
                                    <input type="password" 
                                           class="form-control" 
                                           id="senha_confirmacao" 
                                           name="senha_confirmacao" 
                                           required>
                                </div>
                                <button type="submit" name="desativar_2fa" class="btn btn-danger">
                                    <i class="fas fa-unlock me-2"></i>Desativar 2FA
                                </button>
                                <a href="dashboard.php" class="btn btn-secondary ms-2">
                                    <i class="fas fa-arrow-left me-2"></i>Voltar
                                </a>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-formatar input do código
        document.getElementById('codigo_verificacao')?.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    </script>
</body>
</html>