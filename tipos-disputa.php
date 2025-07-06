<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tipos de Disputa - Arbitrivm Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --success-color: #27ae60;
            --danger-color: #e74c3c;
            --warning-color: #f39c12;
            --dark-bg: #1a1a1a;
            --card-bg: #2d2d2d;
            --text-light: #ecf0f1;
        }

        body {
            background-color: #f8f9fa;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }

        .sidebar {
            background-color: var(--primary-color);
            min-height: 100vh;
            color: white;
            padding-top: 20px;
        }

        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 12px 20px;
            margin: 5px 15px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .sidebar .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
        }

        .sidebar .nav-link.active {
            background-color: var(--secondary-color);
            color: white;
        }

        .main-content {
            padding: 30px;
        }

        .dispute-type-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            margin-bottom: 25px;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .dispute-type-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            border-color: var(--secondary-color);
        }

        .dispute-type-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .dispute-type-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .status-toggle {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-check-input {
            width: 50px;
            height: 25px;
            cursor: pointer;
        }

        .field-list {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }

        .field-item {
            background: white;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border: 1px solid #dee2e6;
        }

        .field-item:hover {
            background-color: #f8f9fa;
        }

        .field-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .field-required {
            background-color: #ffeaa7;
            color: #d63031;
        }

        .field-optional {
            background-color: #e3f2fd;
            color: #1976d2;
        }

        .stats-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .stat-box {
            text-align: center;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 8px;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .stat-label {
            font-size: 0.875rem;
            color: #6c757d;
            text-transform: uppercase;
        }

        .modal-content {
            border-radius: 12px;
            border: none;
        }

        .modal-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            border-radius: 12px 12px 0 0;
        }

        .sortable-handle {
            cursor: move;
            color: #6c757d;
        }

        .add-field-btn {
            border: 2px dashed #dee2e6;
            background-color: transparent;
            color: #6c757d;
            padding: 15px;
            border-radius: 6px;
            width: 100%;
            transition: all 0.3s ease;
        }

        .add-field-btn:hover {
            border-color: var(--secondary-color);
            color: var(--secondary-color);
            background-color: rgba(52, 152, 219, 0.05);
        }

        @media (max-width: 768px) {
            .sidebar {
                display: none;
            }
            
            .main-content {
                padding: 15px;
            }

            .stats-info {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid p-0">
        <div class="row g-0">
            <!-- Sidebar -->
            <div class="col-md-2 sidebar">
                <div class="text-center mb-4">
                    <h4 class="fw-bold">Arbitrivm</h4>
                    <p class="small mb-0">Painel Admin</p>
                </div>
                <nav class="nav flex-column">
                    <a class="nav-link" href="dashboard.php"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a>
                    <a class="nav-link" href="usuarios.php"><i class="bi bi-people me-2"></i> Usuários</a>
                    <a class="nav-link" href="empresas.php"><i class="bi bi-building me-2"></i> Empresas</a>
                    <a class="nav-link active" href="tipos-disputa.php"><i class="bi bi-list-check me-2"></i> Tipos de Disputa</a>
                    <a class="nav-link" href="arbitros.php"><i class="bi bi-person-badge me-2"></i> Árbitros</a>
                    <a class="nav-link" href="disputas.php"><i class="bi bi-clipboard-data me-2"></i> Disputas</a>
                    <a class="nav-link" href="relatorios.php"><i class="bi bi-graph-up me-2"></i> Relatórios</a>
                    <a class="nav-link mt-5" href="logout.php"><i class="bi bi-box-arrow-left me-2"></i> Sair</a>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="col-md-10">
                <div class="main-content">
                    <!-- Header -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h2 class="mb-1">Tipos de Disputa</h2>
                            <p class="text-muted mb-0">Configure os tipos de disputa e seus campos obrigatórios</p>
                        </div>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#novoTipoModal">
                            <i class="bi bi-plus-lg me-2"></i>Novo Tipo de Disputa
                        </button>
                    </div>

                    <!-- Dispute Type: Danos ao Imóvel -->
                    <div class="dispute-type-card">
                        <div class="dispute-type-header">
                            <div class="d-flex align-items-center">
                                <div class="dispute-type-icon bg-danger me-3">
                                    <i class="bi bi-house-damage"></i>
                                </div>
                                <div>
                                    <h4 class="mb-1">Danos ao Imóvel</h4>
                                    <p class="text-muted mb-0">Disputas relacionadas a danos físicos em propriedades</p>
                                </div>
                            </div>
                            <div class="status-toggle">
                                <label class="form-check-label">Status:</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" checked>
                                </div>
                            </div>
                        </div>

                        <div class="stats-info">
                            <div class="stat-box">
                                <p class="stat-value mb-0">1,247</p>
                                <p class="stat-label mb-0">Total de Casos</p>
                            </div>
                            <div class="stat-box">
                                <p class="stat-value mb-0">89%</p>
                                <p class="stat-label mb-0">Taxa de Resolução</p>
                            </div>
                            <div class="stat-box">
                                <p class="stat-value mb-0">12 dias</p>
                                <p class="stat-label mb-0">Tempo Médio</p>
                            </div>
                            <div class="stat-box">
                                <p class="stat-value mb-0">R$ 3.2k</p>
                                <p class="stat-label mb-0">Valor Médio</p>
                            </div>
                        </div>

                        <div class="field-list">
                            <h6 class="mb-3">Campos do Formulário</h6>
                            <div class="field-item">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-grip-vertical sortable-handle me-2"></i>
                                    <i class="bi bi-geo-alt me-2"></i>
                                    <span>Endereço do Imóvel</span>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="field-badge field-required">Obrigatório</span>
                                    <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></button>
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </div>
                            </div>
                            <div class="field-item">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-grip-vertical sortable-handle me-2"></i>
                                    <i class="bi bi-file-text me-2"></i>
                                    <span>Descrição dos Danos</span>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="field-badge field-required">Obrigatório</span>
                                    <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></button>
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </div>
                            </div>
                            <div class="field-item">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-grip-vertical sortable-handle me-2"></i>
                                    <i class="bi bi-camera me-2"></i>
                                    <span>Fotos/Vídeos dos Danos</span>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="field-badge field-required">Obrigatório</span>
                                    <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></button>
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </div>
                            </div>
                            <div class="field-item">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-grip-vertical sortable-handle me-2"></i>
                                    <i class="bi bi-calendar me-2"></i>
                                    <span>Data de Ocorrência</span>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="field-badge field-required">Obrigatório</span>
                                    <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></button>
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </div>
                            </div>
                            <div class="field-item">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-grip-vertical sortable-handle me-2"></i>
                                    <i class="bi bi-currency-dollar me-2"></i>
                                    <span>Valor Estimado do Prejuízo</span>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="field-badge field-optional">Opcional</span>
                                    <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></button>
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </div>
                            </div>
                            <div class="field-item">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-grip-vertical sortable-handle me-2"></i>
                                    <i class="bi bi-file-earmark-text me-2"></i>
                                    <span>Laudo de Vistoria</span>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="field-badge field-optional">Opcional</span>
                                    <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></button>
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </div>
                            </div>
                            <button class="add-field-btn mt-3">
                                <i class="bi bi-plus-lg me-2"></i>Adicionar Campo
                            </button>
                        </div>
                    </div>

                    <!-- Dispute Type: Infração Condominial -->
                    <div class="dispute-type-card">
                        <div class="dispute-type-header">
                            <div class="d-flex align-items-center">
                                <div class="dispute-type-icon bg-warning me-3">
                                    <i class="bi bi-exclamation-triangle"></i>
                                </div>
                                <div>
                                    <h4 class="mb-1">Infração Condominial</h4>
                                    <p class="text-muted mb-0">Violações de regras e convenções condominiais</p>
                                </div>
                            </div>
                            <div class="status-toggle">
                                <label class="form-check-label">Status:</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" checked>
                                </div>
                            </div>
                        </div>

                        <div class="stats-info">
                            <div class="stat-box">
                                <p class="stat-value mb-0">892</p>
                                <p class="stat-label mb-0">Total de Casos</p>
                            </div>
                            <div class="stat-box">
                                <p class="stat-value mb-0">94%</p>
                                <p class="stat-label mb-0">Taxa de Resolução</p>
                            </div>
                            <div class="stat-box">
                                <p class="stat-value mb-0">8 dias</p>
                                <p class="stat-label mb-0">Tempo Médio</p>
                            </div>
                            <div class="stat-box">
                                <p class="stat-value mb-0">R$ 1.8k</p>
                                <p class="stat-label mb-0">Valor Médio</p>
                            </div>
                        </div>

                        <div class="field-list">
                            <h6 class="mb-3">Campos do Formulário</h6>
                            <div class="field-item">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-grip-vertical sortable-handle me-2"></i>
                                    <i class="bi bi-building me-2"></i>
                                    <span>Identificação do Condomínio</span>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="field-badge field-required">Obrigatório</span>
                                    <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></button>
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </div>
                            </div>
                            <div class="field-item">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-grip-vertical sortable-handle me-2"></i>
                                    <i class="bi bi-list-ul me-2"></i>
                                    <span>Tipo de Infração</span>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="field-badge field-required">Obrigatório</span>
                                    <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></button>
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </div>
                            </div>
                            <div class="field-item">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-grip-vertical sortable-handle me-2"></i>
                                    <i class="bi bi-file-text me-2"></i>
                                    <span>Descrição da Infração</span>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="field-badge field-required">Obrigatório</span>
                                    <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></button>
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </div>
                            </div>
                            <div class="field-item">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-grip-vertical sortable-handle me-2"></i>
                                    <i class="bi bi-book me-2"></i>
                                    <span>Artigo do Regimento Interno</span>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="field-badge field-optional">Opcional</span>
                                    <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></button>
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </div>
                            </div>
                            <div class="field-item">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-grip-vertical sortable-handle me-2"></i>
                                    <i class="bi bi-bell me-2"></i>
                                    <span>Notificações Anteriores</span>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="field-badge field-optional">Opcional</span>
                                    <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></button>
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </div>
                            </div>
                            <button class="add-field-btn mt-3">
                                <i class="bi bi-plus-lg me-2"></i>Adicionar Campo
                            </button>
                        </div>
                    </div>

                    <!-- Dispute Type: Inadimplência (Inactive) -->
                    <div class="dispute-type-card" style="opacity: 0.6;">
                        <div class="dispute-type-header">
                            <div class="d-flex align-items-center">
                                <div class="dispute-type-icon bg-secondary me-3">
                                    <i class="bi bi-cash-stack"></i>
                                </div>
                                <div>
                                    <h4 class="mb-1">Inadimplência de Aluguel</h4>
                                    <p class="text-muted mb-0">Atrasos e falta de pagamento de aluguéis</p>
                                </div>
                            </div>
                            <div class="status-toggle">
                                <label class="form-check-label">Status:</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox">
                                </div>
                            </div>
                        </div>

                        <div class="stats-info">
                            <div class="stat-box">
                                <p class="stat-value mb-0">0</p>
                                <p class="stat-label mb-0">Total de Casos</p>
                            </div>
                            <div class="stat-box">
                                <p class="stat-value mb-0">-</p>
                                <p class="stat-label mb-0">Taxa de Resolução</p>
                            </div>
                            <div class="stat-box">
                                <p class="stat-value mb-0">-</p>
                                <p class="stat-label mb-0">Tempo Médio</p>
                            </div>
                            <div class="stat-box">
                                <p class="stat-value mb-0">-</p>
                                <p class="stat-label mb-0">Valor Médio</p>
                            </div>
                        </div>

                        <div class="text-center py-4">
                            <p class="text-muted">Tipo de disputa desativado</p>
                            <button class="btn btn-sm btn-outline-primary">Ativar Tipo de Disputa</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Novo Tipo de Disputa -->
    <div class="modal fade" id="novoTipoModal" tabindex="-1" aria-labelledby="novoTipoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="novoTipoModalLabel">Novo Tipo de Disputa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form>
                        <div class="mb-3">
                            <label for="nomeTipo" class="form-label">Nome do Tipo de Disputa</label>
                            <input type="text" class="form-control" id="nomeTipo" placeholder="Ex: Cobrança de Taxas Condominiais">
                        </div>
                        <div class="mb-3">
                            <label for="descricaoTipo" class="form-label">Descrição</label>
                            <textarea class="form-control" id="descricaoTipo" rows="3" placeholder="Descreva brevemente este tipo de disputa"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="iconeTipo" class="form-label">Ícone</label>
                            <select class="form-select" id="iconeTipo">
                                <option value="bi-house-damage">Casa com Danos</option>
                                <option value="bi-exclamation-triangle">Triângulo de Aviso</option>
                                <option value="bi-cash-stack">Dinheiro</option>
                                <option value="bi-file-text">Documento</option>
                                <option value="bi-people">Pessoas</option>
                                <option value="bi-shield-check">Escudo</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="corTipo" class="form-label">Cor do Tema</label>
                            <select class="form-select" id="corTipo">
                                <option value="primary">Azul</option>
                                <option value="success">Verde</option>
                                <option value="danger">Vermelho</option>
                                <option value="warning">Amarelo</option>
                                <option value="info">Azul Claro</option>
                                <option value="secondary">Cinza</option>
                            </select>
                        </div>
                        <hr>
                        <h6 class="mb-3">Campos do Formulário</h6>
                        <div id="camposContainer">
                            <div class="field-config mb-3">
                                <div class="row">
                                    <div class="col-md-5">
                                        <input type="text" class="form-control" placeholder="Nome do campo">
                                    </div>
                                    <div class="col-md-3">
                                        <select class="form-select">
                                            <option>Texto</option>
                                            <option>Número</option>
                                            <option>Data</option>
                                            <option>Arquivo</option>
                                            <option>Seleção</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" checked>
                                            <label class="form-check-label">Obrigatório</label>
                                        </div>
                                    </div>
                                    <div class="col-md-1">
                                        <button type="button" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="adicionarCampo()">
                            <i class="bi bi-plus-lg me-1"></i>Adicionar Campo
                        </button>
                        <hr>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="" id="ativoCheck" checked>
                            <label class="form-check-label" for="ativoCheck">
                                Tipo de disputa ativo
                            </label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary">Criar Tipo de Disputa</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Editar Campo -->
    <div class="modal fade" id="editarCampoModal" tabindex="-1" aria-labelledby="editarCampoModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editarCampoModalLabel">Editar Campo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form>
                        <div class="mb-3">
                            <label for="nomeCampo" class="form-label">Nome do Campo</label>
                            <input type="text" class="form-control" id="nomeCampo">
                        </div>
                        <div class="mb-3">
                            <label for="tipoCampo" class="form-label">Tipo de Campo</label>
                            <select class="form-select" id="tipoCampo">
                                <option>Texto</option>
                                <option>Texto Longo</option>
                                <option>Número</option>
                                <option>Moeda</option>
                                <option>Data</option>
                                <option>Arquivo</option>
                                <option>Múltiplos Arquivos</option>
                                <option>Seleção</option>
                                <option>Múltipla Escolha</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="descricaoCampo" class="form-label">Descrição/Ajuda</label>
                            <input type="text" class="form-control" id="descricaoCampo" placeholder="Texto de ajuda para o usuário">
                        </div>
                        <div class="mb-3">
                            <label for="validacaoCampo" class="form-label">Validação</label>
                            <select class="form-select" id="validacaoCampo">
                                <option>Nenhuma</option>
                                <option>E-mail</option>
                                <option>CPF/CNPJ</option>
                                <option>Telefone</option>
                                <option>CEP</option>
                            </select>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="obrigatorioCheck">
                            <label class="form-check-label" for="obrigatorioCheck">
                                Campo obrigatório
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="unicoCheck">
                            <label class="form-check-label" for="unicoCheck">
                                Valor único (não pode se repetir)
                            </label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary">Salvar Alterações</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add new field to form
        function adicionarCampo() {
            const container = document.getElementById('camposContainer');
            const fieldDiv = document.createElement('div');
            fieldDiv.className = 'field-config mb-3';
            fieldDiv.innerHTML = `
                <div class="row">
                    <div class="col-md-5">
                        <input type="text" class="form-control" placeholder="Nome do campo">
                    </div>
                    <div class="col-md-3">
                        <select class="form-select">
                            <option>Texto</option>
                            <option>Número</option>
                            <option>Data</option>
                            <option>Arquivo</option>
                            <option>Seleção</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox">
                            <label class="form-check-label">Obrigatório</label>
                        </div>
                    </div>
                    <div class="col-md-1">
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.field-config').remove()">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            `;
            container.appendChild(fieldDiv);
        }

        // Handle edit field buttons
        document.querySelectorAll('.field-item .btn-outline-secondary').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const modal = new bootstrap.Modal(document.getElementById('editarCampoModal'));
                modal.show();
            });
        });

        // Handle add field buttons
        document.querySelectorAll('.add-field-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const modal = new bootstrap.Modal(document.getElementById('editarCampoModal'));
                modal.show();
            });
        });

        // Toggle dispute type status
        document.querySelectorAll('.form-check-input').forEach(toggle => {
            toggle.addEventListener('change', function() {
                const card = this.closest('.dispute-type-card');
                if (this.checked) {
                    card.style.opacity = '1';
                } else {
                    card.style.opacity = '0.6';
                }
            });
        });
    </script>
</body>
</html>