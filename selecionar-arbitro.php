<?php
require_once 'config.php';

requireLogin();

// Verificar se tem uma disputa em processo
if (!isset($_GET['disputa_id'])) {
    header("Location: disputas.php");
    exit();
}

$disputaId = intval($_GET['disputa_id']);
$db = getDBConnection();

// Verificar se a disputa existe e o usuário tem acesso
$stmt = $db->prepare("
    SELECT d.*, td.nome as tipo_nome, td.categoria
    FROM disputas d
    JOIN tipos_disputa td ON d.tipo_disputa_id = td.id
    WHERE d.id = ? AND d.status IN ('triagem', 'aguardando_aceite')
");
$stmt->execute([$disputaId]);
$disputa = $stmt->fetch();

if (!$disputa) {
    header("Location: disputas.php");
    exit();
}

// Verificar permissão
$temPermissao = false;
if ($_SESSION['user_type'] === 'admin') {
    $temPermissao = true;
} elseif ($_SESSION['user_type'] === 'empresa' && $disputa['empresa_id'] == $_SESSION['empresa_id']) {
    $temPermissao = true;
} elseif ($_SESSION['user_type'] === 'solicitante' && $disputa['reclamante_id'] == $_SESSION['user_id']) {
    $temPermissao = true;
}

if (!$temPermissao) {
    header("HTTP/1.1 403 Forbidden");
    die("Acesso negado.");
}

// Processar seleção de árbitro
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['arbitro_id'])) {
    $arbitroId = intval($_POST['arbitro_id']);
    
    try {
        $db->beginTransaction();
        
        // Verificar se o árbitro está ativo
        $stmt = $db->prepare("
            SELECT a.id FROM arbitros a
            JOIN usuarios u ON a.usuario_id = u.id
            WHERE a.id = ? AND u.ativo = 1
        ");
        $stmt->execute([$arbitroId]);
        
        if ($stmt->fetch()) {
            // Atualizar disputa com o árbitro selecionado
            $stmt = $db->prepare("
                UPDATE disputas 
                SET arbitro_id = ?, status = 'aguardando_aceite'
                WHERE id = ?
            ");
            $stmt->execute([$arbitroId, $disputaId]);
            
            // Registrar no histórico
            $stmt = $db->prepare("
                INSERT INTO disputa_historico (disputa_id, usuario_id, evento, descricao) 
                VALUES (?, ?, 'arbitro_selecionado', 'Árbitro selecionado para o caso')
            ");
            $stmt->execute([$disputaId, $_SESSION['user_id']]);
            
            // Notificar árbitro
            $stmt = $db->prepare("SELECT usuario_id FROM arbitros WHERE id = ?");
            $stmt->execute([$arbitroId]);
            $arbitroUserId = $stmt->fetchColumn();
            
            createNotification($arbitroUserId, 'nova_disputa_triagem', 
                'Nova Disputa para Análise', 
                "Você foi selecionado para arbitrar a disputa {$disputa['codigo_caso']}",
                "disputa-detalhes.php?id=$disputaId"
            );
            
            $db->commit();
            
            logActivity('arbitro_selecionado', "Árbitro ID $arbitroId selecionado para disputa $disputaId", $disputaId);
            
            header("Location: disputa-detalhes.php?id=$disputaId&arbitro_selecionado=1");
            exit();
        } else {
            throw new Exception("Árbitro não disponível.");
        }
        
    } catch (Exception $e) {
        $db->rollBack();
        $error = $e->getMessage();
    }
}

// Filtros
$filtroEspecializacao = $_GET['especializacao'] ?? '';
$filtroPremium = isset($_GET['premium']) ? 1 : 0;
$filtroPos = isset($_GET['pos']) ? 1 : 0;
$filtroOrdem = $_GET['ordem'] ?? 'avaliacao';

// Determinar especializações relevantes baseado no tipo de disputa
$especializacoesRelevantes = [];
switch ($disputa['categoria']) {
    case 'locacao':
        $especializacoesRelevantes = ['locacoes', 'imobiliario_geral'];
        break;
    case 'infracao_condominial':
        $especializacoesRelevantes = ['disputas_condominiais', 'infracoes', 'imobiliario_geral'];
        break;
    case 'danos':
        $especializacoesRelevantes = ['danos', 'imobiliario_geral'];
        break;
    default:
        $especializacoesRelevantes = ['imobiliario_geral'];
}

// Buscar árbitros
$query = "
    SELECT DISTINCT a.*, u.nome_completo, u.email, u.telefone,
           (SELECT COUNT(DISTINCT d.id) FROM disputas d WHERE d.arbitro_id = a.id) as total_casos,
           (SELECT COUNT(DISTINCT d.id) FROM disputas d WHERE d.arbitro_id = a.id AND d.status = 'finalizada') as casos_finalizados,
           (SELECT AVG(av.nota) FROM avaliacoes av WHERE av.arbitro_id = a.id) as avaliacao_media,
           (SELECT COUNT(av.id) FROM avaliacoes av WHERE av.arbitro_id = a.id) as total_avaliacoes,
           (SELECT GROUP_CONCAT(ae.especializacao) FROM arbitro_especializacoes ae WHERE ae.arbitro_id = a.id) as especializacoes,
           (SELECT COUNT(*) FROM arbitro_especializacoes ae WHERE ae.arbitro_id = a.id AND ae.especializacao IN ('" . implode("','", $especializacoesRelevantes) . "')) as especializacoes_relevantes
    FROM arbitros a
    JOIN usuarios u ON a.usuario_id = u.id
    WHERE u.ativo = 1";

$params = [];

if ($filtroEspecializacao) {
    $query .= " AND EXISTS (SELECT 1 FROM arbitro_especializacoes ae WHERE ae.arbitro_id = a.id AND ae.especializacao = ?)";
    $params[] = $filtroEspecializacao;
}

if ($filtroPremium) {
    $query .= " AND a.perfil_premium = 1";
}

if ($filtroPos) {
    $query .= " AND a.pos_imobiliario = 1";
}

// Ordenação
switch ($filtroOrdem) {
    case 'casos':
        $query .= " ORDER BY total_casos DESC, avaliacao_media DESC";
        break;
    case 'experiencia':
        $query .= " ORDER BY a.experiencia_anos DESC, total_casos DESC";
        break;
    case 'relevancia':
        $query .= " ORDER BY especializacoes_relevantes DESC, avaliacao_media DESC, total_casos DESC";
        break;
    default: // avaliacao
        $query .= " ORDER BY avaliacao_media DESC, total_casos DESC";
}

$stmt = $db->prepare($query);
$stmt->execute($params);
$arbitros = $stmt->fetchAll();

// Nomes das especializações
$nomesEspecializacoes = [
    'locacoes' => 'Locações',
    'disputas_condominiais' => 'Disputas Condominiais',
    'danos' => 'Danos ao Imóvel',
    'infracoes' => 'Infrações',
    'imobiliario_geral' => 'Imobiliário Geral'
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Selecionar Árbitro - Arbitrivm</title>
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
        
        /* Breadcrumb */
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
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
        
        /* Page Header */
        .page-header {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        .page-title {
            color: #1a365d;
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .page-subtitle {
            color: #718096;
            margin-bottom: 20px;
        }
        
        .disputa-info {
            background-color: #f7fafc;
            border-radius: 8px;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .disputa-info-item {
            display: flex;
            flex-direction: column;
        }
        
        .info-label {
            font-size: 0.85rem;
            color: #718096;
            margin-bottom: 5px;
        }
        
        .info-value {
            font-weight: 600;
            color: #1a365d;
        }
        
        /* Filtros */
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
        
        .filter-group select {
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
        }
        
        .filter-checkboxes {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .checkbox-group label {
            cursor: pointer;
            font-weight: normal;
        }
        
        /* Sugestões */
        .suggestions-banner {
            background: linear-gradient(135deg, #ebf8ff 0%, #ddd6fe 100%);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .suggestions-icon {
            font-size: 2rem;
        }
        
        .suggestions-text {
            flex: 1;
        }
        
        .suggestions-title {
            font-weight: 600;
            color: #1a365d;
            margin-bottom: 5px;
        }
        
        .suggestions-desc {
            color: #4a5568;
            font-size: 0.95rem;
        }
        
        /* Árbitros Grid */
        .arbitros-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }
        
        .arbitro-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            cursor: pointer;
            border: 2px solid transparent;
        }
        
        .arbitro-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border-color: #e0e7ff;
        }
        
        .arbitro-card.recommended {
            border-color: #2b6cb0;
            background: linear-gradient(to bottom, #ebf8ff 0%, white 50px);
        }
        
        .recommendation-badge {
            background-color: #2b6cb0;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 15px;
            display: inline-block;
        }
        
        .arbitro-header {
            display: flex;
            align-items: start;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .arbitro-avatar {
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
        
        .arbitro-info {
            flex: 1;
        }
        
        .arbitro-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1a365d;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .arbitro-details {
            color: #718096;
            font-size: 0.9rem;
        }
        
        .arbitro-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            padding: 15px 0;
            border-top: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
            margin: 15px 0;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-number {
            font-size: 1.25rem;
            font-weight: 600;
            color: #2b6cb0;
        }
        
        .stat-text {
            font-size: 0.8rem;
            color: #718096;
        }
        
        .arbitro-badges {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
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
        
        .especialidades-list {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }
        
        .especialidade-tag {
            background-color: #e0e7ff;
            color: #3730a3;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
        }
        
        .especialidade-tag.relevante {
            background-color: #2b6cb0;
            color: white;
        }
        
        .arbitro-actions {
            display: flex;
            gap: 10px;
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
            flex: 1;
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
            .arbitros-grid {
                grid-template-columns: 1fr;
            }
            
            .filters-row {
                grid-template-columns: 1fr;
            }
            
            .arbitro-stats {
                grid-template-columns: repeat(3, 1fr);
                font-size: 0.9rem;
            }
            
            .arbitro-actions {
                flex-direction: column;
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
        <!-- Breadcrumb -->
        <div class="breadcrumb">
            <a href="index.php">Dashboard</a>
            <span>›</span>
            <a href="disputas.php">Disputas</a>
            <span>›</span>
            <a href="disputa-detalhes.php?id=<?php echo $disputaId; ?>">
                <?php echo htmlspecialchars($disputa['codigo_caso']); ?>
            </a>
            <span>›</span>
            <span>Selecionar Árbitro</span>
        </div>
        
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">Selecionar Árbitro</h1>
            <p class="page-subtitle">
                Escolha o árbitro mais adequado para sua disputa baseado em experiência e especialização
            </p>
            
            <div class="disputa-info">
                <div class="disputa-info-item">
                    <span class="info-label">Código da Disputa</span>
                    <span class="info-value"><?php echo htmlspecialchars($disputa['codigo_caso']); ?></span>
                </div>
                <div class="disputa-info-item">
                    <span class="info-label">Tipo de Disputa</span>
                    <span class="info-value"><?php echo htmlspecialchars($disputa['tipo_nome']); ?></span>
                </div>
                <div class="disputa-info-item">
                    <span class="info-label">Valor da Causa</span>
                    <span class="info-value"><?php echo formatMoney($disputa['valor_causa']); ?></span>
                </div>
            </div>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <!-- Filtros -->
        <div class="filters-container">
            <form method="GET" action="">
                <input type="hidden" name="disputa_id" value="<?php echo $disputaId; ?>">
                
                <div class="filters-row">
                    <div class="filter-group">
                        <label for="especializacao">Especialização</label>
                        <select name="especializacao" id="especializacao">
                            <option value="">Todas</option>
                            <?php foreach ($nomesEspecializacoes as $key => $nome): ?>
                                <option value="<?php echo $key; ?>" 
                                        <?php echo $filtroEspecializacao === $key ? 'selected' : ''; ?>
                                        <?php echo in_array($key, $especializacoesRelevantes) ? 'style="font-weight: bold;"' : ''; ?>>
                                    <?php echo $nome; ?>
                                    <?php echo in_array($key, $especializacoesRelevantes) ? '⭐' : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="ordem">Ordenar por</label>
                        <select name="ordem" id="ordem">
                            <option value="relevancia" <?php echo $filtroOrdem === 'relevancia' ? 'selected' : ''; ?>>
                                Mais Relevantes
                            </option>
                            <option value="avaliacao" <?php echo $filtroOrdem === 'avaliacao' ? 'selected' : ''; ?>>
                                Melhor Avaliados
                            </option>
                            <option value="casos" <?php echo $filtroOrdem === 'casos' ? 'selected' : ''; ?>>
                                Mais Experientes
                            </option>
                            <option value="experiencia" <?php echo $filtroOrdem === 'experiencia' ? 'selected' : ''; ?>>
                                Anos de Experiência
                            </option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>Filtros Adicionais</label>
                        <div class="filter-checkboxes">
                            <div class="checkbox-group">
                                <input type="checkbox" name="premium" id="premium" value="1" 
                                       <?php echo $filtroPremium ? 'checked' : ''; ?>>
                                <label for="premium">Apenas Premium</label>
                            </div>
                            <div class="checkbox-group">
                                <input type="checkbox" name="pos" id="pos" value="1" 
                                       <?php echo $filtroPos ? 'checked' : ''; ?>>
                                <label for="pos">Pós em Imobiliário</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="filter-group">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-primary">Filtrar</button>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Sugestões -->
        <div class="suggestions-banner">
            <div class="suggestions-icon">💡</div>
            <div class="suggestions-text">
                <div class="suggestions-title">Árbitros Recomendados</div>
                <div class="suggestions-desc">
                    Baseado no tipo de disputa "<?php echo htmlspecialchars($disputa['tipo_nome']); ?>", 
                    destacamos árbitros com especialização relevante para seu caso.
                </div>
            </div>
        </div>
        
        <!-- Lista de Árbitros -->
        <?php if (empty($arbitros)): ?>
            <div class="empty-state">
                <h3>Nenhum árbitro encontrado</h3>
                <p>Ajuste os filtros para ver mais opções.</p>
            </div>
        <?php else: ?>
            <div class="arbitros-grid">
                <?php foreach ($arbitros as $arbitro): ?>
                    <?php 
                    $especializacoes = $arbitro['especializacoes'] ? explode(',', $arbitro['especializacoes']) : [];
                    $isRecommended = $arbitro['especializacoes_relevantes'] > 0;
                    ?>
                    <div class="arbitro-card <?php echo $isRecommended ? 'recommended' : ''; ?>">
                        <?php if ($isRecommended): ?>
                            <span class="recommendation-badge">⭐ Recomendado para seu caso</span>
                        <?php endif; ?>
                        
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
                                    OAB <?php echo htmlspecialchars($arbitro['oab_numero'] . '/' . $arbitro['oab_estado']); ?>
                                    <br>
                                    <?php echo $arbitro['experiencia_anos']; ?> anos de experiência
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($arbitro['perfil_premium'] || $arbitro['pos_imobiliario']): ?>
                            <div class="arbitro-badges">
                                <?php if ($arbitro['perfil_premium']): ?>
                                    <span class="badge badge-premium">Premium</span>
                                <?php endif; ?>
                                <?php if ($arbitro['pos_imobiliario']): ?>
                                    <span class="badge badge-pos">Pós em Imobiliário</span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="arbitro-stats">
                            <div class="stat-item">
                                <div class="stat-number"><?php echo $arbitro['total_casos'] ?: 0; ?></div>
                                <div class="stat-text">Casos</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number">
                                    <?php 
                                    echo $arbitro['total_casos'] > 0 
                                        ? round(($arbitro['casos_finalizados'] / $arbitro['total_casos']) * 100) . '%'
                                        : 'N/A';
                                    ?>
                                </div>
                                <div class="stat-text">Resolução</div>
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
                        
                        <?php if (!empty($especializacoes)): ?>
                            <div class="especialidades-list">
                                <?php foreach ($especializacoes as $esp): ?>
                                    <span class="especialidade-tag <?php echo in_array($esp, $especializacoesRelevantes) ? 'relevante' : ''; ?>">
                                        <?php echo $nomesEspecializacoes[$esp] ?? $esp; ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="arbitro-actions">
                            <a href="perfil-arbitro.php?id=<?php echo $arbitro['id']; ?>" 
                               target="_blank" class="btn btn-secondary">
                                Ver Perfil
                            </a>
                            <form method="POST" style="flex: 1;">
                                <input type="hidden" name="arbitro_id" value="<?php echo $arbitro['id']; ?>">
                                <button type="submit" class="btn btn-primary" style="width: 100%;">
                                    Selecionar
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
    
    <script>
        // Auto-submit form when filters change
        document.getElementById('especializacao').addEventListener('change', function() {
            this.form.submit();
        });
        
        document.getElementById('ordem').addEventListener('change', function() {
            this.form.submit();
        });
        
        document.getElementById('premium').addEventListener('change', function() {
            this.form.submit();
        });
        
        document.getElementById('pos').addEventListener('change', function() {
            this.form.submit();
        });
    </script>
</body>
</html>