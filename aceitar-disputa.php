<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

$error = '';
$success = '';
$disputeInfo = null;
$userExists = false;
$disputeToken = '';

// Verificar token da disputa
if (isset($_GET['token'])) {
    $disputeToken = $_GET['token'];
    
    try {
        // Buscar informações da disputa
        $stmt = $pdo->prepare("
            SELECT d.*, 
                   u_reclamante.nome as nome_reclamante,
                   u_reclamante.email as email_reclamante,
                   td.nome as tipo_disputa_nome,
                   d.valor_disputa,
                   d.descricao,
                   d.endereco_imovel,
                   d.numero_contrato
            FROM disputas d
            JOIN usuarios u_reclamante ON d.reclamante_id = u_reclamante.id
            LEFT JOIN tipos_disputa td ON d.tipo_disputa_id = td.id
            WHERE d.token_reclamado = ? AND d.status = 'aguardando_aceitacao'
        ");
        $stmt->execute([$disputeToken]);
        $disputeInfo = $stmt->fetch();
        
        if (!$disputeInfo) {
            $error = 'Link inválido ou disputa já processada.';
        } else {
            // Verificar se o reclamado já tem conta
            if ($disputeInfo['email_reclamado']) {
                $stmt = $pdo->prepare("SELECT id, nome FROM usuarios WHERE email = ?");
                $stmt->execute([$disputeInfo['email_reclamado']]);
                $existingUser = $stmt->fetch();
                
                if ($existingUser) {
                    $userExists = true;
                    $_SESSION['temp_user_id'] = $existingUser['id'];
                    $_SESSION['temp_user_name'] = $existingUser['nome'];
                }
            }
        }
        
    } catch (PDOException $e) {
        error_log("Erro ao buscar disputa: " . $e->getMessage());
        $error = 'Ocorreu um erro. Por favor, tente novamente.';
    }
}

// Processar aceitação da disputa
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $disputeInfo) {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'accept') {
            // Se usuário existe, fazer login
            if ($userExists && isset($_POST['password'])) {
                $password = $_POST['password'];
                
                $stmt = $pdo->prepare("SELECT id, senha FROM usuarios WHERE email = ?");
                $stmt->execute([$disputeInfo['email_reclamado']]);
                $user = $stmt->fetch();
                
                if ($user && password_verify($password, $user['senha'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $reclamadoId = $user['id'];
                } else {
                    $error = 'Senha incorreta.';
                }
            } 
            // Se usuário não existe, criar conta
            elseif (!$userExists) {
                $nome = $_POST['nome'];
                $email = $_POST['email'];
                $cpf_cnpj = $_POST['cpf_cnpj'];
                $telefone = $_POST['telefone'];
                $password = $_POST['password'];
                $tipo = strlen(preg_replace('/[^0-9]/', '', $cpf_cnpj)) > 11 ? 'empresa' : 'pessoa_fisica';
                
                // Validações
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $error = 'Email inválido.';
                } elseif (strlen($password) < 8) {
                    $error = 'A senha deve ter pelo menos 8 caracteres.';
                } else {
                    // Verificar se email já existe
                    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
                    $stmt->execute([$email]);
                    if ($stmt->fetch()) {
                        $error = 'Este email já está cadastrado.';
                    } else {
                        // Criar usuário
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                        $verificationToken = bin2hex(random_bytes(32));
                        
                        $stmt = $pdo->prepare("
                            INSERT INTO usuarios (nome, email, senha, tipo_usuario, cpf_cnpj, telefone, 
                                                verification_token, email_verified, ativo, data_cadastro)
                            VALUES (?, ?, ?, ?, ?, ?, ?, 1, 1, NOW())
                        ");
                        $stmt->execute([$nome, $email, $hashedPassword, $tipo, $cpf_cnpj, $telefone, $verificationToken]);
                        
                        $reclamadoId = $pdo->lastInsertId();
                        $_SESSION['user_id'] = $reclamadoId;
                        
                        // Log de criação de conta
                        $stmt = $pdo->prepare("
                            INSERT INTO atividades_log (user_id, acao, detalhes) 
                            VALUES (?, 'conta_criada', 'Conta criada através de convite para disputa')
                        ");
                        $stmt->execute([$reclamadoId]);
                    }
                }
            }
            
            // Se login/cadastro bem sucedido, aceitar disputa
            if (!$error && isset($reclamadoId)) {
                // Verificar aceite dos termos
                if (!isset($_POST['aceite_termos']) || !isset($_POST['aceite_regras'])) {
                    $error = 'Você deve aceitar os termos e as regras de arbitragem.';
                } else {
                    $pdo->beginTransaction();
                    
                    // Atualizar disputa
                    $stmt = $pdo->prepare("
                        UPDATE disputas 
                        SET reclamado_id = ?,
                            status = 'em_analise',
                            data_aceitacao = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$reclamadoId, $disputeInfo['id']]);
                    
                    // Registrar aceitação
                    $stmt = $pdo->prepare("
                        INSERT INTO disputas_historico (disputa_id, usuario_id, acao, detalhes)
                        VALUES (?, ?, 'disputa_aceita', 'Reclamado aceitou participar da disputa')
                    ");
                    $stmt->execute([$disputeInfo['id'], $reclamadoId]);
                    
                    // Registrar aceite dos termos
                    $stmt = $pdo->prepare("
                        INSERT INTO termos_aceites (usuario_id, disputa_id, tipo_termo, ip_address, user_agent)
                        VALUES (?, ?, 'termos_uso', ?, ?),
                               (?, ?, 'regras_arbitragem', ?, ?)
                    ");
                    $stmt->execute([
                        $reclamadoId, $disputeInfo['id'], $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'],
                        $reclamadoId, $disputeInfo['id'], $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']
                    ]);
                    
                    // Notificar reclamante
                    $emailSubject = "Disputa Aceita - Arbitrivm";
                    $emailMessage = "
                    <html>
                    <body>
                        <h2>Disputa Aceita</h2>
                        <p>O reclamado aceitou participar da disputa #" . $disputeInfo['id'] . ".</p>
                        <p>A disputa agora está em análise e um árbitro será designado em breve.</p>
                        <p><a href='https://arbitrivm.com.br/dashboard'>Acompanhe o processo</a></p>
                    </body>
                    </html>
                    ";
                    
                    $headers = "MIME-Version: 1.0\r\n";
                    $headers .= "Content-type:text/html;charset=UTF-8\r\n";
                    $headers .= 'From: Arbitrivm <noreply@arbitrivm.com.br>' . "\r\n";
                    
                    mail($disputeInfo['email_reclamante'], $emailSubject, $emailMessage, $headers);
                    
                    $pdo->commit();
                    
                    $success = 'Disputa aceita com sucesso! Você será redirecionado para o painel.';
                    header("refresh:3;url=dashboard.php");
                }
            }
            
        } elseif ($action === 'reject') {
            // Rejeitar disputa
            $motivo = $_POST['motivo_rejeicao'] ?? '';
            
            $stmt = $pdo->prepare("
                UPDATE disputas 
                SET status = 'rejeitada',
                    motivo_rejeicao = ?,
                    data_rejeicao = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$motivo, $disputeInfo['id']]);
            
            // Registrar rejeição
            $stmt = $pdo->prepare("
                INSERT INTO disputas_historico (disputa_id, acao, detalhes)
                VALUES (?, 'disputa_rejeitada', ?)
            ");
            $stmt->execute([$disputeInfo['id'], 'Reclamado rejeitou participar. Motivo: ' . $motivo]);
            
            $success = 'Disputa rejeitada. O reclamante será notificado.';
        }
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Erro ao processar disputa: " . $e->getMessage());
        $error = 'Ocorreu um erro ao processar sua solicitação.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aceitar Disputa - Arbitrivm</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f0f2f5;
            min-height: 100vh;
            padding: 20px 0;
        }
        .dispute-container {
            max-width: 800px;
            margin: 0 auto;
        }
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        .card-header {
            background-color: #0066cc;
            color: white;
            border-radius: 12px 12px 0 0 !important;
            padding: 20px;
        }
        .dispute-info {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e9ecef;
        }
        .info-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        .info-label {
            font-weight: 600;
            color: #495057;
        }
        .info-value {
            color: #212529;
        }
        .btn-primary {
            background-color: #0066cc;
            border: none;
            padding: 12px 30px;
            font-weight: 500;
        }
        .btn-danger {
            padding: 12px 30px;
            font-weight: 500;
        }
        .form-control {
            padding: 12px;
            border-radius: 8px;
        }
        .terms-box {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            max-height: 200px;
            overflow-y: auto;
            margin-bottom: 15px;
            font-size: 0.9rem;
        }
        .form-check {
            margin-bottom: 15px;
        }
        .alert {
            border-radius: 8px;
        }
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
        }
        .step {
            display: flex;
            align-items: center;
            color: #6c757d;
        }
        .step.active {
            color: #0066cc;
        }
        .step-number {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background-color: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 10px;
        }
        .step.active .step-number {
            background-color: #0066cc;
            color: white;
        }
        .step-line {
            width: 100px;
            height: 2px;
            background-color: #e9ecef;
            margin: 0 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="dispute-container">
            <!-- Logo e Título -->
            <div class="text-center mb-4">
                <h1 class="mb-2">
                    <i class="fas fa-balance-scale text-primary"></i> Arbitrivm
                </h1>
                <p class="text-muted">Plataforma de Resolução de Disputas</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo htmlspecialchars($success); ?>
                    <div class="mt-2">
                        <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                        <small>Redirecionando...</small>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($disputeInfo && !$success): ?>
                <!-- Indicador de Passos -->
                <div class="step-indicator">
                    <div class="step active">
                        <div class="step-number">1</div>
                        <span>Convite Recebido</span>
                    </div>
                    <div class="step-line"></div>
                    <div class="step">
                        <div class="step-number">2</div>
                        <span>Aceitar/Rejeitar</span>
                    </div>
                    <div class="step-line"></div>
                    <div class="step">
                        <div class="step-number">3</div>
                        <span>Processo Iniciado</span>
                    </div>
                </div>
                
                <!-- Informações da Disputa -->
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">
                            <i class="fas fa-gavel me-2"></i>
                            Convite para Participar de Disputa
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="dispute-info">
                            <h5 class="mb-3">Detalhes da Disputa</h5>
                            <div class="info-row">
                                <span class="info-label">Tipo de Disputa:</span>
                                <span class="info-value"><?php echo htmlspecialchars($disputeInfo['tipo_disputa_nome'] ?? 'Não especificado'); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Reclamante:</span>
                                <span class="info-value"><?php echo htmlspecialchars($disputeInfo['nome_reclamante']); ?></span>
                            </div>
                            <?php if ($disputeInfo['valor_disputa']): ?>
                            <div class="info-row">
                                <span class="info-label">Valor em Disputa:</span>
                                <span class="info-value">R$ <?php echo number_format($disputeInfo['valor_disputa'], 2, ',', '.'); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if ($disputeInfo['endereco_imovel']): ?>
                            <div class="info-row">
                                <span class="info-label">Imóvel:</span>
                                <span class="info-value"><?php echo htmlspecialchars($disputeInfo['endereco_imovel']); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if ($disputeInfo['numero_contrato']): ?>
                            <div class="info-row">
                                <span class="info-label">Contrato:</span>
                                <span class="info-value"><?php echo htmlspecialchars($disputeInfo['numero_contrato']); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($disputeInfo['descricao']): ?>
                        <div class="mb-4">
                            <h6>Descrição do Caso:</h6>
                            <p class="text-muted"><?php echo nl2br(htmlspecialchars($disputeInfo['descricao'])); ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Como funciona a Arbitragem?</strong>
                            <ul class="mb-0 mt-2">
                                <li>Processo 100% online e seguro</li>
                                <li>Decisão em até 30 dias</li>
                                <li>Árbitro especializado e imparcial</li>
                                <li>Decisão vinculante com força de título executivo</li>
                            </ul>
                        </div>
                        
                        <!-- Formulário de Aceitação -->
                        <form method="POST" action="" id="acceptForm">
                            <input type="hidden" name="action" value="accept">
                            
                            <?php if (!$userExists): ?>
                                <!-- Criar Conta -->
                                <h5 class="mb-3 mt-4">
                                    <i class="fas fa-user-plus me-2"></i>
                                    Criar Conta para Continuar
                                </h5>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Nome Completo</label>
                                        <input type="text" class="form-control" name="nome" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">CPF/CNPJ</label>
                                        <input type="text" class="form-control" name="cpf_cnpj" required>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Email</label>
                                        <input type="email" class="form-control" name="email" 
                                               value="<?php echo htmlspecialchars($disputeInfo['email_reclamado'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Telefone</label>
                                        <input type="tel" class="form-control" name="telefone" required>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Senha</label>
                                        <input type="password" class="form-control" name="password" minlength="8" required>
                                        <small class="text-muted">Mínimo 8 caracteres</small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Confirmar Senha</label>
                                        <input type="password" class="form-control" name="confirm_password" minlength="8" required>
                                    </div>
                                </div>
                            <?php else: ?>
                                <!-- Login -->
                                <h5 class="mb-3 mt-4">
                                    <i class="fas fa-sign-in-alt me-2"></i>
                                    Faça Login para Continuar
                                </h5>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Detectamos que você já possui uma conta com o email: 
                                    <strong><?php echo htmlspecialchars($disputeInfo['email_reclamado']); ?></strong>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Senha</label>
                                    <input type="password" class="form-control" name="password" required autofocus>
                                    <div class="mt-2">
                                        <a href="forgot-password.php" class="text-decoration-none">
                                            <small>Esqueceu sua senha?</small>
                                        </a>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Termos e Condições -->
                            <h5 class="mb-3 mt-4">
                                <i class="fas fa-file-contract me-2"></i>
                                Termos e Condições
                            </h5>
                            
                            <div class="terms-box">
                                <h6>Termos de Uso da Plataforma Arbitrivm</h6>
                                <p>Ao aceitar participar desta disputa, você concorda com os seguintes termos:</p>
                                <ol>
                                    <li>A decisão do árbitro será final e vinculante para ambas as partes;</li>
                                    <li>O processo seguirá as regras de arbitragem estabelecidas pela plataforma;</li>
                                    <li>Todas as comunicações serão feitas através da plataforma;</li>
                                    <li>As partes se comprometem a fornecer informações verdadeiras;</li>
                                    <li>A confidencialidade do processo será mantida;</li>
                                    <li>Os custos serão divididos conforme estabelecido nas regras.</li>
                                </ol>
                            </div>
                            
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="aceite_termos" name="aceite_termos" required>
                                <label class="form-check-label" for="aceite_termos">
                                    Li e aceito os <a href="termos.php" target="_blank">Termos de Uso</a> da plataforma
                                </label>
                            </div>
                            
                            <div class="terms-box">
                                <h6>Regras de Arbitragem</h6>
                                <p>O processo de arbitragem seguirá as seguintes regras:</p>
                                <ol>
                                    <li>Prazo de 5 dias para apresentação de defesa;</li>
                                    <li>Possibilidade de apresentar provas documentais;</li>
                                    <li>Direito a manifestação sobre as alegações da outra parte;</li>
                                    <li>Decisão será proferida em até 30 dias;</li>
                                    <li>Custas processuais conforme tabela vigente;</li>
                                    <li>Possibilidade de acordo a qualquer momento.</li>
                                </ol>
                            </div>
                            
                            <div class="form-check mb-4">
                                <input class="form-check-input" type="checkbox" id="aceite_regras" name="aceite_regras" required>
                                <label class="form-check-label" for="aceite_regras">
                                    Li e aceito as <a href="regras-arbitragem.php" target="_blank">Regras de Arbitragem</a>
                                </label>
                            </div>
                            
                            <!-- Botões de Ação -->
                            <div class="d-grid gap-2 d-md-flex justify-content-md-between">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-check me-2"></i>
                                    Aceitar e Participar da Disputa
                                </button>
                                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">
                                    <i class="fas fa-times me-2"></i>
                                    Rejeitar Disputa
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Modal de Rejeição -->
                <div class="modal fade" id="rejectModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                                    Rejeitar Disputa
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <form method="POST" action="">
                                <input type="hidden" name="action" value="reject">
                                <div class="modal-body">
                                    <div class="alert alert-warning">
                                        <i class="fas fa-info-circle me-2"></i>
                                        Ao rejeitar a disputa, o reclamante poderá buscar outras formas de resolução, 
                                        incluindo o Poder Judiciário.
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Motivo da Rejeição (opcional)</label>
                                        <textarea class="form-control" name="motivo_rejeicao" rows="3" 
                                                  placeholder="Explique brevemente o motivo da rejeição"></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                    <button type="submit" class="btn btn-danger">
                                        <i class="fas fa-times me-2"></i>Confirmar Rejeição
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
            <?php elseif (!$disputeInfo && !$success): ?>
                <!-- Erro -->
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-exclamation-triangle text-warning" style="font-size: 4rem;"></i>
                        <h4 class="mt-3">Link Inválido</h4>
                        <p class="text-muted">
                            Este link de disputa é inválido ou já foi utilizado.
                        </p>
                        <a href="index.php" class="btn btn-primary">
                            <i class="fas fa-home me-2"></i>Ir para Página Inicial
                        </a>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Informações de Suporte -->
            <div class="text-center mt-4">
                <p class="text-muted">
                    <i class="fas fa-question-circle me-2"></i>
                    Dúvidas? Entre em contato: 
                    <a href="mailto:suporte@arbitrivm.com.br">suporte@arbitrivm.com.br</a>
                </p>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
    <script>
        $(document).ready(function() {
            // Máscaras
            $('input[name="cpf_cnpj"]').on('input', function() {
                var value = $(this).val().replace(/\D/g, '');
                if (value.length <= 11) {
                    $(this).mask('000.000.000-00');
                } else {
                    $(this).mask('00.000.000/0000-00');
                }
            });
            
            $('input[name="telefone"]').mask('(00) 00000-0000');
            
            // Validação de senha
            <?php if (!$userExists): ?>
            $('form#acceptForm').on('submit', function(e) {
                var password = $('input[name="password"]').val();
                var confirmPassword = $('input[name="confirm_password"]').val();
                
                if (password !== confirmPassword) {
                    e.preventDefault();
                    alert('As senhas não coincidem!');
                    return false;
                }
                
                // Verificar checkboxes
                if (!$('#aceite_termos').is(':checked') || !$('#aceite_regras').is(':checked')) {
                    e.preventDefault();
                    alert('Você deve aceitar os termos e as regras de arbitragem para continuar.');
                    return false;
                }
            });
            <?php endif; ?>
            
            // Atualizar indicador de passos
            $('.step').eq(1).addClass('active');
        });
    </script>
</body>
</html>