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
    SELECT d.*, td.nome as tipo_nome
    FROM disputas d
    JOIN tipos_disputa td ON d.tipo_disputa_id = td.id
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
    die("Acesso negado.");
}

$errors = [];
$success = false;

// Processar upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['documento'])) {
    $tipoDocumento = sanitizeInput($_POST['tipo_documento'] ?? '');
    $descricao = sanitizeInput($_POST['descricao'] ?? '');
    
    // Validar tipo de documento
    $tiposPermitidos = ['contrato', 'laudo', 'foto_video', 'ata', 'notificacao', 'comprovante', 'outros'];
    if (!in_array($tipoDocumento, $tiposPermitidos)) {
        $errors[] = "Tipo de documento inválido.";
    }
    
    // Processar cada arquivo
    $arquivosEnviados = 0;
    foreach ($_FILES['documento']['name'] as $key => $filename) {
        if ($_FILES['documento']['error'][$key] === UPLOAD_ERR_OK) {
            $tmpName = $_FILES['documento']['tmp_name'][$key];
            $fileSize = $_FILES['documento']['size'][$key];
            $fileType = $_FILES['documento']['type'][$key];
            
            // Validar tamanho
            if ($fileSize > UPLOAD_MAX_SIZE) {
                $errors[] = "Arquivo '$filename' excede o tamanho máximo permitido.";
                continue;
            }
            
            // Validar extensão
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (!in_array($ext, ALLOWED_EXTENSIONS)) {
                $errors[] = "Formato de arquivo não permitido: $ext";
                continue;
            }
            
            // Gerar nome único
            $novoNome = uniqid('doc_') . '_' . time() . '.' . $ext;
            $caminhoCompleto = UPLOAD_PATH . $disputaId . '/' . $novoNome;
            
            // Criar diretório se não existir
            $diretorio = UPLOAD_PATH . $disputaId;
            if (!file_exists($diretorio)) {
                mkdir($diretorio, 0777, true);
            }
            
            // Mover arquivo
            if (move_uploaded_file($tmpName, $caminhoCompleto)) {
                try {
                    // Registrar no banco
                    $stmt = $db->prepare("
                        INSERT INTO disputa_documentos 
                        (disputa_id, usuario_id, tipo_documento, nome_arquivo, nome_original, tamanho, mime_type, descricao) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    $stmt->execute([
                        $disputaId,
                        $_SESSION['user_id'],
                        $tipoDocumento,
                        $novoNome,
                        $filename,
                        $fileSize,
                        $fileType,
                        $descricao
                    ]);
                    
                    $arquivosEnviados++;
                    
                    // Registrar no histórico
                    $stmt = $db->prepare("
                        INSERT INTO disputa_historico (disputa_id, usuario_id, evento, descricao) 
                        VALUES (?, ?, 'documento_adicionado', ?)
                    ");
                    $stmt->execute([
                        $disputaId,
                        $_SESSION['user_id'],
                        "Documento adicionado: $filename"
                    ]);
                    
                    // Notificar partes
                    if ($_SESSION['user_id'] == $disputa['reclamante_id']) {
                        createNotification($disputa['reclamado_id'], 'novo_documento', 
                            'Novo Documento', 
                            "Um novo documento foi adicionado à disputa {$disputa['codigo_caso']}",
                            "disputa-detalhes.php?id=$disputaId"
                        );
                    } else {
                        createNotification($disputa['reclamante_id'], 'novo_documento', 
                            'Novo Documento', 
                            "Um novo documento foi adicionado à disputa {$disputa['codigo_caso']}",
                            "disputa-detalhes.php?id=$disputaId"
                        );
                    }
                    
                    if ($disputa['arbitro_id']) {
                        $stmt = $db->prepare("SELECT usuario_id FROM arbitros WHERE id = ?");
                        $stmt->execute([$disputa['arbitro_id']]);
                        $arbitroUserId = $stmt->fetchColumn();
                        
                        createNotification($arbitroUserId, 'novo_documento', 
                            'Novo Documento', 
                            "Um novo documento foi adicionado à disputa {$disputa['codigo_caso']}",
                            "disputa-detalhes.php?id=$disputaId"
                        );
                    }
                    
                } catch (Exception $e) {
                    $errors[] = "Erro ao registrar documento: " . $e->getMessage();
                    unlink($caminhoCompleto); // Remover arquivo em caso de erro
                }
            } else {
                $errors[] = "Erro ao enviar arquivo: $filename";
            }
        }
    }
    
    if ($arquivosEnviados > 0) {
        $success = true;
        logActivity('documentos_enviados', "$arquivosEnviados documento(s) enviado(s)", $disputaId);
    }
}

// Buscar documentos existentes
$stmt = $db->prepare("
    SELECT dd.*, u.nome_completo as enviado_por
    FROM disputa_documentos dd
    JOIN usuarios u ON dd.usuario_id = u.id
    WHERE dd.disputa_id = ?
    ORDER BY dd.data_upload DESC
");
$stmt->execute([$disputaId]);
$documentos = $stmt->fetchAll();

// Agrupar documentos por tipo
$documentosPorTipo = [];
foreach ($documentos as $doc) {
    $documentosPorTipo[$doc['tipo_documento']][] = $doc;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload de Documentos - Arbitrivm</title>
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
        }
        
        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 30px;
        }
        
        /* Upload Form */
        .upload-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            height: fit-content;
        }
        
        .card-title {
            font-size: 1.25rem;
            color: #1a365d;
            margin-bottom: 20px;
            font-weight: 600;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            color: #4a5568;
            font-weight: 500;
            margin-bottom: 8px;
        }
        
        select, textarea {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        select:focus, textarea:focus {
            outline: none;
            border-color: #4299e1;
            box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
        }
        
        textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        /* Drop Zone */
        .drop-zone {
            border: 2px dashed #cbd5e0;
            border-radius: 12px;
            padding: 40px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background-color: #f7fafc;
        }
        
        .drop-zone:hover {
            border-color: #4299e1;
            background-color: #ebf8ff;
        }
        
        .drop-zone.drag-over {
            border-color: #4299e1;
            background-color: #ebf8ff;
            transform: scale(1.02);
        }
        
        .drop-zone-icon {
            width: 60px;
            height: 60px;
            margin: 0 auto 20px;
            opacity: 0.5;
        }
        
        .drop-zone-text {
            color: #4a5568;
            margin-bottom: 10px;
        }
        
        .drop-zone-hint {
            color: #718096;
            font-size: 0.9rem;
        }
        
        .file-input {
            display: none;
        }
        
        /* File List */
        .file-list {
            margin-top: 20px;
        }
        
        .file-item {
            display: flex;
            align-items: center;
            padding: 12px;
            background-color: #f7fafc;
            border-radius: 8px;
            margin-bottom: 10px;
        }
        
        .file-icon {
            width: 32px;
            height: 32px;
            margin-right: 12px;
            opacity: 0.7;
        }
        
        .file-info {
            flex: 1;
        }
        
        .file-name {
            font-weight: 500;
            color: #1a365d;
        }
        
        .file-size {
            font-size: 0.85rem;
            color: #718096;
        }
        
        .file-remove {
            color: #e53e3e;
            cursor: pointer;
            padding: 5px;
        }
        
        /* Documents List */
        .documents-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        .document-category {
            margin-bottom: 30px;
        }
        
        .category-title {
            font-size: 1.1rem;
            color: #1a365d;
            font-weight: 600;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e2e8f0;
        }
        
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
            font-size: 1.5rem;
        }
        
        .document-details {
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
        
        .document-actions {
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
        
        .btn-icon {
            padding: 8px;
            background: none;
            border: none;
            cursor: pointer;
            color: #718096;
            transition: color 0.3s;
        }
        
        .btn-icon:hover {
            color: #4a5568;
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
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #718096;
        }
        
        @media (max-width: 968px) {
            .content-grid {
                grid-template-columns: 1fr;
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
            <span>Documentos</span>
        </div>
        
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">Documentos da Disputa</h1>
            <p class="page-subtitle">
                <?php echo htmlspecialchars($disputa['codigo_caso']); ?> - 
                <?php echo htmlspecialchars($disputa['tipo_nome']); ?>
            </p>
        </div>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                Documento(s) enviado(s) com sucesso!
            </div>
        <?php endif; ?>
        
        <div class="content-grid">
            <!-- Upload Form -->
            <div class="upload-card">
                <h2 class="card-title">Enviar Novo Documento</h2>
                
                <form method="POST" enctype="multipart/form-data" id="uploadForm">
                    <div class="form-group">
                        <label for="tipo_documento">Tipo de Documento *</label>
                        <select name="tipo_documento" id="tipo_documento" required>
                            <option value="">Selecione...</option>
                            <option value="contrato">Contrato de Locação</option>
                            <option value="laudo">Laudo de Vistoria</option>
                            <option value="foto_video">Fotos/Vídeos</option>
                            <option value="ata">Ata de Condomínio</option>
                            <option value="notificacao">Notificação</option>
                            <option value="comprovante">Comprovante</option>
                            <option value="outros">Outros</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="descricao">Descrição</label>
                        <textarea name="descricao" id="descricao" 
                                  placeholder="Descreva brevemente o documento..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Arquivos *</label>
                        <div class="drop-zone" id="dropZone">
                            <svg class="drop-zone-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                      d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                            </svg>
                            <p class="drop-zone-text">Arraste arquivos aqui ou clique para selecionar</p>
                            <p class="drop-zone-hint">
                                Formatos aceitos: PDF, JPG, PNG, DOC, DOCX, MP4, AVI<br>
                                Tamanho máximo: 10MB por arquivo
                            </p>
                            <input type="file" name="documento[]" id="fileInput" class="file-input" 
                                   multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.mp4,.avi">
                        </div>
                        
                        <div class="file-list" id="fileList"></div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        Enviar Documentos
                    </button>
                </form>
            </div>
            
            <!-- Documents List -->
            <div class="documents-card">
                <h2 class="card-title">Documentos Anexados</h2>
                
                <?php if (empty($documentos)): ?>
                    <div class="empty-state">
                        <p>Nenhum documento foi anexado ainda.</p>
                    </div>
                <?php else: ?>
                    <?php 
                    $categorias = [
                        'contrato' => 'Contratos',
                        'laudo' => 'Laudos de Vistoria',
                        'foto_video' => 'Fotos e Vídeos',
                        'ata' => 'Atas de Condomínio',
                        'notificacao' => 'Notificações',
                        'comprovante' => 'Comprovantes',
                        'outros' => 'Outros Documentos'
                    ];
                    ?>
                    
                    <?php foreach ($categorias as $tipo => $nomeCategoria): ?>
                        <?php if (isset($documentosPorTipo[$tipo])): ?>
                            <div class="document-category">
                                <h3 class="category-title"><?php echo $nomeCategoria; ?></h3>
                                
                                <?php foreach ($documentosPorTipo[$tipo] as $doc): ?>
                                    <div class="document-item">
                                        <div class="document-icon">
                                            <?php
                                            $icones = [
                                                'pdf' => '📄',
                                                'jpg' => '🖼️',
                                                'jpeg' => '🖼️',
                                                'png' => '🖼️',
                                                'doc' => '📝',
                                                'docx' => '📝',
                                                'mp4' => '🎥',
                                                'avi' => '🎥'
                                            ];
                                            $ext = strtolower(pathinfo($doc['nome_original'], PATHINFO_EXTENSION));
                                            echo $icones[$ext] ?? '📎';
                                            ?>
                                        </div>
                                        
                                        <div class="document-details">
                                            <div class="document-name">
                                                <?php echo htmlspecialchars($doc['nome_original']); ?>
                                            </div>
                                            <div class="document-meta">
                                                <?php echo htmlspecialchars($doc['enviado_por']); ?> • 
                                                <?php echo formatDate($doc['data_upload'], 'd/m/Y H:i'); ?> • 
                                                <?php echo number_format($doc['tamanho'] / 1024 / 1024, 2); ?> MB
                                                <?php if ($doc['descricao']): ?>
                                                    <br><?php echo htmlspecialchars($doc['descricao']); ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="document-actions">
                                            <a href="download.php?tipo=documento&id=<?php echo $doc['id']; ?>" 
                                               class="btn-icon" title="Baixar">
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                          d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                </svg>
                                            </a>
                                            <?php
                                            $ext = strtolower(pathinfo($doc['nome_original'], PATHINFO_EXTENSION));
                                            if (in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'])): 
                                            ?>
                                                <a href="visualizar.php?id=<?php echo $doc['id']; ?>" 
                                                   target="_blank" class="btn-icon" title="Visualizar">
                                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                              d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                              d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                    </svg>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <script>
        // Drop zone functionality
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('fileInput');
        const fileList = document.getElementById('fileList');
        let selectedFiles = [];
        
        dropZone.addEventListener('click', () => fileInput.click());
        
        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('drag-over');
        });
        
        dropZone.addEventListener('dragleave', () => {
            dropZone.classList.remove('drag-over');
        });
        
        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('drag-over');
            handleFiles(e.dataTransfer.files);
        });
        
        fileInput.addEventListener('change', (e) => {
            handleFiles(e.target.files);
        });
        
        function handleFiles(files) {
            selectedFiles = Array.from(files);
            displayFiles();
        }
        
        function displayFiles() {
            fileList.innerHTML = '';
            
            selectedFiles.forEach((file, index) => {
                const fileItem = document.createElement('div');
                fileItem.className = 'file-item';
                
                const fileIcon = document.createElement('div');
                fileIcon.className = 'file-icon';
                fileIcon.textContent = getFileIcon(file.name);
                
                const fileInfo = document.createElement('div');
                fileInfo.className = 'file-info';
                
                const fileName = document.createElement('div');
                fileName.className = 'file-name';
                fileName.textContent = file.name;
                
                const fileSize = document.createElement('div');
                fileSize.className = 'file-size';
                fileSize.textContent = formatFileSize(file.size);
                
                fileInfo.appendChild(fileName);
                fileInfo.appendChild(fileSize);
                
                const removeBtn = document.createElement('span');
                removeBtn.className = 'file-remove';
                removeBtn.innerHTML = '✕';
                removeBtn.onclick = () => removeFile(index);
                
                fileItem.appendChild(fileIcon);
                fileItem.appendChild(fileInfo);
                fileItem.appendChild(removeBtn);
                
                fileList.appendChild(fileItem);
            });
        }
        
        function removeFile(index) {
            selectedFiles.splice(index, 1);
            displayFiles();
            
            // Update file input
            const dt = new DataTransfer();
            selectedFiles.forEach(file => dt.items.add(file));
            fileInput.files = dt.files;
        }
        
        function getFileIcon(filename) {
            const ext = filename.split('.').pop().toLowerCase();
            const icons = {
                'pdf': '📄',
                'jpg': '🖼️',
                'jpeg': '🖼️',
                'png': '🖼️',
                'doc': '📝',
                'docx': '📝',
                'mp4': '🎥',
                'avi': '🎥'
            };
            return icons[ext] || '📎';
        }
        
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
    </script>
</body>
</html>