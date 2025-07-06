<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Usuários - Arbitrivm Admin</title>
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

        .table-container {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            margin-top: 30px;
        }

        .table thead th {
            border-bottom: 2px solid #dee2e6;
            color: var(--primary-color);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.875rem;
            letter-spacing: 0.5px;
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

        .action-btn {
            padding: 6px 12px;
            font-size: 0.875rem;
            border-radius: 6px;
            margin: 0 2px;
            transition: all 0.3s ease;
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

        .filter-chip {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            background-color: #e9ecef;
            color: #495057;
            font-size: 0.875rem;
            margin: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .filter-chip.active {
            background-color: var(--secondary-color);
            color: white;
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

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--secondary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }

        .dropdown-toggle::after {
            display: none;
        }

        @media (max-width: 768px) {
            .sidebar {
                display: none;
            }
            
            .main-content {
                padding: 15px;
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
                    <a class="nav-link active" href="usuarios.php"><i class="bi bi-people me-2"></i> Usuários</a>
                    <a class="nav-link" href="empresas.php"><i class="bi bi-building me-2"></i> Empresas</a>
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
                            <h2 class="mb-1">Gestão de Usuários</h2>
                            <p class="text-muted mb-0">Gerencie todos os usuários da plataforma</p>
                        </div>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#novoUsuarioModal">
                            <i class="bi bi-plus-lg me-2"></i>Novo Usuário
                        </button>
                    </div>

                    <!-- Stats Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div>
                                        <p class="stats-number text-primary">1,248</p>
                                        <p class="stats-label mb-0">Total de Usuários</p>
                                    </div>
                                    <i class="bi bi-people-fill text-primary" style="font-size: 2.5rem; opacity: 0.3;"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div>
                                        <p class="stats-number text-success">1,087</p>
                                        <p class="stats-label mb-0">Usuários Ativos</p>
                                    </div>
                                    <i class="bi bi-check-circle-fill text-success" style="font-size: 2.5rem; opacity: 0.3;"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div>
                                        <p class="stats-number text-warning">342</p>
                                        <p class="stats-label mb-0">Empresas B2B</p>
                                    </div>
                                    <i class="bi bi-building text-warning" style="font-size: 2.5rem; opacity: 0.3;"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div>
                                        <p class="stats-number text-info">89</p>
                                        <p class="stats-label mb-0">Árbitros</p>
                                    </div>
                                    <i class="bi bi-person-badge-fill text-info" style="font-size: 2.5rem; opacity: 0.3;"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filters and Search -->
                    <div class="table-container">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="search-bar">
                                    <i class="bi bi-search"></i>
                                    <input type="text" class="form-control" placeholder="Buscar por nome, email ou CPF/CNPJ...">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-end">
                                    <button class="btn btn-outline-secondary btn-sm">
                                        <i class="bi bi-funnel me-1"></i> Filtros
                                    </button>
                                    <button class="btn btn-outline-secondary btn-sm">
                                        <i class="bi bi-download me-1"></i> Exportar
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <span class="filter-chip active">Todos</span>
                            <span class="filter-chip">Ativos</span>
                            <span class="filter-chip">Inativos</span>
                            <span class="filter-chip">Empresas</span>
                            <span class="filter-chip">Árbitros</span>
                            <span class="filter-chip">Pessoas Físicas</span>
                        </div>

                        <!-- Users Table -->
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Usuário</th>
                                        <th>Tipo</th>
                                        <th>Empresa</th>
                                        <th>Data Cadastro</th>
                                        <th>Status</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="user-avatar me-3">JC</div>
                                                <div>
                                                    <p class="mb-0 fw-semibold">João Carlos Silva</p>
                                                    <p class="mb-0 small text-muted">joao.carlos@email.com</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td><span class="badge bg-primary">Pessoa Física</span></td>
                                        <td>-</td>
                                        <td>15/03/2024</td>
                                        <td><span class="status-badge status-active">Ativo</span></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary action-btn" title="Visualizar">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-secondary action-btn" title="Editar">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger action-btn" title="Desativar">
                                                <i class="bi bi-toggle-off"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="user-avatar me-3" style="background-color: #e74c3c;">MP</div>
                                                <div>
                                                    <p class="mb-0 fw-semibold">Maria Paula Imóveis</p>
                                                    <p class="mb-0 small text-muted">contato@mariapaulaimoveis.com.br</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td><span class="badge bg-warning text-dark">Empresa B2B</span></td>
                                        <td>Maria Paula Imóveis LTDA</td>
                                        <td>22/02/2024</td>
                                        <td><span class="status-badge status-active">Ativo</span></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary action-btn" title="Visualizar">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-secondary action-btn" title="Editar">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger action-btn" title="Desativar">
                                                <i class="bi bi-toggle-off"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="user-avatar me-3" style="background-color: #27ae60;">RF</div>
                                                <div>
                                                    <p class="mb-0 fw-semibold">Dr. Roberto Farias</p>
                                                    <p class="mb-0 small text-muted">roberto.farias@advocacia.com</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td><span class="badge bg-info">Árbitro</span></td>
                                        <td>-</td>
                                        <td>10/01/2024</td>
                                        <td><span class="status-badge status-active">Ativo</span></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary action-btn" title="Visualizar">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-secondary action-btn" title="Editar">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger action-btn" title="Desativar">
                                                <i class="bi bi-toggle-off"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="user-avatar me-3" style="background-color: #f39c12;">CS</div>
                                                <div>
                                                    <p class="mb-0 fw-semibold">Condomínio Solar</p>
                                                    <p class="mb-0 small text-muted">administracao@condominiosolar.com</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td><span class="badge bg-warning text-dark">Empresa B2B</span></td>
                                        <td>Condomínio Ed. Solar</td>
                                        <td>05/03/2024</td>
                                        <td><span class="status-badge status-inactive">Inativo</span></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary action-btn" title="Visualizar">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-secondary action-btn" title="Editar">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-success action-btn" title="Ativar">
                                                <i class="bi bi-toggle-on"></i>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <nav aria-label="Page navigation" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <li class="page-item disabled">
                                    <a class="page-link" href="#" tabindex="-1">Anterior</a>
                                </li>
                                <li class="page-item active"><a class="page-link" href="#">1</a></li>
                                <li class="page-item"><a class="page-link" href="#">2</a></li>
                                <li class="page-item"><a class="page-link" href="#">3</a></li>
                                <li class="page-item">
                                    <a class="page-link" href="#">Próximo</a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Novo Usuário -->
    <div class="modal fade" id="novoUsuarioModal" tabindex="-1" aria-labelledby="novoUsuarioModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="novoUsuarioModalLabel">Novo Usuário</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="tipoUsuario" class="form-label">Tipo de Usuário</label>
                                <select class="form-select" id="tipoUsuario">
                                    <option value="pf">Pessoa Física</option>
                                    <option value="pj">Pessoa Jurídica (B2B)</option>
                                    <option value="arbitro">Árbitro</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="documento" class="form-label">CPF/CNPJ</label>
                                <input type="text" class="form-control" id="documento">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="nome" class="form-label">Nome Completo / Razão Social</label>
                                <input type="text" class="form-control" id="nome">
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label">E-mail</label>
                                <input type="email" class="form-control" id="email">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="telefone" class="form-label">Telefone</label>
                                <input type="tel" class="form-control" id="telefone">
                            </div>
                            <div class="col-md-6">
                                <label for="senha" class="form-label">Senha Temporária</label>
                                <input type="password" class="form-control" id="senha">
                            </div>
                        </div>
                        <div class="mb-3 d-none" id="empresaField">
                            <label for="empresa" class="form-label">Empresa Vinculada</label>
                            <select class="form-select" id="empresa">
                                <option value="">Selecione uma empresa</option>
                                <option value="1">Maria Paula Imóveis LTDA</option>
                                <option value="2">Condomínio Ed. Solar</option>
                            </select>
                        </div>
                        <div class="mb-3 d-none" id="especialidadeField">
                            <label for="especialidade" class="form-label">Especialidade (Árbitro)</label>
                            <select class="form-select" id="especialidade" multiple>
                                <option value="locacao">Locações</option>
                                <option value="condominio">Disputas Condominiais</option>
                                <option value="geral">Imobiliário Geral</option>
                            </select>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" value="" id="ativoCheck" checked>
                            <label class="form-check-label" for="ativoCheck">
                                Usuário ativo
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="" id="emailCheck" checked>
                            <label class="form-check-label" for="emailCheck">
                                Enviar e-mail de boas-vindas com credenciais
                            </label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary">Criar Usuário</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle empresa/especialidade fields based on user type
        document.getElementById('tipoUsuario').addEventListener('change', function() {
            const empresaField = document.getElementById('empresaField');
            const especialidadeField = document.getElementById('especialidadeField');
            
            if (this.value === 'pj') {
                empresaField.classList.remove('d-none');
                especialidadeField.classList.add('d-none');
            } else if (this.value === 'arbitro') {
                empresaField.classList.add('d-none');
                especialidadeField.classList.remove('d-none');
            } else {
                empresaField.classList.add('d-none');
                especialidadeField.classList.add('d-none');
            }
        });

        // Filter chips functionality
        document.querySelectorAll('.filter-chip').forEach(chip => {
            chip.addEventListener('click', function() {
                document.querySelectorAll('.filter-chip').forEach(c => c.classList.remove('active'));
                this.classList.add('active');
                // Add filtering logic here
            });
        });
    </script>
</body>
</html>