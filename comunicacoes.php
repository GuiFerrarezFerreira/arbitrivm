<?php
require_once 'config.php';

requireLogin();

if (!isset($_GET['disputa_id'])) {
    header("Location: disputas.php");
    exit();
}

$disputaId = intval($_GET['disputa_id']);
$db = getDBConnection();

// Verificar se tem acesso à disputa
$stmt = $db->prepare("
    SELECT d.*, 
           u1.nome_completo as reclamante_nome,
           u2.nome_completo as reclamado_nome,
           u3.nome_completo as arbitro_nome
    FROM disputas d
    JOIN usuarios u1 ON d.reclamante_id = u1.id
    LEFT JOIN usuarios u2 ON d.reclamado_id = u2.id
    LEFT JOIN arbitros a ON d.arbitro_id = a.id
    LEFT JOIN usuarios u3 ON a.usuario_id = u3.id
    WHERE d.id = ?
");
$stmt->execute([$disputaId]);
$disputa = $stmt->fetch();

if (!$disputa) {
    header("Location: disputas.php");
    exit();
}

// Verificar permissão
$temAcesso = false;
$usuarioAtual = $_SESSION['user_id'];

if ($_SESSION['user_type'] === 'admin') {
    $temAcesso = true;
} elseif ($_SESSION['user_type'] === 'empresa' && $disputa['empresa_id'] == $_SESSION['empresa_id']) {
    $temAcesso = true;
} elseif ($_SESSION['user_type'] === 'arbitro' && isset($_SESSION['arbitro_id']) && $disputa['arbitro_id'] == $_SESSION['arbitro_id']) {
    $temAcesso = true;
} elseif ($_SESSION['user_type'] === 'solicitante' && 
         ($disputa['reclamante_id'] == $usuarioAtual || $disputa['reclamado_id'] == $usuarioAtual)) {
    $temAcesso = true;
}

if (!$temAcesso) {
    header("HTTP/1.1 403 Forbidden");
    die("Acesso negado.");
}

// Processar envio de mensagem
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mensagem'])) {
    $mensagem = trim($_POST['mensagem']);
    
    if (!empty($mensagem)) {
        try {
            $db->beginTransaction();
            
            // Inserir mensagem
            $stmt = $db->prepare("
                INSERT INTO disputa_mensagens (disputa_id, usuario_id, mensagem) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$disputaId, $usuarioAtual, $mensagem]);
            
            // Registrar no histórico
            $stmt = $db->prepare("
                INSERT INTO disputa_historico (disputa_id, usuario_id, evento, descricao) 
                VALUES (?, ?, 'mensagem_enviada', 'Nova mensagem enviada')
            ");
            $stmt->execute([$disputaId, $usuarioAtual]);
            
            // Notificar outras partes
            $notificar = [];
            if ($usuarioAtual != $disputa['reclamante_id']) {
                $notificar[] = $disputa['reclamante_id'];
            }
            if ($disputa['reclamado_id'] && $usuarioAtual != $disputa['reclamado_id']) {
                $notificar[] = $disputa['reclamado_id'];
            }
            if ($disputa['arbitro_id']) {
                $stmt = $db->prepare("SELECT usuario_id FROM arbitros WHERE id = ?");
                $stmt->execute([$disputa['arbitro_id']]);
                $arbitroUserId = $stmt->fetchColumn();
                if ($arbitroUserId && $usuarioAtual != $arbitroUserId) {
                    $notificar[] = $arbitroUserId;
                }
            }
            
            foreach ($notificar as $userId) {
                createNotification($userId, 'nova_mensagem', 
                    'Nova Mensagem', 
                    "Nova mensagem na disputa {$disputa['codigo_caso']}",
                    "comunicacoes.php?disputa_id=$disputaId"
                );
            }
            
            $db->commit();
            
            // Redirecionar para evitar reenvio
            header("Location: comunicacoes.php?disputa_id=$disputaId");
            exit();
            
        } catch (Exception $e) {
            $db->rollBack();
            $error = "Erro ao enviar mensagem: " . $e->getMessage();
        }
    }
}

// Marcar mensagens como lidas
$stmt = $db->prepare("
    UPDATE disputa_mensagens 
    SET lida = 1 
    WHERE disputa_id = ? AND usuario_id != ? AND lida = 0
");
$stmt->execute([$disputaId, $usuarioAtual]);

// Buscar todas as mensagens
$stmt = $db->prepare("
    SELECT dm.*, u.nome_completo, u.tipo_usuario_id,
           tu.tipo as tipo_usuario
    FROM disputa_mensagens dm
    JOIN usuarios u ON dm.usuario_id = u.id
    JOIN tipos_usuario tu ON u.tipo_usuario_id = tu.id
    WHERE dm.disputa_id = ?
    ORDER BY dm.data_envio ASC
");
$stmt->execute([$disputaId]);
$mensagens = $stmt->fetchAll();

// Buscar participantes
$participantes = [];
$participantes[$disputa['reclamante_id']] = [
    'nome' => $disputa['reclamante_nome'],
    'papel' => 'Reclamante'
];

if ($disputa['reclamado_id']) {
    $participantes[$disputa['reclamado_id']] = [
        'nome' => $disputa['reclamado_nome'],
        'papel' => 'Reclamado'
    ];
}

if ($disputa['arbitro_id']) {
    $stmt = $db->prepare("SELECT usuario_id FROM arbitros WHERE id = ?");
    $stmt->execute([$disputa['arbitro_id']]);
    $arbitroUserId = $stmt->fetchColumn();
    
    $participantes[$arbitroUserId] = [
        'nome' => $disputa['arbitro_nome'],
        'papel' => 'Árbitro'
    ];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comunicações - <?php echo htmlspecialchars($disputa['codigo_caso']); ?> - Arbitrivm</title>
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
            height: 100vh;
            overflow: hidden;
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
        
        /* Chat Container */
        .chat-container {
            display: flex;
            height: calc(100vh - 80px);
            max-width: 1200px;
            margin: 0 auto;
        }
        
        /* Sidebar */
        .chat-sidebar {
            width: 300px;
            background: white;
            border-right: 1px solid #e2e8f0;
            display: flex;
            flex-direction: column;
        }
        
        .case-info {
            padding: 20px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .case-code {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1a365d;
            margin-bottom: 5px;
        }
        
        .case-type {
            color: #718096;
            font-size: 0.9rem;
        }
        
        .participants-section {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
        }
        
        .section-title {
            font-size: 0.85rem;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 15px;
            font-weight: 600;
        }
        
        .participant {
            display: flex;
            align-items: center;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 8px;
            background-color: #f7fafc;
        }
        
        .participant-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            font-weight: 600;
            color: #4a5568;
        }
        
        .participant-info {
            flex: 1;
        }
        
        .participant-name {
            font-weight: 500;
            color: #1a365d;
            margin-bottom: 2px;
        }
        
        .participant-role {
            font-size: 0.85rem;
            color: #718096;
        }
        
        .participant-status {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: #48bb78;
        }
        
        /* Chat Main */
        .chat-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: white;
        }
        
        .chat-header {
            padding: 20px;
            border-bottom: 1px solid #e2e8f0;
            background: #f7fafc;
        }
        
        .chat-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1a365d;
        }
        
        /* Messages Area */
        .messages-container {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            background: #fafbfc;
        }
        
        .date-separator {
            text-align: center;
            margin: 20px 0;
            position: relative;
        }
        
        .date-separator span {
            background: #fafbfc;
            padding: 0 15px;
            color: #718096;
            font-size: 0.85rem;
            position: relative;
            z-index: 1;
        }
        
        .date-separator::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: #e2e8f0;
        }
        
        .message {
            display: flex;
            margin-bottom: 20px;
            align-items: flex-start;
        }
        
        .message.own {
            flex-direction: row-reverse;
        }
        
        .message-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background-color: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 12px;
            font-size: 0.9rem;
            font-weight: 600;
            color: #4a5568;
            flex-shrink: 0;
        }
        
        .message-content {
            max-width: 70%;
        }
        
        .message-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 5px;
        }
        
        .message-sender {
            font-weight: 500;
            color: #1a365d;
            font-size: 0.9rem;
        }
        
        .message-time {
            font-size: 0.8rem;
            color: #a0aec0;
        }
        
        .message-bubble {
            background: white;
            padding: 12px 16px;
            border-radius: 12px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            word-wrap: break-word;
        }
        
        .message.own .message-bubble {
            background: #2b6cb0;
            color: white;
        }
        
        .message-bubble p {
            margin: 0;
            line-height: 1.5;
        }
        
        /* Input Area */
        .chat-input-container {
            padding: 20px;
            border-top: 1px solid #e2e8f0;
            background: white;
        }
        
        .chat-input-form {
            display: flex;
            gap: 12px;
        }
        
        .chat-input {
            flex: 1;
            padding: 12px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 24px;
            font-size: 1rem;
            resize: none;
            outline: none;
            transition: border-color 0.3s;
            font-family: inherit;
        }
        
        .chat-input:focus {
            border-color: #4299e1;
        }
        
        .chat-input::placeholder {
            color: #a0aec0;
        }
        
        .send-button {
            background-color: #2b6cb0;
            color: white;
            border: none;
            border-radius: 50%;
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .send-button:hover {
            background-color: #2558a3;
            transform: scale(1.05);
        }
        
        .send-button:disabled {
            background-color: #cbd5e0;
            cursor: not-allowed;
        }
        
        /* Empty State */
        .empty-messages {
            text-align: center;
            padding: 60px 20px;
            color: #718096;
        }
        
        .empty-messages svg {
            width: 80px;
            height: 80px;
            margin-bottom: 20px;
            opacity: 0.3;
        }
        
        /* Mobile */
        @media (max-width: 768px) {
            .chat-sidebar {
                display: none;
            }
            
            .message-content {
                max-width: 85%;
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
                    <li><a href="disputa-detalhes.php?id=<?php echo $disputaId; ?>">Detalhes</a></li>
                    <li><a href="logout.php">Sair</a></li>
                </ul>
            </nav>
        </div>
    </header>
    
    <div class="chat-container">
        <!-- Sidebar -->
        <aside class="chat-sidebar">
            <div class="case-info">
                <div class="case-code"><?php echo htmlspecialchars($disputa['codigo_caso']); ?></div>
                <div class="case-type">Comunicações da Disputa</div>
            </div>
            
            <div class="participants-section">
                <h3 class="section-title">Participantes</h3>
                
                <?php foreach ($participantes as $id => $participante): ?>
                    <div class="participant">
                        <div class="participant-avatar">
                            <?php echo strtoupper(substr($participante['nome'], 0, 1)); ?>
                        </div>
                        <div class="participant-info">
                            <div class="participant-name">
                                <?php echo htmlspecialchars($participante['nome']); ?>
                                <?php if ($id == $usuarioAtual): ?>
                                    (Você)
                                <?php endif; ?>
                            </div>
                            <div class="participant-role"><?php echo $participante['papel']; ?></div>
                        </div>
                        <div class="participant-status"></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </aside>
        
        <!-- Chat Main -->
        <main class="chat-main">
            <div class="chat-header">
                <h2 class="chat-title">Conversação Segura</h2>
            </div>
            
            <div class="messages-container" id="messagesContainer">
                <?php if (empty($mensagens)): ?>
                    <div class="empty-messages">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                        </svg>
                        <h3>Nenhuma mensagem ainda</h3>
                        <p>Inicie a conversa enviando a primeira mensagem.</p>
                    </div>
                <?php else: ?>
                    <?php 
                    $lastDate = '';
                    foreach ($mensagens as $msg): 
                        $msgDate = date('d/m/Y', strtotime($msg['data_envio']));
                        $isOwn = $msg['usuario_id'] == $usuarioAtual;
                        
                        // Mostrar separador de data
                        if ($msgDate != $lastDate):
                            $lastDate = $msgDate;
                    ?>
                        <div class="date-separator">
                            <span>
                                <?php 
                                if ($msgDate == date('d/m/Y')) {
                                    echo 'Hoje';
                                } elseif ($msgDate == date('d/m/Y', strtotime('-1 day'))) {
                                    echo 'Ontem';
                                } else {
                                    echo $msgDate;
                                }
                                ?>
                            </span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="message <?php echo $isOwn ? 'own' : ''; ?>">
                        <div class="message-avatar">
                            <?php echo strtoupper(substr($msg['nome_completo'], 0, 1)); ?>
                        </div>
                        <div class="message-content">
                            <div class="message-header">
                                <span class="message-sender">
                                    <?php echo htmlspecialchars($msg['nome_completo']); ?>
                                    <?php if (isset($participantes[$msg['usuario_id']])): ?>
                                        (<?php echo $participantes[$msg['usuario_id']]['papel']; ?>)
                                    <?php endif; ?>
                                </span>
                                <span class="message-time">
                                    <?php echo date('H:i', strtotime($msg['data_envio'])); ?>
                                </span>
                            </div>
                            <div class="message-bubble">
                                <p><?php echo nl2br(htmlspecialchars($msg['mensagem'])); ?></p>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="chat-input-container">
                <form method="POST" class="chat-input-form" id="chatForm">
                    <textarea 
                        name="mensagem" 
                        class="chat-input" 
                        placeholder="Digite sua mensagem..." 
                        rows="1"
                        required
                        maxlength="1000"
                        id="messageInput"
                    ></textarea>
                    <button type="submit" class="send-button">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                        </svg>
                    </button>
                </form>
            </div>
        </main>
    </div>
    
    <script>
        // Auto-resize textarea
        const messageInput = document.getElementById('messageInput');
        messageInput.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
            
            // Limit height
            if (this.scrollHeight > 120) {
                this.style.height = '120px';
                this.style.overflowY = 'auto';
            } else {
                this.style.overflowY = 'hidden';
            }
        });
        
        // Send with Enter (Shift+Enter for new line)
        messageInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                document.getElementById('chatForm').submit();
            }
        });
        
        // Scroll to bottom
        const messagesContainer = document.getElementById('messagesContainer');
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
        
        // Auto-refresh messages (polling every 10 seconds)
        setInterval(() => {
            // In a real application, this would check for new messages via AJAX
            // For now, it just reloads the page if there are messages
            <?php if (!empty($mensagens)): ?>
            // window.location.reload();
            <?php endif; ?>
        }, 10000);
    </script>
</body>
</html>