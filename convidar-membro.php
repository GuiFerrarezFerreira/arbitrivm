<?php
require_once 'config.php';

// Verificar se está logado e é empresa com permissão de gerenciar
requireLogin();
requireUserType('empresa');

$db = getDBConnection();
$empresaId = $_SESSION['empresa_id'];

// Verificar permissão do usuário
$stmt = $db->prepare("
    SELECT permissao_nivel FROM equipe_empresa 
    WHERE usuario_id = ? AND empresa_id = ? AND ativo = 1
");
$stmt->execute([$_SESSION['user_id'], $empresaId]);
$permissao = $stmt->fetchColumn() ?: 'gerenciar';

if ($permissao !== 'gerenciar') {
    header("HTTP/1.1 403 Forbidden");
    die("Você não tem permissão para convidar novos membros.");
}

$errors = [];
$success = false;

// Buscar dados da empresa
$stmt = $db->prepare("SELECT * FROM empresas WHERE id = ?");
$stmt->execute([$empresaId]);
$empresa = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar dados
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $nomeCompleto = sanitizeInput($_POST['nome_completo'] ?? '');
    $cargo = sanitizeInput($_POST['cargo'] ?? '');
    $permissaoNivel = sanitizeInput($_POST['permissao_nivel'] ?? 'visualizar');
    $mensagemPersonalizada = sanitizeInput($_POST['mensagem_personalizada'] ?? '');
    
    // Validações
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Email inválido.";
    }
    
    if (empty($nomeCompleto)) {
        $errors[] = "Nome completo é obrigatório.";
    }
    
    if (!in_array($permissaoNivel, ['visualizar', 'criar', 'gerenciar'])) {
        $errors[] = "Nível de permissão inválido.";
    }
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Verificar se email já existe
            $stmt = $db->prepare("SELECT id, ativo, empresa_id FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            $usuarioExistente = $stmt->fetch();
            
            if ($usuarioExistente) {
                if ($usuarioExistente['empresa_id'] == $empresaId) {
                    throw new Exception("Este email já está cadastrado em sua equipe.");
                } elseif ($usuarioExistente['empresa_id']) {
                    throw new Exception("Este email já está vinculado a outra empresa.");
                } else {
                    // Usuário existe mas não está vinculado a nenhuma empresa
                    // Podemos convidá-lo
                    $usuarioId = $usuarioExistente['id'];
                    
                    // Atualizar empresa_id
                    $stmt = $db->prepare("UPDATE usuarios SET empresa_id = ? WHERE id = ?");
                    $stmt->execute([$empresaId, $usuarioId]);
                }
            } else {
                // Criar novo usuário temporário
                $tokenConvite = generateToken();
                $senhaTemporaria = generateToken(8);
                $senhaHash = password_hash($senhaTemporaria, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
                
                $stmt = $db->prepare("
                    INSERT INTO usuarios 
                    (tipo_usuario_id, email, senha, nome_completo, empresa_id, token_verificacao, ativo, email_verificado) 
                    VALUES (
                        (SELECT id FROM tipos_usuario WHERE tipo = 'solicitante'), 
                        ?, ?, ?, ?, ?, 0, 0
                    )
                ");
                $stmt->execute([$email, $senhaHash, $nomeCompleto, $empresaId, $tokenConvite]);
                $usuarioId = $db->lastInsertId();
            }
            
            // Verificar se já existe registro na equipe
            $stmt = $db->prepare("
                SELECT id FROM equipe_empresa 
                WHERE empresa_id = ? AND usuario_id = ?
            ");
            $stmt->execute([$empresaId, $usuarioId]);
            $equipeExistente = $stmt->fetch();
            
            if ($equipeExistente) {
                // Reativar membro
                $stmt = $db->prepare("
                    UPDATE equipe_empresa 
                    SET ativo = 1, cargo = ?, permissao_nivel = ?, data_adicao = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$cargo, $permissaoNivel, $equipeExistente['id']]);
            } else {
                // Adicionar à equipe
                $stmt = $db->prepare("
                    INSERT INTO equipe_empresa (empresa_id, usuario_id, cargo, permissao_nivel) 
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$empresaId, $usuarioId, $cargo, $permissaoNivel]);
            }
            
            // Enviar email de convite
            $nomeEmpresa = $empresa['nome_fantasia'] ?: $empresa['razao_social'];
            $linkConvite = APP_URL . "/aceitar-convite.php?token=" . ($tokenConvite ?? '');
            
            $permissaoTexto = [
                'visualizar' => 'visualizar disputas e relatórios',
                'criar' => 'criar e gerenciar disputas',
                'gerenciar' => 'gerenciar toda a plataforma'
            ];
            
            $emailBody = "
                <h2>Você foi convidado para a equipe {$nomeEmpresa} no Arbitrivm</h2>
                <p>Olá {$nomeCompleto},</p>
                <p>{$_SESSION['user_name']} convidou você para fazer parte da equipe da {$nomeEmpresa} na plataforma Arbitrivm.</p>
                
                <p><strong>Seu cargo:</strong> {$cargo}</p>
                <p><strong>Permissões:</strong> Você poderá {$permissaoTexto[$permissaoNivel]}.</p>
                
                " . (!empty($mensagemPersonalizada) ? "<p><strong>Mensagem:</strong><br>" . nl2br(htmlspecialchars($mensagemPersonalizada)) . "</p>" : "") . "
                
                <p>Para aceitar o convite e configurar sua conta, clique no botão abaixo:</p>
                <p style='text-align: center; margin: 30px 0;'>
                    <a href='{$linkConvite}' style='background-color: #2b6cb0; color: white; padding: 12px 30px; text-decoration: none; border-radius: 6px; display: inline-block;'>
                        Aceitar Convite
                    </a>
                </p>
                
                <p>Se você não esperava este convite, pode ignorar este email.</p>
                
                <hr style='margin: 30px 0; border: none; border-top: 1px solid #e2e8f0;'>
                <p style='color: #718096; font-size: 0.9em;'>
                    Arbitrivm - Plataforma de Arbitragem Digital<br>
                    Este é um email automático, por favor não responda.
                </p>
            ";
            
            sendEmail($email, "Convite para equipe - $nomeEmpresa", $emailBody);
            
            // Criar notificação para o usuário convidado
            createNotification($usuarioId, 'sistema', 
                'Convite para Equipe', 
                "Você foi convidado para a equipe da $nomeEmpresa",
                "aceitar-convite.php"
            );
            
            $db->commit();
            
            logActivity('equipe_membro_convidado', "Novo membro convidado: $email");
            
            $_SESSION['success_message'] = "Convite enviado com sucesso para $email";
            header("Location: equipe.php?convite_enviado=1");
            exit();
            
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = $e->getMessage();
        }
    }
}

// Buscar membros atuais para mostrar limite
$stmt = $db->prepare("
    SELECT COUNT(*) FROM equipe_empresa 
    WHERE empresa_id = ? AND ativo = 1
");
$stmt->execute([$empresaId]);
$totalMembros = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Convidar Membro - Arbitrivm</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
        }
        
        /* Header */
        .header {
            background-color: #1a365d;
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: -0.5px;
        }
        
        .nav-menu {
            display: flex;
            list-style: none;
            gap: 30px;
            align-items: center;
        }
        
        .nav-menu a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            transition: opacity 0.3s;
        }
        
        .nav-menu a:hover {
            opacity: 0.8;
        }
        
        /* Main Content */
        .main-container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* Breadcrumb */
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 30px;
            color: #718096;
            font-size: 0.95rem;
        }
        
        .breadcrumb a {
            color: #2b6cb0;
            text-decoration: none;
        }
        
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        
        /* Form Card */
        .form-card {
            background: white;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        .form-title {
            font-size: 2rem;
            color: #1a365d;
            margin-bottom: 10px;
        }
        
        .form-subtitle {
            color: #718096;
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        label {
            display: block;
            color: #4a5568;
            font-weight: 500;
            margin-bottom: 8px;
        }
        
        .required {
            color: #e53e3e;
        }
        
        input[type="text"],
        input[type="email"],
        select,
        textarea {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        input:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: #4299e1;
            box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
        }
        
        textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .help-text {
            font-size: 0.875rem;
            color: #718096;
            margin-top: 5px;
        }
        
        .permission-options {
            display: grid;
            gap: 15px;
            margin-top: 10px;
        }
        
        .permission-option {
            display: flex;
            align-items: start;
            padding: 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .permission-option:hover {
            border-color: #cbd5e0;
            background-color: #f7fafc;
        }
        
        .permission-option input[type="radio"] {
            margin-right: 15px;
            margin-top: 2px;
        }
        
        .permission-option.selected {
            border-color: #2b6cb0;
            background-color: #ebf8ff;
        }
        
        .permission-info {
            flex: 1;
        }
        
        .permission-title {
            font-weight: 600;
            color: #1a365d;
            margin-bottom: 5px;
        }
        
        .permission-description {
            font-size: 0.9rem;
            color: #4a5568;
        }
        
        .alert {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-error {
            background-color: #fee;
            color: #c53030;
            border: 1px solid #fc8181;
        }
        
        .alert-info {
            background-color: #ebf8ff;
            color: #2b6cb0;
            border: 1px solid #90cdf4;
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
        }
        
        .btn-primary:hover {
            background-color: #2558a3;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(43, 108, 176, 0.3);
        }
        
        .btn-secondary {
            background-color: #e2e8f0;
            color: #4a5568;
        }
        
        .btn-secondary:hover {
            background-color: #cbd5e0;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        
        .members-count {
            background-color: #f7fafc;
            border-radius: 8px;
            padding: 15px 20px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .members-count-icon {
            width: 40px;
            height: 40px;
            background-color: #e2e8f0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .members-count-info {
            flex: 1;
        }
        
        .members-count-number {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1a365d;
        }
        
        .members-count-text {
            font-size: 0.9rem;
            color: #718096;
        }
        
        @media (max-width: 768px) {
            .form-card {
                padding: 25px;
            }
            
            .form-actions {
                flex-direction: column-reverse;
            }
            
            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <div class="logo">ARBITRIVM</div>
            
            <nav>
                <ul class="nav-menu">
                    <li><a href="index.php">Dashboard</a></li>
                    <li><a href="disputas.php">Disputas</a></li>
                    <li><a href="equipe.php">Equipe</a></li>
                    <li><a href="relatorios.php">Relatórios</a></li>
                    <li><a href="perfil.php">Perfil</a></li>
                    <li><a href="logout.php">Sair</a></li>
                </ul>
            </nav>
        </div>
    </header>
    
    <main class="main-container">
        <!-- Breadcrumb -->
        <div class="breadcrumb">
            <a href="index.php">Dashboard</a>
            <span>›</span>
            <a href="equipe.php">Equipe</a>
            <span>›</span>
            <span>Convidar Membro</span>
        </div>
        
        <div class="form-card">
            <h1 class="form-title">Convidar Novo Membro</h1>
            <p class="form-subtitle">
                Convide membros da sua equipe para colaborar na gestão de disputas
            </p>
            
            <div class="members-count">
                <div class="members-count-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" style="color: #4a5568;">
                        <path d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                </div>
                <div class="members-count-info">
                    <div class="members-count-number"><?php echo $totalMembros; ?> membros ativos</div>
                    <div class="members-count-text">na sua equipe atualmente</div>
                </div>
            </div>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="email">Email do Convidado <span class="required">*</span></label>
                    <input type="email" name="email" id="email" required 
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    <p class="help-text">O convite será enviado para este email</p>
                </div>
                
                <div class="form-group">
                    <label for="nome_completo">Nome Completo <span class="required">*</span></label>
                    <input type="text" name="nome_completo" id="nome_completo" required
                           value="<?php echo isset($_POST['nome_completo']) ? htmlspecialchars($_POST['nome_completo']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="cargo">Cargo</label>
                    <input type="text" name="cargo" id="cargo" 
                           placeholder="Ex: Gerente de Locação, Analista de Cobrança"
                           value="<?php echo isset($_POST['cargo']) ? htmlspecialchars($_POST['cargo']) : ''; ?>">
                    <p class="help-text">Opcional - ajuda a identificar a função do membro</p>
                </div>
                
                <div class="form-group">
                    <label>Nível de Permissão <span class="required">*</span></label>
                    <div class="permission-options">
                        <label class="permission-option">
                            <input type="radio" name="permissao_nivel" value="visualizar" 
                                   <?php echo (!isset($_POST['permissao_nivel']) || $_POST['permissao_nivel'] === 'visualizar') ? 'checked' : ''; ?>>
                            <div class="permission-info">
                                <div class="permission-title">Visualizador</div>
                                <div class="permission-description">
                                    Pode visualizar disputas e relatórios, mas não pode criar ou editar
                                </div>
                            </div>
                        </label>
                        
                        <label class="permission-option">
                            <input type="radio" name="permissao_nivel" value="criar"
                                   <?php echo (isset($_POST['permissao_nivel']) && $_POST['permissao_nivel'] === 'criar') ? 'checked' : ''; ?>>
                            <div class="permission-info">
                                <div class="permission-title">Criador</div>
                                <div class="permission-description">
                                    Pode criar e gerenciar disputas, visualizar relatórios
                                </div>
                            </div>
                        </label>
                        
                        <label class="permission-option">
                            <input type="radio" name="permissao_nivel" value="gerenciar"
                                   <?php echo (isset($_POST['permissao_nivel']) && $_POST['permissao_nivel'] === 'gerenciar') ? 'checked' : ''; ?>>
                            <div class="permission-info">
                                <div class="permission-title">Gerente</div>
                                <div class="permission-description">
                                    Acesso total - pode criar disputas, gerenciar equipe e configurações
                                </div>
                            </div>
                        </label>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="mensagem_personalizada">Mensagem Personalizada</label>
                    <textarea name="mensagem_personalizada" id="mensagem_personalizada" 
                              placeholder="Adicione uma mensagem pessoal ao convite (opcional)"><?php echo isset($_POST['mensagem_personalizada']) ? htmlspecialchars($_POST['mensagem_personalizada']) : ''; ?></textarea>
                </div>
                
                <div class="alert alert-info">
                    <strong>Como funciona:</strong>
                    <ul style="margin-top: 10px; padding-left: 20px;">
                        <li>O convidado receberá um email com link para aceitar o convite</li>
                        <li>Se já tiver conta, poderá fazer login e acessar imediatamente</li>
                        <li>Se não tiver conta, poderá criar uma com o email informado</li>
                        <li>Você pode alterar as permissões a qualquer momento</li>
                    </ul>
                </div>
                
                <div class="form-actions">
                    <a href="equipe.php" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary">Enviar Convite</button>
                </div>
            </form>
        </div>
    </main>
    
    <script>
        // Adicionar classe 'selected' ao option de permissão selecionado
        document.querySelectorAll('input[name="permissao_nivel"]').forEach(radio => {
            radio.addEventListener('change', function() {
                document.querySelectorAll('.permission-option').forEach(opt => {
                    opt.classList.remove('selected');
                });
                if (this.checked) {
                    this.closest('.permission-option').classList.add('selected');
                }
            });
            
            // Marcar o selecionado inicialmente
            if (radio.checked) {
                radio.closest('.permission-option').classList.add('selected');
            }
        });
    </script>
</body>
</html>