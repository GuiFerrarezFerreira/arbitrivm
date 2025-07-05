<?php
require_once 'config.php';

requireLogin();

$db = getDBConnection();
$userId = $_SESSION['user_id'];

// Marcar notificação como lida
if (isset($_GET['marcar_lida']) && is_numeric($_GET['marcar_lida'])) {
    $notifId = intval($_GET['marcar_lida']);
    $stmt = $db->prepare("UPDATE notificacoes SET lida = 1, data_leitura = NOW() WHERE id = ? AND usuario_id = ?");
    $stmt->execute([$notifId, $userId]);
    
    header("Location: notificacoes.php");
    exit();
}

// Marcar todas como lidas
if (isset($_GET['marcar_todas'])) {
    $stmt = $db->prepare("UPDATE notificacoes SET lida = 1, data_leitura = NOW() WHERE usuario_id = ? AND lida = 0");
    $stmt->execute([$userId]);
    
    header("Location: notificacoes.php");
    exit();
}

// Excluir notificação
if (isset($_GET['excluir']) && is_numeric($_GET['excluir'])) {
    $notifId = intval($_GET['excluir']);
    $stmt = $db->prepare("DELETE FROM notificacoes WHERE id = ? AND usuario_id = ?");
    $stmt->execute([$notifId, $userId]);
    
    header("Location: notificacoes.php");
    exit();
}

// Filtros
$filtro = $_GET['filtro'] ?? 'todas';
$tipo = $_GET['tipo'] ?? '';

// Construir query
$query = "SELECT * FROM notificacoes WHERE usuario_id = ?";
$params = [$userId];

if ($filtro === 'nao_lidas') {
    $query .= " AND lida = 0";
} elseif ($filtro === 'lidas') {
    $query .= " AND lida = 1";
}

if ($tipo) {
    $query .= " AND tipo = ?";
    $params[] = $tipo;
}

$query .= " ORDER BY data_criacao DESC";

// Buscar notificações
$stmt = $db->prepare($query);
$stmt->execute($params);
$notificacoes = $stmt->fetchAll();

// Contar notificações não lidas
$stmt = $db->prepare("SELECT COUNT(*) FROM notificacoes WHERE usuario_id = ? AND lida = 0");
$stmt->execute([$userId]);
$naoLidas = $stmt->fetchColumn();

// Agrupar por data
$notificacoesPorData = [];
foreach ($notificacoes as $notif) {
    $data = date('Y-m-d', strtotime($notif['data_criacao']));
    $notificacoesPorData[$data][] = $notif;
}

// Tipos de notificação
$tiposNotificacao = [
    'disputa_criada' => ['icon' => '📋', 'color' => '#3182ce'],
    'nova_disputa' => ['icon' => '🆕', 'color' => '#8b5cf6'],
    'disputa_aceita' => ['icon' => '✅', 'color' => '#10b981'],
    'disputa_finalizada' => ['icon' => '🏁', 'color' => '#059669'],
    'nova_mensagem' => ['icon' => '💬', 'color' => '#3b82f6'],
    'novo_documento' => ['icon' => '📄', 'color' => '#f59e0b'],
    'sentenca_proferida' => ['icon' => '⚖️', 'color' => '#dc2626'],
    'prazo_proximo' => ['icon' => '⏰', 'color' => '#ef4444'],
    'sistema' => ['icon' => 'ℹ️', 'color' => '#6b7280']
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notificações - Arbitrivm</title>
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
        
        /* Main Container */
        .main-container {
            max-width: 1000px;
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
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .badge-count {
            background-color: #e53e3e;
            color: white;
            font-size: 0.9rem;
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: bold;
        }
        
        .page-actions {
            display: flex;
            gap: 10px;
        }
        
        /* Filters */
        .filters-container {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .filter-group {
            display: flex;
            gap: 10px;
        }
        
        .filter-btn {
            padding: 8px 16px;
            border: 1px solid #e2e8f0;
            background: white;
            border-radius: 8px;
            color: #4a5568;
            text-decoration: none;
            font-size: 0.95rem;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .filter-btn:hover {
            background-color: #f7fafc;
            border-color: #cbd5e0;
        }
        
        .filter-btn.active {
            background-color: #2b6cb0;
            color: white;
            border-color: #2b6cb0;
        }
        
        /* Notifications Container */
        .notifications-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        
        /* Date Group */
        .date-group {
            border-bottom: 1px solid #e2e8f0;
        }
        
        .date-group:last-child {
            border-bottom: none;
        }
        
        .date-header {
            padding: 20px 30px 15px;
            background-color: #f7fafc;
            font-weight: 600;
            color: #4a5568;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .date-header .date-label {
            flex: 1;
        }
        
        .date-count {
            font-size: 0.85rem;
            color: #718096;
            font-weight: normal;
        }
        
        /* Notification Item */
        .notification-item {
            display: flex;
            padding: 20px 30px;
            border-bottom: 1px solid #f7fafc;
            transition: background-color 0.3s;
            position: relative;
        }
        
        .notification-item:hover {
            background-color: #f7fafc;
        }
        
        .notification-item:last-child {
            border-bottom: none;
        }
        
        .notification-item.unread {
            background-color: #ebf8ff;
        }
        
        .notification-item.unread::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background-color: #3182ce;
        }
        
        .notification-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-right: 20px;
            flex-shrink: 0;
        }
        
        .notification-content {
            flex: 1;
            min-width: 0;
        }
        
        .notification-title {
            font-weight: 600;
            color: #1a365d;
            margin-bottom: 5px;
            font-size: 1.05rem;
        }
        
        .notification-message {
            color: #4a5568;
            margin-bottom: 8px;
            line-height: 1.5;
        }
        
        .notification-meta {
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 0.85rem;
            color: #718096;
        }
        
        .notification-time {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .notification-link {
            color: #2b6cb0;
            text-decoration: none;
            font-weight: 500;
        }
        
        .notification-link:hover {
            text-decoration: underline;
        }
        
        .notification-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-left: 20px;
        }
        
        .action-btn {
            padding: 6px 12px;
            border: none;
            background: none;
            color: #718096;
            cursor: pointer;
            border-radius: 6px;
            transition: all 0.3s;
            font-size: 0.85rem;
        }
        
        .action-btn:hover {
            background-color: #e2e8f0;
            color: #4a5568;
        }
        
        .action-btn.mark-read {
            color: #3182ce;
        }
        
        .action-btn.delete {
            color: #e53e3e;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: #718096;
        }
        
        .empty-icon {
            width: 100px;
            height: 100px;
            margin: 0 auto 20px;
            opacity: 0.3;
        }
        
        .empty-title {
            font-size: 1.5rem;
            color: #4a5568;
            margin-bottom: 10px;
        }
        
        .empty-message {
            font-size: 1.05rem;
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
        
        @media (max-width: 768px) {
            .page-title-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .filters-container {
                width: 100%;
            }
            
            .notification-item {
                padding: 15px 20px;
            }
            
            .notification-icon {
                width: 40px;
                height: 40px;
                font-size: 1.2rem;
                margin-right: 15px;
            }
            
            .notification-actions {
                margin-left: 0;
                margin-top: 10px;
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
            <div class="page-title-row">
                <h1 class="page-title">
                    Notificações
                    <?php if ($naoLidas > 0): ?>
                        <span class="badge-count"><?php echo $naoLidas; ?></span>
                    <?php endif; ?>
                </h1>
                
                <div class="page-actions">
                    <?php if ($naoLidas > 0): ?>
                        <a href="?marcar_todas=1" class="btn btn-secondary">Marcar todas como lidas</a>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="filters-container">
                <div class="filter-group">
                    <a href="?filtro=todas" class="filter-btn <?php echo $filtro === 'todas' ? 'active' : ''; ?>">
                        Todas
                    </a>
                    <a href="?filtro=nao_lidas" class="filter-btn <?php echo $filtro === 'nao_lidas' ? 'active' : ''; ?>">
                        Não lidas
                    </a>
                    <a href="?filtro=lidas" class="filter-btn <?php echo $filtro === 'lidas' ? 'active' : ''; ?>">
                        Lidas
                    </a>
                </div>
                
                <div class="filter-group">
                    <select class="filter-btn" onchange="window.location.href='?filtro=<?php echo $filtro; ?>&tipo=' + this.value">
                        <option value="">Todos os tipos</option>
                        <option value="disputa_criada" <?php echo $tipo === 'disputa_criada' ? 'selected' : ''; ?>>
                            Disputas Criadas
                        </option>
                        <option value="nova_mensagem" <?php echo $tipo === 'nova_mensagem' ? 'selected' : ''; ?>>
                            Mensagens
                        </option>
                        <option value="novo_documento" <?php echo $tipo === 'novo_documento' ? 'selected' : ''; ?>>
                            Documentos
                        </option>
                        <option value="sentenca_proferida" <?php echo $tipo === 'sentenca_proferida' ? 'selected' : ''; ?>>
                            Sentenças
                        </option>
                    </select>
                </div>
            </div>
        </div>
        
        <div class="notifications-container">
            <?php if (empty($notificacoes)): ?>
                <div class="empty-state">
                    <svg class="empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                              d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                    <h2 class="empty-title">Nenhuma notificação</h2>
                    <p class="empty-message">
                        <?php if ($filtro === 'nao_lidas'): ?>
                            Você não tem notificações não lidas.
                        <?php else: ?>
                            Você ainda não recebeu nenhuma notificação.
                        <?php endif; ?>
                    </p>
                </div>
            <?php else: ?>
                <?php foreach ($notificacoesPorData as $data => $notifs): ?>
                    <div class="date-group">
                        <div class="date-header">
                            <span class="date-label">
                                <?php
                                if ($data === date('Y-m-d')) {
                                    echo 'Hoje';
                                } elseif ($data === date('Y-m-d', strtotime('-1 day'))) {
                                    echo 'Ontem';
                                } else {
                                    echo formatDate($data);
                                }
                                ?>
                            </span>
                            <span class="date-count"><?php echo count($notifs); ?> notificações</span>
                        </div>
                        
                        <?php foreach ($notifs as $notif): ?>
                            <?php
                            $tipoInfo = $tiposNotificacao[$notif['tipo']] ?? $tiposNotificacao['sistema'];
                            ?>
                            <div class="notification-item <?php echo !$notif['lida'] ? 'unread' : ''; ?>">
                                <div class="notification-icon" style="background-color: <?php echo $tipoInfo['color']; ?>20;">
                                    <span style="color: <?php echo $tipoInfo['color']; ?>">
                                        <?php echo $tipoInfo['icon']; ?>
                                    </span>
                                </div>
                                
                                <div class="notification-content">
                                    <h3 class="notification-title">
                                        <?php echo htmlspecialchars($notif['titulo']); ?>
                                    </h3>
                                    
                                    <?php if ($notif['mensagem']): ?>
                                        <p class="notification-message">
                                            <?php echo htmlspecialchars($notif['mensagem']); ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <div class="notification-meta">
                                        <span class="notification-time">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                                <circle cx="12" cy="12" r="10"></circle>
                                                <polyline points="12 6 12 12 16 14"></polyline>
                                            </svg>
                                            <?php echo date('H:i', strtotime($notif['data_criacao'])); ?>
                                        </span>
                                        
                                        <?php if ($notif['link']): ?>
                                            <a href="<?php echo htmlspecialchars($notif['link']); ?>" 
                                               class="notification-link">
                                                Ver detalhes →
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="notification-actions">
                                    <?php if (!$notif['lida']): ?>
                                        <a href="?marcar_lida=<?php echo $notif['id']; ?>" 
                                           class="action-btn mark-read" 
                                           title="Marcar como lida">
                                            ✓ Marcar como lida
                                        </a>
                                    <?php endif; ?>
                                    
                                    <a href="?excluir=<?php echo $notif['id']; ?>" 
                                       class="action-btn delete" 
                                       title="Excluir"
                                       onclick="return confirm('Deseja excluir esta notificação?')">
                                        🗑️
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>