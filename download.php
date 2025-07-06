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
               d.imobiliaria_id, d.condominio_id,
               u.nome as nome_usuario_upload
        FROM arquivos a
        INNER JOIN disputas d ON a.disputa_id = d.id
        LEFT JOIN usuarios u ON a.usuario_upload_id = u.id
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
            VALUES (?, 'download_arquivo', 'arquivo', ?, ?, 'negado', 'Sem permissão')
        ");
        $stmt->execute([$usuario_id, $arquivo_id, $_SERVER['REMOTE_ADDR']]);
        
        http_response_code(403);
        die('Você não tem permissão para baixar este arquivo.');
    }
    
    // Verificar se há restrições de download
    if ($arquivo['permite_download'] == 0 && $tipo_usuario != 'admin') {
        // Registrar tentativa
        $stmt = $pdo->prepare("
            INSERT INTO logs_acesso (usuario_id, acao, tipo_objeto, objeto_id, ip, resultado, detalhes)
            VALUES (?, 'download_arquivo', 'arquivo', ?, ?, 'negado', 'Download não permitido')
        ");
        $stmt->execute([$usuario_id, $arquivo_id, $_SERVER['REMOTE_ADDR']]);
        
        http_response_code(403);
        die('Este arquivo não permite download. Use a opção de visualização.');
    }
    
    // Verificar limite de downloads se aplicável
    if ($arquivo['limite_downloads'] > 0) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total_downloads 
            FROM logs_download 
            WHERE arquivo_id = ? AND usuario_id = ?
        ");
        $stmt->execute([$arquivo_id, $usuario_id]);
        $downloads = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($downloads['total_downloads'] >= $arquivo['limite_downloads']) {
            http_response_code(403);
            die('Você atingiu o limite de downloads para este arquivo.');
        }
    }
    
    // Caminho do arquivo
    $caminho_arquivo = $arquivo['caminho_arquivo'];
    if (!file_exists($caminho_arquivo)) {
        // Registrar erro
        $stmt = $pdo->prepare("
            INSERT INTO logs_erro (tipo_erro, mensagem, arquivo_id, usuario_id, detalhes)
            VALUES ('arquivo_nao_encontrado', 'Arquivo físico não encontrado', ?, ?, ?)
        ");
        $stmt->execute([$arquivo_id, $usuario_id, json_encode(['caminho' => $caminho_arquivo])]);
        
        http_response_code(404);
        die('Arquivo físico não encontrado no servidor.');
    }
    
    // Registrar download
    $stmt = $pdo->prepare("
        INSERT INTO logs_download (arquivo_id, usuario_id, disputa_id, ip, user_agent, tamanho_arquivo)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $arquivo_id, 
        $usuario_id, 
        $disputa_id, 
        $_SERVER['REMOTE_ADDR'],
        $_SERVER['HTTP_USER_AGENT'],
        $arquivo['tamanho_arquivo']
    ]);
    
    // Atualizar contador de downloads
    $stmt = $pdo->prepare("
        UPDATE arquivos SET downloads = downloads + 1, ultimo_download = NOW() WHERE id = ?
    ");
    $stmt->execute([$arquivo_id]);
    
    // Registrar log de acesso bem-sucedido
    $stmt = $pdo->prepare("
        INSERT INTO logs_acesso (usuario_id, acao, tipo_objeto, objeto_id, ip, resultado, detalhes)
        VALUES (?, 'download_arquivo', 'arquivo', ?, ?, 'sucesso', ?)
    ");
    $stmt->execute([
        $usuario_id, 
        $arquivo_id, 
        $_SERVER['REMOTE_ADDR'],
        json_encode([
            'nome_arquivo' => $arquivo['nome_arquivo'],
            'tamanho' => $arquivo['tamanho_arquivo'],
            'tipo_disputa' => $arquivo['tipo_disputa']
        ])
    ]);
    
    // Preparar nome do arquivo para download
    $nome_download = $arquivo['nome_arquivo'];
    
    // Adicionar prefixo com ID da disputa para organização
    if ($arquivo['tipo_disputa']) {
        $nome_download = "Disputa{$disputa_id}_" . $nome_download;
    }
    
    // Sanitizar nome do arquivo
    $nome_download = preg_replace('/[^a-zA-Z0-9._-]/', '_', $nome_download);
    
    // Headers de segurança
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Content-Security-Policy: default-src \'none\'');
    
    // Headers para download
    header('Content-Type: ' . $arquivo['tipo_arquivo']);
    header('Content-Disposition: attachment; filename="' . $nome_download . '"');
    header('Content-Length: ' . $arquivo['tamanho_arquivo']);
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Adicionar header customizado com hash para verificação de integridade
    if ($arquivo['hash_arquivo']) {
        header('X-File-Hash: ' . $arquivo['hash_arquivo']);
    }
    
    // Desabilitar buffer de saída para arquivos grandes
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Fazer download em chunks para arquivos grandes
    $chunk_size = 8192; // 8KB por vez
    $handle = fopen($caminho_arquivo, 'rb');
    
    if ($handle === false) {
        http_response_code(500);
        die('Erro ao abrir arquivo.');
    }
    
    while (!feof($handle)) {
        $buffer = fread($handle, $chunk_size);
        echo $buffer;
        flush();
        
        // Verificar se conexão ainda está ativa
        if (connection_aborted()) {
            // Registrar download incompleto
            $stmt = $pdo->prepare("
                UPDATE logs_download 
                SET status = 'incompleto', 
                    observacoes = 'Conexão interrompida' 
                WHERE arquivo_id = ? AND usuario_id = ? 
                ORDER BY data_download DESC 
                LIMIT 1
            ");
            $stmt->execute([$arquivo_id, $usuario_id]);
            break;
        }
    }
    
    fclose($handle);
    
    // Registrar conclusão bem-sucedida
    if (!connection_aborted()) {
        $stmt = $pdo->prepare("
            UPDATE logs_download 
            SET status = 'completo' 
            WHERE arquivo_id = ? AND usuario_id = ? 
            ORDER BY data_download DESC 
            LIMIT 1
        ");
        $stmt->execute([$arquivo_id, $usuario_id]);
    }
    
    exit;
    
} catch (Exception $e) {
    // Registrar erro
    error_log("Erro no download de arquivo: " . $e->getMessage());
    
    if (isset($pdo) && isset($usuario_id)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO logs_erro (tipo_erro, mensagem, arquivo_id, usuario_id, detalhes)
                VALUES ('erro_download', ?, ?, ?, ?)
            ");
            $stmt->execute([
                $e->getMessage(),
                $arquivo_id ?? null,
                $usuario_id,
                json_encode([
                    'trace' => $e->getTraceAsString(),
                    'ip' => $_SERVER['REMOTE_ADDR']
                ])
            ]);
        } catch (Exception $logError) {
            error_log("Erro ao registrar log: " . $logError->getMessage());
        }
    }
    
    http_response_code(500);
    die('Erro ao processar download. Por favor, tente novamente.');
}
?>  