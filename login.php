<?php
require_once 'config.php';

// Se já estiver logado, redirecionar
if (isLoggedIn()) {
    header("Location: index.php");
    exit();
}

$error = '';
$success = '';

// Verificar se há mensagem de sucesso na URL
if (isset($_GET['registered'])) {
    $success = "Cadastro realizado com sucesso! Faça login para continuar.";
}

if (isset($_GET['verified'])) {
    $success = "Email verificado com sucesso! Faça login para continuar.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $senha = $_POST['senha'] ?? '';
    $lembrar = isset($_POST['lembrar']);
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Email inválido.";
    } else if (empty($senha)) {
        $error = "Senha é obrigatória.";
    } else {
        try {
            $db = getDBConnection();
            
            // Buscar usuário
            $stmt = $db->prepare("
                SELECT u.*, tu.tipo as tipo_usuario 
                FROM usuarios u 
                JOIN tipos_usuario tu ON u.tipo_usuario_id = tu.id 
                WHERE u.email = ? AND u.ativo = 1
            ");
            $stmt->execute([$email]);
            $usuario = $stmt->fetch();
            
            if (!$usuario) {
                $error = "Email ou senha incorretos.";
            } else if (!$usuario['email_verificado']) {
                $error = "Por favor, verifique seu email antes de fazer login.";
            } else if ($senha != $usuario['senha']) {
                $error = "Email ou senha incorretos.";
                
                // Log tentativa falha
                logActivity('login_falha', "Tentativa de login falha para: $email");
            } else {
                // Login bem-sucedido
                $_SESSION['user_id'] = $usuario['id'];
                $_SESSION['user_name'] = $usuario['nome_completo'];
                $_SESSION['user_email'] = $usuario['email'];
                $_SESSION['user_type'] = $usuario['tipo_usuario'];
                
                // Se for empresa, buscar dados adicionais
                if ($usuario['tipo_usuario'] === 'empresa') {
                    $stmt = $db->prepare("SELECT * FROM empresas WHERE usuario_id = ?");
                    $stmt->execute([$usuario['id']]);
                    $empresa = $stmt->fetch();
                    if ($empresa) {
                        $_SESSION['empresa_id'] = $empresa['id'];
                        $_SESSION['empresa_nome'] = $empresa['nome_fantasia'] ?: $empresa['razao_social'];
                    }
                }
                
                // Se for árbitro, buscar dados adicionais
                if ($usuario['tipo_usuario'] === 'arbitro') {
                    $stmt = $db->prepare("SELECT * FROM arbitros WHERE usuario_id = ?");
                    $stmt->execute([$usuario['id']]);
                    $arbitro = $stmt->fetch();
                    if ($arbitro) {
                        $_SESSION['arbitro_id'] = $arbitro['id'];
                    }
                }
                
                // Atualizar último acesso
                $stmt = $db->prepare("UPDATE usuarios SET ultimo_acesso = NOW() WHERE id = ?");
                $stmt->execute([$usuario['id']]);
                
                // Se marcou "lembrar-me", estender sessão
                if ($lembrar) {
                    session_set_cookie_params(30 * 24 * 60 * 60); // 30 dias
                }
                
                logActivity('login', "Login bem-sucedido");
                
                // Redirecionar para página inicial
                header("Location: index.php");
                exit();
            }
            
        } catch (Exception $e) {
            error_log("Erro no login: " . $e->getMessage());
            $error = "Erro ao processar login. Tente novamente.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Arbitrivm</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%);
            color: #333;
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-container {
            width: 100%;
            max-width: 400px;
            padding: 20px;
        }
        
        .logo {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .logo h1 {
            color: #1a365d;
            font-size: 2.5rem;
            font-weight: 700;
            letter-spacing: -1px;
            margin-bottom: 5px;
        }
        
        .logo p {
            color: #718096;
            font-size: 0.95rem;
        }
        
        .login-box {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            padding: 40px;
        }
        
        .form-title {
            font-size: 1.5rem;
            color: #1a365d;
            margin-bottom: 30px;
            text-align: center;
            font-weight: 600;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            color: #4a5568;
            font-weight: 500;
            margin-bottom: 8px;
            font-size: 0.95rem;
        }
        
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        input:focus {
            outline: none;
            border-color: #4299e1;
            box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        
        .checkbox-group label {
            display: flex;
            align-items: center;
            margin-bottom: 0;
            font-weight: normal;
            cursor: pointer;
        }
        
        .checkbox-group input[type="checkbox"] {
            margin-right: 8px;
        }
        
        .forgot-password {
            color: #2b6cb0;
            text-decoration: none;
            font-size: 0.9rem;
        }
        
        .forgot-password:hover {
            text-decoration: underline;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 24px;
            font-size: 1rem;
            font-weight: 500;
            text-align: center;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
        }
        
        .btn-primary {
            background-color: #2b6cb0;
            color: white;
            width: 100%;
        }
        
        .btn-primary:hover {
            background-color: #2558a3;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(43, 108, 176, 0.3);
        }
        
        .alert {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.95rem;
        }
        
        .alert-error {
            background-color: #fee;
            color: #c53030;
            border: 1px solid #fc8181;
        }
        
        .alert-success {
            background-color: #f0fff4;
            color: #22543d;
            border: 1px solid #9ae6b4;
        }
        
        .form-footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 30px;
            border-top: 1px solid #e2e8f0;
            color: #718096;
        }
        
        .form-footer a {
            color: #2b6cb0;
            text-decoration: none;
            font-weight: 500;
        }
        
        .form-footer a:hover {
            text-decoration: underline;
        }
        
        .divider {
            text-align: center;
            margin: 20px 0;
            color: #a0aec0;
            font-size: 0.9rem;
        }
        
        @media (max-width: 480px) {
            .login-container {
                padding: 10px;
            }
            
            .login-box {
                padding: 30px 20px;
            }
            
            .logo h1 {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <h1>ARBITRIVM</h1>
            <p>Resolução Digital de Disputas Imobiliárias</p>
        </div>
        
        <div class="login-box">
            <h2 class="form-title">Acesse sua conta</h2>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" name="email" id="email" required autofocus 
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="senha">Senha</label>
                    <input type="password" name="senha" id="senha" required>
                </div>
                
                <div class="checkbox-group">
                    <label>
                        <input type="checkbox" name="lembrar" id="lembrar">
                        Lembrar-me
                    </label>
                    <a href="forgot-password.php" class="forgot-password">Esqueceu a senha?</a>
                </div>
                
                <button type="submit" class="btn btn-primary">Entrar</button>
            </form>
            
            <div class="form-footer">
                <p>Não tem uma conta?</p>
                <a href="register.php">Cadastre-se gratuitamente</a>
            </div>
        </div>
    </div>
</body>
</html>