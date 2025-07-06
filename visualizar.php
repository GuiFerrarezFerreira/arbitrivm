<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

// Verificar se usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(403);
    die('Acesso negado. Faça login para continuar.');
}

// Validar parâmetros
$arquivo_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$disputa_id = isset($_GET['disputa']) ? intval($_GET['disputa']) : 0;

if (!$arquivo_id || !$disputa_id) {
    http_response_code(400);
    die('Parâmetros inválidos.');
}

try {
    // Buscar informações do arquivo
    $stmt = $pdo->prepare("
        SELECT a.*, d.tipo_disputa, d.status as disputa_status,
               d.reclamante_id, d.reclamado_id, d.arbitro_id,
               d.imobiliaria_id, d.condominio_id
        FROM arquivos a
        INNER JOIN disputas d ON a.disputa_id = d.id
        WHERE a.id = ? AND a.disputa_id = ? AND a.ativo = 1
    ");
    $stmt->execute([$arquivo_id, $disputa_id]);
    $arquivo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$arquivo) {
        http_response_code(404);
        die('Arquivo não encontrado.');
    }
    
    // Verificar permissões de acesso
    $tem_acesso = false;
    $usuario_id = $_SESSION['usuario_id'];
    $tipo_usuario = $_SESSION['tipo_usuario'];
    
    // Verificar se usuário tem acesso ao arquivo
    if ($tipo_usuario == 'admin') {
        $tem_acesso = true;
    } elseif ($tipo_usuario == 'arbitro' && $arquivo['arbitro_id'] == $usuario_id) {
        $tem_acesso = true;
    } elseif ($tipo_usuario == 'parte') {
        // Verificar se é reclamante ou reclamado
        if ($arquivo['reclamante_id'] == $usuario_id || $arquivo['reclamado_id'] == $usuario_id) {
            $tem_acesso = true;
        }
    } elseif ($tipo_usuario == 'imobiliaria' && $arquivo['imobiliaria_id'] == $_SESSION['empresa_id']) {
        $tem_acesso = true;
    } elseif ($tipo_usuario == 'condominio' && $arquivo['condominio_id'] == $_SESSION['empresa_id']) {
        $tem_acesso = true;
    }
    
    // Verificar se arquivo é público dentro da disputa
    if (!$tem_acesso && $arquivo['visibilidade'] == 'publico') {
        // Verificar se usuário está envolvido na disputa de alguma forma
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM disputas_participantes 
            WHERE disputa_id = ? AND usuario_id = ? AND ativo = 1
        ");
        $stmt->execute([$disputa_id, $usuario_id]);
        if ($stmt->fetchColumn() > 0) {
            $tem_acesso = true;
        }
    }
    
    if (!$tem_acesso) {
        // Registrar tentativa de acesso negado
        $stmt = $pdo->prepare("
            INSERT INTO logs_acesso (usuario_id, acao, tipo_objeto, objeto_id, ip, resultado, detalhes)
            VALUES (?, 'visualizar_arquivo', 'arquivo', ?, ?, 'negado', 'Sem permissão')
        ");
        $stmt->execute([$usuario_id, $arquivo_id, $_SERVER['REMOTE_ADDR']]);
        
        http_response_code(403);
        die('Você não tem permissão para visualizar este arquivo.');
    }
    
    // Caminho do arquivo
    $caminho_arquivo = $arquivo['caminho_arquivo'];
    if (!file_exists($caminho_arquivo)) {
        http_response_code(404);
        die('Arquivo físico não encontrado.');
    }
    
    // Registrar visualização
    $stmt = $pdo->prepare("
        INSERT INTO logs_visualizacao (arquivo_id, usuario_id, disputa_id, ip, user_agent)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $arquivo_id, 
        $usuario_id, 
        $disputa_id, 
        $_SERVER['REMOTE_ADDR'],
        $_SERVER['HTTP_USER_AGENT']
    ]);
    
    // Atualizar contador de visualizações
    $stmt = $pdo->prepare("
        UPDATE arquivos SET visualizacoes = visualizacoes + 1 WHERE id = ?
    ");
    $stmt->execute([$arquivo_id]);
    
    // Determinar tipo MIME
    $mime_type = $arquivo['tipo_arquivo'];
    $extensao = strtolower(pathinfo($arquivo['nome_arquivo'], PATHINFO_EXTENSION));
    
    // Tipos suportados para visualização inline
    $tipos_inline = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp'
    ];
    
    if (!isset($tipos_inline[$extensao])) {
        // Redirecionar para download se não for visualizável
        header("Location: download.php?id=$arquivo_id&disputa=$disputa_id");
        exit;
    }
    
    // Headers de segurança
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Content-Security-Policy: default-src \'self\'');
    
    // Para PDFs
    if ($extensao == 'pdf') {
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . basename($arquivo['nome_arquivo']) . '"');
        header('Cache-Control: private, max-age=3600');
        
        // Enviar arquivo
        readfile($caminho_arquivo);
        exit;
    }
    
    // Para imagens - criar página HTML com visualizador
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($arquivo['nome_arquivo']); ?> - Arbitrivm</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body {
                background-color: #f8f9fa;
                margin: 0;
                padding: 0;
            }
            .viewer-container {
                display: flex;
                flex-direction: column;
                height: 100vh;
            }
            .viewer-header {
                background-color: #fff;
                border-bottom: 1px solid #dee2e6;
                padding: 1rem;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            .viewer-content {
                flex: 1;
                display: flex;
                justify-content: center;
                align-items: center;
                padding: 2rem;
                overflow: auto;
            }
            .image-container {
                max-width: 100%;
                max-height: 100%;
                text-align: center;
            }
            .image-container img {
                max-width: 100%;
                max-height: calc(100vh - 200px);
                object-fit: contain;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                border-radius: 8px;
            }
            .file-info {
                background-color: #e9ecef;
                padding: 0.5rem 1rem;
                border-radius: 5px;
                font-size: 0.9rem;
            }
            .watermark {
                position: fixed;
                bottom: 20px;
                right: 20px;
                opacity: 0.1;
                font-size: 3rem;
                font-weight: bold;
                color: #000;
                transform: rotate(-45deg);
                pointer-events: none;
                z-index: 1000;
            }
        </style>
    </head>
    <body>
        <div class="viewer-container">
            <div class="viewer-header">
                <div class="container-fluid">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h5 class="mb-0">
                                <i class="bi bi-file-earmark-image"></i>
                                <?php echo htmlspecialchars($arquivo['nome_arquivo']); ?>
                            </h5>
                            <div class="file-info mt-2">
                                <span class="me-3">
                                    <i class="bi bi-calendar"></i> 
                                    Upload: <?php echo date('d/m/Y H:i', strtotime($arquivo['data_upload'])); ?>
                                </span>
                                <span class="me-3">
                                    <i class="bi bi-person"></i>
                                    Por: <?php echo htmlspecialchars($arquivo['nome_usuario_upload'] ?? 'Sistema'); ?>
                                </span>
                                <span class="me-3">
                                    <i class="bi bi-eye"></i>
                                    <?php echo $arquivo['visualizacoes']; ?> visualizações
                                </span>
                                <span>
                                    <i class="bi bi-hdd"></i>
                                    <?php echo formatarTamanhoArquivo($arquivo['tamanho_arquivo']); ?>
                                </span>
                            </div>
                        </div>
                        <div class="col-md-4 text-end">
                            <a href="download.php?id=<?php echo $arquivo_id; ?>&disputa=<?php echo $disputa_id; ?>" 
                               class="btn btn-primary btn-sm">
                                <i class="bi bi-download"></i> Baixar
                            </a>
                            <button onclick="window.print();" class="btn btn-secondary btn-sm">
                                <i class="bi bi-printer"></i> Imprimir
                            </button>
                            <button onclick="window.close();" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-x-lg"></i> Fechar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="viewer-content">
                <div class="image-container">
                    <img src="data:<?php echo $mime_type; ?>;base64,<?php echo base64_encode(file_get_contents($caminho_arquivo)); ?>" 
                         alt="<?php echo htmlspecialchars($arquivo['nome_arquivo']); ?>"
                         id="documentImage">
                </div>
            </div>
        </div>
        
        <!-- Marca d'água opcional -->
        <?php if ($arquivo['confidencial'] == 1): ?>
        <div class="watermark">CONFIDENCIAL</div>
        <?php endif; ?>
        
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            // Desabilitar clique direito na imagem
            document.getElementById('documentImage').addEventListener('contextmenu', function(e) {
                e.preventDefault();
                return false;
            });
            
            // Log de tempo de visualização
            let startTime = new Date().getTime();
            
            window.addEventListener('beforeunload', function() {
                let endTime = new Date().getTime();
                let timeSpent = Math.round((endTime - startTime) / 1000);
                
                // Enviar tempo de visualização via beacon API
                if (navigator.sendBeacon) {
                    let data = new FormData();
                    data.append('arquivo_id', '<?php echo $arquivo_id; ?>');
                    data.append('tempo_visualizacao', timeSpent);
                    navigator.sendBeacon('log_tempo_visualizacao.php', data);
                }
            });
        </script>
    </body>
    </html>
    <?php
    
} catch (Exception $e) {
    error_log("Erro ao visualizar arquivo: " . $e->getMessage());
    http_response_code(500);
    die('Erro ao processar solicitação.');
}

// Função auxiliar para formatar tamanho de arquivo
function formatarTamanhoArquivo($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}
?>