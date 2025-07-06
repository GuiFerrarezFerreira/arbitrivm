<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

$error = '';
$success = '';
$validToken = false;
$userId = null;

// Verificar token
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    try {
        $stmt = $pdo->prepare("SELECT pr.user_id, u.email, u.nome 
                              FROM password_resets pr 
                              JOIN usuarios u ON pr.user_id = u.id 
                              WHERE pr.token = ? AND pr.expiry > NOW() AND pr.used = 0");
        $stmt->execute([$token]);
        $reset = $stmt->fetch();
        
        if ($reset) {
            $validToken = true;
            $userId = $reset['user_id'];
        } else {
            $error = 'Link inválido ou expirado. Por favor, solicite uma nova recuperação de senha.';
        }
    } catch (PDOException $e) {
        error_log("Erro ao verificar token: " . $e->getMessage());
        $error = 'Ocorreu um erro. Por favor, tente novamente.';
    }
}

// Processar nova senha
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    $token = $_POST['token'];
    
    // Validações
    if (strlen($password) < 8) {
        $error = 'A senha deve ter pelo menos 8 caracteres.';
    } elseif ($password !== $confirmPassword) {
        $error = 'As senhas não coincidem.';
    } else {
        try {
            // Atualizar senha
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            $pdo->beginTransaction();
            
            // Atualizar senha do usuário
            $stmt = $pdo->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
            $stmt->execute([$hashedPassword, $userId]);
            
            // Marcar token como usado
            $stmt = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
            $stmt->execute([$token]);
            
            $pdo->commit();
            
            $success = 'Senha redefinida com sucesso! Você será redirecionado para o login.';
            
            // Redirecionar após 3 segundos
            header("refresh:3;url=login.php");
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Erro ao redefinir senha: " . $e->getMessage());
            $error = 'Ocorreu um erro ao redefinir a senha. Por favor, tente novamente.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redefinir Senha - Arbitrivm</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f0f2f5;
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .reset-container {
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
        .password-strength {
            height: 5px;
            border-radius: 3px;
            margin-top: 5px;
            transition: all 0.3s ease;
        }
        .strength-weak { background-color: #dc3545; width: 33%; }
        .strength-medium { background-color: #ffc107; width: 66%; }
        .strength-strong { background-color: #28a745; width: 100%; }
        .password-requirements {
            font-size: 0.85rem;
            color: #6c757d;
        }
        .requirement {
            margin-bottom: 3px;
        }
        .requirement.met {
            color: #28a745;
        }
        .requirement i {
            width: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="reset-container">
            <div class="card">
                <div class="card-header">
                    <div class="logo">
                        <i class="fas fa-balance-scale"></i> Arbitrivm
                    </div>
                    <h4 class="mb-0">Redefinir Senha</h4>
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
                        <div class="alert alert-success" role="alert">
                            <i class="fas fa-check-circle me-2"></i>
                            <?php echo htmlspecialchars($success); ?>
                            <div class="mt-2">
                                <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                                <small>Redirecionando...</small>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($validToken && !$success): ?>
                        <p class="text-muted mb-4">
                            Digite sua nova senha abaixo. A senha deve ter pelo menos 8 caracteres.
                        </p>
                        
                        <form method="POST" action="">
                            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">
                                    <i class="fas fa-lock me-2"></i>Nova Senha
                                </label>
                                <div class="input-group">
                                    <input type="password" 
                                           class="form-control" 
                                           id="password" 
                                           name="password" 
                                           required 
                                           minlength="8">
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="password-strength" id="passwordStrength"></div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="confirm_password" class="form-label">
                                    <i class="fas fa-lock me-2"></i>Confirmar Senha
                                </label>
                                <div class="input-group">
                                    <input type="password" 
                                           class="form-control" 
                                           id="confirm_password" 
                                           name="confirm_password" 
                                           required 
                                           minlength="8">
                                    <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="password-requirements mb-4">
                                <div class="requirement" id="lengthReq">
                                    <i class="fas fa-times-circle"></i> Mínimo 8 caracteres
                                </div>
                                <div class="requirement" id="upperReq">
                                    <i class="fas fa-times-circle"></i> Pelo menos uma letra maiúscula
                                </div>
                                <div class="requirement" id="numberReq">
                                    <i class="fas fa-times-circle"></i> Pelo menos um número
                                </div>
                                <div class="requirement" id="matchReq">
                                    <i class="fas fa-times-circle"></i> As senhas coincidem
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-save me-2"></i>Redefinir Senha
                            </button>
                        </form>
                    <?php elseif (!$validToken && !$success): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-exclamation-triangle text-warning" style="font-size: 3rem;"></i>
                            <p class="mt-3">Link inválido ou expirado</p>
                            <a href="forgot-password.php" class="btn btn-primary">
                                <i class="fas fa-redo me-2"></i>Solicitar Novo Link
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="text-center mt-4 text-muted">
                <small>
                    <i class="fas fa-shield-alt me-2"></i>
                    Sua nova senha será criptografada e armazenada com segurança.
                </small>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle password visibility
        document.getElementById('togglePassword')?.addEventListener('click', function() {
            const password = document.getElementById('password');
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            this.querySelector('i').classList.toggle('fa-eye');
            this.querySelector('i').classList.toggle('fa-eye-slash');
        });
        
        document.getElementById('toggleConfirmPassword')?.addEventListener('click', function() {
            const confirmPassword = document.getElementById('confirm_password');
            const type = confirmPassword.getAttribute('type') === 'password' ? 'text' : 'password';
            confirmPassword.setAttribute('type', type);
            this.querySelector('i').classList.toggle('fa-eye');
            this.querySelector('i').classList.toggle('fa-eye-slash');
        });
        
        // Password strength and requirements
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirm_password');
        const strengthBar = document.getElementById('passwordStrength');
        
        function updateRequirements() {
            const pwd = password.value;
            const confirmPwd = confirmPassword.value;
            
            // Length requirement
            const lengthReq = document.getElementById('lengthReq');
            if (pwd.length >= 8) {
                lengthReq.classList.add('met');
                lengthReq.querySelector('i').className = 'fas fa-check-circle';
            } else {
                lengthReq.classList.remove('met');
                lengthReq.querySelector('i').className = 'fas fa-times-circle';
            }
            
            // Uppercase requirement
            const upperReq = document.getElementById('upperReq');
            if (/[A-Z]/.test(pwd)) {
                upperReq.classList.add('met');
                upperReq.querySelector('i').className = 'fas fa-check-circle';
            } else {
                upperReq.classList.remove('met');
                upperReq.querySelector('i').className = 'fas fa-times-circle';
            }
            
            // Number requirement
            const numberReq = document.getElementById('numberReq');
            if (/\d/.test(pwd)) {
                numberReq.classList.add('met');
                numberReq.querySelector('i').className = 'fas fa-check-circle';
            } else {
                numberReq.classList.remove('met');
                numberReq.querySelector('i').className = 'fas fa-times-circle';
            }
            
            // Match requirement
            const matchReq = document.getElementById('matchReq');
            if (pwd && confirmPwd && pwd === confirmPwd) {
                matchReq.classList.add('met');
                matchReq.querySelector('i').className = 'fas fa-check-circle';
            } else {
                matchReq.classList.remove('met');
                matchReq.querySelector('i').className = 'fas fa-times-circle';
            }
            
            // Update strength bar
            let strength = 0;
            if (pwd.length >= 8) strength++;
            if (/[A-Z]/.test(pwd) && /[a-z]/.test(pwd)) strength++;
            if (/\d/.test(pwd)) strength++;
            if (/[^A-Za-z0-9]/.test(pwd)) strength++;
            
            strengthBar.className = 'password-strength';
            if (pwd.length > 0) {
                if (strength <= 1) {
                    strengthBar.classList.add('strength-weak');
                } else if (strength <= 2) {
                    strengthBar.classList.add('strength-medium');
                } else {
                    strengthBar.classList.add('strength-strong');
                }
            }
        }
        
        password?.addEventListener('input', updateRequirements);
        confirmPassword?.addEventListener('input', updateRequirements);
    </script>
</body>
</html>