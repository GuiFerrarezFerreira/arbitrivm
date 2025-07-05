<?php
require_once 'config.php';

// Verificar se está logado
requireLogin();

$userInfo = getUserInfo();
$db = getDBConnection();

// Estatísticas do dashboard baseadas no tipo de usuário
$stats = [];

switch ($_SESSION['user_type']) {
    case 'admin':
        // Estatísticas para admin
        $stmt = $db->query("SELECT COUNT(*) FROM usuarios WHERE ativo = 1");
        $stats['total_usuarios'] = $stmt->fetchColumn();
        
        $stmt = $db->query("SELECT COUNT(*) FROM disputas");
        $stats['total_disputas'] = $stmt->fetchColumn();
        
        $stmt = $db->query("SELECT COUNT(*) FROM disputas WHERE status = 'em_andamento'");
        $stats['disputas_ativas'] = $stmt->fetchColumn();
        
        $stmt = $db->query("SELECT COUNT(*) FROM empresas");
        $stats['total_empresas'] = $stmt->fetchColumn();
        break;
        
    case 'empresa':
        // Estatísticas para empresa
        $empresaId = $_SESSION['empresa_id'];
        
        $stmt = $db->prepare("SELECT COUNT(*) FROM disputas WHERE empresa_id = ?");
        $stmt->execute([$empresaId]);
        $stats['total_disputas'] = $stmt->fetchColumn();
        
        $stmt = $db->prepare("SELECT COUNT(*) FROM disputas WHERE empresa_id = ? AND status = 'em_andamento'");
        $stmt->execute([$empresaId]);
        $stats['disputas_ativas'] = $stmt->fetchColumn();
        
        $stmt = $db->prepare("SELECT COUNT(*) FROM disputas WHERE empresa_id = ? AND status = 'finalizada'");
        $stmt->execute([$empresaId]);
        $stats['disputas_finalizadas'] = $stmt->fetchColumn();
        
        $stmt = $db->prepare("SELECT AVG(DATEDIFF(data_finalizacao, data_abertura)) FROM disputas WHERE empresa_id = ? AND status = 'finalizada'");
        $stmt->execute([$empresaId]);
        $stats['tempo_medio'] = round($stmt->fetchColumn() ?: 0);
        break;
        
    case 'arbitro':
        // Estatísticas para árbitro
        $arbitroId = $_SESSION['arbitro_id'];
        
        $stmt = $db->prepare("SELECT COUNT(*) FROM disputas WHERE arbitro_id = ?");
        $stmt->execute([$arbitroId]);
        $stats['total_casos'] = $stmt->fetchColumn();
        
        $stmt = $db->prepare("SELECT COUNT(*) FROM disputas WHERE arbitro_id = ? AND status = 'em_andamento'");
        $stmt->execute([$arbitroId]);
        $stats['casos_ativos'] = $stmt->fetchColumn();
        
        $stmt = $db->prepare("SELECT COUNT(*) FROM disputas WHERE arbitro_id = ? AND status = 'finalizada'");
        $stmt->execute([$arbitroId]);
        $stats['casos_finalizados'] = $stmt->fetchColumn();
        
        $stmt = $db->prepare("SELECT AVG(nota) FROM avaliacoes WHERE arbitro_id = ?");
        $stmt->execute([$arbitroId]);
        $stats['avaliacao_media'] = number_format($stmt->fetchColumn() ?: 0, 1);
        break;
        
    case 'solicitante':
        // Estatísticas para solicitante
        $userId = $_SESSION['user_id'];
        
        $stmt = $db->prepare("SELECT COUNT(*) FROM disputas WHERE reclamante_id = ? OR reclamado_id = ?");
        $stmt->execute([$userId, $userId]);
        $stats['total_disputas'] = $stmt->fetchColumn();
        
        $stmt = $db->prepare("SELECT COUNT(*) FROM disputas WHERE (reclamante_id = ? OR reclamado_id = ?) AND status = 'em_andamento'");
        $stmt->execute([$userId, $userId]);
        $stats['disputas_ativas'] = $stmt->fetchColumn();
        break;
}

// Buscar notificações não lidas
$stmt = $db->prepare("SELECT COUNT(*) FROM notificacoes WHERE usuario_id = ? AND lida = 0");
$stmt->execute([$_SESSION['user_id']]);
$notificacoesNaoLidas = $stmt->fetchColumn();

// Buscar disputas recentes
$disputasQuery = "SELECT d.*, td.nome as tipo_nome, 
                         u1.nome_completo as reclamante_nome,
                         u2.nome_completo as reclamado_nome
                  FROM disputas d
                  JOIN tipos_disputa td ON d.tipo_disputa_id = td.id
                  JOIN usuarios u1 ON d.reclamante_id = u1.id
                  LEFT JOIN usuarios u2 ON d.reclamado_id = u2.id";

if ($_SESSION['user_type'] === 'empresa') {
    $disputasQuery .= " WHERE d.empresa_id = " . $_SESSION['empresa_id'];
} elseif ($_SESSION['user_type'] === 'arbitro') {
    $disputasQuery .= " WHERE d.arbitro_id = " . $_SESSION['arbitro_id'];
} elseif ($_SESSION['user_type'] === 'solicitante') {
    $disputasQuery .= " WHERE d.reclamante_id = " . $_SESSION['user_id'] . " OR d.reclamado_id = " . $_SESSION['user_id'];
}

$disputasQuery .= " ORDER BY d.data_abertura DESC LIMIT 5";
$disputasRecentes = $db->query($disputasQuery)->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Arbitrivm</title>
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
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .notification-badge {
            position: relative;
        }
        
        .badge-count {
            position: absolute;
            top: -8px;
            right: -8px;
            background-color: #e53e3e;
            color: white;
            font-size: 0.75rem;
            padding: 2px 6px;
            border-radius: 10px;
            font-weight: bold;
        }
        
        /* Main Content */
        .main-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .welcome-section {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        .welcome-section h1 {
            color: #1a365d;
            margin-bottom: 10px;
        }
        
        .welcome-section p {
            color: #718096;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
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
        
        /* Quick Actions */
        .quick-actions {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        .section-title {
            font-size: 1.25rem;
            color: #1a365d;
            margin-bottom: 20px;
            font-weight: 600;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
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
        
        /* Recent Disputes Table */
        .recent-disputes {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            text-align: left;
            padding: 12px;
            color: #4a5568;
            font-weight: 600;
            border-bottom: 2px solid #e2e8f0;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        td {
            padding: 16px 12px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        tr:hover {
            background-color: #f7fafc;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .status-triagem {
            background-color: #bee3f8;
            color: #2c5282;
        }
        
        .status-em_andamento {
            background-color: #fbd38d;
            color: #975a16;
        }
        
        .status-finalizada {
            background-color: #c6f6d5;
            color: #22543d;
        }
        
        .status-cancelada {
            background-color: #fed7d7;
            color: #742a2a;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #718096;
        }
        
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 20px;
            }
            
            .nav-menu {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
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
                    <?php if ($_SESSION['user_type'] === 'admin'): ?>
                        <li><a href="usuarios.php">Usuários</a></li>
                        <li><a href="empresas.php">Empresas</a></li>
                        <li><a href="arbitros.php">Árbitros</a></li>
                    <?php endif; ?>
                    <li><a href="disputas.php">Disputas</a></li>
                    <?php if ($_SESSION['user_type'] === 'empresa'): ?>
                        <li><a href="equipe.php">Equipe</a></li>
                        <li><a href="relatorios.php">Relatórios</a></li>
                    <?php endif; ?>
                    <?php if ($_SESSION['user_type'] === 'arbitro'): ?>
                        <li><a href="perfil-arbitro.php">Meu Perfil</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
            
            <div class="user-info">
                <div class="notification-badge">
                    <a href="notificacoes.php" style="color: white;">
                        <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a3 3 0 01-3-3h6a3 3 0 01-3 3z"></path>
                        </svg>
                        <?php if ($notificacoesNaoLidas > 0): ?>
                            <span class="badge-count"><?php echo $notificacoesNaoLidas; ?></span>
                        <?php endif; ?>
                    </a>
                </div>
                <span><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                <a href="logout.php" style="color: white;">Sair</a>
            </div>
        </div>
    </header>
    
    <main class="main-container">
        <section class="welcome-section">
            <h1>Bem-vindo, <?php echo htmlspecialchars(explode(' ', $_SESSION['user_name'])[0]); ?>!</h1>
            <p>
                <?php
                switch ($_SESSION['user_type']) {
                    case 'admin':
                        echo "Gerencie a plataforma e acompanhe todas as disputas.";
                        break;
                    case 'empresa':
                        echo "Acompanhe suas disputas e gerencie sua equipe.";
                        break;
                    case 'arbitro':
                        echo "Gerencie seus casos e acompanhe suas avaliações.";
                        break;
                    case 'solicitante':
                        echo "Acompanhe suas disputas e crie novas solicitações.";
                        break;
                }
                ?>
            </p>
        </section>
        
        <div class="stats-grid">
            <?php if ($_SESSION['user_type'] === 'admin'): ?>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['total_usuarios']; ?></div>
                    <div class="stat-label">Total de Usuários</div>
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
                    <div class="stat-value"><?php echo $stats['total_empresas']; ?></div>
                    <div class="stat-label">Empresas Cadastradas</div>
                </div>
            <?php elseif ($_SESSION['user_type'] === 'empresa'): ?>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['total_disputas']; ?></div>
                    <div class="stat-label">Total de Disputas</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['disputas_ativas']; ?></div>
                    <div class="stat-label">Disputas Ativas</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['disputas_finalizadas']; ?></div>
                    <div class="stat-label">Disputas Finalizadas</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['tempo_medio']; ?> dias</div>
                    <div class="stat-label">Tempo Médio de Resolução</div>
                </div>
            <?php elseif ($_SESSION['user_type'] === 'arbitro'): ?>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['total_casos']; ?></div>
                    <div class="stat-label">Total de Casos</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['casos_ativos']; ?></div>
                    <div class="stat-label">Casos Ativos</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['casos_finalizados']; ?></div>
                    <div class="stat-label">Casos Finalizados</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['avaliacao_media']; ?> ⭐</div>
                    <div class="stat-label">Avaliação Média</div>
                </div>
            <?php elseif ($_SESSION['user_type'] === 'solicitante'): ?>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['total_disputas']; ?></div>
                    <div class="stat-label">Total de Disputas</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['disputas_ativas']; ?></div>
                    <div class="stat-label">Disputas Ativas</div>
                </div>
            <?php endif; ?>
        </div>
        
        <section class="quick-actions">
            <h2 class="section-title">Ações Rápidas</h2>
            <div class="action-buttons">
                <?php if ($_SESSION['user_type'] !== 'arbitro'): ?>
                    <a href="nova-disputa.php" class="btn btn-primary">Nova Disputa</a>
                <?php endif; ?>
                <a href="disputas.php" class="btn btn-secondary">Ver Todas as Disputas</a>
                <?php if ($_SESSION['user_type'] === 'empresa'): ?>
                    <a href="convidar-membro.php" class="btn btn-secondary">Convidar Membro da Equipe</a>
                <?php endif; ?>
                <a href="perfil.php" class="btn btn-secondary">Meu Perfil</a>
            </div>
        </section>
        
        <section class="recent-disputes">
            <h2 class="section-title">Disputas Recentes</h2>
            
            <?php if (empty($disputasRecentes)): ?>
                <div class="empty-state">
                    <p>Nenhuma disputa encontrada.</p>
                    <?php if ($_SESSION['user_type'] !== 'arbitro'): ?>
                        <a href="nova-disputa.php" class="btn btn-primary" style="margin-top: 20px;">Criar Primeira Disputa</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Tipo</th>
                                <th>Reclamante</th>
                                <th>Reclamado</th>
                                <th>Status</th>
                                <th>Data</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($disputasRecentes as $disputa): ?>
                                <tr onclick="window.location.href='disputa-detalhes.php?id=<?php echo $disputa['id']; ?>'" style="cursor: pointer;">
                                    <td><?php echo htmlspecialchars($disputa['codigo_caso']); ?></td>
                                    <td><?php echo htmlspecialchars($disputa['tipo_nome']); ?></td>
                                    <td><?php echo htmlspecialchars($disputa['reclamante_nome']); ?></td>
                                    <td><?php echo htmlspecialchars($disputa['reclamado_nome'] ?: 'Aguardando'); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $disputa['status']; ?>">
                                            <?php
                                            $statusLabels = [
                                                'triagem' => 'Triagem',
                                                'aguardando_aceite' => 'Aguardando Aceite',
                                                'em_andamento' => 'Em Andamento',
                                                'aguardando_sentenca' => 'Aguardando Sentença',
                                                'finalizada' => 'Finalizada',
                                                'cancelada' => 'Cancelada'
                                            ];
                                            echo $statusLabels[$disputa['status']];
                                            ?>
                                        </span>
                                    </td>
                                    <td><?php echo formatDate($disputa['data_abertura']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>