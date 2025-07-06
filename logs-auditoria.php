<?php
session_start();
require_once 'config/conexao.php';

// Verificar se usuário está logado e é admin
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_tipo'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Configurações de paginação
$registros_por_pagina = 50;
$pagina_atual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina_atual - 1) * $registros_por_pagina;

// Filtros
$filtro_usuario = isset($_GET['usuario']) ? $_GET['usuario'] : '';
$filtro_acao = isset($_GET['acao']) ? $_GET['acao'] : '';
$filtro_disputa = isset($_GET['disputa']) ? $_GET['disputa'] : '';
$filtro_data_inicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : '';
$filtro_data_fim = isset($_GET['data_fim']) ? $_GET['data_fim'] : '';

// Construir query com filtros
$where_conditions = [];
$params = [];

if ($filtro_usuario) {
    $where_conditions[] = "(u.nome LIKE ? OR u.email LIKE ?)";
    $params[] = "%$filtro_usuario%";
    $params[] = "%$filtro_usuario%";
}

if ($filtro_acao) {
    $where_conditions[] = "l.acao = ?";
    $params[] = $filtro_acao;
}

if ($filtro_disputa) {
    $where_conditions[] = "l.disputa_id = ?";
    $params[] = $filtro_disputa;
}

if ($filtro_data_inicio) {
    $where_conditions[] = "DATE(l.data_hora) >= ?";
    $params[] = $filtro_data_inicio;
}

if ($filtro_data_fim) {
    $where_conditions[] = "DATE(l.data_hora) <= ?";
    $params[] = $filtro_data_fim;
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Contar total de registros
$sql_count = "SELECT COUNT(*) FROM logs_auditoria l 
              LEFT JOIN usuarios u ON l.usuario_id = u.id 
              $where_clause";
$stmt = $conexao->prepare($sql_count);
$stmt->execute($params);
$total_registros = $stmt->fetchColumn();
$total_paginas = ceil($total_registros / $registros_por_pagina);

// Buscar logs
$sql = "SELECT l.*, u.nome as usuario_nome, u.email as usuario_email, 
               d.numero_caso as disputa_numero
        FROM logs_auditoria l
        LEFT JOIN usuarios u ON l.usuario_id = u.id
        LEFT JOIN disputas d ON l.disputa_id = d.id
        $where_clause
        ORDER BY l.data_hora DESC
        LIMIT ? OFFSET ?";

$params[] = $registros_por_pagina;
$params[] = $offset;

$stmt = $conexao->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Buscar tipos de ações distintas para o filtro
$sql_acoes = "SELECT DISTINCT acao FROM logs_auditoria ORDER BY acao";
$stmt_acoes = $conexao->query($sql_acoes);
$acoes = $stmt_acoes->fetchAll(PDO::FETCH_COLUMN);

// Função para formatar ação
function formatarAcao($acao) {
    $acoes_formatadas = [
        'login' => 'Login',
        'logout' => 'Logout',
        'login_2fa_sucesso' => 'Login com 2FA',
        'login_2fa_bloqueado' => 'Login 2FA Bloqueado',
        'disputa_criada' => 'Disputa Criada',
        'disputa_atualizada' => 'Disputa Atualizada',
        'documento_upload' => 'Upload de Documento',
        'documento_visualizado' => 'Documento Visualizado',
        'sentenca_publicada' => 'Sentença Publicada',
        'mensagem_enviada' => 'Mensagem Enviada',
        'arbitro_selecionado' => 'Árbitro Selecionado',
        'perfil_atualizado' => 'Perfil Atualizado',
        'senha_alterada' => 'Senha Alterada',
        '2fa_ativado' => '2FA Ativado',
        '2fa_desativado' => '2FA Desativado'
    ];
    
    return isset($acoes_formatadas[$acao]) ? $acoes_formatadas[$acao] : ucfirst(str_replace('_', ' ', $acao));
}

// Função para formatar o ícone da ação
function getIconeAcao($acao) {
    $icones = [
        'login' => 'fa-sign-in-alt text-success',
        'logout' => 'fa-sign-out-alt text-secondary',
        'login_2fa_sucesso' => 'fa-shield-alt text-success',
        'login_2fa_bloqueado' => 'fa-ban text-danger',
        'disputa_criada' => 'fa-plus-circle text-primary',
        'disputa_atualizada' => 'fa-edit text-info',
        'documento_upload' => 'fa-file-upload text-info',
        'documento_visualizado' => 'fa-eye text-secondary',
        'sentenca_publicada' => 'fa-gavel text-warning',
        'mensagem_enviada' => 'fa-comment text-primary',
        'arbitro_selecionado' => 'fa-user-check text-success',
        'perfil_atualizado' => 'fa-user-edit text-info',
        'senha_alterada' => 'fa-key text-warning',
        '2fa_ativado' => 'fa-lock text-success',
        '2fa_desativado' => 'fa-unlock text-warning'
    ];
    
    return isset($icones[$acao]) ? $icones[$acao] : 'fa-circle text-muted';
}

// Processar exportação
if (isset($_GET['exportar'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=logs_auditoria_' . date('Y-m-d_H-i-s') . '.csv');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM para UTF-8
    
    // Cabeçalhos
    fputcsv($output, ['Data/Hora', 'Usuário', 'Email', 'Ação', 'Disputa', 'Detalhes', 'IP']);
    
    // Dados
    foreach ($logs as $log) {
        fputcsv($output, [
            $log['data_hora'],
            $log['usuario_nome'],
            $log['usuario_email'],
            formatarAcao($log['acao']),
            $log['disputa_numero'],
            $log['detalhes'],
            $log['ip_address']
        ]);
    }
    
    fclose($output);
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs de Auditoria - Arbitrivm Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .filter-card {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }
        .log-entry {
            border-left: 3px solid transparent;
            transition: all 0.3s;
        }
        .log-entry:hover {
            background-color: #f8f9fa;
            border-left-color: #0066cc;
        }
        .action-icon {
            width: 30px;
            text-align: center;
        }
        .details-cell {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .stats-box {
            background-color: #fff;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .stats-number {
            font-size: 2rem;
            font-weight: bold;
            color: #0066cc;
        }
        .export-btn {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .pagination {
            justify-content: center;
        }
    </style>
</head>
<body>
    <?php include 'includes/header-admin.php'; ?>

    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <h2 class="mb-4">
                    <i class="fas fa-history me-2"></i>Logs de Auditoria
                </h2>

                <!-- Estatísticas -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stats-box text-center">
                            <div class="stats-number"><?php echo number_format($total_registros); ?></div>
                            <div class="text-muted">Total de Registros</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-box text-center">
                            <div class="stats-number"><?php echo date('d'); ?></div>
                            <div class="text-muted">Registros Hoje</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-box text-center">
                            <div class="stats-number"><?php echo count($acoes); ?></div>
                            <div class="text-muted">Tipos de Ações</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-box text-center">
                            <div class="stats-number"><?php echo $total_paginas; ?></div>
                            <div class="text-muted">Páginas</div>
                        </div>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="filter-card">
                    <h5 class="mb-3"><i class="fas fa-filter me-2"></i>Filtros</h5>
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label for="usuario" class="form-label">Usuário</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="usuario" 
                                   name="usuario" 
                                   value="<?php echo htmlspecialchars($filtro_usuario); ?>"
                                   placeholder="Nome ou email">
                        </div>
                        <div class="col-md-2">
                            <label for="acao" class="form-label">Ação</label>
                            <select class="form-select" id="acao" name="acao">
                                <option value="">Todas</option>
                                <?php foreach ($acoes as $acao): ?>
                                    <option value="<?php echo $acao; ?>" 
                                            <?php echo $filtro_acao === $acao ? 'selected' : ''; ?>>
                                        <?php echo formatarAcao($acao); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="disputa" class="form-label">Disputa #</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="disputa" 
                                   name="disputa" 
                                   value="<?php echo htmlspecialchars($filtro_disputa); ?>"
                                   placeholder="ID da disputa">
                        </div>
                        <div class="col-md-2">
                            <label for="data_inicio" class="form-label">Data Início</label>
                            <input type="date" 
                                   class="form-control" 
                                   id="data_inicio" 
                                   name="data_inicio" 
                                   value="<?php echo $filtro_data_inicio; ?>">
                        </div>
                        <div class="col-md-2">
                            <label for="data_fim" class="form-label">Data Fim</label>
                            <input type="date" 
                                   class="form-control" 
                                   id="data_fim" 
                                   name="data_fim" 
                                   value="<?php echo $filtro_data_fim; ?>">
                        </div>
                        <div class="col-md-1 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </form>
                    <?php if (array_filter($_GET)): ?>
                        <div class="mt-3">
                            <a href="logs-auditoria.php" class="btn btn-sm btn-secondary">
                                <i class="fas fa-times me-1"></i>Limpar Filtros
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Tabela de Logs -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th width="40"></th>
                                        <th>Data/Hora</th>
                                        <th>Usuário</th>
                                        <th>Ação</th>
                                        <th>Disputa</th>
                                        <th>Detalhes</th>
                                        <th>IP</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($logs as $log): ?>
                                        <tr class="log-entry">
                                            <td class="action-icon">
                                                <i class="fas <?php echo getIconeAcao($log['acao']); ?>"></i>
                                            </td>
                                            <td>
                                                <small><?php echo date('d/m/Y H:i:s', strtotime($log['data_hora'])); ?></small>
                                            </td>
                                            <td>
                                                <?php if ($log['usuario_nome']): ?>
                                                    <strong><?php echo htmlspecialchars($log['usuario_nome']); ?></strong><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($log['usuario_email']); ?></small>
                                                <?php else: ?>
                                                    <span class="text-muted">Sistema</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    <?php echo formatarAcao($log['acao']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($log['disputa_numero']): ?>
                                                    <a href="disputa-detalhes.php?id=<?php echo $log['disputa_id']; ?>" 
                                                       target="_blank">
                                                        #<?php echo $log['disputa_numero']; ?>
                                                    </a>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td class="details-cell" title="<?php echo htmlspecialchars($log['detalhes']); ?>">
                                                <?php echo htmlspecialchars($log['detalhes']); ?>
                                            </td>
                                            <td>
                                                <small><?php echo $log['ip_address']; ?></small>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    
                                    <?php if (empty($logs)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-4 text-muted">
                                                <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                                Nenhum registro encontrado
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Paginação -->
                        <?php if ($total_paginas > 1): ?>
                            <nav aria-label="Navegação de logs">
                                <ul class="pagination mt-4">
                                    <li class="page-item <?php echo $pagina_atual == 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?pagina=<?php echo $pagina_atual - 1; ?>&<?php echo http_build_query(array_diff_key($_GET, ['pagina' => ''])); ?>">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    </li>
                                    
                                    <?php for ($i = max(1, $pagina_atual - 2); $i <= min($total_paginas, $pagina_atual + 2); $i++): ?>
                                        <li class="page-item <?php echo $i == $pagina_atual ? 'active' : ''; ?>">
                                            <a class="page-link" href="?pagina=<?php echo $i; ?>&<?php echo http_build_query(array_diff_key($_GET, ['pagina' => ''])); ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <li class="page-item <?php echo $pagina_atual == $total_paginas ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?pagina=<?php echo $pagina_atual + 1; ?>&<?php echo http_build_query(array_diff_key($_GET, ['pagina' => ''])); ?>">
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Botão de Exportar -->
    <a href="?exportar=true&<?php echo http_build_query($_GET); ?>" 
       class="btn btn-success btn-lg export-btn"
       title="Exportar logs filtrados para CSV">
        <i class="fas fa-download me-2"></i>Exportar CSV
    </a>

    <?php include 'includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>