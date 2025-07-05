<?php
require_once 'config.php';

// Verificar se está logado e não é árbitro
requireLogin();
if ($_SESSION['user_type'] === 'arbitro') {
    header("Location: index.php");
    exit();
}

$errors = [];
$success = false;
$db = getDBConnection();

// Buscar tipos de disputa
$tiposDisputa = $db->query("SELECT * FROM tipos_disputa WHERE ativo = 1 ORDER BY nome")->fetchAll();

// Se for empresa, buscar membros da equipe
$membrosEquipe = [];
if ($_SESSION['user_type'] === 'empresa') {
    $stmt = $db->prepare("
        SELECT u.id, u.nome_completo, u.email 
        FROM usuarios u 
        WHERE u.empresa_id = ? AND u.ativo = 1 
        ORDER BY u.nome_completo
    ");
    $stmt->execute([$_SESSION['empresa_id']]);
    $membrosEquipe = $stmt->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar dados básicos
    $tipoDisputaId = intval($_POST['tipo_disputa_id'] ?? 0);
    $descricao = sanitizeInput($_POST['descricao'] ?? '');
    $valorCausa = floatval(str_replace(['R$', '.', ','], ['', '', '.'], $_POST['valor_causa'] ?? '0'));
    
    // Dados do reclamado
    $reclamadoNome = sanitizeInput($_POST['reclamado_nome'] ?? '');
    $reclamadoEmail = filter_var($_POST['reclamado_email'] ?? '', FILTER_SANITIZE_EMAIL);
    $reclamadoCpfCnpj = preg_replace('/[^0-9]/', '', $_POST['reclamado_cpf_cnpj'] ?? '');
    $reclamadoTelefone = sanitizeInput($_POST['reclamado_telefone'] ?? '');
    
    // Dados específicos do imóvel
    $enderecoImovel = sanitizeInput($_POST['endereco_imovel'] ?? '');
    $numeroUnidade = sanitizeInput($_POST['numero_unidade'] ?? '');
    $numeroContrato = sanitizeInput($_POST['numero_contrato'] ?? '');
    $valorAluguel = floatval(str_replace(['R$', '.', ','], ['', '', '.'], $_POST['valor_aluguel'] ?? '0'));
    
    // Se for infração condominial
    $tipoInfracao = sanitizeInput($_POST['tipo_infracao'] ?? '');
    $dataInfracao = $_POST['data_infracao'] ?? null;
    
    // Validações
    if (!$tipoDisputaId) {
        $errors[] = "Selecione o tipo de disputa.";
    }
    
    if (strlen($descricao) < 50) {
        $errors[] = "A descrição deve ter no mínimo 50 caracteres.";
    }
    
    if ($valorCausa <= 0) {
        $errors[] = "Informe o valor da causa.";
    }
    
    if (empty($reclamadoNome)) {
        $errors[] = "Nome do reclamado é obrigatório.";
    }
    
    if (!filter_var($reclamadoEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Email do reclamado inválido.";
    }
    
    if (empty($enderecoImovel)) {
        $errors[] = "Endereço do imóvel é obrigatório.";
    }
    
    // Se não houver erros, criar disputa
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Determinar quem é o reclamante
            $reclamanteId = $_SESSION['user_id'];
            if ($_SESSION['user_type'] === 'empresa' && !empty($_POST['reclamante_id'])) {
                // Verificar se o membro selecionado pertence à empresa
                $stmt = $db->prepare("SELECT id FROM usuarios WHERE id = ? AND empresa_id = ?");
                $stmt->execute([intval($_POST['reclamante_id']), $_SESSION['empresa_id']]);
                if ($stmt->fetch()) {
                    $reclamanteId = intval($_POST['reclamante_id']);
                }
            }
            
            // Verificar se reclamado já existe no sistema
            $stmt = $db->prepare("SELECT id FROM usuarios WHERE email = ? OR cpf_cnpj = ?");
            $stmt->execute([$reclamadoEmail, $reclamadoCpfCnpj]);
            $reclamadoExistente = $stmt->fetch();
            
            $reclamadoId = null;
            if ($reclamadoExistente) {
                $reclamadoId = $reclamadoExistente['id'];
            } else {
                // Criar usuário temporário para o reclamado
                $tokenConvite = generateToken();
                $stmt = $db->prepare("
                    INSERT INTO usuarios (tipo_usuario_id, email, nome_completo, cpf_cnpj, telefone, token_verificacao, ativo) 
                    VALUES ((SELECT id FROM tipos_usuario WHERE tipo = 'solicitante'), ?, ?, ?, ?, ?, 0)
                ");
                $stmt->execute([$reclamadoEmail, $reclamadoNome, $reclamadoCpfCnpj, $reclamadoTelefone, $tokenConvite]);
                $reclamadoId = $db->lastInsertId();
                
                // Enviar email de convite
                $linkConvite = APP_URL . "/aceitar-disputa.php?token=" . $tokenConvite;
                $emailBody = "
                    <h2>Você foi incluído em uma disputa no Arbitrivm</h2>
                    <p>Olá $reclamadoNome,</p>
                    <p>Você foi indicado como parte em uma disputa de arbitragem. Para participar e apresentar sua defesa, clique no link abaixo:</p>
                    <p><a href='$linkConvite'>Aceitar e Participar da Disputa</a></p>
                    <p>Se você não conhece o remetente ou acredita que isso seja um erro, ignore este email.</p>
                ";
                sendEmail($reclamadoEmail, "Convite para Disputa - Arbitrivm", $emailBody);
            }
            
            // Gerar código do caso
            $codigoCaso = generateCaseCode();
            
            // Inserir disputa
            $stmt = $db->prepare("
                INSERT INTO disputas (
                    codigo_caso, tipo_disputa_id, reclamante_id, reclamado_id, 
                    empresa_id, descricao, valor_causa, status, 
                    endereco_imovel, numero_unidade, numero_contrato, valor_aluguel
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'triagem', ?, ?, ?, ?)
            ");
            
            $empresaId = $_SESSION['user_type'] === 'empresa' ? $_SESSION['empresa_id'] : null;
            
            $stmt->execute([
                $codigoCaso, $tipoDisputaId, $reclamanteId, $reclamadoId,
                $empresaId, $descricao, $valorCausa,
                $enderecoImovel, $numeroUnidade, $numeroContrato, $valorAluguel
            ]);
            
            $disputaId = $db->lastInsertId();
            
            // Se for infração condominial, inserir dados adicionais
            if ($tipoInfracao) {
                $stmt = $db->prepare("
                    INSERT INTO disputa_infracoes (disputa_id, tipo_infracao, data_infracao) 
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$disputaId, $tipoInfracao, $dataInfracao]);
            }
            
            // Inserir primeiro evento no histórico
            $stmt = $db->prepare("
                INSERT INTO disputa_historico (disputa_id, usuario_id, evento, descricao) 
                VALUES (?, ?, 'disputa_criada', 'Disputa criada e em fase de triagem')
            ");
            $stmt->execute([$disputaId, $_SESSION['user_id']]);
            
            // Criar notificações
            createNotification($reclamanteId, 'disputa_criada', 'Nova Disputa Criada', 
                "Sua disputa $codigoCaso foi criada com sucesso.", 
                "disputa-detalhes.php?id=$disputaId"
            );
            
            if ($reclamadoId && $reclamadoExistente) {
                createNotification($reclamadoId, 'nova_disputa', 'Você foi incluído em uma disputa', 
                    "Você foi indicado como reclamado na disputa $codigoCaso.", 
                    "disputa-detalhes.php?id=$disputaId"
                );
            }
            
            // Notificar administradores sobre nova disputa
            $stmt = $db->prepare("
                SELECT u.id FROM usuarios u 
                JOIN tipos_usuario tu ON u.tipo_usuario_id = tu.id 
                WHERE tu.tipo = 'admin' AND u.ativo = 1
            ");
            $stmt->execute();
            $admins = $stmt->fetchAll();
            
            foreach ($admins as $admin) {
                createNotification($admin['id'], 'nova_disputa_triagem', 'Nova Disputa em Triagem', 
                    "A disputa $codigoCaso precisa de triagem.", 
                    "disputa-detalhes.php?id=$disputaId"
                );
            }
            
            $db->commit();
            
            logActivity('disputa_criada', "Nova disputa criada: $codigoCaso", $disputaId);
            
            $_SESSION['success_message'] = "Disputa criada com sucesso! Código: $codigoCaso";
            header("Location: disputa-detalhes.php?id=$disputaId");
            exit();
            
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = "Erro ao criar disputa: " . $e->getMessage();
            error_log("Erro ao criar disputa: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nova Disputa - Arbitrivm</title>
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
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
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
        
        .form-container {
            background: white;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        .form-section {
            margin-bottom: 40px;
            padding-bottom: 40px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .form-section:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }
        
        .section-title {
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
        
        .required {
            color: #e53e3e;
        }
        
        input[type="text"],
        input[type="email"],
        input[type="tel"],
        input[type="date"],
        input[type="number"],
        select,
        textarea {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        input:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: #4299e1;
            box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
        }
        
        textarea {
            resize: vertical;
            min-height: 150px;
        }
        
        .two-columns {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .help-text {
            font-size: 0.875rem;
            color: #718096;
            margin-top: 5px;
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
        
        .alert-info {
            background-color: #ebf8ff;
            color: #2b6cb0;
            border: 1px solid #90cdf4;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 24px;
            font-size: 1rem;
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
        
        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 30px;
        }
        
        .hidden {
            display: none;
        }
        
        @media (max-width: 768px) {
            .two-columns {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
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
            <h1 class="page-title">Nova Disputa</h1>
            <p class="page-subtitle">Preencha as informações para iniciar uma nova disputa de arbitragem</p>
        </div>
        
        <div class="form-container">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <!-- Seção 1: Informações Básicas -->
                <div class="form-section">
                    <h2 class="section-title">Informações Básicas</h2>
                    
                    <?php if ($_SESSION['user_type'] === 'empresa' && !empty($membrosEquipe)): ?>
                        <div class="form-group">
                            <label for="reclamante_id">Reclamante (Membro da Equipe)</label>
                            <select name="reclamante_id" id="reclamante_id">
                                <option value="">Eu mesmo (<?php echo htmlspecialchars($_SESSION['user_name']); ?>)</option>
                                <?php foreach ($membrosEquipe as $membro): ?>
                                    <option value="<?php echo $membro['id']; ?>">
                                        <?php echo htmlspecialchars($membro['nome_completo'] . ' - ' . $membro['email']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="tipo_disputa_id">Tipo de Disputa <span class="required">*</span></label>
                        <select name="tipo_disputa_id" id="tipo_disputa_id" required onchange="toggleDisputaFields()">
                            <option value="">Selecione o tipo de disputa...</option>
                            <?php foreach ($tiposDisputa as $tipo): ?>
                                <option value="<?php echo $tipo['id']; ?>" 
                                        data-slug="<?php echo $tipo['slug']; ?>">
                                    <?php echo htmlspecialchars($tipo['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="valor_causa">Valor da Causa <span class="required">*</span></label>
                        <input type="text" name="valor_causa" id="valor_causa" required placeholder="R$ 0,00">
                        <p class="help-text">Valor total envolvido na disputa</p>
                    </div>
                    
                    <div class="form-group">
                        <label for="descricao">Descrição Detalhada <span class="required">*</span></label>
                        <textarea name="descricao" id="descricao" required minlength="50"
                                  placeholder="Descreva detalhadamente o problema, incluindo datas, valores e circunstâncias relevantes..."></textarea>
                        <p class="help-text">Mínimo de 50 caracteres. Seja claro e objetivo.</p>
                    </div>
                </div>
                
                <!-- Seção 2: Dados do Reclamado -->
                <div class="form-section">
                    <h2 class="section-title">Dados do Reclamado</h2>
                    
                    <div class="two-columns">
                        <div class="form-group">
                            <label for="reclamado_nome">Nome Completo <span class="required">*</span></label>
                            <input type="text" name="reclamado_nome" id="reclamado_nome" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="reclamado_cpf_cnpj">CPF/CNPJ <span class="required">*</span></label>
                            <input type="text" name="reclamado_cpf_cnpj" id="reclamado_cpf_cnpj" required maxlength="18">
                        </div>
                    </div>
                    
                    <div class="two-columns">
                        <div class="form-group">
                            <label for="reclamado_email">Email <span class="required">*</span></label>
                            <input type="email" name="reclamado_email" id="reclamado_email" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="reclamado_telefone">Telefone</label>
                            <input type="tel" name="reclamado_telefone" id="reclamado_telefone" placeholder="(11) 99999-9999">
                        </div>
                    </div>
                </div>
                
                <!-- Seção 3: Informações do Imóvel -->
                <div class="form-section">
                    <h2 class="section-title">Informações do Imóvel</h2>
                    
                    <div class="form-group">
                        <label for="endereco_imovel">Endereço Completo <span class="required">*</span></label>
                        <input type="text" name="endereco_imovel" id="endereco_imovel" required 
                               placeholder="Rua, número, complemento, bairro, cidade - UF">
                    </div>
                    
                    <div class="two-columns">
                        <div class="form-group">
                            <label for="numero_unidade">Número da Unidade/Apartamento</label>
                            <input type="text" name="numero_unidade" id="numero_unidade" placeholder="Ex: Apto 101, Casa 5">
                        </div>
                        
                        <div class="form-group">
                            <label for="numero_contrato">Número do Contrato</label>
                            <input type="text" name="numero_contrato" id="numero_contrato" placeholder="Ex: CTR-2024-001">
                        </div>
                    </div>
                    
                    <div class="form-group" id="valor_aluguel_group" style="display: none;">
                        <label for="valor_aluguel">Valor do Aluguel</label>
                        <input type="text" name="valor_aluguel" id="valor_aluguel" placeholder="R$ 0,00">
                    </div>
                </div>
                
                <!-- Seção 4: Informações Específicas para Infração Condominial -->
                <div class="form-section hidden" id="infracao_fields">
                    <h2 class="section-title">Detalhes da Infração</h2>
                    
                    <div class="form-group">
                        <label for="tipo_infracao">Tipo de Infração</label>
                        <select name="tipo_infracao" id="tipo_infracao">
                            <option value="">Selecione...</option>
                            <option value="barulho">Perturbação do sossego</option>
                            <option value="areas_comuns">Uso irregular de áreas comuns</option>
                            <option value="animais">Animais em desacordo com regimento</option>
                            <option value="obras">Obras não autorizadas</option>
                            <option value="inadimplencia">Inadimplência de taxas condominiais</option>
                            <option value="outros">Outros</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="data_infracao">Data da Infração</label>
                        <input type="date" name="data_infracao" id="data_infracao">
                    </div>
                </div>
                
                <div class="alert alert-info">
                    <strong>Próximos passos:</strong>
                    <ul>
                        <li>Após criar a disputa, você poderá fazer upload de documentos comprobatórios</li>
                        <li>O reclamado será notificado por email para participar da arbitragem</li>
                        <li>Um árbitro especializado será designado para o caso</li>
                    </ul>
                </div>
                
                <div class="form-actions">
                    <a href="index.php" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary">Criar Disputa</button>
                </div>
            </form>
        </div>
    </main>
    
    <script>
        // Máscara para valor monetário
        function formatMoney(input) {
            let value = input.value.replace(/\D/g, '');
            value = (value / 100).toFixed(2);
            value = value.replace('.', ',');
            value = value.replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1.');
            input.value = 'R$ ' + value;
        }
        
        document.getElementById('valor_causa').addEventListener('input', function(e) {
            formatMoney(e.target);
        });
        
        document.getElementById('valor_aluguel').addEventListener('input', function(e) {
            formatMoney(e.target);
        });
        
        // Máscara para CPF/CNPJ
        document.getElementById('reclamado_cpf_cnpj').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            
            if (value.length <= 11) {
                // CPF
                value = value.replace(/^(\d{3})(\d)/, '$1.$2');
                value = value.replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3');
                value = value.replace(/\.(\d{3})(\d)/, '.$1-$2');
            } else {
                // CNPJ
                value = value.substring(0, 14);
                value = value.replace(/^(\d{2})(\d)/, '$1.$2');
                value = value.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
                value = value.replace(/\.(\d{3})(\d)/, '.$1/$2');
                value = value.replace(/(\d{4})(\d)/, '$1-$2');
            }
            
            e.target.value = value;
        });
        
        // Máscara para telefone
        document.getElementById('reclamado_telefone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length <= 11) {
                if (value.length <= 10) {
                    value = value.replace(/^(\d{2})(\d)/, '($1) $2');
                    value = value.replace(/(\d{4})(\d)/, '$1-$2');
                } else {
                    value = value.replace(/^(\d{2})(\d)/, '($1) $2');
                    value = value.replace(/(\d{5})(\d)/, '$1-$2');
                }
            }
            e.target.value = value;
        });
        
        // Mostrar/ocultar campos específicos por tipo de disputa
        function toggleDisputaFields() {
            const tipoDisputa = document.getElementById('tipo_disputa_id');
            const selectedOption = tipoDisputa.options[tipoDisputa.selectedIndex];
            const slug = selectedOption.getAttribute('data-slug');
            
            // Campos de infração
            const infracaoFields = document.getElementById('infracao_fields');
            if (slug === 'infracao-condominial') {
                infracaoFields.classList.remove('hidden');
            } else {
                infracaoFields.classList.add('hidden');
            }
            
            // Campo de valor do aluguel
            const valorAluguelGroup = document.getElementById('valor_aluguel_group');
            if (slug === 'locacao' || slug === 'danos-imovel') {
                valorAluguelGroup.style.display = 'block';
            } else {
                valorAluguelGroup.style.display = 'none';
            }
        }
    </script>
</body>
</html>