<?php
require_once 'config.php';

requireLogin();

$db = getDBConnection();
$userId = $_SESSION['user_id'];
$errors = [];
$success = false;

// Buscar dados do usuário
$stmt = $db->prepare("
    SELECT u.*, tu.tipo as tipo_usuario 
    FROM usuarios u 
    JOIN tipos_usuario tu ON u.tipo_usuario_id = tu.id 
    WHERE u.id = ?
");
$stmt->execute([$userId]);
$usuario = $stmt->fetch();

// Buscar dados específicos por tipo
$dadosEspecificos = [];
if ($_SESSION['user_type'] === 'empresa') {
    $stmt = $db->prepare("SELECT * FROM empresas WHERE usuario_id = ?");
    $stmt->execute([$userId]);
    $dadosEspecificos = $stmt->fetch();
} elseif ($_SESSION['user_type'] === 'arbitro') {
    $stmt = $db->prepare("SELECT * FROM arbitros WHERE usuario_id = ?");
    $stmt->execute([$userId]);
    $dadosEspecificos = $stmt->fetch();
    
    // Buscar especializações
    $stmt = $db->prepare("SELECT especializacao FROM arbitro_especializacoes WHERE arbitro_id = ?");
    $stmt->execute([$dadosEspecificos['id']]);
    $especializacoes = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Processar atualização
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        // Dados básicos
        $nomeCompleto = sanitizeInput($_POST['nome_completo'] ?? '');
        $telefone = sanitizeInput($_POST['telefone'] ?? '');
        $notificacoesEmail = isset($_POST['notificacoes_email']) ? 1 : 0;
        
        // Validações
        if (empty($nomeCompleto)) {
            $errors[] = "Nome completo é obrigatório.";
        }
        
        if (empty($errors)) {
            try {
                $db->beginTransaction();
                
                // Atualizar dados básicos
                $stmt = $db->prepare("
                    UPDATE usuarios 
                    SET nome_completo = ?, telefone = ?, notificacoes_email = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$nomeCompleto, $telefone, $notificacoesEmail, $userId]);
                
                // Atualizar dados específicos
                if ($_SESSION['user_type'] === 'empresa') {
                    $nomeFantasia = sanitizeInput($_POST['nome_fantasia'] ?? '');
                    $endereco = sanitizeInput($_POST['endereco'] ?? '');
                    $cidade = sanitizeInput($_POST['cidade'] ?? '');
                    $estado = sanitizeInput($_POST['estado'] ?? '');
                    $cep = preg_replace('/[^0-9]/', '', $_POST['cep'] ?? '');
                    $website = sanitizeInput($_POST['website'] ?? '');
                    
                    $stmt = $db->prepare("
                        UPDATE empresas 
                        SET nome_fantasia = ?, endereco = ?, cidade = ?, estado = ?, cep = ?, website = ?
                        WHERE usuario_id = ?
                    ");
                    $stmt->execute([$nomeFantasia, $endereco, $cidade, $estado, $cep, $website, $userId]);
                    
                } elseif ($_SESSION['user_type'] === 'arbitro') {
                    $biografia = sanitizeInput($_POST['biografia'] ?? '');
                    $experienciaAnos = intval($_POST['experiencia_anos'] ?? 0);
                    $taxaHora = floatval($_POST['taxa_hora'] ?? 0);
                    $posImobiliario = isset($_POST['pos_imobiliario']) ? 1 : 0;
                    
                    $stmt = $db->prepare("
                        UPDATE arbitros 
                        SET biografia = ?, experiencia_anos = ?, taxa_hora = ?, pos_imobiliario = ?
                        WHERE usuario_id = ?
                    ");
                    $stmt->execute([$biografia, $experienciaAnos, $taxaHora, $posImobiliario, $userId]);
                    
                    // Atualizar especializações
                    $arbitroId = $dadosEspecificos['id'];
                    $novasEspecializacoes = $_POST['especializacoes'] ?? [];
                    
                    // Remover antigas
                    $stmt = $db->prepare("DELETE FROM arbitro_especializacoes WHERE arbitro_id = ?");
                    $stmt->execute([$arbitroId]);
                    
                    // Inserir novas
                    if (!empty($novasEspecializacoes)) {
                        $stmt = $db->prepare("INSERT INTO arbitro_especializacoes (arbitro_id, especializacao) VALUES (?, ?)");
                        foreach ($novasEspecializacoes as $esp) {
                            if (in_array($esp, ['locacoes', 'disputas_condominiais', 'imobiliario_geral', 'danos', 'infracoes'])) {
                                $stmt->execute([$arbitroId, $esp]);
                            }
                        }
                    }
                }
                
                $db->commit();
                $success = true;
                $_SESSION['user_name'] = $nomeCompleto; // Atualizar nome na sessão
                
                logActivity('perfil_atualizado', 'Perfil atualizado com sucesso');
                
                // Recarregar dados
                header("Location: perfil.php?success=1");
                exit();
                
            } catch (Exception $e) {
                $db->rollBack();
                $errors[] = "Erro ao atualizar perfil: " . $e->getMessage();
            }
        }
        
    } elseif ($action === 'change_password') {
        $senhaAtual = $_POST['senha_atual'] ?? '';
        $novaSenha = $_POST['nova_senha'] ?? '';
        $confirmarSenha = $_POST['confirmar_senha'] ?? '';
        
        if (empty($senhaAtual)) {
            $errors[] = "Senha atual é obrigatória.";
        } elseif (!password_verify($senhaAtual, $usuario['senha'])) {
            $errors[] = "Senha atual incorreta.";
        }
        
        if (strlen($novaSenha) < 8) {
            $errors[] = "A nova senha deve ter no mínimo 8 caracteres.";
        }
        
        if ($novaSenha !== $confirmarSenha) {
            $errors[] = "As senhas não coincidem.";
        }
        
        if (empty($errors)) {
            try {
                $senhaHash = password_hash($novaSenha, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
                
                $stmt = $db->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
                $stmt->execute([$senhaHash, $userId]);
                
                $success = true;
                logActivity('senha_alterada', 'Senha alterada com sucesso');
                
            } catch (Exception $e) {
                $errors[] = "Erro ao alterar senha: " . $e->getMessage();
            }
        }
    }
}

// Buscar estatísticas do usuário
$stats = [];
if ($_SESSION['user_type'] === 'arbitro') {
    $stmt = $db->prepare("
        SELECT 
            COUNT(DISTINCT d.id) as total_casos,
            COUNT(DISTINCT CASE WHEN d.status = 'finalizada' THEN d.id END) as casos_finalizados,
            AVG(av.nota) as avaliacao_media
        FROM arbitros a
        LEFT JOIN disputas d ON d.arbitro_id = a.id
        LEFT JOIN avaliacoes av ON av.arbitro_id = a.id
        WHERE a.usuario_id = ?
    ");
    $stmt->execute([$userId]);
    $stats = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Perfil - Arbitrivm</title>
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
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* Profile Header */
        .profile-header {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            gap: 30px;
        }
        
        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background-color: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            font-weight: 600;
            color: #4a5568;
        }
        
        .profile-info {
            flex: 1;
        }
        
        .profile-name {
            font-size: 2rem;
            color: #1a365d;
            margin-bottom: 5px;
        }
        
        .profile-email {
            color: #718096;
            margin-bottom: 10px;
        }
        
        .profile-badges {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 12px;
            background-color: #e2e8f0;
            color: #4a5568;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .badge.verified {
            background-color: #c6f6d5;
            color: #22543d;
        }
        
        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 30px;
        }
        
        /* Sidebar */
        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .nav-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        .nav-item {
            display: block;
            padding: 12px 16px;
            color: #4a5568;
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 5px;
            transition: all 0.3s;
        }
        
        .nav-item:hover,
        .nav-item.active {
            background-color: #f7fafc;
            color: #2b6cb0;
        }
        
        /* Stats Card */
        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        .stat-item {
            padding: 15px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .stat-item:last-child {
            border-bottom: none;
        }
        
        .stat-label {
            color: #718096;
            font-size: 0.9rem;
            margin-bottom: 5px;
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: 600;
            color: #2b6cb0;
        }
        
        /* Forms */
        .form-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }
        
        .card-title {
            font-size: 1.25rem;
            color: #1a365d;
            margin-bottom: 25px;
            font-weight: 600;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        label {
            display: block;
            color: #4a5568;
            font-weight: 500;
            margin-bottom: 8px;
        }
        
        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="tel"],
        input[type="number"],
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
        
        input:disabled {
            background-color: #f7fafc;
            color: #a0aec0;
            cursor: not-allowed;
        }
        
        textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: auto;
            margin-right: 8px;
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
        
        .alert-success {
            background-color: #f0fff4;
            color: #22543d;
            border: 1px solid #9ae6b4;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        @media (max-width: 968px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .profile-header {
                flex-direction: column;
                text-align: center;
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
                    <li><a href="perfil.php">Perfil</a></li>
                    <li><a href="logout.php">Sair</a></li>
                </ul>
            </nav>
        </div>
    </header>
    
    <main class="main-container">
        <!-- Profile Header -->
        <div class="profile-header">
            <div class="profile-avatar">
                <?php echo strtoupper(substr($usuario['nome_completo'], 0, 1)); ?>
            </div>
            <div class="profile-info">
                <h1 class="profile-name"><?php echo htmlspecialchars($usuario['nome_completo']); ?></h1>
                <p class="profile-email"><?php echo htmlspecialchars($usuario['email']); ?></p>
                <div class="profile-badges">
                    <span class="badge"><?php echo ucfirst($_SESSION['user_type']); ?></span>
                    <?php if ($usuario['email_verificado']): ?>
                        <span class="badge verified">✓ Email Verificado</span>
                    <?php endif; ?>
                    <?php if ($_SESSION['user_type'] === 'arbitro' && !empty($dadosEspecificos['pos_imobiliario'])): ?>
                        <span class="badge verified">Pós em Direito Imobiliário</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">
                Perfil atualizado com sucesso!
            </div>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <div class="content-grid">
            <!-- Sidebar -->
            <aside class="sidebar">
                <nav class="nav-card">
                    <a href="#dados-pessoais" class="nav-item active" onclick="showTab('dados-pessoais')">
                        Dados Pessoais
                    </a>
                    <a href="#seguranca" class="nav-item" onclick="showTab('seguranca')">
                        Segurança
                    </a>
                    <?php if ($_SESSION['user_type'] === 'empresa'): ?>
                        <a href="#dados-empresa" class="nav-item" onclick="showTab('dados-empresa')">
                            Dados da Empresa
                        </a>
                    <?php elseif ($_SESSION['user_type'] === 'arbitro'): ?>
                        <a href="#dados-arbitro" class="nav-item" onclick="showTab('dados-arbitro')">
                            Dados Profissionais
                        </a>
                    <?php endif; ?>
                    <a href="#notificacoes" class="nav-item" onclick="showTab('notificacoes')">
                        Notificações
                    </a>
                </nav>
                
                <?php if ($_SESSION['user_type'] === 'arbitro' && !empty($stats)): ?>
                    <div class="stats-card">
                        <h3 style="font-size: 1.1rem; color: #1a365d; margin-bottom: 20px;">Estatísticas</h3>
                        
                        <div class="stat-item">
                            <div class="stat-label">Total de Casos</div>
                            <div class="stat-value"><?php echo $stats['total_casos'] ?: 0; ?></div>
                        </div>
                        
                        <div class="stat-item">
                            <div class="stat-label">Casos Finalizados</div>
                            <div class="stat-value"><?php echo $stats['casos_finalizados'] ?: 0; ?></div>
                        </div>
                        
                        <div class="stat-item">
                            <div class="stat-label">Avaliação Média</div>
                            <div class="stat-value">
                                <?php echo $stats['avaliacao_media'] ? number_format($stats['avaliacao_media'], 1) : 'N/A'; ?> ⭐
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </aside>
            
            <!-- Main Content -->
            <div class="main-content">
                <!-- Dados Pessoais -->
                <div id="dados-pessoais" class="tab-content active">
                    <form method="POST" class="form-card">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <h2 class="card-title">Dados Pessoais</h2>
                        
                        <div class="form-group">
                            <label for="nome_completo">Nome Completo</label>
                            <input type="text" name="nome_completo" id="nome_completo" 
                                   value="<?php echo htmlspecialchars($usuario['nome_completo']); ?>" required>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" id="email" value="<?php echo htmlspecialchars($usuario['email']); ?>" disabled>
                            </div>
                            
                            <div class="form-group">
                                <label for="cpf_cnpj">CPF/CNPJ</label>
                                <input type="text" id="cpf_cnpj" value="<?php echo htmlspecialchars($usuario['cpf_cnpj']); ?>" disabled>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="telefone">Telefone</label>
                            <input type="tel" name="telefone" id="telefone" 
                                   value="<?php echo htmlspecialchars($usuario['telefone']); ?>" 
                                   placeholder="(11) 99999-9999">
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                    </form>
                </div>
                
                <!-- Segurança -->
                <div id="seguranca" class="tab-content">
                    <form method="POST" class="form-card">
                        <input type="hidden" name="action" value="change_password">
                        
                        <h2 class="card-title">Alterar Senha</h2>
                        
                        <div class="form-group">
                            <label for="senha_atual">Senha Atual</label>
                            <input type="password" name="senha_atual" id="senha_atual" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="nova_senha">Nova Senha</label>
                            <input type="password" name="nova_senha" id="nova_senha" required minlength="8">
                        </div>
                        
                        <div class="form-group">
                            <label for="confirmar_senha">Confirmar Nova Senha</label>
                            <input type="password" name="confirmar_senha" id="confirmar_senha" required minlength="8">
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Alterar Senha</button>
                    </form>
                </div>
                
                <!-- Dados da Empresa -->
                <?php if ($_SESSION['user_type'] === 'empresa'): ?>
                    <div id="dados-empresa" class="tab-content">
                        <form method="POST" class="form-card">
                            <input type="hidden" name="action" value="update_profile">
                            
                            <h2 class="card-title">Dados da Empresa</h2>
                            
                            <div class="form-group">
                                <label for="razao_social">Razão Social</label>
                                <input type="text" id="razao_social" 
                                       value="<?php echo htmlspecialchars($dadosEspecificos['razao_social'] ?? ''); ?>" disabled>
                            </div>
                            
                            <div class="form-group">
                                <label for="nome_fantasia">Nome Fantasia</label>
                                <input type="text" name="nome_fantasia" id="nome_fantasia" 
                                       value="<?php echo htmlspecialchars($dadosEspecificos['nome_fantasia'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="endereco">Endereço</label>
                                <input type="text" name="endereco" id="endereco" 
                                       value="<?php echo htmlspecialchars($dadosEspecificos['endereco'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="cidade">Cidade</label>
                                    <input type="text" name="cidade" id="cidade" 
                                           value="<?php echo htmlspecialchars($dadosEspecificos['cidade'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="estado">Estado</label>
                                    <select name="estado" id="estado">
                                        <option value="">Selecione...</option>
                                        <?php
                                        $estados = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];
                                        foreach ($estados as $uf):
                                        ?>
                                            <option value="<?php echo $uf; ?>" 
                                                    <?php echo ($dadosEspecificos['estado'] ?? '') == $uf ? 'selected' : ''; ?>>
                                                <?php echo $uf; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="cep">CEP</label>
                                    <input type="text" name="cep" id="cep" 
                                           value="<?php echo htmlspecialchars($dadosEspecificos['cep'] ?? ''); ?>" 
                                           maxlength="9">
                                </div>
                                
                                <div class="form-group">
                                    <label for="website">Website</label>
                                    <input type="text" name="website" id="website" 
                                           value="<?php echo htmlspecialchars($dadosEspecificos['website'] ?? ''); ?>" 
                                           placeholder="https://exemplo.com.br">
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                        </form>
                    </div>
                <?php endif; ?>
                
                <!-- Dados do Árbitro -->
                <?php if ($_SESSION['user_type'] === 'arbitro'): ?>
                    <div id="dados-arbitro" class="tab-content">
                        <form method="POST" class="form-card">
                            <input type="hidden" name="action" value="update_profile">
                            
                            <h2 class="card-title">Dados Profissionais</h2>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="oab_numero">Número OAB</label>
                                    <input type="text" id="oab_numero" 
                                           value="<?php echo htmlspecialchars($dadosEspecificos['oab_numero'] ?? ''); ?>" disabled>
                                </div>
                                
                                <div class="form-group">
                                    <label for="oab_estado">Estado OAB</label>
                                    <input type="text" id="oab_estado" 
                                           value="<?php echo htmlspecialchars($dadosEspecificos['oab_estado'] ?? ''); ?>" disabled>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="experiencia_anos">Anos de Experiência</label>
                                    <input type="number" name="experiencia_anos" id="experiencia_anos" 
                                           value="<?php echo htmlspecialchars($dadosEspecificos['experiencia_anos'] ?? ''); ?>" 
                                           min="0" max="50">
                                </div>
                                
                                <div class="form-group">
                                    <label for="taxa_hora">Taxa por Hora (R$)</label>
                                    <input type="number" name="taxa_hora" id="taxa_hora" 
                                           value="<?php echo htmlspecialchars($dadosEspecificos['taxa_hora'] ?? ''); ?>" 
                                           min="0" step="0.01">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="biografia">Biografia Profissional</label>
                                <textarea name="biografia" id="biografia" rows="4"><?php echo htmlspecialchars($dadosEspecificos['biografia'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label>Especializações</label>
                                <div class="checkbox-group">
                                    <input type="checkbox" name="especializacoes[]" value="locacoes" id="esp_locacoes"
                                           <?php echo in_array('locacoes', $especializacoes ?? []) ? 'checked' : ''; ?>>
                                    <label for="esp_locacoes">Locações</label>
                                </div>
                                <div class="checkbox-group">
                                    <input type="checkbox" name="especializacoes[]" value="disputas_condominiais" id="esp_cond"
                                           <?php echo in_array('disputas_condominiais', $especializacoes ?? []) ? 'checked' : ''; ?>>
                                    <label for="esp_cond">Disputas Condominiais</label>
                                </div>
                                <div class="checkbox-group">
                                    <input type="checkbox" name="especializacoes[]" value="danos" id="esp_danos"
                                           <?php echo in_array('danos', $especializacoes ?? []) ? 'checked' : ''; ?>>
                                    <label for="esp_danos">Danos ao Imóvel</label>
                                </div>
                                <div class="checkbox-group">
                                    <input type="checkbox" name="especializacoes[]" value="infracoes" id="esp_infracoes"
                                           <?php echo in_array('infracoes', $especializacoes ?? []) ? 'checked' : ''; ?>>
                                    <label for="esp_infracoes">Infrações</label>
                                </div>
                                <div class="checkbox-group">
                                    <input type="checkbox" name="especializacoes[]" value="imobiliario_geral" id="esp_geral"
                                           <?php echo in_array('imobiliario_geral', $especializacoes ?? []) ? 'checked' : ''; ?>>
                                    <label for="esp_geral">Imobiliário Geral</label>
                                </div>
                            </div>
                            
                            <div class="checkbox-group">
                                <input type="checkbox" name="pos_imobiliario" id="pos_imobiliario"
                                       <?php echo !empty($dadosEspecificos['pos_imobiliario']) ? 'checked' : ''; ?>>
                                <label for="pos_imobiliario">Possuo pós-graduação em Direito Imobiliário</label>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                        </form>
                    </div>
                <?php endif; ?>
                
                <!-- Notificações -->
                <div id="notificacoes" class="tab-content">
                    <form method="POST" class="form-card">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <h2 class="card-title">Preferências de Notificação</h2>
                        
                        <div class="checkbox-group">
                            <input type="checkbox" name="notificacoes_email" id="notificacoes_email"
                                   <?php echo $usuario['notificacoes_email'] ? 'checked' : ''; ?>>
                            <label for="notificacoes_email">Receber notificações por email</label>
                        </div>
                        
                        <p style="color: #718096; margin-top: 20px;">
                            Você receberá notificações sobre:
                        </p>
                        <ul style="color: #718096; margin-left: 20px;">
                            <li>Novas mensagens em suas disputas</li>
                            <li>Atualizações de status das disputas</li>
                            <li>Documentos adicionados</li>
                            <li>Decisões e sentenças</li>
                        </ul>
                        
                        <button type="submit" class="btn btn-primary" style="margin-top: 20px;">Salvar Preferências</button>
                    </form>
                </div>
            </div>
        </div>
    </main>
    
    <script>
        function showTab(tabId) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all nav items
            document.querySelectorAll('.nav-item').forEach(item => {
                item.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabId).classList.add('active');
            
            // Add active class to clicked nav item
            document.querySelector(`a[href="#${tabId}"]`).classList.add('active');
        }
        
        // Máscara para telefone
        document.getElementById('telefone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length <= 11) {
                if (value.length <= 10) {
                    value = value.replace(/^(\d{2})(\d)/, '($1) $2');
                    value = value.replace(/(\d{4})(\d)/, '$1-$2');
                } else {
                    value = value.replace(/^(\d{2})(\d)/, '($1) $2');
                    value = value.replace(/(\d{5})(\d)/, '$1-$2');
                }
            }
            e.target.value = value;
        });
        
        // Máscara para CEP
        const cepInput = document.getElementById('cep');
        if (cepInput) {
            cepInput.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length <= 8) {
                    value = value.replace(/^(\d{5})(\d)/, '$1-$2');
                }
                e.target.value = value;
            });
        }
    </script>
</body>
</html>