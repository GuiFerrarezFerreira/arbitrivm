<?php
require_once 'config.php';

requireLogin();

if (!isset($_GET['id'])) {
    header("Location: disputas.php");
    exit();
}

$disputaId = intval($_GET['id']);
$db = getDBConnection();

// Buscar informações da disputa
$stmt = $db->prepare("
    SELECT d.*, td.nome as tipo_nome, td.slug as tipo_slug,
           u1.nome_completo as reclamante_nome, u1.email as reclamante_email, u1.telefone as reclamante_telefone,
           u2.nome_completo as reclamado_nome, u2.email as reclamado_email, u2.telefone as reclamado_telefone,
           u3.nome_completo as arbitro_nome, u3.email as arbitro_email,
           a.oab_numero, a.oab_estado,
           e.razao_social as empresa_razao, e.nome_fantasia as empresa_fantasia
    FROM disputas d
    JOIN tipos_disputa td ON d.tipo_disputa_id = td.id
    JOIN usuarios u1 ON d.reclamante_id = u1.id
    LEFT JOIN usuarios u2 ON d.reclamado_id = u2.id
    LEFT JOIN arbitros a ON d.arbitro_id = a.id
    LEFT JOIN usuarios u3 ON a.usuario_id = u3.id
    LEFT JOIN empresas e ON d.empresa_id = e.id
    WHERE d.id = ?
");
$stmt->execute([$disputaId]);
$disputa = $stmt->fetch();

if (!$disputa) {
    header("Location: disputas.php");
    exit();
}

// Verificar permissão de acesso
$temAcesso = false;
if ($_SESSION['user_type'] === 'admin') {
    $temAcesso = true;
} elseif ($_SESSION['user_type'] === 'empresa' && $disputa['empresa_id'] == $_SESSION['empresa_id']) {
    $temAcesso = true;
} elseif ($_SESSION['user_type'] === 'arbitro' && isset($_SESSION['arbitro_id']) && $disputa['arbitro_id'] == $_SESSION['arbitro_id']) {
    $temAcesso = true;
} elseif ($_SESSION['user_type'] === 'solicitante' && 
         ($disputa['reclamante_id'] == $_SESSION['user_id'] || $disputa['reclamado_id'] == $_SESSION['user_id'])) {
    $temAcesso = true;
}

if (!$temAcesso) {
    header("HTTP/1.1 403 Forbidden");
    die("Acesso negado. Você não tem permissão para visualizar esta disputa.");
}

// Buscar histórico da disputa
$stmt = $db->prepare("
    SELECT dh.*, u.nome_completo as usuario_nome
    FROM disputa_historico dh
    JOIN usuarios u ON dh.usuario_id = u.id
    WHERE dh.disputa_id = ?
    ORDER BY dh.data_evento DESC
");
$stmt->execute([$disputaId]);
$historico = $stmt->fetchAll();

// Buscar documentos anexados
$stmt = $db->prepare("
    SELECT dd.*, u.nome_completo as enviado_por
    FROM disputa_documentos dd
    JOIN usuarios u ON dd.usuario_id = u.id
    WHERE dd.disputa_id = ?
    ORDER BY dd.data_upload DESC
");
$stmt->execute([$disputaId]);
$documentos = $stmt->fetchAll();

// Buscar mensagens/comunicações
$stmt = $db->prepare("
    SELECT dm.*, u.nome_completo as remetente_nome
    FROM disputa_mensagens dm
    JOIN usuarios u ON dm.usuario_id = u.id
    WHERE dm.disputa_id = ?
    ORDER BY dm.data_envio DESC
    LIMIT 5
");
$stmt->execute([$disputaId]);
$mensagensRecentes = $stmt->fetchAll();

// Buscar informações adicionais se for infração condominial
$infracao = null;
if ($disputa['tipo_slug'] === 'infracao-condominial') {
    $stmt = $db->prepare("SELECT * FROM disputa_infracoes WHERE disputa_id = ?");
    $stmt->execute([$disputaId]);
    $infracao = $stmt->fetch();
}

// Determinar ações disponíveis baseadas no status e tipo de usuário
$acoesDisponiveis = [];
if ($_SESSION['user_type'] === 'admin' || 
    ($_SESSION['user_type'] === 'arbitro' && $disputa['arbitro_id'] == $_SESSION['arbitro_id'])) {
    
    switch ($disputa['status']) {
        case 'triagem':
            $acoesDisponiveis[] = ['action' => 'aceitar_caso', 'label' => 'Aceitar Caso', 'class' => 'btn-primary'];
            $acoesDisponiveis[] = ['action' => 'rejeitar_caso', 'label' => 'Rejeitar Caso', 'class' => 'btn-danger'];
            break;
            
        case 'em_andamento':
            $acoesDisponiveis[] = ['action' => 'solicitar_documentos', 'label' => 'Solicitar Documentos', 'class' => 'btn-secondary'];
            $acoesDisponiveis[] = ['action' => 'agendar_audiencia', 'label' => 'Agendar Audiência', 'class' => 'btn-secondary'];
            $acoesDisponiveis[] = ['action' => 'proferir_sentenca', 'label' => 'Proferir Sentença', 'class' => 'btn-primary'];
            break;
    }
}

// Processar ações POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $permitido = false;
    
    foreach ($acoesDisponiveis as $acao) {
        if ($acao['action'] === $action) {
            $permitido = true;
            break;
        }
    }
    
    if ($permitido) {
        try {
            $db->beginTransaction();
            
            switch ($action) {
                case 'aceitar_caso':
                    $stmt = $db->prepare("UPDATE disputas SET status = 'em_andamento', data_inicio = NOW() WHERE id = ?");
                    $stmt->execute([$disputaId]);
                    
                    $stmt = $db->prepare("INSERT INTO disputa_historico (disputa_id, usuario_id, evento, descricao) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$disputaId, $_SESSION['user_id'], 'caso_aceito', 'Caso aceito e disputa iniciada']);
                    
                    createNotification($disputa['reclamante_id'], 'disputa_aceita', 'Disputa Aceita', 
                        "Sua disputa {$disputa['codigo_caso']} foi aceita e está em andamento.");
                    createNotification($disputa['reclamado_id'], 'disputa_aceita', 'Disputa Aceita', 
                        "A disputa {$disputa['codigo_caso']} foi aceita e está em andamento.");
                    
                    logActivity('disputa_aceita', "Disputa {$disputa['codigo_caso']} aceita", $disputaId);
                    break;
                    
                case 'proferir_sentenca':
                    header("Location: sentenca.php?disputa_id=$disputaId");
                    exit();
                    break;
            }
            
            $db->commit();
            header("Location: disputa-detalhes.php?id=$disputaId&success=1");
            exit();
            
        } catch (Exception $e) {
            $db->rollBack();
            $error = "Erro ao processar ação: " . $e->getMessage();
        }
    }
}

// Status labels
$statusLabels = [
    'triagem' => 'Em Triagem',
    'aguardando_aceite' => 'Aguardando Aceite',
    'em_andamento' => 'Em Andamento',
    'aguardando_sentenca' => 'Aguardando Sentença',
    'finalizada' => 'Finalizada',
    'cancelada' => 'Cancelada'
];

$statusColors = [
    'triagem' => '#e0e7ff',
    'aguardando_aceite' => '#ddd6fe',
    'em_andamento' => '#fef3c7',
    'aguardando_sentenca' => '#fed7aa',
    'finalizada' => '#d1fae5',
    'cancelada' => '#fee2e2'
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($disputa['codigo_caso']); ?> - Arbitrivm</title>
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
        
        /* Case Header */
        .case-header {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        .case-title-row {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 20px;
        }
        
        .case-title {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .case-code {
            font-size: 2rem;
            color: #1a365d;
            font-weight: 700;
        }
        
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .case-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
        }
        
        .info-label {
            color: #718096;
            font-size: 0.9rem;
            margin-bottom: 5px;
        }
        
        .info-value {
            color: #1a365d;
            font-weight: 500;
        }
        
        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }
        
        /* Cards */
        .card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .card-title {
            font-size: 1.25rem;
            color: #1a365d;
            font-weight: 600;
        }
        
        /* Parties */
        .party-card {
            padding: 20px;
            background-color: #f7fafc;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        
        .party-role {
            font-size: 0.85rem;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }
        
        .party-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1a365d;
            margin-bottom: 10px;
        }
        
        .party-details {
            font-size: 0.95rem;
            color: #4a5568;
        }
        
        /* Timeline */
        .timeline-item {
            position: relative;
            padding-left: 40px;
            padding-bottom: 20px;
            border-left: 2px solid #e2e8f0;
            margin-left: 10px;
        }
        
        .timeline-item:last-child {
            border-left: none;
        }
        
        .timeline-dot {
            position: absolute;
            left: -8px;
            top: 0;
            width: 14px;
            height: 14px;
            background: #2b6cb0;
            border-radius: 50%;
            border: 3px solid white;
        }
        
        .timeline-date {
            font-size: 0.85rem;
            color: #718096;
            margin-bottom: 5px;
        }
        
        .timeline-event {
            font-weight: 500;
            color: #1a365d;
            margin-bottom: 5px;
        }
        
        .timeline-description {
            color: #4a5568;
            font-size: 0.95rem;
        }
        
        /* Documents */
        .document-item {
            display: flex;
            align-items: center;
            padding: 15px;
            background-color: #f7fafc;
            border-radius: 8px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .document-item:hover {
            background-color: #e2e8f0;
        }
        
        .document-icon {
            width: 40px;
            height: 40px;
            background-color: #e2e8f0;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
        }
        
        .document-info {
            flex: 1;
        }
        
        .document-name {
            font-weight: 500;
            color: #1a365d;
            margin-bottom: 3px;
        }
        
        .document-meta {
            font-size: 0.85rem;
            color: #718096;
        }
        
        /* Messages */
        .message-preview {
            padding: 15px;
            background-color: #f7fafc;
            border-radius: 8px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .message-preview:hover {
            background-color: #e2e8f0;
        }
        
        .message-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        
        .message-sender {
            font-weight: 500;
            color: #1a365d;
        }
        
        .message-time {
            font-size: 0.85rem;
            color: #718096;
        }
        
        .message-excerpt {
            color: #4a5568;
            font-size: 0.95rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        /* Actions */
        .actions-container {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 20px;
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
        
        .btn-danger {
            background-color: #e53e3e;
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #c53030;
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
        
        @media (max-width: 968px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .case-title-row {
                flex-direction: column;
                gap: 15px;
            }
            
            .actions-container {
                width: 100%;
            }
            
            .btn {
                flex: 1;
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
            <span><?php echo htmlspecialchars($disputa['codigo_caso']); ?></span>
        </div>
        
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">
                Ação realizada com sucesso!
            </div>
        <?php endif; ?>
        
        <!-- Case Header -->
        <div class="case-header">
            <div class="case-title-row">
                <div class="case-title">
                    <h1 class="case-code"><?php echo htmlspecialchars($disputa['codigo_caso']); ?></h1>
                    <span class="status-badge" style="background-color: <?php echo $statusColors[$disputa['status']]; ?>">
                        <?php echo $statusLabels[$disputa['status']]; ?>
                    </span>
                </div>
                
                <?php if (!empty($acoesDisponiveis)): ?>
                    <div class="actions-container">
                        <?php foreach ($acoesDisponiveis as $acao): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="<?php echo $acao['action']; ?>">
                                <button type="submit" class="btn <?php echo $acao['class']; ?>">
                                    <?php echo $acao['label']; ?>
                                </button>
                            </form>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="case-info-grid">
                <div class="info-item">
                    <span class="info-label">Tipo de Disputa</span>
                    <span class="info-value"><?php echo htmlspecialchars($disputa['tipo_nome']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Valor da Causa</span>
                    <span class="info-value"><?php echo formatMoney($disputa['valor_causa']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Data de Abertura</span>
                    <span class="info-value"><?php echo formatDate($disputa['data_abertura']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Endereço do Imóvel</span>
                    <span class="info-value"><?php echo htmlspecialchars($disputa['endereco_imovel']); ?></span>
                </div>
            </div>
        </div>
        
        <div class="content-grid">
            <!-- Main Content -->
            <div class="main-content">
                <!-- Descrição -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Descrição da Disputa</h2>
                    </div>
                    <p><?php echo nl2br(htmlspecialchars($disputa['descricao'])); ?></p>
                    
                    <?php if ($infracao): ?>
                        <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e2e8f0;">
                            <h3 style="font-size: 1.1rem; margin-bottom: 10px;">Detalhes da Infração</h3>
                            <p><strong>Tipo:</strong> <?php echo htmlspecialchars($infracao['tipo_infracao']); ?></p>
                            <?php if ($infracao['data_infracao']): ?>
                                <p><strong>Data:</strong> <?php echo formatDate($infracao['data_infracao']); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Partes Envolvidas -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Partes Envolvidas</h2>
                    </div>
                    
                    <div class="party-card">
                        <div class="party-role">Reclamante</div>
                        <div class="party-name"><?php echo htmlspecialchars($disputa['reclamante_nome']); ?></div>
                        <div class="party-details">
                            <?php echo htmlspecialchars($disputa['reclamante_email']); ?><br>
                            <?php if ($disputa['reclamante_telefone']): ?>
                                <?php echo htmlspecialchars($disputa['reclamante_telefone']); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="party-card">
                        <div class="party-role">Reclamado</div>
                        <div class="party-name"><?php echo htmlspecialchars($disputa['reclamado_nome'] ?: 'Aguardando aceite'); ?></div>
                        <div class="party-details">
                            <?php echo htmlspecialchars($disputa['reclamado_email']); ?><br>
                            <?php if ($disputa['reclamado_telefone']): ?>
                                <?php echo htmlspecialchars($disputa['reclamado_telefone']); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if ($disputa['arbitro_nome']): ?>
                        <div class="party-card">
                            <div class="party-role">Árbitro</div>
                            <div class="party-name"><?php echo htmlspecialchars($disputa['arbitro_nome']); ?></div>
                            <div class="party-details">
                                OAB: <?php echo htmlspecialchars($disputa['oab_numero'] . '/' . $disputa['oab_estado']); ?><br>
                                <?php echo htmlspecialchars($disputa['arbitro_email']); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Linha do Tempo -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Histórico</h2>
                    </div>
                    
                    <div class="timeline">
                        <?php foreach ($historico as $evento): ?>
                            <div class="timeline-item">
                                <div class="timeline-dot"></div>
                                <div class="timeline-date">
                                    <?php echo formatDate($evento['data_evento'], 'd/m/Y H:i'); ?>
                                </div>
                                <div class="timeline-event">
                                    <?php echo htmlspecialchars($evento['evento']); ?>
                                </div>
                                <div class="timeline-description">
                                    <?php echo htmlspecialchars($evento['descricao']); ?>
                                    <br>
                                    <small>Por: <?php echo htmlspecialchars($evento['usuario_nome']); ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <!-- Sidebar -->
            <div class="sidebar">
                <!-- Ações Rápidas -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Ações Rápidas</h2>
                    </div>
                    
                    <div class="actions-container" style="flex-direction: column;">
                        <a href="upload-documentos.php?disputa_id=<?php echo $disputaId; ?>" class="btn btn-secondary">
                            Enviar Documentos
                        </a>
                        <a href="comunicacoes.php?disputa_id=<?php echo $disputaId; ?>" class="btn btn-secondary">
                            Ver Comunicações
                        </a>
                        <?php if ($disputa['status'] === 'finalizada' && $disputa['sentenca_arquivo']): ?>
                            <a href="download.php?tipo=sentenca&id=<?php echo $disputaId; ?>" class="btn btn-primary">
                                Baixar Sentença
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Documentos -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Documentos</h2>
                        <a href="upload-documentos.php?disputa_id=<?php echo $disputaId; ?>" style="font-size: 0.9rem;">
                            + Adicionar
                        </a>
                    </div>
                    
                    <?php if (empty($documentos)): ?>
                        <p style="color: #718096;">Nenhum documento anexado.</p>
                    <?php else: ?>
                        <?php foreach ($documentos as $doc): ?>
                            <a href="download.php?tipo=documento&id=<?php echo $doc['id']; ?>" class="document-item">
                                <div class="document-icon">
                                    📄
                                </div>
                                <div class="document-info">
                                    <div class="document-name"><?php echo htmlspecialchars($doc['nome_arquivo']); ?></div>
                                    <div class="document-meta">
                                        <?php echo htmlspecialchars($doc['enviado_por']); ?> • 
                                        <?php echo formatDate($doc['data_upload'], 'd/m H:i'); ?>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Mensagens Recentes -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Mensagens Recentes</h2>
                        <a href="comunicacoes.php?disputa_id=<?php echo $disputaId; ?>" style="font-size: 0.9rem;">
                            Ver todas
                        </a>
                    </div>
                    
                    <?php if (empty($mensagensRecentes)): ?>
                        <p style="color: #718096;">Nenhuma mensagem ainda.</p>
                    <?php else: ?>
                        <?php foreach ($mensagensRecentes as $msg): ?>
                            <div class="message-preview" onclick="window.location.href='comunicacoes.php?disputa_id=<?php echo $disputaId; ?>'">
                                <div class="message-header">
                                    <span class="message-sender"><?php echo htmlspecialchars($msg['remetente_nome']); ?></span>
                                    <span class="message-time"><?php echo formatDate($msg['data_envio'], 'd/m H:i'); ?></span>
                                </div>
                                <div class="message-excerpt">
                                    <?php echo htmlspecialchars(substr($msg['mensagem'], 0, 100)) . '...'; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</body>
</html>