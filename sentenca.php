<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proferir Sentença - Arbitrivm</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background-color: #f5f5f5;
            color: #333;
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background-color: #1a237e;
            color: white;
            padding: 20px 0;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .header h1 {
            font-size: 28px;
            font-weight: 500;
        }

        .case-info {
            background-color: #e8eaf6;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            border-left: 4px solid #3f51b5;
        }

        .case-info h2 {
            color: #1a237e;
            margin-bottom: 15px;
            font-size: 20px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
        }

        .info-label {
            font-weight: 600;
            color: #5c6bc0;
            font-size: 14px;
            margin-bottom: 5px;
        }

        .info-value {
            color: #424242;
            font-size: 16px;
        }

        .form-section {
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .form-section h3 {
            color: #1a237e;
            margin-bottom: 20px;
            font-size: 22px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-section h3::before {
            content: '';
            width: 4px;
            height: 24px;
            background-color: #3f51b5;
            display: inline-block;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #424242;
            font-size: 16px;
        }

        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 15px;
            font-family: inherit;
            resize: vertical;
            transition: border-color 0.3s ease;
        }

        .form-group textarea:focus {
            outline: none;
            border-color: #3f51b5;
            box-shadow: 0 0 0 3px rgba(63, 81, 181, 0.1);
        }

        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group input[type="date"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 15px;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: #3f51b5;
            box-shadow: 0 0 0 3px rgba(63, 81, 181, 0.1);
        }

        .help-text {
            font-size: 14px;
            color: #757575;
            margin-top: 5px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }

        .file-upload {
            position: relative;
            display: inline-block;
            width: 100%;
        }

        .file-upload input[type="file"] {
            display: none;
        }

        .file-upload-label {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 20px;
            border: 2px dashed #3f51b5;
            border-radius: 6px;
            background-color: #f5f5f5;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .file-upload-label:hover {
            background-color: #e8eaf6;
            border-color: #1a237e;
        }

        .file-upload-label svg {
            width: 24px;
            height: 24px;
            color: #3f51b5;
        }

        .file-info {
            margin-top: 10px;
            font-size: 14px;
            color: #757575;
        }

        .button-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            justify-content: flex-end;
        }

        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background-color: #3f51b5;
            color: white;
        }

        .btn-primary:hover {
            background-color: #303f9f;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(63, 81, 181, 0.3);
        }

        .btn-secondary {
            background-color: #757575;
            color: white;
        }

        .btn-secondary:hover {
            background-color: #616161;
        }

        .btn-outline {
            background-color: transparent;
            color: #3f51b5;
            border: 2px solid #3f51b5;
        }

        .btn-outline:hover {
            background-color: #3f51b5;
            color: white;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-info {
            background-color: #e3f2fd;
            color: #1565c0;
            border-left: 4px solid #1976d2;
        }

        .character-count {
            font-size: 14px;
            color: #757575;
            text-align: right;
            margin-top: 5px;
        }

        .preview-section {
            background-color: #fafafa;
            padding: 20px;
            border-radius: 6px;
            margin-top: 20px;
            border: 1px solid #e0e0e0;
        }

        .preview-section h4 {
            color: #424242;
            margin-bottom: 10px;
        }

        .loading {
            display: none;
            align-items: center;
            justify-content: center;
            gap: 10px;
            color: #3f51b5;
            font-weight: 500;
        }

        .spinner {
            width: 20px;
            height: 20px;
            border: 3px solid #e0e0e0;
            border-top-color: #3f51b5;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <h1>Proferir Sentença Arbitral</h1>
        </div>
    </div>

    <div class="container">
        <!-- Informações do Caso -->
        <div class="case-info">
            <h2>Informações do Caso</h2>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Número do Processo</span>
                    <span class="info-value">#ARB-2024-001234</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Tipo de Disputa</span>
                    <span class="info-value">Danos ao Imóvel</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Reclamante</span>
                    <span class="info-value">Imobiliária Central Ltda.</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Reclamado</span>
                    <span class="info-value">João Silva Santos</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Data de Abertura</span>
                    <span class="info-value">15/01/2024</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Valor da Causa</span>
                    <span class="info-value">R$ 15.000,00</span>
                </div>
            </div>
        </div>

        <!-- Formulário de Sentença -->
        <form id="sentencaForm" action="processar-sentenca.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="processo_id" value="ARB-2024-001234">
            
            <!-- Relatório -->
            <div class="form-section">
                <h3>Relatório</h3>
                <div class="alert alert-info">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                    </svg>
                    Descreva os fatos relevantes do caso, as alegações das partes e o procedimento arbitral realizado.
                </div>
                <div class="form-group">
                    <label for="relatorio">Relatório do Caso</label>
                    <textarea 
                        id="relatorio" 
                        name="relatorio" 
                        rows="10" 
                        required
                        placeholder="Trata-se de procedimento arbitral instaurado por IMOBILIÁRIA CENTRAL LTDA. em face de JOÃO SILVA SANTOS, tendo por objeto..."
                    ></textarea>
                    <div class="character-count">
                        <span id="relatorioCount">0</span> caracteres
                    </div>
                </div>
            </div>

            <!-- Fundamentação -->
            <div class="form-section">
                <h3>Fundamentação</h3>
                <div class="form-group">
                    <label for="fundamentacao">Fundamentação Jurídica</label>
                    <textarea 
                        id="fundamentacao" 
                        name="fundamentacao" 
                        rows="12" 
                        required
                        placeholder="Analise as provas apresentadas, os argumentos das partes e fundamente sua decisão com base no direito aplicável..."
                    ></textarea>
                    <div class="character-count">
                        <span id="fundamentacaoCount">0</span> caracteres
                    </div>
                    <p class="help-text">Inclua a análise das provas, aplicação do direito e motivação da decisão.</p>
                </div>
            </div>

            <!-- Dispositivo -->
            <div class="form-section">
                <h3>Dispositivo</h3>
                <div class="form-group">
                    <label for="dispositivo">Decisão Final</label>
                    <textarea 
                        id="dispositivo" 
                        name="dispositivo" 
                        rows="8" 
                        required
                        placeholder="Diante do exposto, JULGO PROCEDENTE/IMPROCEDENTE o pedido..."
                    ></textarea>
                    <div class="character-count">
                        <span id="dispositivoCount">0</span> caracteres
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="valor_condenacao">Valor da Condenação (R$)</label>
                        <input 
                            type="number" 
                            id="valor_condenacao" 
                            name="valor_condenacao" 
                            step="0.01" 
                            min="0"
                            placeholder="0,00"
                        >
                        <p class="help-text">Deixe em branco se não houver condenação pecuniária.</p>
                    </div>

                    <div class="form-group">
                        <label for="prazo_cumprimento">Prazo para Cumprimento</label>
                        <input 
                            type="text" 
                            id="prazo_cumprimento" 
                            name="prazo_cumprimento" 
                            placeholder="Ex: 30 dias"
                        >
                        <p class="help-text">Especifique o prazo para cumprimento da decisão.</p>
                    </div>
                </div>
            </div>

            <!-- Upload de Documento -->
            <div class="form-section">
                <h3>Documento Final</h3>
                <div class="form-group">
                    <label for="documento_sentenca">Upload da Sentença Assinada (Opcional)</label>
                    <div class="file-upload">
                        <label for="documento_sentenca" class="file-upload-label">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                            </svg>
                            <span>Clique para selecionar ou arraste o arquivo aqui</span>
                        </label>
                        <input type="file" id="documento_sentenca" name="documento_sentenca" accept=".pdf,.doc,.docx">
                    </div>
                    <div class="file-info" id="fileInfo"></div>
                    <p class="help-text">Formatos aceitos: PDF, DOC, DOCX. Tamanho máximo: 10MB.</p>
                </div>
            </div>

            <!-- Preview da Sentença -->
            <div class="form-section">
                <h3>Prévia da Sentença</h3>
                <div class="preview-section" id="previewSection">
                    <h4>A prévia aparecerá aqui conforme você preenche os campos...</h4>
                </div>
            </div>

            <!-- Botões de Ação -->
            <div class="button-group">
                <button type="button" class="btn btn-outline" onclick="salvarRascunho()">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path>
                    </svg>
                    Salvar Rascunho
                </button>
                <button type="button" class="btn btn-secondary" onclick="window.history.back()">
                    Cancelar
                </button>
                <button type="submit" class="btn btn-primary">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Proferir Sentença
                </button>
            </div>

            <div class="loading" id="loading">
                <div class="spinner"></div>
                <span>Processando sentença...</span>
            </div>
        </form>
    </div>

    <script>
        // Contador de caracteres
        const textareas = ['relatorio', 'fundamentacao', 'dispositivo'];
        
        textareas.forEach(id => {
            const textarea = document.getElementById(id);
            const counter = document.getElementById(id + 'Count');
            
            textarea.addEventListener('input', function() {
                counter.textContent = this.value.length;
                updatePreview();
            });
        });

        // Upload de arquivo
        const fileInput = document.getElementById('documento_sentenca');
        const fileInfo = document.getElementById('fileInfo');
        
        fileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const fileSize = (file.size / 1024 / 1024).toFixed(2);
                fileInfo.innerHTML = `
                    <strong>Arquivo selecionado:</strong> ${file.name} (${fileSize} MB)
                `;
                
                if (fileSize > 10) {
                    fileInfo.innerHTML += '<br><span style="color: #d32f2f;">Arquivo muito grande. Máximo permitido: 10MB</span>';
                    fileInput.value = '';
                }
            }
        });

        // Drag and drop
        const fileLabel = document.querySelector('.file-upload-label');
        
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            fileLabel.addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        ['dragenter', 'dragover'].forEach(eventName => {
            fileLabel.addEventListener(eventName, highlight, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            fileLabel.addEventListener(eventName, unhighlight, false);
        });
        
        function highlight(e) {
            fileLabel.style.backgroundColor = '#e8eaf6';
            fileLabel.style.borderColor = '#1a237e';
        }
        
        function unhighlight(e) {
            fileLabel.style.backgroundColor = '#f5f5f5';
            fileLabel.style.borderColor = '#3f51b5';
        }
        
        fileLabel.addEventListener('drop', handleDrop, false);
        
        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            fileInput.files = files;
            
            const event = new Event('change', { bubbles: true });
            fileInput.dispatchEvent(event);
        }

        // Preview da sentença
        function updatePreview() {
            const relatorio = document.getElementById('relatorio').value;
            const fundamentacao = document.getElementById('fundamentacao').value;
            const dispositivo = document.getElementById('dispositivo').value;
            const valor = document.getElementById('valor_condenacao').value;
            const prazo = document.getElementById('prazo_cumprimento').value;
            
            const preview = document.getElementById('previewSection');
            
            if (relatorio || fundamentacao || dispositivo) {
                let previewHTML = '<h4>SENTENÇA ARBITRAL</h4>';
                
                if (relatorio) {
                    previewHTML += `
                        <div style="margin-top: 20px;">
                            <strong>I - RELATÓRIO</strong>
                            <p style="margin-top: 10px; text-align: justify;">${relatorio.replace(/\n/g, '<br>')}</p>
                        </div>
                    `;
                }
                
                if (fundamentacao) {
                    previewHTML += `
                        <div style="margin-top: 20px;">
                            <strong>II - FUNDAMENTAÇÃO</strong>
                            <p style="margin-top: 10px; text-align: justify;">${fundamentacao.replace(/\n/g, '<br>')}</p>
                        </div>
                    `;
                }
                
                if (dispositivo) {
                    previewHTML += `
                        <div style="margin-top: 20px;">
                            <strong>III - DISPOSITIVO</strong>
                            <p style="margin-top: 10px; text-align: justify;">${dispositivo.replace(/\n/g, '<br>')}</p>
                        </div>
                    `;
                }
                
                if (valor) {
                    previewHTML += `
                        <p style="margin-top: 15px;"><strong>Valor da Condenação:</strong> R$ ${parseFloat(valor).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</p>
                    `;
                }
                
                if (prazo) {
                    previewHTML += `
                        <p><strong>Prazo para Cumprimento:</strong> ${prazo}</p>
                    `;
                }
                
                preview.innerHTML = previewHTML;
            } else {
                preview.innerHTML = '<h4>A prévia aparecerá aqui conforme você preenche os campos...</h4>';
            }
        }

        // Salvar rascunho
        function salvarRascunho() {
            const formData = {
                relatorio: document.getElementById('relatorio').value,
                fundamentacao: document.getElementById('fundamentacao').value,
                dispositivo: document.getElementById('dispositivo').value,
                valor_condenacao: document.getElementById('valor_condenacao').value,
                prazo_cumprimento: document.getElementById('prazo_cumprimento').value,
                timestamp: new Date().toISOString()
            };
            
            localStorage.setItem('sentenca_rascunho_ARB-2024-001234', JSON.stringify(formData));
            
            alert('Rascunho salvo com sucesso!');
        }

        // Carregar rascunho se existir
        window.addEventListener('load', function() {
            const rascunho = localStorage.getItem('sentenca_rascunho_ARB-2024-001234');
            
            if (rascunho) {
                const data = JSON.parse(rascunho);
                const confirmLoad = confirm('Foi encontrado um rascunho salvo. Deseja carregá-lo?');
                
                if (confirmLoad) {
                    document.getElementById('relatorio').value = data.relatorio || '';
                    document.getElementById('fundamentacao').value = data.fundamentacao || '';
                    document.getElementById('dispositivo').value = data.dispositivo || '';
                    document.getElementById('valor_condenacao').value = data.valor_condenacao || '';
                    document.getElementById('prazo_cumprimento').value = data.prazo_cumprimento || '';
                    
                    // Atualizar contadores e preview
                    textareas.forEach(id => {
                        const textarea = document.getElementById(id);
                        const counter = document.getElementById(id + 'Count');
                        counter.textContent = textarea.value.length;
                    });
                    
                    updatePreview();
                }
            }
        });

        // Validação e envio do formulário
        document.getElementById('sentencaForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Validação básica
            const relatorio = document.getElementById('relatorio').value.trim();
            const fundamentacao = document.getElementById('fundamentacao').value.trim();
            const dispositivo = document.getElementById('dispositivo').value.trim();
            
            if (!relatorio || !fundamentacao || !dispositivo) {
                alert('Por favor, preencha todos os campos obrigatórios da sentença.');
                return;
            }
            
            if (relatorio.length < 100) {
                alert('O relatório deve conter pelo menos 100 caracteres.');
                return;
            }
            
            if (fundamentacao.length < 200) {
                alert('A fundamentação deve conter pelo menos 200 caracteres.');
                return;
            }
            
            if (dispositivo.length < 50) {
                alert('O dispositivo deve conter pelo menos 50 caracteres.');
                return;
            }
            
            // Confirmação antes de enviar
            const confirmSubmit = confirm('Tem certeza que deseja proferir esta sentença? Esta ação não poderá ser desfeita.');
            
            if (confirmSubmit) {
                // Mostrar loading
                document.getElementById('loading').style.display = 'flex';
                
                // Simular envio (em produção, seria um submit real)
                setTimeout(() => {
                    // Limpar rascunho após envio bem-sucedido
                    localStorage.removeItem('sentenca_rascunho_ARB-2024-001234');
                    
                    // Em produção, fazer o submit real
                    // this.submit();
                    
                    // Para demonstração, redirecionar
                    alert('Sentença proferida com sucesso!');
                    window.location.href = 'visualizar-sentenca.php?id=ARB-2024-001234';
                }, 2000);
            }
        });

        // Auto-save periódico
        setInterval(function() {
            const relatorio = document.getElementById('relatorio').value;
            const fundamentacao = document.getElementById('fundamentacao').value;
            const dispositivo = document.getElementById('dispositivo').value;
            
            if (relatorio || fundamentacao || dispositivo) {
                salvarRascunho();
                console.log('Auto-save executado');
            }
        }, 60000); // A cada 1 minuto
    </script>
</body>
</html>