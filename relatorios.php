<?php
require_once 'config.php';

// Verificar se está logado e é empresa
requireLogin();
requireUserType('empresa');

$db = getDBConnection();
$empresaId = $_SESSION['empresa_id'];

// Período padrão - últimos 30 dias
$periodoInicio = $_GET['inicio'] ?? date('Y-m-d', strtotime('-30 days'));
$periodoFim = $_GET['fim'] ?? date('Y-m-d');

// Validar datas
if (strtotime($periodoInicio) > strtotime($periodoFim)) {
    $temp = $periodoInicio;
    $periodoInicio = $periodoFim;
    $periodoFim = $temp;
}

// Buscar estatísticas gerais
$stmt = $db->prepare("
    SELECT 
        COUNT(DISTINCT d.id) as total_casos,
        COUNT(DISTINCT CASE WHEN d.status = 'finalizada' THEN d.id END) as casos_resolvidos,
        COUNT(DISTINCT CASE WHEN d.status = 'cancelada' THEN d.id END) as casos_cancelados,
        COUNT(DISTINCT CASE WHEN d.status IN ('triagem', 'aguardando_aceite', 'em_andamento', 'aguardando_sentenca') THEN d.id END) as casos_ativos,
        AVG(CASE WHEN d.status = 'finalizada' THEN DATEDIFF(d.data_finalizacao, d.data_abertura) END) as tempo_medio_resolucao,
        SUM(d.valor_causa) as valor_total_disputas,
        COUNT(DISTINCT d.reclamante_id) as total_reclamantes,
        COUNT(DISTINCT d.arbitro_id) as total_arbitros
    FROM disputas d
    WHERE d.empresa_id = ? 
    AND DATE(d.data_abertura) BETWEEN ? AND ?
");
$stmt->execute([$empresaId, $periodoInicio, $periodoFim]);
$stats = $stmt->fetch();

// Calcular taxa de resolução
$taxaResolucao = $stats['total_casos'] > 0 
    ? round(($stats['casos_resolvidos'] / $stats['total_casos']) * 100, 1) 
    : 0;

// Buscar disputas por tipo
$stmt = $db->prepare("
    SELECT 
        td.nome as tipo_nome,
        COUNT(d.id) as quantidade,
        AVG(CASE WHEN d.status = 'finalizada' THEN DATEDIFF(d.data_finalizacao, d.data_abertura) END) as tempo_medio,
        SUM(d.valor_causa) as valor_total
    FROM disputas d
    JOIN tipos_disputa td ON d.tipo_disputa_id = td.id
    WHERE d.empresa_id = ? 
    AND DATE(d.data_abertura) BETWEEN ? AND ?
    GROUP BY td.id, td.nome
    ORDER BY quantidade DESC
");
$stmt->execute([$empresaId, $periodoInicio, $periodoFim]);
$disputasPorTipo = $stmt->fetchAll();

// Buscar disputas por status
$stmt = $db->prepare("
    SELECT 
        d.status,
        COUNT(d.id) as quantidade
    FROM disputas d
    WHERE d.empresa_id = ? 
    AND DATE(d.data_abertura) BETWEEN ? AND ?
    GROUP BY d.status
");
$stmt->execute([$empresaId, $periodoInicio, $periodoFim]);
$disputasPorStatus = $stmt->fetchAll();

// Buscar evolução mensal
$stmt = $db->prepare("
    SELECT 
        DATE_FORMAT(d.data_abertura, '%Y-%m') as mes,
        COUNT(d.id) as total_disputas,
        COUNT(CASE WHEN d.status = 'finalizada' THEN d.id END) as disputas_resolvidas,
        SUM(d.valor_causa) as valor_total
    FROM disputas d
    WHERE d.empresa_id = ? 
    AND d.data_abertura >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(d.data_abertura, '%Y-%m')
    ORDER BY mes
");
$stmt->execute([$empresaId]);
$evolucaoMensal = $stmt->fetchAll();

// Buscar top membros da equipe
$stmt = $db->prepare("
    SELECT 
        u.nome_completo,
        eq.cargo,
        COUNT(DISTINCT d.id) as total_disputas,
        COUNT(DISTINCT CASE WHEN d.status = 'finalizada' THEN d.id END) as disputas_resolvidas,
        SUM(d.valor_causa) as valor_total
    FROM equipe_empresa eq
    JOIN usuarios u ON eq.usuario_id = u.id
    LEFT JOIN disputas d ON d.reclamante_id = u.id AND d.empresa_id = eq.empresa_id
        AND DATE(d.data_abertura) BETWEEN ? AND ?
    WHERE eq.empresa_id = ? AND eq.ativo = 1
    GROUP BY u.id, u.nome_completo, eq.cargo
    HAVING total_disputas > 0
    ORDER BY total_disputas DESC
    LIMIT 10
");
$stmt->execute([$periodoInicio, $periodoFim, $empresaId]);
$topMembros = $stmt->fetchAll();

// Buscar árbitros mais utilizados
$stmt = $db->prepare("
    SELECT 
        u.nome_completo as arbitro_nome,
        COUNT(DISTINCT d.id) as total_casos,
        AVG(CASE WHEN d.status = 'finalizada' THEN DATEDIFF(d.data_finalizacao, d.data_abertura) END) as tempo_medio,
        AVG(av.nota) as avaliacao_media
    FROM disputas d
    JOIN arbitros a ON d.arbitro_id = a.id
    JOIN usuarios u ON a.usuario_id = u.id
    LEFT JOIN avaliacoes av ON av.arbitro_id = a.id AND av.disputa_id = d.id
    WHERE d.empresa_id = ? 
    AND DATE(d.data_abertura) BETWEEN ? AND ?
    GROUP BY a.id, u.nome_completo
    ORDER BY total_casos DESC
    LIMIT 5
");
$stmt->execute([$empresaId, $periodoInicio, $periodoFim]);
$topArbitros = $stmt->fetchAll();

// Gerar dados para gráficos
$mesesLabels = [];
$mesesDisputas = [];
$mesesResolvidas = [];
$mesesValores = [];

foreach ($evolucaoMensal as $mes) {
    $mesesLabels[] = date('M/Y', strtotime($mes['mes'] . '-01'));
    $mesesDisputas[] = $mes['total_disputas'];
    $mesesResolvidas[] = $mes['disputas_resolvidas'];
    $mesesValores[] = $mes['valor_total'] ?: 0;
}

$tiposLabels = [];
$tiposQuantidades = [];
$tiposValores = [];

foreach ($disputasPorTipo as $tipo) {
    $tiposLabels[] = $tipo['tipo_nome'];
    $tiposQuantidades[] = $tipo['quantidade'];
    $tiposValores[] = $tipo['valor_total'] ?: 0;
}

// Processar exportação se solicitado
if (isset($_GET['exportar']) && $_GET['exportar'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=relatorio_arbitrivm_' . date('Y-m-d') . '.csv');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM para UTF-8
    
    // Cabeçalho do relatório
    fputcsv($output, ['RELATÓRIO DE DISPUTAS - ARBITRIVM']);
    fputcsv($output, ['Empresa: ' . $_SESSION['empresa_nome']]);
    fputcsv($output, ['Período: ' . date('d/m/Y', strtotime($periodoInicio)) . ' a ' . date('d/m/Y', strtotime($periodoFim))]);
    fputcsv($output, []);
    
    // Resumo Geral
    fputcsv($output, ['RESUMO GERAL']);
    fputcsv($output, ['Total de Casos', $stats['total_casos']]);
    fputcsv($output, ['Casos Resolvidos', $stats['casos_resolvidos']]);
    fputcsv($output, ['Taxa de Resolução (%)', $taxaResolucao]);
    fputcsv($output, ['Tempo Médio de Resolução (dias)', round($stats['tempo_medio_resolucao'] ?: 0)]);
    fputcsv($output, ['Valor Total das Disputas', 'R$ ' . number_format($stats['valor_total_disputas'] ?: 0, 2, ',', '.')]);
    fputcsv($output, []);
    
    // Disputas por Tipo
    fputcsv($output, ['DISPUTAS POR TIPO']);
    fputcsv($output, ['Tipo', 'Quantidade', 'Valor Total', 'Tempo Médio (dias)']);
    foreach ($disputasPorTipo as $tipo) {
        fputcsv($output, [
            $tipo['tipo_nome'],
            $tipo['quantidade'],
            'R$ ' . number_format($tipo['valor_total'] ?: 0, 2, ',', '.'),
            round($tipo['tempo_medio'] ?: 0)
        ]);
    }
    
    fclose($output);
    exit();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios - Arbitrivm</title>
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
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        .page-title-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .page-title {
            color: #1a365d;
            font-size: 2rem;
        }
        
        .page-actions {
            display: flex;
            gap: 10px;
        }
        
        /* Date Filter */
        .date-filter {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .date-filter-label {
            color: #4a5568;
            font-weight: 500;
        }
        
        .date-input-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .date-input {
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 0.95rem;
        }
        
        /* KPI Cards */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .kpi-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        .kpi-label {
            color: #718096;
            font-size: 0.9rem;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .kpi-value {
            font-size: 2rem;
            font-weight: 700;
            color: #1a365d;
            margin-bottom: 5px;
        }
        
        .kpi-trend {
            font-size: 0.85rem;
            color: #48bb78;
        }
        
        .kpi-trend.negative {
            color: #e53e3e;
        }
        
        /* Charts Section */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .chart-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        .chart-title {
            font-size: 1.25rem;
            color: #1a365d;
            margin-bottom: 20px;
            font-weight: 600;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
        }
        
        /* Tables */
        .table-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
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
            background-color: #f7fafc;
            color: #4a5568;
            font-weight: 600;
            font-size: 0.9rem;
            border-bottom: 2px solid #e2e8f0;
        }
        
        td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        tr:hover {
            background-color: #f7fafc;
        }
        
        .rating {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            color: #f6ad55;
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
        }
        
        .btn-secondary {
            background-color: #e2e8f0;
            color: #4a5568;
        }
        
        .btn-secondary:hover {
            background-color: #cbd5e0;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 0.85rem;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #718096;
        }
        
        .empty-state svg {
            width: 80px;
            height: 80px;
            margin-bottom: 20px;
            opacity: 0.3;
        }
        
        @media (max-width: 768px) {
            .page-title-row {
                flex-direction: column;
                gap: 20px;
                align-items: flex-start;
            }
            
            .date-filter {
                width: 100%;
            }
            
            .charts-grid {
                grid-template-columns: 1fr;
            }
            
            .chart-container {
                height: 250px;
            }
            
            .table-responsive {
                font-size: 0.9rem;
            }
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            <div class="page-title-row">
                <h1 class="page-title">Relatórios e Análises</h1>
                <div class="page-actions">
                    <a href="?exportar=csv&inicio=<?php echo $periodoInicio; ?>&fim=<?php echo $periodoFim; ?>" 
                       class="btn btn-secondary">
                        <span style="margin-right: 5px;">📊</span> Exportar CSV
                    </a>
                </div>
            </div>
            
            <form method="GET" class="date-filter">
                <span class="date-filter-label">Período:</span>
                <div class="date-input-group">
                    <input type="date" name="inicio" value="<?php echo $periodoInicio; ?>" 
                           max="<?php echo date('Y-m-d'); ?>" class="date-input">
                    <span>até</span>
                    <input type="date" name="fim" value="<?php echo $periodoFim; ?>" 
                           max="<?php echo date('Y-m-d'); ?>" class="date-input">
                    <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
                </div>
                <div class="date-input-group">
                    <a href="?inicio=<?php echo date('Y-m-d', strtotime('-7 days')); ?>&fim=<?php echo date('Y-m-d'); ?>" 
                       class="btn btn-secondary btn-sm">Última Semana</a>
                    <a href="?inicio=<?php echo date('Y-m-d', strtotime('-30 days')); ?>&fim=<?php echo date('Y-m-d'); ?>" 
                       class="btn btn-secondary btn-sm">Últimos 30 dias</a>
                    <a href="?inicio=<?php echo date('Y-m-01'); ?>&fim=<?php echo date('Y-m-d'); ?>" 
                       class="btn btn-secondary btn-sm">Este Mês</a>
                </div>
            </form>
        </div>
        
        <!-- KPIs -->
        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-label">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    Total de Casos
                </div>
                <div class="kpi-value"><?php echo $stats['total_casos']; ?></div>
                <div class="kpi-trend"><?php echo $stats['casos_ativos']; ?> ativos</div>
            </div>
            
            <div class="kpi-card">
                <div class="kpi-label">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Taxa de Resolução
                </div>
                <div class="kpi-value"><?php echo $taxaResolucao; ?>%</div>
                <div class="kpi-trend"><?php echo $stats['casos_resolvidos']; ?> resolvidos</div>
            </div>
            
            <div class="kpi-card">
                <div class="kpi-label">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12 6 12 12 16 14"></polyline>
                    </svg>
                    Tempo Médio de Resolução
                </div>
                <div class="kpi-value"><?php echo round($stats['tempo_medio_resolucao'] ?: 0); ?> dias</div>
                <div class="kpi-trend">média do período</div>
            </div>
            
            <div class="kpi-card">
                <div class="kpi-label">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Valor Total das Disputas
                </div>
                <div class="kpi-value"><?php echo formatMoney($stats['valor_total_disputas'] ?: 0); ?></div>
                <div class="kpi-trend">soma do período</div>
            </div>
        </div>
        
        <!-- Gráficos -->
        <div class="charts-grid">
            <div class="chart-card">
                <h2 class="chart-title">Evolução Mensal de Disputas</h2>
                <div class="chart-container">
                    <canvas id="evolucaoChart"></canvas>
                </div>
            </div>
            
            <div class="chart-card">
                <h2 class="chart-title">Disputas por Tipo</h2>
                <div class="chart-container">
                    <canvas id="tiposChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Tabela de Tipos de Disputa -->
        <div class="table-card">
            <h2 class="chart-title">Análise por Tipo de Disputa</h2>
            <?php if (empty($disputasPorTipo)): ?>
                <div class="empty-state">
                    <p>Nenhuma disputa encontrada no período selecionado.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Tipo de Disputa</th>
                                <th>Quantidade</th>
                                <th>Valor Total</th>
                                <th>Tempo Médio</th>
                                <th>% do Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($disputasPorTipo as $tipo): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($tipo['tipo_nome']); ?></strong></td>
                                    <td><?php echo $tipo['quantidade']; ?></td>
                                    <td><?php echo formatMoney($tipo['valor_total'] ?: 0); ?></td>
                                    <td><?php echo round($tipo['tempo_medio'] ?: 0); ?> dias</td>
                                    <td>
                                        <?php 
                                        $percentual = $stats['total_casos'] > 0 
                                            ? round(($tipo['quantidade'] / $stats['total_casos']) * 100, 1) 
                                            : 0;
                                        echo $percentual . '%';
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Top Membros da Equipe -->
        <div class="table-card">
            <h2 class="chart-title">Desempenho da Equipe</h2>
            <?php if (empty($topMembros)): ?>
                <div class="empty-state">
                    <p>Nenhum membro com disputas no período selecionado.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Membro</th>
                                <th>Cargo</th>
                                <th>Disputas Criadas</th>
                                <th>Resolvidas</th>
                                <th>Valor Total</th>
                                <th>Taxa de Resolução</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topMembros as $membro): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($membro['nome_completo']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($membro['cargo'] ?: 'Não informado'); ?></td>
                                    <td><?php echo $membro['total_disputas']; ?></td>
                                    <td><?php echo $membro['disputas_resolvidas']; ?></td>
                                    <td><?php echo formatMoney($membro['valor_total'] ?: 0); ?></td>
                                    <td>
                                        <?php 
                                        $taxa = $membro['total_disputas'] > 0 
                                            ? round(($membro['disputas_resolvidas'] / $membro['total_disputas']) * 100, 1) 
                                            : 0;
                                        echo $taxa . '%';
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Top Árbitros -->
        <div class="table-card">
            <h2 class="chart-title">Árbitros Mais Utilizados</h2>
            <?php if (empty($topArbitros)): ?>
                <div class="empty-state">
                    <p>Nenhum árbitro designado no período selecionado.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Árbitro</th>
                                <th>Casos</th>
                                <th>Tempo Médio</th>
                                <th>Avaliação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topArbitros as $arbitro): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($arbitro['arbitro_nome']); ?></strong></td>
                                    <td><?php echo $arbitro['total_casos']; ?></td>
                                    <td><?php echo round($arbitro['tempo_medio'] ?: 0); ?> dias</td>
                                    <td>
                                        <div class="rating">
                                            <?php 
                                            $nota = $arbitro['avaliacao_media'] ? round($arbitro['avaliacao_media'], 1) : 'N/A';
                                            echo $nota;
                                            if ($nota !== 'N/A') {
                                                echo ' ⭐';
                                            }
                                            ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>
    
    <script>
        // Gráfico de Evolução Mensal
        const ctxEvolucao = document.getElementById('evolucaoChart').getContext('2d');
        new Chart(ctxEvolucao, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($mesesLabels); ?>,
                datasets: [{
                    label: 'Total de Disputas',
                    data: <?php echo json_encode($mesesDisputas); ?>,
                    borderColor: '#2b6cb0',
                    backgroundColor: 'rgba(43, 108, 176, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Disputas Resolvidas',
                    data: <?php echo json_encode($mesesResolvidas); ?>,
                    borderColor: '#48bb78',
                    backgroundColor: 'rgba(72, 187, 120, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
        
        // Gráfico de Tipos de Disputa
        const ctxTipos = document.getElementById('tiposChart').getContext('2d');
        new Chart(ctxTipos, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($tiposLabels); ?>,
                datasets: [{
                    data: <?php echo json_encode($tiposQuantidades); ?>,
                    backgroundColor: [
                        '#2b6cb0',
                        '#48bb78',
                        '#f6ad55',
                        '#ed64a6',
                        '#9f7aea',
                        '#4299e1'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    </script>
</body>
</html>