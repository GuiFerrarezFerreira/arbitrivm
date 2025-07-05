<?php
require_once 'config.php';

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitizar inputs
    $tipoUsuario = sanitizeInput($_POST['tipo_usuario'] ?? '');
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $senha = $_POST['senha'] ?? '';
    $confirmarSenha = $_POST['confirmar_senha'] ?? '';
    $nomeCompleto = sanitizeInput($_POST['nome_completo'] ?? '');
    $cpfCnpj = preg_replace('/[^0-9]/', '', $_POST['cpf_cnpj'] ?? '');
    $telefone = sanitizeInput($_POST['telefone'] ?? '');
    
    // Validações básicas
    if (empty($tipoUsuario) || !in_array($tipoUsuario, ['empresa', 'arbitro', 'solicitante'])) {
        $errors[] = "Tipo de usuário inválido.";
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Email inválido.";
    }
    
    if (strlen($senha) < 8) {
        $errors[] = "A senha deve ter no mínimo 8 caracteres.";
    }
    
    if ($senha !== $confirmarSenha) {
        $errors[] = "As senhas não coincidem.";
    }
    
    if (empty($nomeCompleto)) {
        $errors[] = "Nome completo é obrigatório.";
    }
    
    // Validar CPF/CNPJ baseado no tipo
    if ($tipoUsuario === 'empresa') {
        if (!validateCNPJ($cpfCnpj)) {
            $errors[] = "CNPJ inválido.";
        }
    } else {
        if (!validateCPF($cpfCnpj)) {
            $errors[] = "CPF inválido.";
        }
    }
    
    // Se for empresa, validar campos adicionais
    if ($tipoUsuario === 'empresa') {
        $razaoSocial = sanitizeInput($_POST['razao_social'] ?? '');
        $nomeFantasia = sanitizeInput($_POST['nome_fantasia'] ?? '');
        $tipoEmpresa = sanitizeInput($_POST['tipo_empresa'] ?? '');
        $endereco = sanitizeInput($_POST['endereco'] ?? '');
        $cidade = sanitizeInput($_POST['cidade'] ?? '');
        $estado = sanitizeInput($_POST['estado'] ?? '');
        $cep = preg_replace('/[^0-9]/', '', $_POST['cep'] ?? '');
        
        if (empty($razaoSocial)) {
            $errors[] = "Razão social é obrigatória.";
        }
        
        if (!in_array($tipoEmpresa, ['imobiliaria', 'condominio'])) {
            $errors[] = "Tipo de empresa inválido.";
        }
    }
    
    // Se for árbitro, validar campos adicionais
    if ($tipoUsuario === 'arbitro') {
        $oabNumero = sanitizeInput($_POST['oab_numero'] ?? '');
        $oabEstado = sanitizeInput($_POST['oab_estado'] ?? '');
        $experienciaAnos = intval($_POST['experiencia_anos'] ?? 0);
        $biografia = sanitizeInput($_POST['biografia'] ?? '');
        $especializacoes = $_POST['especializacoes'] ?? [];
        
        if (empty($oabNumero) || empty($oabEstado)) {
            $errors[] = "Número da OAB e estado são obrigatórios.";
        }
    }
    
    // Se não houver erros, processar o cadastro
    if (empty($errors)) {
        try {
            $db = getDBConnection();
            $db->beginTransaction();
            
            // Verificar se email já existe
            $stmt = $db->prepare("SELECT id FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                throw new Exception("Este email já está cadastrado.");
            }
            
            // Buscar ID do tipo de usuário
            $stmt = $db->prepare("SELECT id FROM tipos_usuario WHERE tipo = ?");
            $stmt->execute([$tipoUsuario]);
            $tipoUsuarioId = $stmt->fetchColumn();
            
            // Inserir usuário
            $senhaHash = password_hash($senha, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
            $tokenVerificacao = generateToken();
            
            $stmt = $db->prepare("
                INSERT INTO usuarios (tipo_usuario_id, email, senha, nome_completo, cpf_cnpj, telefone, token_verificacao) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$tipoUsuarioId, $email, $senhaHash, $nomeCompleto, $cpfCnpj, $telefone, $tokenVerificacao]);
            $usuarioId = $db->lastInsertId();
            
            // Inserir dados específicos por tipo
            if ($tipoUsuario === 'empresa') {
                $stmt = $db->prepare("
                    INSERT INTO empresas (usuario_id, razao_social, nome_fantasia, cnpj, tipo_empresa, endereco, cidade, estado, cep) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$usuarioId, $razaoSocial, $nomeFantasia, $cpfCnpj, $tipoEmpresa, $endereco, $cidade, $estado, $cep]);
            }
            
            if ($tipoUsuario === 'arbitro') {
                $stmt = $db->prepare("
                    INSERT INTO arbitros (usuario_id, oab_numero, oab_estado, biografia, experiencia_anos, especializacao_imobiliaria, pos_imobiliario) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $especializacaoImobiliaria = in_array('imobiliario_geral', $especializacoes);
                $posImobiliario = isset($_POST['pos_imobiliario']) ? 1 : 0;
                $stmt->execute([$usuarioId, $oabNumero, $oabEstado, $biografia, $experienciaAnos, $especializacaoImobiliaria, $posImobiliario]);
                
                $arbitroId = $db->lastInsertId();
                
                // Inserir especializações
                if (!empty($especializacoes)) {
                    $stmt = $db->prepare("INSERT INTO arbitro_especializacoes (arbitro_id, especializacao) VALUES (?, ?)");
                    foreach ($especializacoes as $esp) {
                        if (in_array($esp, ['locacoes', 'disputas_condominiais', 'imobiliario_geral', 'danos', 'infracoes'])) {
                            $stmt->execute([$arbitroId, $esp]);
                        }
                    }
                }
            }
            
            $db->commit();
            
            // Enviar email de verificação
            $verifyLink = APP_URL . "/verify.php?token=" . $tokenVerificacao;
            $emailBody = "
                <h2>Bem-vindo ao Arbitrivm!</h2>
                <p>Olá $nomeCompleto,</p>
                <p>Seu cadastro foi realizado com sucesso. Para ativar sua conta, clique no link abaixo:</p>
                <p><a href='$verifyLink'>Verificar Email</a></p>
                <p>Se você não criou esta conta, ignore este email.</p>
            ";
            sendEmail($email, "Verifique seu email - Arbitrivm", $emailBody);
            
            logActivity('registro', "Novo usuário registrado: $email");
            
            $success = true;
            
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - Arbitrivm</title>
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
        
        .container {
            max-width: 600px;
            margin: 50px auto;
            padding: 0 20px;
        }
        
        .logo {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .logo h1 {
            color: #1a365d;
            font-size: 2.5rem;
            font-weight: 700;
            letter-spacing: -1px;
        }
        
        .logo p {
            color: #718096;
            margin-top: 5px;
        }
        
        .register-box {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
            padding: 40px;
        }
        
        .form-title {
            font-size: 1.5rem;
            color: #1a365d;
            margin-bottom: 30px;
            text-align: center;
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
        
        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="tel"],
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
        
        select {
            cursor: pointer;
        }
        
        textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: auto;
            margin-right: 8px;
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
            width: 100%;
        }
        
        .btn-primary:hover {
            background-color: #2558a3;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(43, 108, 176, 0.3);
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
        
        .form-footer {
            text-align: center;
            margin-top: 30px;
            color: #718096;
        }
        
        .form-footer a {
            color: #2b6cb0;
            text-decoration: none;
        }
        
        .form-footer a:hover {
            text-decoration: underline;
        }
        
        .hidden {
            display: none;
        }
        
        .two-columns {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        @media (max-width: 768px) {
            .two-columns {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <h1>ARBITRIVM</h1>
            <p>Resolução Digital de Disputas Imobiliárias</p>
        </div>
        
        <div class="register-box">
            <h2 class="form-title">Criar Conta</h2>
            
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
                    <p>Cadastro realizado com sucesso! Verifique seu email para ativar sua conta.</p>
                    <p style="margin-top: 10px;">
                        <a href="login.php">Ir para página de login</a>
                    </p>
                </div>
            <?php else: ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="tipo_usuario">Tipo de Conta</label>
                    <select name="tipo_usuario" id="tipo_usuario" required onchange="toggleFields()">
                        <option value="">Selecione...</option>
                        <option value="solicitante">Pessoa Física (Locador/Locatário/Condômino)</option>
                        <option value="empresa">Empresa (Imobiliária/Condomínio)</option>
                        <option value="arbitro">Árbitro</option>
                    </select>
                </div>
                
                <div class="two-columns">
                    <div class="form-group">
                        <label for="nome_completo">Nome Completo</label>
                        <input type="text" name="nome_completo" id="nome_completo" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="cpf_cnpj"><span id="doc_label">CPF</span></label>
                        <input type="text" name="cpf_cnpj" id="cpf_cnpj" required maxlength="18">
                    </div>
                </div>
                
                <div class="two-columns">
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" name="email" id="email" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="telefone">Telefone</label>
                        <input type="tel" name="telefone" id="telefone" placeholder="(11) 99999-9999">
                    </div>
                </div>
                
                <div class="two-columns">
                    <div class="form-group">
                        <label for="senha">Senha</label>
                        <input type="password" name="senha" id="senha" required minlength="8">
                    </div>
                    
                    <div class="form-group">
                        <label for="confirmar_senha">Confirmar Senha</label>
                        <input type="password" name="confirmar_senha" id="confirmar_senha" required minlength="8">
                    </div>
                </div>
                
                <!-- Campos específicos para empresa -->
                <div id="empresa_fields" class="hidden">
                    <div class="form-group">
                        <label for="razao_social">Razão Social</label>
                        <input type="text" name="razao_social" id="razao_social">
                    </div>
                    
                    <div class="two-columns">
                        <div class="form-group">
                            <label for="nome_fantasia">Nome Fantasia</label>
                            <input type="text" name="nome_fantasia" id="nome_fantasia">
                        </div>
                        
                        <div class="form-group">
                            <label for="tipo_empresa">Tipo de Empresa</label>
                            <select name="tipo_empresa" id="tipo_empresa">
                                <option value="">Selecione...</option>
                                <option value="imobiliaria">Imobiliária</option>
                                <option value="condominio">Condomínio</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="endereco">Endereço</label>
                        <input type="text" name="endereco" id="endereco">
                    </div>
                    
                    <div class="two-columns">
                        <div class="form-group">
                            <label for="cidade">Cidade</label>
                            <input type="text" name="cidade" id="cidade">
                        </div>
                        
                        <div class="form-group">
                            <label for="estado">Estado</label>
                            <select name="estado" id="estado">
                                <option value="">Selecione...</option>
                                <option value="AC">Acre</option>
                                <option value="AL">Alagoas</option>
                                <option value="AP">Amapá</option>
                                <option value="AM">Amazonas</option>
                                <option value="BA">Bahia</option>
                                <option value="CE">Ceará</option>
                                <option value="DF">Distrito Federal</option>
                                <option value="ES">Espírito Santo</option>
                                <option value="GO">Goiás</option>
                                <option value="MA">Maranhão</option>
                                <option value="MT">Mato Grosso</option>
                                <option value="MS">Mato Grosso do Sul</option>
                                <option value="MG">Minas Gerais</option>
                                <option value="PA">Pará</option>
                                <option value="PB">Paraíba</option>
                                <option value="PR">Paraná</option>
                                <option value="PE">Pernambuco</option>
                                <option value="PI">Piauí</option>
                                <option value="RJ">Rio de Janeiro</option>
                                <option value="RN">Rio Grande do Norte</option>
                                <option value="RS">Rio Grande do Sul</option>
                                <option value="RO">Rondônia</option>
                                <option value="RR">Roraima</option>
                                <option value="SC">Santa Catarina</option>
                                <option value="SP">São Paulo</option>
                                <option value="SE">Sergipe</option>
                                <option value="TO">Tocantins</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="cep">CEP</label>
                        <input type="text" name="cep" id="cep" maxlength="9">
                    </div>
                </div>
                
                <!-- Campos específicos para árbitro -->
                <div id="arbitro_fields" class="hidden">
                    <div class="two-columns">
                        <div class="form-group">
                            <label for="oab_numero">Número OAB</label>
                            <input type="text" name="oab_numero" id="oab_numero">
                        </div>
                        
                        <div class="form-group">
                            <label for="oab_estado">Estado OAB</label>
                            <select name="oab_estado" id="oab_estado">
                                <option value="">Selecione...</option>
                                <option value="AC">AC</option>
                                <option value="AL">AL</option>
                                <option value="AP">AP</option>
                                <option value="AM">AM</option>
                                <option value="BA">BA</option>
                                <option value="CE">CE</option>
                                <option value="DF">DF</option>
                                <option value="ES">ES</option>
                                <option value="GO">GO</option>
                                <option value="MA">MA</option>
                                <option value="MT">MT</option>
                                <option value="MS">MS</option>
                                <option value="MG">MG</option>
                                <option value="PA">PA</option>
                                <option value="PB">PB</option>
                                <option value="PR">PR</option>
                                <option value="PE">PE</option>
                                <option value="PI">PI</option>
                                <option value="RJ">RJ</option>
                                <option value="RN">RN</option>
                                <option value="RS">RS</option>
                                <option value="RO">RO</option>
                                <option value="RR">RR</option>
                                <option value="SC">SC</option>
                                <option value="SP">SP</option>
                                <option value="SE">SE</option>
                                <option value="TO">TO</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="experiencia_anos">Anos de Experiência</label>
                        <input type="number" name="experiencia_anos" id="experiencia_anos" min="0" max="50">
                    </div>
                    
                    <div class="form-group">
                        <label for="biografia">Biografia Profissional</label>
                        <textarea name="biografia" id="biografia" rows="4"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Especializações</label>
                        <div class="checkbox-group">
                            <input type="checkbox" name="especializacoes[]" value="locacoes" id="esp_locacoes">
                            <label for="esp_locacoes">Locações</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" name="especializacoes[]" value="disputas_condominiais" id="esp_cond">
                            <label for="esp_cond">Disputas Condominiais</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" name="especializacoes[]" value="danos" id="esp_danos">
                            <label for="esp_danos">Danos ao Imóvel</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" name="especializacoes[]" value="infracoes" id="esp_infracoes">
                            <label for="esp_infracoes">Infrações</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" name="especializacoes[]" value="imobiliario_geral" id="esp_geral">
                            <label for="esp_geral">Imobiliário Geral</label>
                        </div>
                    </div>
                    
                    <div class="checkbox-group">
                        <input type="checkbox" name="pos_imobiliario" id="pos_imobiliario">
                        <label for="pos_imobiliario">Possuo pós-graduação em Direito Imobiliário</label>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">Criar Conta</button>
            </form>
            
            <?php endif; ?>
            
            <div class="form-footer">
                Já tem uma conta? <a href="login.php">Faça login</a>
            </div>
        </div>
    </div>
    
    <script>
        function toggleFields() {
            const tipoUsuario = document.getElementById('tipo_usuario').value;
            const empresaFields = document.getElementById('empresa_fields');
            const arbitroFields = document.getElementById('arbitro_fields');
            const docLabel = document.getElementById('doc_label');
            const cpfCnpjInput = document.getElementById('cpf_cnpj');
            
            // Esconder todos os campos específicos
            empresaFields.classList.add('hidden');
            arbitroFields.classList.add('hidden');
            
            // Resetar validações
            const empresaInputs = empresaFields.querySelectorAll('input, select, textarea');
            const arbitroInputs = arbitroFields.querySelectorAll('input, select, textarea');
            
            empresaInputs.forEach(input => input.removeAttribute('required'));
            arbitroInputs.forEach(input => input.removeAttribute('required'));
            
            // Mostrar campos específicos e ajustar label
            if (tipoUsuario === 'empresa') {
                empresaFields.classList.remove('hidden');
                docLabel.textContent = 'CNPJ';
                cpfCnpjInput.setAttribute('maxlength', '18');
                cpfCnpjInput.setAttribute('placeholder', '00.000.000/0000-00');
                
                // Tornar campos obrigatórios
                document.getElementById('razao_social').setAttribute('required', '');
                document.getElementById('tipo_empresa').setAttribute('required', '');
            } else if (tipoUsuario === 'arbitro') {
                arbitroFields.classList.remove('hidden');
                docLabel.textContent = 'CPF';
                cpfCnpjInput.setAttribute('maxlength', '14');
                cpfCnpjInput.setAttribute('placeholder', '000.000.000-00');
                
                // Tornar campos obrigatórios
                document.getElementById('oab_numero').setAttribute('required', '');
                document.getElementById('oab_estado').setAttribute('required', '');
            } else {
                docLabel.textContent = 'CPF';
                cpfCnpjInput.setAttribute('maxlength', '14');
                cpfCnpjInput.setAttribute('placeholder', '000.000.000-00');
            }
        }
        
        // Máscara para CPF/CNPJ
        document.getElementById('cpf_cnpj').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            
            if (document.getElementById('tipo_usuario').value === 'empresa') {
                // Máscara CNPJ
                if (value.length <= 14) {
                    value = value.replace(/^(\d{2})(\d)/, '$1.$2');
                    value = value.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
                    value = value.replace(/\.(\d{3})(\d)/, '.$1/$2');
                    value = value.replace(/(\d{4})(\d)/, '$1-$2');
                }
            } else {
                // Máscara CPF
                if (value.length <= 11) {
                    value = value.replace(/^(\d{3})(\d)/, '$1.$2');
                    value = value.replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3');
                    value = value.replace(/\.(\d{3})(\d)/, '.$1-$2');
                }
            }
            
            e.target.value = value;
        });
        
        // Máscara para CEP
        document.getElementById('cep')?.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length <= 8) {
                value = value.replace(/^(\d{5})(\d)/, '$1-$2');
            }
            e.target.value = value;
        });
        
        // Máscara para telefone
        document.getElementById('telefone').addEventListener('input', function(e) {
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
    </script>
</body>
</html>