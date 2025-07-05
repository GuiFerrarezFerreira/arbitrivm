<?php
require_once 'config.php';

// Verificar se está logado e é empresa
requireLogin();
requireUserType('empresa');

$db = getDBConnection();
$empresaId = $_SESSION['empresa_id'];
$errors = [];
$success = false;

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'alterar_permissao':
            $membroId = intval($_POST['membro_id'] ?? 0);
            $novaPermissao = sanitizeInput($_POST['permissao'] ?? '');
            
            if (in_array($novaPermissao, ['visualizar', 'criar', 'gerenciar'])) {
                try {
                    $stmt = $db->prepare("
                        UPDATE equipe_empresa 
                        SET permissao_nivel = ? 
                        WHERE id = ? AND empresa_id = ?
                    ");
                    $stmt->execute([$novaPermissao, $membroId, $empresaId]);
                    
                    $success = true;
                    logActivity('equipe_permissao_alterada', "Permissão alterada para membro ID: $membroId");
                } catch (Exception $e) {
                    $errors[] = "Erro ao alterar permissão: " . $e->getMessage();
                }
            }
            break;
            
        case 'alterar_cargo':
            $membroId = intval($_POST['membro_id'] ?? 0);
            $novoCargo = sanitizeInput($_POST['cargo'] ?? '');
            
            try {
                $stmt = $db->prepare("
                    UPDATE equipe_empresa 
                    SET cargo = ? 
                    WHERE id = ? AND empresa_id = ?
                ");
                $stmt->execute([$novoCargo, $membroId, $empresaId]);
                
                $success = true;
                logActivity('equipe_cargo_alterado', "Cargo alterado para membro ID: $membroId");
            } catch (Exception $e) {
                $errors[] = "Erro ao alterar cargo: " . $e->getMessage();
            }
            break;
            
        case 'remover_membro':
            $membroId = intval($_POST['membro_id'] ?? 0);
            
            try {
                $db->beginTransaction();
                
                // Verificar se não é o único administrador
                $stmt = $db->prepare("
                    SELECT COUNT(*) FROM equipe_empresa 
                    WHERE empresa_id = ? AND permissao_nivel = 'gerenciar' AND ativo = 1
                ");
                $stmt->execute([$empresaId]);
                $totalGerentes = $stmt->fetchColumn();
                
                $stmt = $db->prepare("
                    SELECT permissao_nivel FROM equipe_empresa 
                    WHERE id = ? AND empresa_id = ?
                ");
                $stmt->execute([$membroId, $empresaId]);
                $membroPermissao = $stmt->fetchColumn();
                
                if ($totalGerentes <= 1 && $membroPermissao === 'gerenciar') {
                    throw new Exception("Não é possível remover o último gerente da equipe.");
                }
                
                // Desativar membro
                $stmt = $db->prepare("
                    UPDATE equipe_empresa 
                    SET ativo = 0 
                    WHERE id = ? AND empresa_id = ?
                ");
                $stmt->execute([$membroId, $empresaId]);
                
                // Remover empresa_id do usuário
                $stmt = $db->prepare("
                    UPDATE usuarios u
                    INNER JOIN equipe_empresa eq ON u.id = eq.usuario_id
                    SET u.empresa_id = NULL
                    WHERE eq.id = ? AND eq.empresa_id = ?
                ");
                $stmt->execute([$membroId, $empresaId]);
                
                $db->commit();
                $success = true;
                logActivity('equipe_membro_removido', "Membro removido ID: $membroId");
                
            } catch (Exception $e) {
                $db->rollBack();
                $errors[] = $e->getMessage();
            }
            break;
    }
    
    if ($success) {
        header("Location: equipe.php?success=1");
        exit();
    }
}

// Buscar membros da equipe
$stmt = $db->prepare("
    SELECT eq.*, u.nome_completo, u.email, u.telefone, u.ultimo_acesso,
           (SELECT COUNT(*) FROM disputas WHERE reclamante_id = u.id AND empresa_id = ?) as total_disputas
    FROM equipe_empresa eq
    JOIN usuarios u ON eq.usuario_id = u.id
    WHERE eq.empresa_id = ? AND eq.ativo = 1
    ORDER BY 
        CASE eq.permissao_nivel 
            WHEN 'gerenciar' THEN 1 
            WHEN 'criar' THEN 2 
            ELSE 3 
        END,
        u.nome_completo
");
$stmt->execute([$empresaId, $empresaId]);
$membros = $stmt->fetchAll();

// Buscar estatísticas da equipe
$stmt = $db->prepare("
    SELECT 
        COUNT(DISTINCT eq.usuario_id) as total_membros,
        COUNT(DISTINCT d.id) as total_disputas,
        COUNT(DISTINCT CASE WHEN d.status = 'em_andamento' THEN d.id END) as disputas_ativas,
        COUNT(DISTINCT CASE WHEN d.data_abertura >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN d.id END) as disputas_mes
    FROM equipe_empresa eq
    LEFT JOIN disputas d ON d.reclamante_id = eq.usuario_id AND d.empresa_id = eq.empresa_id
    WHERE eq.empresa_id = ? AND eq.ativo = 1
");
$stmt->execute([$empresaId]);
$stats = $stmt->fetch();

// Contar convites pendentes
$stmt = $db->prepare("
    SELECT COUNT(*) FROM usuarios 
    WHERE empresa_id = ? AND email_verificado = 0 AND ativo = 0
");
$stmt->execute([$empresaId]);
$convitesPendentes = $stmt->fetchColumn();

// Verificar permissão do usuário atual
$stmt = $db->prepare("
    SELECT permissao_nivel FROM equipe_empresa 
    WHERE usuario_id = ? AND empresa_id = ? AND ativo = 1
");
$stmt->execute([$_SESSION['user_id'], $empresaId]);
$minhaPermissao = $stmt->fetchColumn() ?: 'gerenciar'; // Se for o dono da empresa, tem permissão total
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Equipe - Arbitrivm</title>
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
        
        /* Page Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .page-title {
            color: #1a365d;
            font-size: 2rem;
        }
        
        /* Stats Row */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            text-align: center;
        }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: #2b6cb0;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #718096;
            font-size: 0.95rem;
        }
        
        /* Team Grid */
        .team-grid {
            display: grid;
            gap: 20px;
        }
        
        .member-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .member-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .member-header {
            display: flex;
            align-items: start;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .member-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background-color: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 600;
            color: #4a5568;
            flex-shrink: 0;
        }
        
        .member-info {
            flex: 1;
        }
        
        .member-name {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1a365d;
            margin-bottom: 5px;
        }
        
        .member-email {
            color: #718096;
            font-size: 0.95rem;
            margin-bottom: 5px;
        }
        
        .member-meta {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            font-size: 0.9rem;
            color: #4a5568;
        }
        
        .member-meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .permission-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            margin-left: auto;
        }
        
        .permission-gerenciar {
            background-color: #fef3c7;
            color: #92400e;
        }
        
        .permission-criar {
            background-color: #ddd6fe;
            color: #6b21a8;
        }
        
        .permission-visualizar {
            background-color: #e0e7ff;
            color: #3730a3;
        }
        
        .member-actions {
            display: flex;
            gap: 15px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }
        
        .action-group {
            flex: 1;
        }
        
        .action-label {
            font-size: 0.85rem;
            color: #718096;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .action-input {
            display: flex;
            gap: 10px;
        }
        
        .action-input select,
        .action-input input {
            flex: 1;
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 0.9rem;
        }
        
        .btn {
            display: inline-block;
            padding: 8px 16px;
            font-size: 0.9rem;
            font-weight: 500;
            text-align: center;
            text-decoration: none;
            border-radius: 6px;
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
        }
        
        .btn-secondary {
            background-color: #e2e8f0;
            color: #4a5568;
        }
        
        .btn-secondary:hover {
            background-color: #cbd5e0;
        }
        
        .btn-danger {
            background-color: #e53e3e;
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #c53030;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 0.85rem;
        }
        
        .alert {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background-color: #f0fff4;
            color: #22543d;
            border: 1px solid #9ae6b4;
        }
        
        .alert-error {
            background-color: #fee;
            color: #c53030;
            border: 1px solid #fc8181;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        .empty-state svg {
            width: 80px;
            height: 80px;
            margin-bottom: 20px;
            opacity: 0.3;
        }
        
        .empty-state h3 {
            font-size: 1.5rem;
            color: #4a5568;
            margin-bottom: 10px;
        }
        
        .empty-state p {
            color: #718096;
            margin-bottom: 20px;
        }
        
        .pending-badge {
            background-color: #fed7aa;
            color: #c2410c;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-left: 5px;
        }
        
        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                gap: 20px;
                align-items: flex-start;
            }
            
            .member-header {
                flex-direction: column;
                text-align: center;
            }
            
            .member-actions {
                flex-direction: column;
            }
            
            .permission-badge {
                margin: 10px 0 0 0;
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
        <div class="page-header">
            <h1 class="page-title">
                Gestão de Equipe
                <?php if ($convitesPendentes > 0): ?>
                    <span class="pending-badge"><?php echo $convitesPendentes; ?> pendente<?php echo $convitesPendentes > 1 ? 's' : ''; ?></span>
                <?php endif; ?>
            </h1>
            <?php if ($minhaPermissao === 'gerenciar'): ?>
                <a href="convidar-membro.php" class="btn btn-primary">
                    + Convidar Membro
                </a>
            <?php endif; ?>
        </div>
        
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">
                Ação realizada com sucesso!
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
        
        <!-- Estatísticas -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['total_membros']; ?></div>
                <div class="stat-label">Membros Ativos</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['total_disputas']; ?></div>
                <div class="stat-label">Total de Disputas</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['disputas_ativas']; ?></div>
                <div class="stat-label">Disputas Ativas</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['disputas_mes']; ?></div>
                <div class="stat-label">Disputas (30 dias)</div>
            </div>
        </div>
        
        <!-- Lista de Membros -->
        <?php if (empty($membros)): ?>
            <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                </svg>
                <h3>Nenhum membro na equipe</h3>
                <p>Convide membros da sua equipe para colaborar na gestão de disputas.</p>
                <a href="convidar-membro.php" class="btn btn-primary">Convidar Primeiro Membro</a>
            </div>
        <?php else: ?>
            <div class="team-grid">
                <?php foreach ($membros as $membro): ?>
                    <div class="member-card">
                        <div class="member-header">
                            <div class="member-avatar">
                                <?php echo strtoupper(substr($membro['nome_completo'], 0, 1)); ?>
                            </div>
                            <div class="member-info">
                                <h3 class="member-name"><?php echo htmlspecialchars($membro['nome_completo']); ?></h3>
                                <p class="member-email"><?php echo htmlspecialchars($membro['email']); ?></p>
                                <div class="member-meta">
                                    <div class="member-meta-item">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                        </svg>
                                        <?php echo htmlspecialchars($membro['cargo'] ?: 'Sem cargo'); ?>
                                    </div>
                                    <div class="member-meta-item">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                        <?php echo $membro['total_disputas']; ?> disputas
                                    </div>
                                    <?php if ($membro['ultimo_acesso']): ?>
                                        <div class="member-meta-item">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                                <circle cx="12" cy="12" r="10"></circle>
                                                <polyline points="12 6 12 12 16 14"></polyline>
                                            </svg>
                                            Último acesso: <?php echo formatDate($membro['ultimo_acesso'], 'd/m/Y H:i'); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <span class="permission-badge permission-<?php echo $membro['permissao_nivel']; ?>">
                                <?php
                                $permissaoLabels = [
                                    'gerenciar' => 'Gerente',
                                    'criar' => 'Criador',
                                    'visualizar' => 'Visualizador'
                                ];
                                echo $permissaoLabels[$membro['permissao_nivel']];
                                ?>
                            </span>
                        </div>
                        
                        <?php if ($minhaPermissao === 'gerenciar' && $membro['usuario_id'] != $_SESSION['user_id']): ?>
                            <div class="member-actions">
                                <div class="action-group">
                                    <div class="action-label">Cargo</div>
                                    <form method="POST" class="action-input">
                                        <input type="hidden" name="action" value="alterar_cargo">
                                        <input type="hidden" name="membro_id" value="<?php echo $membro['id']; ?>">
                                        <input type="text" name="cargo" value="<?php echo htmlspecialchars($membro['cargo'] ?: ''); ?>" 
                                               placeholder="Ex: Gerente de Locação">
                                        <button type="submit" class="btn btn-sm btn-secondary">Salvar</button>
                                    </form>
                                </div>
                                
                                <div class="action-group">
                                    <div class="action-label">Permissão</div>
                                    <form method="POST" class="action-input">
                                        <input type="hidden" name="action" value="alterar_permissao">
                                        <input type="hidden" name="membro_id" value="<?php echo $membro['id']; ?>">
                                        <select name="permissao" onchange="this.form.submit()">
                                            <option value="visualizar" <?php echo $membro['permissao_nivel'] === 'visualizar' ? 'selected' : ''; ?>>
                                                Visualizar
                                            </option>
                                            <option value="criar" <?php echo $membro['permissao_nivel'] === 'criar' ? 'selected' : ''; ?>>
                                                Criar
                                            </option>
                                            <option value="gerenciar" <?php echo $membro['permissao_nivel'] === 'gerenciar' ? 'selected' : ''; ?>>
                                                Gerenciar
                                            </option>
                                        </select>
                                    </form>
                                </div>
                                
                                <div class="action-group">
                                    <div class="action-label">&nbsp;</div>
                                    <form method="POST" class="action-input" 
                                          onsubmit="return confirm('Tem certeza que deseja remover este membro da equipe?')">
                                        <input type="hidden" name="action" value="remover_membro">
                                        <input type="hidden" name="membro_id" value="<?php echo $membro['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Remover</button>
                                    </form>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <!-- Legenda de Permissões -->
        <div style="margin-top: 40px; padding: 20px; background: #f7fafc; border-radius: 8px;">
            <h3 style="font-size: 1.1rem; margin-bottom: 15px; color: #1a365d;">Níveis de Permissão</h3>
            <div style="display: grid; gap: 10px; font-size: 0.95rem; color: #4a5568;">
                <div>
                    <strong style="color: #92400e;">Gerenciar:</strong> 
                    Acesso total - pode criar disputas, gerenciar equipe, visualizar relatórios e configurar a empresa.
                </div>
                <div>
                    <strong style="color: #6b21a8;">Criar:</strong> 
                    Pode criar e gerenciar disputas, visualizar relatórios, mas não pode gerenciar a equipe.
                </div>
                <div>
                    <strong style="color: #3730a3;">Visualizar:</strong> 
                    Apenas visualização - pode ver disputas e relatórios, mas não pode criar ou editar.
                </div>
            </div>
        </div>
    </main>
</body>
</html>