<?php
require_once 'config.php';

// Verificar se está logado e é admin
requireLogin();
requireUserType('admin');

$db = getDBConnection();

// Filtros
$filtroEspecializacao = $_GET['especializacao'] ?? '';
$filtroStatus = $_GET['status'] ?? '';
$filtroBusca = $_GET['busca'] ?? '';

// Construir query
$query = "SELECT a.*, u.nome_completo, u.email, u.telefone, u.ativo, u.ultimo_acesso,
                 (SELECT COUNT(DISTINCT d.id) FROM disputas d WHERE d.arbitro_id = a.id) as total_casos,
                 (SELECT COUNT(DISTINCT d.id) FROM disputas d WHERE d.arbitro_id = a.id AND d.status = 'finalizada') as casos_finalizados,
                 (SELECT AVG(av.nota) FROM avaliacoes av WHERE av.arbitro_id = a.id) as avaliacao_media,
                 (SELECT GROUP_CONCAT(ae.especializacao) FROM arbitro_especializacoes ae WHERE ae.arbitro_id = a.id) as especializacoes
          FROM arbitros a
          JOIN usuarios u ON a.usuario_id = u.id
          WHERE 1=1";

$params = [];

if ($filtroEspecializacao) {
    $query .= " AND EXISTS (SELECT 1 FROM arbitro_especializacoes ae WHERE ae.arbitro_id = a.id AND ae.especializacao = ?)";
    $params[] = $filtroEspecializacao;
}

if ($filtroStatus === 'ativo') {
    $query .= " AND u.ativo = 1";
} elseif ($filtroStatus === 'inativo') {
    $query .= " AND u.ativo = 0";
} elseif ($filtroStatus === 'premium') {
    $query .= " AND a.perfil_premium = 1";
}

if ($filtroBusca) {
    $query .= " AND (u.nome_completo LIKE ? OR u.email LIKE ? OR a.oab_numero LIKE ?)";
    $searchTerm = "%$filtroBusca%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$query .= " ORDER BY u.nome_completo";

$stmt = $db->prepare($query);
$stmt->execute($params);
$arbitros = $stmt->fetchAll();

// Estatísticas gerais
$stats = $db->query("
    SELECT 
        COUNT(DISTINCT a.id) as total_arbitros,
        COUNT(DISTINCT CASE WHEN u.ativo = 1 THEN a.id END) as arbitros_ativos,
        COUNT(DISTINCT CASE WHEN a.perfil_premium = 1 THEN a.id END) as arbitros_premium,
        COUNT(DISTINCT CASE WHEN a.pos_imobiliario = 1 THEN a.id END) as com_pos_imobiliario
    FROM arbitros a
    JOIN usuarios u ON a.usuario_id = u.id
")->fetch();

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $arbitroId = intval($_POST['arbitro_id'] ?? 0);
    
    switch ($action) {
        case 'toggle_status':
            $stmt = $db->prepare("
                UPDATE usuarios u 
                JOIN arbitros a ON a.usuario_id = u.id 
                SET u.ativo = NOT u.ativo 
                WHERE a.id = ?
            ");
            $stmt->execute([$arbitroId]);
            break;
            
        case 'toggle_premium':
            $stmt = $db->prepare("UPDATE arbitros SET perfil_premium = NOT perfil_premium WHERE id = ?");
            $stmt->execute([$arbitroId]);
            break;
    }
    
    header("Location: arbitros.php?success=1");
    exit();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Árbitros - Arbitrivm</title>
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
        
        /* Stats Cards */
        .stats-row {
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
        
        /* Filters */
        .filters-container {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        .filters-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        
        .filter-group label {
            color: #4a5568;
            font-weight: 500;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }
        
        .filter-group select,
        .filter-group input {
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
        }
        
        /* Arbitros Grid */
        .arbitros-grid {
            display: grid;
            gap: 20px;
        }
        
        .arbitro-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .arbitro-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .arbitro-header {
            display: flex;
            align-items: start;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .arbitro-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background-color: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 600;
            color: #4a5568;
            flex-shrink: 0;
        }
        
        .arbitro-info {
            flex: 1;
        }
        
        .arbitro-name {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1a365d;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .arbitro-details {
            color: #718096;
            font-size: 0.95rem;
        }
        
        .arbitro-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            padding: 20px 0;
            border-top: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
            margin: 20px 0;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-number {
            font-size: 1.5rem;
            font-weight: 600;
            color: #2b6cb0;
        }
        
        .stat-text {
            font-size: 0.85rem;
            color: #718096;
        }
        
        .arbitro-badges {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .badge-premium {
            background-color: #fef3c7;
            color: #92400e;
        }
        
        .badge-pos {
            background-color: #ddd6fe;
            color: #6b21a8;
        }
        
        .badge-active {
            background-color: #d1fae5;
            color: #065f46;
        }
        
        .badge-inactive {
            background-color: #fee2e2;
            color: #991b1b;
        }
        
        .especialidades-list {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 10px;
        }
        
        .especialidade-tag {
            background-color: #e0e7ff;
            color: #3730a3;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.85rem;
        }
        
        .arbitro-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
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
        
        .btn-success {
            background-color: #48bb78;
            color: white;
        }
        
        .btn-success:hover {
            background-color: #38a169;
        }
        
        .btn-danger {
            background-color: #e53e3e;
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #c53030;
        }
        
        .rating {
            display: flex;
            align-items: center;
            gap: 5px;
            color: #f6ad55;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                gap: 20px;
                align-items: flex-start;
            }
            
            .filters-row {
                grid-template-columns: 1fr;
            }
            
            .arbitro-header {
                flex-direction: column;
                text-align: center;
            }
            
            .arbitro-actions {
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
                    <li><a href="usuarios.php">Usuários</a></li>
                    <li><a href="empresas.php">Empresas</a></li>
                    <li><a href="arbitros.php">Árbitros</a></li>
                    <li><a href="disputas.php">Disputas</a></li>
                    <li><a href="logout.php">Sair</a></li>
                </ul>
            </nav>
        </div>
    </header>
    
    <main class="main-container">
        <div class="page-header">
            <h1 class="page-title">Gestão de Árbitros</h1>
        </div>
        
        <!-- Estatísticas -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['total_arbitros']; ?></div>
                <div class="stat-label">Total de Árbitros</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['arbitros_ativos']; ?></div>
                <div class="stat-label">Árbitros Ativos</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['arbitros_premium']; ?></div>
                <div class="stat-label">Perfis Premium</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['com_pos_imobiliario']; ?></div>
                <div class="stat-label">Pós em Imobiliário</div>
            </div>
        </div>
        
        <!-- Filtros -->
        <div class="filters-container">
            <form method="GET" action="">
                <div class="filters-row">
                    <div class="filter-group">
                        <label for="especializacao">Especialização</label>
                        <select name="especializacao" id="especializacao">
                            <option value="">Todas</option>
                            <option value="locacoes" <?php echo $filtroEspecializacao === 'locacoes' ? 'selected' : ''; ?>>Locações</option>
                            <option value="disputas_condominiais" <?php echo $filtroEspecializacao === 'disputas_condominiais' ? 'selected' : ''; ?>>Disputas Condominiais</option>
                            <option value="danos" <?php echo $filtroEspecializacao === 'danos' ? 'selected' : ''; ?>>Danos ao Imóvel</option>
                            <option value="infracoes" <?php echo $filtroEspecializacao === 'infracoes' ? 'selected' : ''; ?>>Infrações</option>
                            <option value="imobiliario_geral" <?php echo $filtroEspecializacao === 'imobiliario_geral' ? 'selected' : ''; ?>>Imobiliário Geral</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="status">Status</label>
                        <select name="status" id="status">
                            <option value="">Todos</option>
                            <option value="ativo" <?php echo $filtroStatus === 'ativo' ? 'selected' : ''; ?>>Ativos</option>
                            <option value="inativo" <?php echo $filtroStatus === 'inativo' ? 'selected' : ''; ?>>Inativos</option>
                            <option value="premium" <?php echo $filtroStatus === 'premium' ? 'selected' : ''; ?>>Premium</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="busca">Buscar</label>
                        <input type="text" name="busca" id="busca" placeholder="Nome, email ou OAB..." 
                               value="<?php echo htmlspecialchars($filtroBusca); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-primary">Filtrar</button>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Lista de Árbitros -->
        <?php if (empty($arbitros)): ?>
            <div class="empty-state">
                <h3>Nenhum árbitro encontrado</h3>
                <p>Ajuste os filtros ou aguarde novos cadastros.</p>
            </div>
        <?php else: ?>
            <div class="arbitros-grid">
                <?php foreach ($arbitros as $arbitro): ?>
                    <div class="arbitro-card">
                        <div class="arbitro-header">
                            <div class="arbitro-avatar">
                                <?php 
                                if ($arbitro['foto_perfil']) {
                                    echo '<img src="' . htmlspecialchars($arbitro['foto_perfil']) . '" alt="Foto" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">';
                                } else {
                                    echo strtoupper(substr($arbitro['nome_completo'], 0, 1));
                                }
                                ?>
                            </div>
                            <div class="arbitro-info">
                                <h3 class="arbitro-name">
                                    <?php echo htmlspecialchars($arbitro['nome_completo']); ?>
                                    <?php if ($arbitro['perfil_premium']): ?>
                                        <span style="color: #f6ad55;">⭐</span>
                                    <?php endif; ?>
                                </h3>
                                <div class="arbitro-details">
                                    <p>OAB: <?php echo htmlspecialchars($arbitro['oab_numero'] . '/' . $arbitro['oab_estado']); ?></p>
                                    <p><?php echo htmlspecialchars($arbitro['email']); ?></p>
                                    <?php if ($arbitro['telefone']): ?>
                                        <p><?php echo htmlspecialchars($arbitro['telefone']); ?></p>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="arbitro-badges">
                                    <?php if ($arbitro['ativo']): ?>
                                        <span class="badge badge-active">Ativo</span>
                                    <?php else: ?>
                                        <span class="badge badge-inactive">Inativo</span>
                                    <?php endif; ?>
                                    
                                    <?php if ($arbitro['perfil_premium']): ?>
                                        <span class="badge badge-premium">Premium</span>
                                    <?php endif; ?>
                                    
                                    <?php if ($arbitro['pos_imobiliario']): ?>
                                        <span class="badge badge-pos">Pós em Imobiliário</span>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($arbitro['especializacoes']): ?>
                                    <div class="especialidades-list">
                                        <?php 
                                        $especializacoes = explode(',', $arbitro['especializacoes']);
                                        $nomes = [
                                            'locacoes' => 'Locações',
                                            'disputas_condominiais' => 'Disputas Condominiais',
                                            'danos' => 'Danos ao Imóvel',
                                            'infracoes' => 'Infrações',
                                            'imobiliario_geral' => 'Imobiliário Geral'
                                        ];
                                        foreach ($especializacoes as $esp): 
                                        ?>
                                            <span class="especialidade-tag">
                                                <?php echo $nomes[$esp] ?? $esp; ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="arbitro-stats">
                            <div class="stat-item">
                                <div class="stat-number"><?php echo $arbitro['total_casos'] ?: 0; ?></div>
                                <div class="stat-text">Casos Total</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number"><?php echo $arbitro['casos_finalizados'] ?: 0; ?></div>
                                <div class="stat-text">Finalizados</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number">
                                    <?php 
                                    $taxa = $arbitro['total_casos'] > 0 
                                        ? round(($arbitro['casos_finalizados'] / $arbitro['total_casos']) * 100) 
                                        : 0;
                                    echo $taxa . '%';
                                    ?>
                                </div>
                                <div class="stat-text">Taxa Sucesso</div>
                            </div>
                            <div class="stat-item">
                                <div class="rating">
                                    <div class="stat-number">
                                        <?php echo $arbitro['avaliacao_media'] ? number_format($arbitro['avaliacao_media'], 1) : 'N/A'; ?>
                                    </div>
                                    <span>⭐</span>
                                </div>
                                <div class="stat-text">Avaliação</div>
                            </div>
                        </div>
                        
                        <div class="arbitro-actions">
                            <a href="perfil-arbitro.php?id=<?php echo $arbitro['id']; ?>" class="btn btn-primary">
                                Ver Perfil
                            </a>
                            
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="arbitro_id" value="<?php echo $arbitro['id']; ?>">
                                <button type="submit" class="btn <?php echo $arbitro['ativo'] ? 'btn-danger' : 'btn-success'; ?>">
                                    <?php echo $arbitro['ativo'] ? 'Desativar' : 'Ativar'; ?>
                                </button>
                            </form>
                            
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="toggle_premium">
                                <input type="hidden" name="arbitro_id" value="<?php echo $arbitro['id']; ?>">
                                <button type="submit" class="btn btn-secondary">
                                    <?php echo $arbitro['perfil_premium'] ? 'Remover Premium' : 'Tornar Premium'; ?>
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>