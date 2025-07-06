<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Empresas - Arbitrivm Admin</title>
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

        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s ease;
            border: none;
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .stats-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 0;
        }

        .stats-label {
            color: #6c757d;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .company-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            margin-bottom: 20px;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .company-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .company-logo {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            background-color: var(--secondary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.5rem;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-active {
            background-color: #d4edda;
            color: #155724;
        }

        .status-inactive {
            background-color: #f8d7da;
            color: #721c24;
        }

        .search-bar {
            position: relative;
        }

        .search-bar input {
            padding-left: 40px;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }

        .search-bar i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }

        .company-stats {
            display: flex;
            gap: 30px;
            margin-top: 15px;
        }

        .company-stat {
            text-align: center;
        }

        .company-stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .company-stat-label {
            font-size: 0.75rem;
            color: #6c757d;
            text-transform: uppercase;
        }

        .chart-container {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            margin-top: 30px;
            height: 400px;
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

        .action-btn {
            padding: 8px 16px;
            font-size: 0.875rem;
            border-radius: 6px;
            margin: 0 5px;
            transition: all 0.3s ease;
        }

        @media (max-width: 768px) {
            .sidebar {
                display: none;
            }
            
            .main-content {
                padding: 15px;
            }

            .company-stats {
                flex-wrap: wrap;
                gap: 15px;
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
                    <a class="nav-link active" href="empresas.php"><i class="bi bi-building me-2"></i> Empresas</a>
                    <a class="nav-link" href="tipos-disputa.php"><i class="bi bi-list-check me-2"></i> Tipos de Disputa</a>
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
                            <h2 class="mb-1">Gestão de Empresas B2B</h2>
                            <p class="text-muted mb-0">Gerencie imobiliárias e condomínios cadastrados</p>
                        </div>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#novaEmpresaModal">
                            <i class="bi bi-plus-lg me-2"></i>Nova Empresa
                        </button>
                    </div>

                    <!-- Stats Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div>
                                        <p class="stats-number text-primary">342</p>
                                        <p class="stats-label mb-0">Total de Empresas</p>
                                    </div>
                                    <i class="bi bi-building text-primary" style="font-size: 2.5rem; opacity: 0.3;"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div>
                                        <p class="stats-number text-success">218</p>
                                        <p class="stats-label mb-0">Imobiliárias</p>
                                    </div>
                                    <i class="bi bi-house-door-fill text-success" style="font-size: 2.5rem; opacity: 0.3;"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div>
                                        <p class="stats-number text-warning">124</p>
                                        <p class="stats-label mb-0">Condomínios</p>
                                    </div>
                                    <i class="bi bi-buildings text-warning" style="font-size: 2.5rem; opacity: 0.3;"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div>
                                        <p class="stats-number text-info">3,847</p>
                                        <p class="stats-label mb-0">Disputas Totais</p>
                                    </div>
                                    <i class="bi bi-clipboard-data text-info" style="font-size: 2.5rem; opacity: 0.3;"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Search and Filters -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="search-bar">
                                <i class="bi bi-search"></i>
                                <input type="text" class="form-control" placeholder="Buscar por nome, CNPJ ou cidade...">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-end">
                                <select class="form-select form-select-sm d-inline-block w-auto me-2">
                                    <option>Todos os tipos</option>
                                    <option>Imobiliárias</option>
                                    <option>Condomínios</option>
                                </select>
                                <select class="form-select form-select-sm d-inline-block w-auto me-2">
                                    <option>Todos os status</option>
                                    <option>Ativas</option>
                                    <option>Inativas</option>
                                </select>
                                <button class="btn btn-outline-secondary btn-sm">
                                    <i class="bi bi-download me-1"></i> Exportar
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Companies List -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="company-card" onclick="showCompanyDetails('Maria Paula Imóveis')">
                                <div class="d-flex align-items-start">
                                    <div class="company-logo me-3">MP</div>
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <h5 class="mb-1">Maria Paula Imóveis LTDA</h5>
                                                <p class="text-muted mb-0">CNPJ: 12.345.678/0001-90</p>
                                                <p class="text-muted mb-0"><i class="bi bi-geo-alt me-1"></i>São Paulo, SP</p>
                                            </div>
                                            <span class="status-badge status-active">Ativa</span>
                                        </div>
                                        <div class="company-stats">
                                            <div class="company-stat">
                                                <p class="company-stat-value mb-0">127</p>
                                                <p class="company-stat-label mb-0">Disputas</p>
                                            </div>
                                            <div class="company-stat">
                                                <p class="company-stat-value mb-0">89%</p>
                                                <p class="company-stat-label mb-0">Resolvidas</p>
                                            </div>
                                            <div class="company-stat">
                                                <p class="company-stat-value mb-0">15</p>
                                                <p class="company-stat-label mb-0">Usuários</p>
                                            </div>
                                            <div class="company-stat">
                                                <p class="company-stat-value mb-0">4.7</p>
                                                <p class="company-stat-label mb-0">Avaliação</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="company-card" onclick="showCompanyDetails('Condomínio Solar')">
                                <div class="d-flex align-items-start">
                                    <div class="company-logo me-3" style="background-color: #f39c12;">CS</div>
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <h5 class="mb-1">Condomínio Edifício Solar</h5>
                                                <p class="text-muted mb-0">CNPJ: 98.765.432/0001-10</p>
                                                <p class="text-muted mb-0"><i class="bi bi-geo-alt me-1"></i>Campinas, SP</p>
                                            </div>
                                            <span class="status-badge status-active">Ativa</span>
                                        </div>
                                        <div class="company-stats">
                                            <div class="company-stat">
                                                <p class="company-stat-value mb-0">43</p>
                                                <p class="company-stat-label mb-0">Disputas</p>
                                            </div>
                                            <div class="company-stat">
                                                <p class="company-stat-value mb-0">95%</p>
                                                <p class="company-stat-label mb-0">Resolvidas</p>
                                            </div>
                                            <div class="company-stat">
                                                <p class="company-stat-value mb-0">8</p>
                                                <p class="company-stat-label mb-0">Usuários</p>
                                            </div>
                                            <div class="company-stat">
                                                <p class="company-stat-value mb-0">4.9</p>
                                                <p class="company-stat-label mb-0">Avaliação</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="company-card" onclick="showCompanyDetails('Imobiliária Central')">
                                <div class="d-flex align-items-start">
                                    <div class="company-logo me-3" style="background-color: #e74c3c;">IC</div>
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <h5 class="mb-1">Imobiliária Central</h5>
                                                <p class="text-muted mb-0">CNPJ: 11.222.333/0001-44</p>
                                                <p class="text-muted mb-0"><i class="bi bi-geo-alt me-1"></i>Rio de Janeiro, RJ</p>
                                            </div>
                                            <span class="status-badge status-active">Ativa</span>
                                        </div>
                                        <div class="company-stats">
                                            <div class="company-stat">
                                                <p class="company-stat-value mb-0">256</p>
                                                <p class="company-stat-label mb-0">Disputas</p>
                                            </div>
                                            <div class="company-stat">
                                                <p class="company-stat-value mb-0">92%</p>
                                                <p class="company-stat-label mb-0">Resolvidas</p>
                                            </div>
                                            <div class="company-stat">
                                                <p class="company-stat-value mb-0">22</p>
                                                <p class="company-stat-label mb-0">Usuários</p>
                                            </div>
                                            <div class="company-stat">
                                                <p class="company-stat-value mb-0">4.6</p>
                                                <p class="company-stat-label mb-0">Avaliação</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="company-card" onclick="showCompanyDetails('Condomínio Jardins')">
                                <div class="d-flex align-items-start">
                                    <div class="company-logo me-3" style="background-color: #27ae60;">CJ</div>
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <h5 class="mb-1">Condomínio Residencial Jardins</h5>
                                                <p class="text-muted mb-0">CNPJ: 55.666.777/0001-88</p>
                                                <p class="text-muted mb-0"><i class="bi bi-geo-alt me-1"></i>Belo Horizonte, MG</p>
                                            </div>
                                            <span class="status-badge status-inactive">Inativa</span>
                                        </div>
                                        <div class="company-stats">
                                            <div class="company-stat">
                                                <p class="company-stat-value mb-0">12</p>
                                                <p class="company-stat-label mb-0">Disputas</p>
                                            </div>
                                            <div class="company-stat">
                                                <p class="company-stat-value mb-0">100%</p>
                                                <p class="company-stat-label mb-0">Resolvidas</p>
                                            </div>
                                            <div class="company-stat">
                                                <p class="company-stat-value mb-0">5</p>
                                                <p class="company-stat-label mb-0">Usuários</p>
                                            </div>
                                            <div class="company-stat">
                                                <p class="company-stat-value mb-0">4.8</p>
                                                <p class="company-stat-label mb-0">Avaliação</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Chart Section -->
                    <div class="chart-container">
                        <h5 class="mb-3">Evolução de Disputas por Tipo de Empresa</h5>
                        <canvas id="disputasChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Nova Empresa -->
    <div class="modal fade" id="novaEmpresaModal" tabindex="-1" aria-labelledby="novaEmpresaModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="novaEmpresaModalLabel">Nova Empresa B2B</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="tipoEmpresa" class="form-label">Tipo de Empresa</label>
                                <select class="form-select" id="tipoEmpresa">
                                    <option value="imobiliaria">Imobiliária</option>
                                    <option value="condominio">Condomínio</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="cnpj" class="form-label">CNPJ</label>
                                <input type="text" class="form-control" id="cnpj" placeholder="00.000.000/0000-00">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label for="razaoSocial" class="form-label">Razão Social</label>
                                <input type="text" class="form-control" id="razaoSocial">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="nomeFantasia" class="form-label">Nome Fantasia</label>
                                <input type="text" class="form-control" id="nomeFantasia">
                            </div>
                            <div class="col-md-6">
                                <label for="inscricaoEstadual" class="form-label">Inscrição Estadual</label>
                                <input type="text" class="form-control" id="inscricaoEstadual">
                            </div>
                        </div>
                        <hr class="my-4">
                        <h6 class="mb-3">Endereço</h6>
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label for="cep" class="form-label">CEP</label>
                                <input type="text" class="form-control" id="cep">
                            </div>
                            <div class="col-md-7">
                                <label for="logradouro" class="form-label">Logradouro</label>
                                <input type="text" class="form-control" id="logradouro">
                            </div>
                            <div class="col-md-2">
                                <label for="numero" class="form-label">Número</label>
                                <input type="text" class="form-control" id="numero">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="complemento" class="form-label">Complemento</label>
                                <input type="text" class="form-control" id="complemento">
                            </div>
                            <div class="col-md-4">
                                <label for="bairro" class="form-label">Bairro</label>
                                <input type="text" class="form-control" id="bairro">
                            </div>
                            <div class="col-md-4">
                                <label for="cidade" class="form-label">Cidade</label>
                                <input type="text" class="form-control" id="cidade">
                            </div>
                        </div>
                        <hr class="my-4">
                        <h6 class="mb-3">Contato Principal</h6>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="nomeContato" class="form-label">Nome do Responsável</label>
                                <input type="text" class="form-control" id="nomeContato">
                            </div>
                            <div class="col-md-6">
                                <label for="emailContato" class="form-label">E-mail</label>
                                <input type="email" class="form-control" id="emailContato">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="telefoneContato" class="form-label">Telefone</label>
                                <input type="tel" class="form-control" id="telefoneContato">
                            </div>
                            <div class="col-md-6">
                                <label for="cargoContato" class="form-label">Cargo</label>
                                <input type="text" class="form-control" id="cargoContato">
                            </div>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" value="" id="ativaCheck" checked>
                            <label class="form-check-label" for="ativaCheck">
                                Empresa ativa
                            </label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary">Cadastrar Empresa</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Detalhes Empresa -->
    <div class="modal fade" id="detalhesEmpresaModal" tabindex="-1" aria-labelledby="detalhesEmpresaModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="detalhesEmpresaModalLabel">Detalhes da Empresa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Company details will be populated here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                    <button type="button" class="btn btn-primary">Editar Empresa</button>
                    <button type="button" class="btn btn-danger">Desativar Empresa</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Chart.js implementation
        const ctx = document.getElementById('disputasChart').getContext('2d');
        const disputasChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'],
                datasets: [{
                    label: 'Imobiliárias',
                    data: [65, 72, 78, 85, 89, 92, 88, 95, 102, 108, 115, 127],
                    borderColor: '#3498db',
                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Condomínios',
                    data: [28, 32, 35, 38, 42, 45, 43, 48, 52, 55, 58, 62],
                    borderColor: '#f39c12',
                    backgroundColor: 'rgba(243, 156, 18, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Número de Disputas'
                        }
                    }
                }
            }
        });

        // Show company details function
        function showCompanyDetails(companyName) {
            const modal = new bootstrap.Modal(document.getElementById('detalhesEmpresaModal'));
            document.getElementById('detalhesEmpresaModalLabel').textContent = companyName;
            modal.show();
        }
    </script>
</body>
</html>