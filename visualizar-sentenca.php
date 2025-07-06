<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualizar Sentença - Arbitrivm</title>
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

        .breadcrumb {
            margin-bottom: 20px;
            font-size: 14px;
            color: #757575;
        }

        .breadcrumb a {
            color: #3f51b5;
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        .case-header {
            background-color: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .case-number {
            font-size: 20px;
            font-weight: 600;
            color: #1a237e;
            margin-bottom: 15px;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 20px;
        }

        .status-proferida {
            background-color: #e8f5e9;
            color: #2e7d32;
        }

        .status-cumprida {
            background-color: #e3f2fd;
            color: #1565c0;
        }

        .status-pendente {
            background-color: #fff3e0;
            color: #ef6c00;
        }

        .case-parties {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            gap: 20px;
            align-items: center;
            margin-top: 20px;
        }

        .party-info {
            text-align: center;
        }

        .party-label {
            font-size: 14px;
            color: #757575;
            margin-bottom: 5px;
        }

        .party-name {
            font-size: 16px;
            font-weight: 500;
            color: #424242;
        }

        .vs {
            font-weight: 600;
            color: #9e9e9e;
        }

        @media (max-width: 768px) {
            .case-parties {
                grid-template-columns: 1fr;
                text-align: left;
            }
            .party-info {
                text-align: left;
            }
            .vs {
                display: none;
            }
        }

        .sentence-document {
            background-color: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .document-header {
            text-align: center;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e0e0e0;
        }

        .document-title {
            font-size: 24px;
            font-weight: 600;
            color: #1a237e;
            margin-bottom: 10px;
        }

        .document-info {
            font-size: 14px;
            color: #757575;
        }

        .sentence-section {
            margin-bottom: 35px;
        }

        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: #1a237e;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            background-color: #3f51b5;
            color: white;
            border-radius: 50%;
            font-size: 14px;
            font-weight: 600;
        }

        .section-content {
            text-align: justify;
            line-height: 1.8;
            color: #424242;
        }

        .section-content p {
            margin-bottom: 15px;
        }

        .decision-summary {
            background-color: #e8eaf6;
            padding: 20px;
            border-radius: 6px;
            border-left: 4px solid #3f51b5;
            margin: 30px 0;
        }

        .decision-summary h4 {
            color: #1a237e;
            margin-bottom: 10px;
        }

        .decision-values {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .value-item {
            display: flex;
            flex-direction: column;
        }

        .value-label {
            font-size: 14px;
            color: #5c6bc0;
            margin-bottom: 5px;
        }

        .value-amount {
            font-size: 20px;
            font-weight: 600;
            color: #1a237e;
        }

        .signature-section {
            margin-top: 50px;
            padding-top: 30px;
            border-top: 1px solid #e0e0e0;
            text-align: center;
        }

        .arbitrator-info {
            margin-bottom: 10px;
        }

        .arbitrator-name {
            font-size: 18px;
            font-weight: 600;
            color: #424242;
        }

        .arbitrator-title {
            font-size: 14px;
            color: #757575;
        }

        .signature-date {
            font-size: 14px;
            color: #757575;
            margin-top: 20px;
        }

        .action-buttons {
            background-color: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .button-group {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }

        .btn {
            padding: 12px 24px;
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

        .compliance-history {
            background-color: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .history-title {
            font-size: 20px;
            font-weight: 600;
            color: #1a237e;
            margin-bottom: 20px;
        }

        .timeline {
            position: relative;
            padding-left: 30px;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 2px;
            background-color: #e0e0e0;
        }

        .timeline-item {
            position: relative;
            padding-bottom: 25px;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: -34px;
            top: 0;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background-color: #3f51b5;
            border: 2px solid white;
            box-shadow: 0 0 0 2px #e0e0e0;
        }

        .timeline-date {
            font-size: 14px;
            color: #757575;
            margin-bottom: 5px;
        }

        .timeline-content {
            background-color: #f5f5f5;
            padding: 15px;
            border-radius: 6px;
        }

        .timeline-title {
            font-weight: 600;
            color: #424242;
            margin-bottom: 5px;
        }

        .timeline-description {
            font-size: 14px;
            color: #616161;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-warning {
            background-color: #fff8e1;
            color: #f57f17;
            border-left: 4px solid #ffc107;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-title {
            font-size: 20px;
            font-weight: 600;
            color: #1a237e;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #757575;
        }

        .close-btn:hover {
            color: #424242;
        }

        @media print {
            .header,
            .breadcrumb,
            .action-buttons,
            .compliance-history,
            .alert {
                display: none;
            }
            
            .sentence-document {
                box-shadow: none;
                padding: 20px;
            }
            
            body {
                background-color: white;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <h1>Sentença Arbitral</h1>
        </div>
    </div>

    <div class="container">
        <!-- Breadcrumb -->
        <div class="breadcrumb">
            <a href="dashboard.php">Dashboard</a> / 
            <a href="processos.php">Processos</a> / 
            <a href="processo-detalhes.php?id=ARB-2024-001234">#ARB-2024-001234</a> / 
            Sentença
        </div>

        <!-- Cabeçalho do Caso -->
        <div class="case-header">
            <div class="case-number">Processo #ARB-2024-001234</div>
            <div class="status-badge status-proferida">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                Sentença Proferida
            </div>
            
            <div class="case-parties">
                <div class="party-info">
                    <div class="party-label">Reclamante</div>
                    <div class="party-name">Imobiliária Central Ltda.</div>
                </div>
                <div class="vs">VS</div>
                <div class="party-info">
                    <div class="party-label">Reclamado</div>
                    <div class="party-name">João Silva Santos</div>
                </div>
            </div>
        </div>

        <!-- Alerta de Prazo -->
        <div class="alert alert-warning">
            <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            <span>Prazo para cumprimento: <strong>15 dias restantes</strong> (vencimento em 20/07/2025)</span>
        </div>

        <!-- Botões de Ação -->
        <div class="action-buttons">
            <div class="button-group">
                <button class="btn btn-primary" onclick="downloadPDF()">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Baixar PDF
                </button>
                <button class="btn btn-outline" onclick="window.print()">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                    </svg>
                    Imprimir
                </button>
                <button class="btn btn-outline" onclick="compartilharSentenca()">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m9.632 4.684C18.114 16.062 18 16.518 18 17c0 .482.114.938.316 1.342m0-2.684a3 3 0 110 2.684M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                    Compartilhar
                </button>
                <button class="btn btn-secondary" onclick="registrarCumprimento()">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                    </svg>
                    Registrar Cumprimento
                </button>
            </div>
        </div>

        <!-- Documento da Sentença -->
        <div class="sentence-document">
            <div class="document-header">
                <h2 class="document-title">SENTENÇA ARBITRAL</h2>
                <div class="document-info">
                    Processo nº ARB-2024-001234 | Proferida em 05/07/2025
                </div>
            </div>

            <!-- Relatório -->
            <div class="sentence-section">
                <h3 class="section-title">
                    <span class="section-number">I</span>
                    RELATÓRIO
                </h3>
                <div class="section-content">
                    <p>
                        Trata-se de procedimento arbitral instaurado por <strong>IMOBILIÁRIA CENTRAL LTDA.</strong>, 
                        pessoa jurídica de direito privado, inscrita no CNPJ sob o nº 12.345.678/0001-90, com sede na 
                        Rua das Flores, nº 123, Centro, São Paulo/SP, neste ato representada na forma de seu contrato social, 
                        em face de <strong>JOÃO SILVA SANTOS</strong>, brasileiro, solteiro, comerciante, portador do RG nº 
                        12.345.678-9 SSP/SP e inscrito no CPF sob o nº 123.456.789-00, residente e domiciliado na Rua das 
                        Acácias, nº 456, Jardim Paulista, São Paulo/SP.
                    </p>
                    <p>
                        A Reclamante alega que o Reclamado, na qualidade de locatário do imóvel situado na Rua das Palmeiras, 
                        nº 789, apartamento 301, Vila Mariana, São Paulo/SP, causou danos significativos ao imóvel durante o 
                        período de locação, compreendido entre 01/01/2023 e 31/12/2023.
                    </p>
                    <p>
                        Segundo a inicial, foram constatados os seguintes danos: (i) infiltração no banheiro decorrente de 
                        negligência na manutenção; (ii) pisos laminados danificados em toda a área social; (iii) pintura 
                        deteriorada com manchas de umidade; (iv) armários da cozinha com portas quebradas; e (v) instalações 
                        elétricas comprometidas por uso inadequado.
                    </p>
                    <p>
                        O Reclamado, devidamente notificado, apresentou defesa alegando que os danos preexistiam à locação e 
                        que o desgaste observado decorreu do uso normal do imóvel. Sustentou ainda que realizou manutenções 
                        periódicas conforme previsto no contrato de locação.
                    </p>
                    <p>
                        Foi realizada audiência de instrução em 15/06/2025, na qual foram ouvidas as partes e analisadas as 
                        provas documentais apresentadas, incluindo laudos de vistoria, fotografias, notas fiscais e o contrato 
                        de locação.
                    </p>
                </div>
            </div>

            <!-- Fundamentação -->
            <div class="sentence-section">
                <h3 class="section-title">
                    <span class="section-number">II</span>
                    FUNDAMENTAÇÃO
                </h3>
                <div class="section-content">
                    <p>
                        A controvérsia cinge-se à responsabilidade pelos danos causados ao imóvel locado e ao valor da 
                        indenização devida. O contrato de locação firmado entre as partes estabelece claramente as obrigações 
                        do locatário quanto à conservação do imóvel, nos termos da Lei nº 8.245/91.
                    </p>
                    <p>
                        Da análise dos laudos de vistoria apresentados, observa-se que o laudo de entrada, datado de 
                        30/12/2022, descreve o imóvel em perfeitas condições de uso, com todos os itens funcionando 
                        adequadamente. Por outro lado, o laudo de saída, elaborado em 05/01/2024, aponta os danos ora 
                        discutidos.
                    </p>
                    <p>
                        As fotografias juntadas aos autos corroboram as alegações da Reclamante, evidenciando que os danos 
                        excedem o desgaste natural esperado para o período de locação. Particularmente relevante é a prova 
                        pericial que demonstra que a infiltração no banheiro decorreu da falta de manutenção adequada das 
                        vedações, obrigação contratual do locatário.
                    </p>
                    <p>
                        Quanto aos pisos laminados, as imagens demonstram danos causados por excesso de umidade e arrastar 
                        de móveis sem a devida proteção, caracterizando uso inadequado. Os demais danos também restaram 
                        comprovados através da documentação apresentada.
                    </p>
                    <p>
                        O Reclamado não logrou êxito em comprovar suas alegações de que os danos preexistiam, uma vez que 
                        o laudo de vistoria de entrada, por ele assinado, não registra qualquer ressalva nesse sentido. 
                        Tampouco comprovou a realização das manutenções alegadas, não tendo apresentado recibos ou notas 
                        fiscais que demonstrassem tais serviços.
                    </p>
                    <p>
                        No que tange ao valor da indenização, foram apresentados três orçamentos para reparo dos danos, 
                        sendo adotado o valor médio de R$ 15.000,00 (quinze mil reais), montante que se mostra razoável 
                        e proporcional aos danos verificados.
                    </p>
                </div>
            </div>

            <!-- Dispositivo -->
            <div class="sentence-section">
                <h3 class="section-title">
                    <span class="section-number">III</span>
                    DISPOSITIVO
                </h3>
                <div class="section-content">
                    <p>
                        Diante do exposto, com fundamento nos artigos 22 e 23 da Lei nº 8.245/91, c/c artigos 186 e 927 
                        do Código Civil, <strong>JULGO PROCEDENTE</strong> o pedido formulado por IMOBILIÁRIA CENTRAL LTDA. 
                        em face de JOÃO SILVA SANTOS, para CONDENAR o Reclamado ao pagamento de indenização no valor de 
                        <strong>R$ 15.000,00 (quinze mil reais)</strong>, a título de reparação pelos danos causados ao 
                        imóvel locado.
                    </p>
                    <p>
                        O valor deverá ser corrigido monetariamente pelo IPCA desde a data do laudo de saída (05/01/2024) 
                        e acrescido de juros de mora de 1% ao mês a partir da citação.
                    </p>
                    <p>
                        Condeno ainda o Reclamado ao pagamento das custas processuais e honorários arbitrais, estes fixados 
                        em 10% sobre o valor da condenação.
                    </p>
                    <p>
                        Fixo o prazo de <strong>30 (trinta) dias</strong> para cumprimento voluntário da sentença, sob pena 
                        de multa de 10% sobre o valor total da condenação.
                    </p>
                </div>
            </div>

            <!-- Resumo da Decisão -->
            <div class="decision-summary">
                <h4>Resumo da Decisão</h4>
                <div class="decision-values">
                    <div class="value-item">
                        <span class="value-label">Valor da Condenação</span>
                        <span class="value-amount">R$ 15.000,00</span>
                    </div>
                    <div class="value-item">
                        <span class="value-label">Prazo para Cumprimento</span>
                        <span class="value-amount">30 dias</span>
                    </div>
                    <div class="value-item">
                        <span class="value-label">Honorários Arbitrais</span>
                        <span class="value-amount">10%</span>
                    </div>
                </div>
            </div>

            <!-- Assinatura -->
            <div class="signature-section">
                <div class="arbitrator-info">
                    <div class="arbitrator-name">Dr. Carlos Eduardo Mendes</div>
                    <div class="arbitrator-title">Árbitro - OAB/SP 123.456</div>
                </div>
                <div class="signature-date">
                    São Paulo, 05 de julho de 2025
                </div>
            </div>
        </div>

        <!-- Histórico de Cumprimento -->
        <div class="compliance-history">
            <h3 class="history-title">Histórico de Cumprimento</h3>
            
            <div class="timeline">
                <div class="timeline-item">
                    <div class="timeline-date">05/07/2025 - 14:30</div>
                    <div class="timeline-content">
                        <div class="timeline-title">Sentença Proferida</div>
                        <div class="timeline-description">
                            Sentença arbitral proferida pelo Dr. Carlos Eduardo Mendes. Prazo de 30 dias para cumprimento.
                        </div>
                    </div>
                </div>
                
                <div class="timeline-item">
                    <div class="timeline-date">06/07/2025 - 09:15</div>
                    <div class="timeline-content">
                        <div class="timeline-title">Partes Notificadas</div>
                        <div class="timeline-description">
                            Ambas as partes foram notificadas da sentença via plataforma e e-mail.
                        </div>
                    </div>
                </div>
                
                <div class="timeline-item">
                    <div class="timeline-date">08/07/2025 - 11:20</div>
                    <div class="timeline-content">
                        <div class="timeline-title">Sentença Visualizada</div>
                        <div class="timeline-description">
                            Reclamado acessou e visualizou a sentença na plataforma.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Compartilhamento -->
    <div id="shareModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Compartilhar Sentença</h3>
                <button class="close-btn" onclick="closeShareModal()">&times;</button>
            </div>
            <div class="form-group">
                <label>Link para compartilhamento:</label>
                <input type="text" id="shareLink" value="https://arbitrivm.com.br/sentenca/ARB-2024-001234" readonly>
                <button class="btn btn-primary" style="margin-top: 10px" onclick="copiarLink()">
                    Copiar Link
                </button>
            </div>
            <div class="form-group">
                <label>Enviar por e-mail:</label>
                <input type="email" placeholder="Digite o e-mail do destinatário">
                <button class="btn btn-outline" style="margin-top: 10px">
                    Enviar
                </button>
            </div>
        </div>
    </div>

    <!-- Modal de Cumprimento -->
    <div id="cumprimentoModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Registrar Cumprimento da Sentença</h3>
                <button class="close-btn" onclick="closeCumprimentoModal()">&times;</button>
            </div>
            <form>
                <div class="form-group">
                    <label>Data do Cumprimento:</label>
                    <input type="date" required>
                </div>
                <div class="form-group">
                    <label>Valor Pago (R$):</label>
                    <input type="number" step="0.01" placeholder="15.000,00" required>
                </div>
                <div class="form-group">
                    <label>Forma de Pagamento:</label>
                    <select required>
                        <option value="">Selecione...</option>
                        <option value="transferencia">Transferência Bancária</option>
                        <option value="deposito">Depósito</option>
                        <option value="boleto">Boleto</option>
                        <option value="pix">PIX</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Comprovante de Pagamento:</label>
                    <input type="file" accept=".pdf,.jpg,.jpeg,.png">
                </div>
                <div class="form-group">
                    <label>Observações:</label>
                    <textarea rows="3" placeholder="Observações adicionais..."></textarea>
                </div>
                <div class="button-group" style="margin-top: 20px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeCumprimentoModal()">
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary">
                        Registrar Cumprimento
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Download PDF
        function downloadPDF() {
            // Em produção, faria a chamada para gerar o PDF no servidor
            alert('Iniciando download do PDF da sentença...');
            
            // Simulação de download
            const link = document.createElement('a');
            link.href = 'gerar-pdf-sentenca.php?id=ARB-2024-001234';
            link.download = 'Sentenca_ARB-2024-001234.pdf';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        // Compartilhar sentença
        function compartilharSentenca() {
            document.getElementById('shareModal').style.display = 'flex';
        }

        function closeShareModal() {
            document.getElementById('shareModal').style.display = 'none';
        }

        // Copiar link
        function copiarLink() {
            const shareLink = document.getElementById('shareLink');
            shareLink.select();
            shareLink.setSelectionRange(0, 99999); // Para mobile
            
            navigator.clipboard.writeText(shareLink.value).then(function() {
                alert('Link copiado para a área de transferência!');
            }, function(err) {
                console.error('Erro ao copiar: ', err);
            });
        }

        // Registrar cumprimento
        function registrarCumprimento() {
            document.getElementById('cumprimentoModal').style.display = 'flex';
        }

        function closeCumprimentoModal() {
            document.getElementById('cumprimentoModal').style.display = 'none';
        }

        // Fechar modals ao clicar fora
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }

        // Formatar valores monetários
        function formatarMoeda(valor) {
            return new Intl.NumberFormat('pt-BR', {
                style: 'currency',
                currency: 'BRL'
            }).format(valor);
        }

        // Calcular valores atualizados (correção + juros)
        function calcularValorAtualizado() {
            const valorOriginal = 15000;
            const dataInicial = new Date('2024-01-05');
            const dataAtual = new Date();
            const meses = Math.floor((dataAtual - dataInicial) / (1000 * 60 * 60 * 24 * 30));
            
            // Simulação de correção monetária (IPCA) + juros
            const correcao = valorOriginal * 0.005 * meses; // 0.5% ao mês (simplificado)
            const juros = valorOriginal * 0.01 * meses; // 1% ao mês
            
            const valorTotal = valorOriginal + correcao + juros;
            
            return {
                principal: valorOriginal,
                correcao: correcao,
                juros: juros,
                total: valorTotal
            };
        }

        // Atualizar valores na página
        function atualizarValores() {
            const valores = calcularValorAtualizado();
            
            // Criar tooltip com detalhamento
            const tooltipHtml = `
                <div style="position: absolute; background: #333; color: white; padding: 10px; 
                            border-radius: 4px; font-size: 14px; z-index: 1000; display: none;" 
                     id="valorTooltip">
                    <strong>Detalhamento:</strong><br>
                    Valor Principal: ${formatarMoeda(valores.principal)}<br>
                    Correção (IPCA): ${formatarMoeda(valores.correcao)}<br>
                    Juros de Mora: ${formatarMoeda(valores.juros)}<br>
                    <hr style="margin: 5px 0; border-color: #666;">
                    Total Atualizado: ${formatarMoeda(valores.total)}
                </div>
            `;
            
            // Adicionar tooltip ao valor
            const valorElement = document.querySelector('.value-amount');
            if (valorElement && !document.getElementById('valorTooltip')) {
                valorElement.style.cursor = 'pointer';
                valorElement.style.textDecoration = 'underline';
                valorElement.style.textDecorationStyle = 'dotted';
                
                document.body.insertAdjacentHTML('beforeend', tooltipHtml);
                
                valorElement.addEventListener('mouseenter', function(e) {
                    const tooltip = document.getElementById('valorTooltip');
                    tooltip.style.display = 'block';
                    tooltip.style.left = e.pageX + 10 + 'px';
                    tooltip.style.top = e.pageY + 10 + 'px';
                });
                
                valorElement.addEventListener('mouseleave', function() {
                    document.getElementById('valorTooltip').style.display = 'none';
                });
            }
        }

        // Verificar prazo de cumprimento
        function verificarPrazo() {
            const dataProferimento = new Date('2025-07-05');
            const prazo = 30; // dias
            const dataVencimento = new Date(dataProferimento);
            dataVencimento.setDate(dataVencimento.getDate() + prazo);
            
            const hoje = new Date();
            const diasRestantes = Math.ceil((dataVencimento - hoje) / (1000 * 60 * 60 * 24));
            
            const alertElement = document.querySelector('.alert-warning span');
            if (alertElement) {
                if (diasRestantes > 0) {
                    alertElement.innerHTML = `Prazo para cumprimento: <strong>${diasRestantes} dias restantes</strong> (vencimento em ${dataVencimento.toLocaleDateString('pt-BR')})`;
                } else if (diasRestantes === 0) {
                    alertElement.innerHTML = `<strong>ATENÇÃO:</strong> Prazo para cumprimento vence hoje!`;
                    alertElement.parentElement.style.backgroundColor = '#ffebee';
                    alertElement.parentElement.style.color = '#c62828';
                    alertElement.parentElement.style.borderLeftColor = '#f44336';
                } else {
                    alertElement.innerHTML = `<strong>PRAZO VENCIDO:</strong> ${Math.abs(diasRestantes)} dias em atraso. Multa de 10% aplicável.`;
                    alertElement.parentElement.style.backgroundColor = '#ffebee';
                    alertElement.parentElement.style.color = '#c62828';
                    alertElement.parentElement.style.borderLeftColor = '#f44336';
                }
            }
        }

        // Adicionar nova entrada ao histórico
        function adicionarHistorico(titulo, descricao) {
            const timeline = document.querySelector('.timeline');
            const novoItem = document.createElement('div');
            novoItem.className = 'timeline-item';
            
            const agora = new Date();
            const dataFormatada = agora.toLocaleDateString('pt-BR') + ' - ' + 
                                 agora.toLocaleTimeString('pt-BR', {hour: '2-digit', minute:'2-digit'});
            
            novoItem.innerHTML = `
                <div class="timeline-date">${dataFormatada}</div>
                <div class="timeline-content">
                    <div class="timeline-title">${titulo}</div>
                    <div class="timeline-description">${descricao}</div>
                </div>
            `;
            
            timeline.insertBefore(novoItem, timeline.firstChild);
            
            // Animação de entrada
            novoItem.style.opacity = '0';
            novoItem.style.transform = 'translateX(-20px)';
            setTimeout(() => {
                novoItem.style.transition = 'all 0.3s ease';
                novoItem.style.opacity = '1';
                novoItem.style.transform = 'translateX(0)';
            }, 100);
        }

        // Handle do formulário de cumprimento
        document.querySelector('#cumprimentoModal form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Simular envio
            const loading = document.createElement('div');
            loading.innerHTML = 'Registrando cumprimento...';
            loading.style.cssText = 'position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: #3f51b5; color: white; padding: 20px; border-radius: 4px; z-index: 2000;';
            document.body.appendChild(loading);
            
            setTimeout(() => {
                document.body.removeChild(loading);
                closeCumprimentoModal();
                
                // Atualizar status
                const statusBadge = document.querySelector('.status-badge');
                statusBadge.className = 'status-badge status-cumprida';
                statusBadge.innerHTML = `
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Sentença Cumprida
                `;
                
                // Remover alerta de prazo
                const alertPrazo = document.querySelector('.alert-warning');
                if (alertPrazo) {
                    alertPrazo.style.display = 'none';
                }
                
                // Adicionar ao histórico
                adicionarHistorico('Cumprimento Registrado', 'Pagamento de R$ 15.000,00 realizado via PIX. Comprovante anexado.');
                
                // Mostrar mensagem de sucesso
                const successMsg = document.createElement('div');
                successMsg.className = 'alert';
                successMsg.style.cssText = 'background-color: #e8f5e9; color: #2e7d32; border-left: 4px solid #4caf50;';
                successMsg.innerHTML = `
                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <span>Cumprimento da sentença registrado com sucesso!</span>
                `;
                
                const caseHeader = document.querySelector('.case-header');
                caseHeader.parentNode.insertBefore(successMsg, caseHeader.nextSibling);
                
                setTimeout(() => {
                    successMsg.style.transition = 'opacity 0.5s ease';
                    successMsg.style.opacity = '0';
                    setTimeout(() => successMsg.remove(), 500);
                }, 5000);
                
            }, 2000);
        });

        // Atalhos de teclado
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + P para imprimir
            if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                e.preventDefault();
                window.print();
            }
            
            // Escape para fechar modais
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal').forEach(modal => {
                    modal.style.display = 'none';
                });
            }
        });

        // Inicialização
        document.addEventListener('DOMContentLoaded', function() {
            atualizarValores();
            verificarPrazo();
            
            // Marcar como visualizada (se for a primeira vez)
            const visualizada = sessionStorage.getItem('sentenca_ARB-2024-001234_visualizada');
            if (!visualizada) {
                sessionStorage.setItem('sentenca_ARB-2024-001234_visualizada', 'true');
                
                // Simular registro de visualização
                setTimeout(() => {
                    console.log('Registrando visualização da sentença...');
                }, 1000);
            }
            
            // Smooth scroll para seções
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });
        });

        // Função para gerar link de compartilhamento seguro
        function gerarLinkCompartilhamento() {
            // Em produção, isso geraria um token único no servidor
            const token = btoa('ARB-2024-001234-' + Date.now());
            return `https://arbitrivm.com.br/sentenca/publico/${token}`;
        }

        // Analytics básico
        function trackEvent(action, category, label) {
            // Em produção, integraria com Google Analytics ou similar
            console.log('Event tracked:', { action, category, label });
        }

        // Rastrear ações importantes
        document.querySelector('.btn-primary').addEventListener('click', () => {
            trackEvent('download', 'sentenca', 'ARB-2024-001234');
        });
    </script>
</body>
</html>