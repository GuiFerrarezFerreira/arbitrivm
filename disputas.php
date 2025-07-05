<?php
require_once 'config.php';

requireLogin();

$db = getDBConnection();

// Filtros
$filtroStatus = $_GET['status'] ?? '';
$filtroTipo = $_GET['tipo'] ?? '';
$filtroPeriodo = $_GET['periodo'] ?? '';
$filtroBusca = $_GET['busca'] ?? '';

// Construir query base
$query = "SELECT d.*, td.nome as tipo_nome, td.slug as tipo_slug,
                 u1.nome_completo as reclamante_nome,
                 u2.nome_completo as reclamado_nome,
                 u3.nome_completo as arbitro_nome,
                 e.nome_fantasia as empresa_nome
          FROM disputas d
          JOIN tipos_disputa td ON d.tipo_disputa_id = td.id
          JOIN usuarios u1 ON d.reclamante_id = u1.id
          LEFT JOIN usuarios u2 ON d.reclamado_id = u2.id
          LEFT JOIN arbitros a ON d.arbitro_id = a.id
          LEFT JOIN usuarios u3 ON a.usuario_id = u3.id
          LEFT JOIN empresas e ON d.empresa_id = e.id
          WHERE 1=1";

$params = [];

// Aplicar filtros baseados no tipo de usuário
switch ($_SESSION['user_type']) {
    case 'empresa':
        $query .= " AND d.empresa_id = ?";
        $params[] = $_SESSION['empresa_id'];
        break;
        
    case 'arbitro':
        $query .= " AND d.arbitro_id = ?";
        $params[] = $_SESSION['arbitro_id'];
        break;
        
    case 'solicitante':
        $query .= " AND (d.reclamante_id = ? OR d.reclamado_id = ?)";
        $params[] = $_SESSION['user_id'];
        $params[] = $_SESSION['user_id'];
        break;
}

// Aplicar filtros adicionais
if ($filtroStatus) {
    $query .= " AND d.status = ?";
    $params[] = $filtroStatus;
}

if ($filtroTipo) {
    $query .= " AND d.tipo_disputa_id = ?";
    $params[] = $filtroTipo;
}

if ($filtroPeriodo) {
    switch ($filtroPeriodo) {
        case 'hoje':
            $query .= " AND DATE(d.data_abertura) = CURDATE()";
            break;
        case 'semana':
            $query .= " AND d.data_abertura >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
            break;
        case 'mes':
            $query .= " AND d.data_abertura >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
            break;
        case 'ano':
            $query .= " AND YEAR(d.data_abertura) = YEAR(CURDATE())";
            break;
    }
}

if ($filtroBusca) {
    $query .= " AND (d.codigo_caso LIKE ? OR d.descricao LIKE ? OR u1.nome_completo LIKE ? OR u2.nome_completo LIKE ?)";
    $searchTerm = "%$filtroBusca%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$query .= " ORDER BY d.data_abertura DESC";

// Executar query
$stmt = $db->prepare($query);
$stmt->execute($params);
$disputas = $stmt->fetchAll();

// Buscar tipos de disputa para filtro
$tiposDisputa = $db->query("SELECT * FROM tipos_disputa WHERE ativo = 1 ORDER BY nome")->fetchAll();

// Estatísticas rápidas
$statsQuery = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'triagem' THEN 1 ELSE 0 END) as triagem,
    SUM(CASE WHEN status = 'em_andamento' THEN 1 ELSE 0 END) as em_andamento,
    SUM(CASE WHEN status = 'finalizada' THEN 1 ELSE 0 END) as finalizada
FROM disputas d WHERE 1=1";

if ($_SESSION['user_type'] === 'empresa') {
    $statsQuery .= " AND d.empresa_id = " . $_SESSION['empresa_id'];
} elseif ($_SESSION['user_type'] === 'arbitro') {
    $statsQuery .= " AND d.arbitro_id = " . $_SESSION['arbitro_id'];
} elseif ($_SESSION['user_type'] === 'solicitante') {
    $statsQuery .= " AND (d.reclamante_id = " . $_SESSION['user_id'] . " OR d.reclamado_id = " . $_SESSION['user_id'] . ")";
}

$stats = $db->query($statsQuery)->fetch();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Disputas - Arbitrivm</title>
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
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            text-align: center;
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #2b6cb0;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #718096;
            font-size: 0.9rem;
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
            margin-bottom: 20px;
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
            transition: all 0.3s ease;
        }
        
        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: #4299e1;
            box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
        }
        
        .filter-actions {
            display: flex;
            gap: 10px;
        }
        
        /* Table */
        .table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            overflow: hidden;
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
            padding: 16px;
            background-color: #f7fafc;
            color: #4a5568;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        td {
            padding: 16px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        tr:hover {
            background-color: #f7fafc;
            cursor: pointer;
        }
        
        tr:last-child td {
            border-bottom: none;
        }
        
        .dispute-code {
            font-weight: 600;
            color: #2b6cb0;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .status-triagem {
            background-color: #e0e7ff;
            color: #3730a3;
        }
        
        .status-aguardando_aceite {
            background-color: #ddd6fe;
            color: #6b21a8;
        }
        
        .status-em_andamento {
            background-color: #fef3c7;
            color: #92400e;
        }
        
        .status-aguardando_sentenca {
            background-color: #fed7aa;
            color: #c2410c;
        }
        
        .status-finalizada {
            background-color: #d1fae5;
            color: #065f46;
        }
        
        .status-cancelada {
            background-color: #fee2e2;
            color: #991b1b;
        }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
            font-size: 0.95rem;
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
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #718096;
        }
        
        .empty-state svg {
            width: 100px;
            height: 100px;
            margin-bottom: 20px;
            opacity: 0.3;
        }
        
        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 10px;
            color: #4a5568;
        }
        
        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                gap: 20px;
                align-items: flex-start;
            }
            
            .stats-row {
                grid-template-columns: 1fr 1fr;
            }
            
            .filters-row {
                grid-template-columns: 1fr;
            }
            
            .table-responsive {
                font-size: 0.9rem;
            }
            
            th, td {
                padding: 12px 8px;
            }
            
            .hide-mobile {
                display: none;
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
        <div class="page-header">
            <h1 class="page-title">Disputas</h1>
            <?php if ($_SESSION['user_type'] !== 'arbitro'): ?>
                <a href="nova-disputa.php" class="btn btn-primary">Nova Disputa</a>
            <?php endif; ?>
        </div>
        
        <!-- Estatísticas -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total']; ?></div>
                <div class="stat-label">Total de Disputas</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['triagem']; ?></div>
                <div class="stat-label">Em Triagem</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['em_andamento']; ?></div>
                <div class="stat-label">Em Andamento</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['finalizada']; ?></div>
                <div class="stat-label">Finalizadas</div>
            </div>
        </div>
        
        <!-- Filtros -->
        <div class="filters-container">
            <form method="GET" action="">
                <div class="filters-row">
                    <div class="filter-group">
                        <label for="status">Status</label>
                        <select name="status" id="status">
                            <option value="">Todos</option>
                            <option value="triagem" <?php echo $filtroStatus === 'triagem' ? 'selected' : ''; ?>>Triagem</option>
                            <option value="aguardando_aceite" <?php echo $filtroStatus === 'aguardando_aceite' ? 'selected' : ''; ?>>Aguardando Aceite</option>
                            <option value="em_andamento" <?php echo $filtroStatus === 'em_andamento' ? 'selected' : ''; ?>>Em Andamento</option>
                            <option value="aguardando_sentenca" <?php echo $filtroStatus === 'aguardando_sentenca' ? 'selected' : ''; ?>>Aguardando Sentença</option>
                            <option value="finalizada" <?php echo $filtroStatus === 'finalizada' ? 'selected' : ''; ?>>Finalizada</option>
                            <option value="cancelada" <?php echo $filtroStatus === 'cancelada' ? 'selected' : ''; ?>>Cancelada</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="tipo">Tipo de Disputa</label>
                        <select name="tipo" id="tipo">
                            <option value="">Todos</option>
                            <?php foreach ($tiposDisputa as $tipo): ?>
                                <option value="<?php echo $tipo['id']; ?>" <?php echo $filtroTipo == $tipo['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($tipo['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="periodo">Período</label>
                        <select name="periodo" id="periodo">
                            <option value="">Todos</option>
                            <option value="hoje" <?php echo $filtroPeriodo === 'hoje' ? 'selected' : ''; ?>>Hoje</option>
                            <option value="semana" <?php echo $filtroPeriodo === 'semana' ? 'selected' : ''; ?>>Última Semana</option>
                            <option value="mes" <?php echo $filtroPeriodo === 'mes' ? 'selected' : ''; ?>>Último Mês</option>
                            <option value="ano" <?php echo $filtroPeriodo === 'ano' ? 'selected' : ''; ?>>Este Ano</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="busca">Buscar</label>
                        <input type="text" name="busca" id="busca" placeholder="Código, nome ou descrição..." 
                               value="<?php echo htmlspecialchars($filtroBusca); ?>">
                    </div>
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                    <a href="disputas.php" class="btn btn-secondary">Limpar</a>
                </div>
            </form>
        </div>
        
        <!-- Tabela de Disputas -->
        <div class="table-container">
            <?php if (empty($disputas)): ?>
                <div class="empty-state">
                    <svg viewBox="0 0 24 24" fill="currentColor">
                        <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/>
                        <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 1 1 0 000 2H6a2 2 0 00-2 2v6a2 2 0 002 2V5zM3 19a1 1 0 011-1h16a1 1 0 110 2H4a1 1 0 01-1-1z"/>
                        <path fill-rule="evenodd" d="M4 5a2 2 0 012-2h8a2 2 0 012 2v10a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm12 0a2 2 0 012-2h2a2 2 0 012 2v10a2 2 0 01-2 2h-2a2 2 0 01-2-2V5z" clip-rule="evenodd"/>
                    </svg>
                    <h3>Nenhuma disputa encontrada</h3>
                    <p>Ajuste os filtros ou crie uma nova disputa.</p>
                    <?php if ($_SESSION['user_type'] !== 'arbitro'): ?>
                        <a href="nova-disputa.php" class="btn btn-primary" style="margin-top: 20px;">Criar Nova Disputa</a>
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
                                <th class="hide-mobile">Reclamado</th>
                                <th>Status</th>
                                <th class="hide-mobile">Valor</th>
                                <th>Data</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($disputas as $disputa): ?>
                                <tr onclick="window.location.href='disputa-detalhes.php?id=<?php echo $disputa['id']; ?>'">
                                    <td class="dispute-code"><?php echo htmlspecialchars($disputa['codigo_caso']); ?></td>
                                    <td><?php echo htmlspecialchars($disputa['tipo_nome']); ?></td>
                                    <td><?php echo htmlspecialchars($disputa['reclamante_nome']); ?></td>
                                    <td class="hide-mobile"><?php echo htmlspecialchars($disputa['reclamado_nome'] ?: 'Aguardando'); ?></td>
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
                                    <td class="hide-mobile"><?php echo formatMoney($disputa['valor_causa']); ?></td>
                                    <td><?php echo formatDate($disputa['data_abertura']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>