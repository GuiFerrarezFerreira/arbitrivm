<?php
require_once 'config.php';

// ID do árbitro pode vir via GET ou da sessão se for o próprio árbitro
$arbitroId = null;
if (isset($_GET['id'])) {
    $arbitroId = intval($_GET['id']);
} elseif (isLoggedIn() && $_SESSION['user_type'] === 'arbitro' && isset($_SESSION['arbitro_id'])) {
    $arbitroId = $_SESSION['arbitro_id'];
}

if (!$arbitroId) {
    header("Location: index.php");
    exit();
}

$db = getDBConnection();

// Buscar informações do árbitro
$stmt = $db->prepare("
    SELECT a.*, u.nome_completo, u.email, u.telefone, u.data_cadastro, u.ativo
    FROM arbitros a
    JOIN usuarios u ON a.usuario_id = u.id
    WHERE a.id = ?
");
$stmt->execute([$arbitroId]);
$arbitro = $stmt->fetch();

if (!$arbitro) {
    header("Location: index.php");
    exit();
}

// Buscar especializações
$stmt = $db->prepare("
    SELECT especializacao FROM arbitro_especializacoes 
    WHERE arbitro_id = ? 
    ORDER BY 
        CASE especializacao 
            WHEN 'imobiliario_geral' THEN 1 
            WHEN 'locacoes' THEN 2 
            WHEN 'disputas_condominiais' THEN 3 
            ELSE 4 
        END
");
$stmt->execute([$arbitroId]);
$especializacoes = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Buscar estatísticas
$stmt = $db->prepare("
    SELECT 
        COUNT(DISTINCT d.id) as total_casos,
        COUNT(DISTINCT CASE WHEN d.status = 'finalizada' THEN d.id END) as casos_finalizados,
        COUNT(DISTINCT CASE WHEN d.status = 'em_andamento' THEN d.id END) as casos_andamento,
        AVG(CASE WHEN d.status = 'finalizada' THEN DATEDIFF(d.data_finalizacao, d.data_inicio) END) as tempo_medio,
        COUNT(DISTINCT CASE WHEN d.data_abertura >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN d.id END) as casos_mes
    FROM disputas d
    WHERE d.arbitro_id = ?
");
$stmt->execute([$arbitroId]);
$stats = $stmt->fetch();

// Buscar avaliações
$stmt = $db->prepare("
    SELECT av.*, u.nome_completo as avaliador_nome, d.codigo_caso
    FROM avaliacoes av
    JOIN usuarios u ON av.avaliador_id = u.id
    JOIN disputas d ON av.disputa_id = d.id
    WHERE av.arbitro_id = ?
    ORDER BY av.data_avaliacao DESC
    LIMIT 10
");
$stmt->execute([$arbitroId]);
$avaliacoes = $stmt->fetchAll();

// Calcular média de avaliações
$stmt = $db->prepare("
    SELECT 
        AVG(nota) as media_geral,
        COUNT(*) as total_avaliacoes,
        SUM(CASE WHEN nota = 5 THEN 1 ELSE 0 END) as cinco_estrelas,
        SUM(CASE WHEN nota = 4 THEN 1 ELSE 0 END) as quatro_estrelas,
        SUM(CASE WHEN nota = 3 THEN 1 ELSE 0 END) as tres_estrelas,
        SUM(CASE WHEN nota = 2 THEN 1 ELSE 0 END) as duas_estrelas,
        SUM(CASE WHEN nota = 1 THEN 1 ELSE 0 END) as uma_estrela
    FROM avaliacoes
    WHERE arbitro_id = ?
");
$stmt->execute([$arbitroId]);
$statsAvaliacoes = $stmt->fetch();

// Buscar tipos de casos mais frequentes
$stmt = $db->prepare("
    SELECT td.nome, COUNT(d.id) as quantidade
    FROM disputas d
    JOIN tipos_disputa td ON d.tipo_disputa_id = td.id
    WHERE d.arbitro_id = ? AND d.status = 'finalizada'
    GROUP BY td.id, td.nome
    ORDER BY quantidade DESC
    LIMIT 5
");
$stmt->execute([$arbitroId]);
$tiposCasos = $stmt->fetchAll();

// Verificar se é o próprio árbitro visualizando
$isOwnProfile = isLoggedIn() && $_SESSION['user_type'] === 'arbitro' && $_SESSION['arbitro_id'] == $arbitroId;

// Nomes das especializações
$nomesEspecializacoes = [
    'locacoes' => 'Locações',
    'disputas_condominiais' => 'Disputas Condominiais',
    'danos' => 'Danos ao Imóvel',
    'infracoes' => 'Infrações',
    'imobiliario_geral' => 'Imobiliário Geral'
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($arbitro['nome_completo']); ?> - Árbitro | Arbitrivm</title>
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
        
        /* Profile Header */
        .profile-header {
            background: linear-gradient(135deg, #1a365d 0%, #2b6cb0 100%);
            color: white;
            padding: 60px 0;
            margin-bottom: -40px;
        }
        
        .profile-header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .profile-top {
            display: flex;
            align-items: center;
            gap: 40px;
            margin-bottom: 30px;
        }
        
        .profile-avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background-color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4rem;
            font-weight: 600;
            color: #1a365d;
            border: 5px solid white;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            flex-shrink: 0;
        }
        
        .profile-info {
            flex: 1;
        }
        
        .profile-name {
            font-size: 2.5rem;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .premium-badge {
            background-color: #fbbf24;
            color: #92400e;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
        }
        
        .profile-title {
            font-size: 1.25rem;
            opacity: 0.9;
            margin-bottom: 20px;
        }
        
        .profile-meta {
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
            font-size: 1rem;
            opacity: 0.9;
        }
        
        .profile-meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        /* Main Container */
        .main-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* Stats Cards */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
            margin-top: 40px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            text-align: center;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            background-color: #ebf8ff;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 1.5rem;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #1a365d;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #718096;
            font-size: 0.95rem;
        }
        
        /* Content Sections */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }
        
        .card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }
        
        .card-title {
            font-size: 1.5rem;
            color: #1a365d;
            margin-bottom: 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        /* Especializações */
        .especializacoes-grid {
            display: grid;
            gap: 15px;
        }
        
        .especializacao-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 20px;
            background-color: #f7fafc;
            border-radius: 8px;
            border-left: 4px solid #2b6cb0;
        }
        
        .especializacao-icon {
            font-size: 1.5rem;
        }
        
        .especializacao-info {
            flex: 1;
        }
        
        .especializacao-nome {
            font-weight: 600;
            color: #1a365d;
            margin-bottom: 3px;
        }
        
        .especializacao-desc {
            font-size: 0.9rem;
            color: #718096;
        }
        
        /* Biografia */
        .biografia {
            line-height: 1.8;
            color: #4a5568;
        }
        
        /* Avaliações */
        .rating-overview {
            display: flex;
            align-items: center;
            gap: 30px;
            padding: 20px;
            background-color: #f7fafc;
            border-radius: 8px;
            margin-bottom: 25px;
        }
        
        .rating-score {
            text-align: center;
            padding-right: 30px;
            border-right: 1px solid #e2e8f0;
        }
        
        .rating-number {
            font-size: 3rem;
            font-weight: 700;
            color: #1a365d;
            line-height: 1;
        }
        
        .rating-stars {
            color: #f6ad55;
            font-size: 1.25rem;
            margin: 10px 0;
        }
        
        .rating-count {
            color: #718096;
            font-size: 0.9rem;
        }
        
        .rating-bars {
            flex: 1;
        }
        
        .rating-bar-item {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
        }
        
        .rating-bar-label {
            width: 60px;
            font-size: 0.9rem;
            color: #4a5568;
        }
        
        .rating-bar {
            flex: 1;
            height: 8px;
            background-color: #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .rating-bar-fill {
            height: 100%;
            background-color: #f6ad55;
            transition: width 0.3s ease;
        }
        
        .rating-bar-count {
            width: 30px;
            text-align: right;
            font-size: 0.85rem;
            color: #718096;
        }
        
        /* Avaliações Lista */
        .avaliacao-item {
            padding: 20px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .avaliacao-item:last-child {
            border-bottom: none;
        }
        
        .avaliacao-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 10px;
        }
        
        .avaliador-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .avaliador-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: #4a5568;
        }
        
        .avaliador-nome {
            font-weight: 600;
            color: #1a365d;
        }
        
        .avaliacao-data {
            font-size: 0.85rem;
            color: #718096;
        }
        
        .avaliacao-stars {
            color: #f6ad55;
        }
        
        .avaliacao-texto {
            color: #4a5568;
            line-height: 1.6;
            margin-top: 10px;
        }
        
        .avaliacao-caso {
            font-size: 0.85rem;
            color: #718096;
            margin-top: 8px;
        }
        
        /* Tipos de Casos */
        .casos-chart {
            padding: 20px 0;
        }
        
        .caso-tipo-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .caso-tipo-item:last-child {
            border-bottom: none;
        }
        
        .caso-tipo-nome {
            font-weight: 500;
            color: #4a5568;
        }
        
        .caso-tipo-count {
            background-color: #ebf8ff;
            color: #2b6cb0;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
        }
        
        /* Badges */
        .badges-list {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 500;
        }
        
        .badge-pos {
            background-color: #ddd6fe;
            color: #6b21a8;
        }
        
        .badge-exp {
            background-color: #d1fae5;
            color: #065f46;
        }
        
        .badge-premium {
            background-color: #fef3c7;
            color: #92400e;
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
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #718096;
        }
        
        .contact-info {
            background-color: #f7fafc;
            border-radius: 8px;
            padding: 20px;
        }
        
        .contact-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 0;
        }
        
        .contact-icon {
            width: 40px;
            height: 40px;
            background-color: #e2e8f0;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #4a5568;
        }
        
        @media (max-width: 968px) {
            .profile-top {
                flex-direction: column;
                text-align: center;
            }
            
            .profile-name {
                justify-content: center;
            }
            
            .profile-meta {
                justify-content: center;
            }
            
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .rating-overview {
                flex-direction: column;
            }
            
            .rating-score {
                border-right: none;
                border-bottom: 1px solid #e2e8f0;
                padding-right: 0;
                padding-bottom: 20px;
                margin-bottom: 20px;
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
                    <?php if (isLoggedIn()): ?>
                        <li><a href="index.php">Dashboard</a></li>
                        <li><a href="disputas.php">Disputas</a></li>
                        <?php if ($isOwnProfile): ?>
                            <li><a href="perfil.php">Meu Perfil</a></li>
                        <?php endif; ?>
                        <li><a href="logout.php">Sair</a></li>
                    <?php else: ?>
                        <li><a href="login.php">Login</a></li>
                        <li><a href="register.php">Cadastro</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>
    
    <!-- Profile Header -->
    <div class="profile-header">
        <div class="profile-header-content">
            <div class="profile-top">
                <div class="profile-avatar">
                    <?php 
                    if ($arbitro['foto_perfil']) {
                        echo '<img src="' . htmlspecialchars($arbitro['foto_perfil']) . '" alt="Foto" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">';
                    } else {
                        echo strtoupper(substr($arbitro['nome_completo'], 0, 1));
                    }
                    ?>
                </div>
                <div class="profile-info">
                    <h1 class="profile-name">
                        <?php echo htmlspecialchars($arbitro['nome_completo']); ?>
                        <?php if ($arbitro['perfil_premium']): ?>
                            <span class="premium-badge">⭐ Premium</span>
                        <?php endif; ?>
                    </h1>
                    <p class="profile-title">Árbitro Especializado em Direito Imobiliário</p>
                    <div class="profile-meta">
                        <div class="profile-meta-item">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                            </svg>
                            OAB <?php echo htmlspecialchars($arbitro['oab_numero'] . '/' . $arbitro['oab_estado']); ?>
                        </div>
                        <div class="profile-meta-item">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <?php echo $arbitro['experiencia_anos']; ?> anos de experiência
                        </div>
                        <div class="profile-meta-item">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Membro desde <?php echo date('Y', strtotime($arbitro['data_cadastro'])); ?>
                        </div>
                    </div>
                </div>
                
                <!-- Especializações -->
                <div class="card">
                    <h3 class="card-title">Especializações</h3>
                    <div class="especializacoes-grid">
                        <?php if (empty($especializacoes)): ?>
                            <p style="color: #718096;">Nenhuma especialização cadastrada.</p>
                        <?php else: ?>
                            <?php foreach ($especializacoes as $esp): ?>
                                <div class="especializacao-item">
                                    <div class="especializacao-icon">
                                        <?php
                                        $icones = [
                                            'locacoes' => '🏠',
                                            'disputas_condominiais' => '🏢',
                                            'danos' => '🔨',
                                            'infracoes' => '⚠️',
                                            'imobiliario_geral' => '📋'
                                        ];
                                        echo $icones[$esp] ?? '📌';
                                        ?>
                                    </div>
                                    <div class="especializacao-info">
                                        <div class="especializacao-nome">
                                            <?php echo $nomesEspecializacoes[$esp] ?? $esp; ?>
                                        </div>
                                        <div class="especializacao-desc">
                                            <?php
                                            $descricoes = [
                                                'locacoes' => 'Conflitos de locação e aluguel',
                                                'disputas_condominiais' => 'Questões condominiais',
                                                'danos' => 'Danos e reparos em imóveis',
                                                'infracoes' => 'Infrações e multas',
                                                'imobiliario_geral' => 'Direito imobiliário amplo'
                                            ];
                                            echo $descricoes[$esp] ?? '';
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Tipos de Casos -->
                <?php if (!empty($tiposCasos)): ?>
                    <div class="card">
                        <h3 class="card-title">Experiência por Tipo</h3>
                        <div class="casos-chart">
                            <?php foreach ($tiposCasos as $tipo): ?>
                                <div class="caso-tipo-item">
                                    <span class="caso-tipo-nome"><?php echo htmlspecialchars($tipo['nome']); ?></span>
                                    <span class="caso-tipo-count"><?php echo $tipo['quantidade']; ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Contato -->
                <?php if (isLoggedIn() && !$isOwnProfile): ?>
                    <div class="card">
                        <h3 class="card-title">Contato</h3>
                        <div class="contact-info">
                            <div class="contact-item">
                                <div class="contact-icon">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                    </svg>
                                </div>
                                <div>
                                    <div style="font-weight: 500; color: #1a365d; margin-bottom: 3px;">Email</div>
                                    <div style="color: #718096; font-size: 0.95rem;">
                                        <?php echo htmlspecialchars($arbitro['email']); ?>
                                    </div>
                                </div>
                            </div>
                            <?php if ($arbitro['telefone']): ?>
                                <div class="contact-item">
                                    <div class="contact-icon">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <div style="font-weight: 500; color: #1a365d; margin-bottom: 3px;">Telefone</div>
                                        <div style="color: #718096; font-size: 0.95rem;">
                                            <?php echo htmlspecialchars($arbitro['telefone']); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Taxa Horária -->
                <?php if ($arbitro['taxa_hora'] > 0): ?>
                    <div class="card">
                        <h3 class="card-title">Honorários</h3>
                        <div style="text-align: center; padding: 20px 0;">
                            <div style="font-size: 2rem; font-weight: 700; color: #1a365d; margin-bottom: 5px;">
                                <?php echo formatMoney($arbitro['taxa_hora']); ?>
                            </div>
                            <div style="color: #718096;">por hora</div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>
            </div>
        </div>
    </div>
    
    <main class="main-container">
        <!-- Estatísticas -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-icon">📊</div>
                <div class="stat-value"><?php echo $stats['total_casos'] ?: 0; ?></div>
                <div class="stat-label">Casos Arbitrados</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-value">
                    <?php 
                    echo $stats['total_casos'] > 0 
                        ? round(($stats['casos_finalizados'] / $stats['total_casos']) * 100) 
                        : 0;
                    ?>%
                </div>
                <div class="stat-label">Taxa de Resolução</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">⏱️</div>
                <div class="stat-value"><?php echo round($stats['tempo_medio'] ?: 0); ?> dias</div>
                <div class="stat-label">Tempo Médio</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">⭐</div>
                <div class="stat-value">
                    <?php echo $statsAvaliacoes['media_geral'] ? number_format($statsAvaliacoes['media_geral'], 1) : 'N/A'; ?>
                </div>
                <div class="stat-label">Avaliação Média</div>
            </div>
        </div>
        
        <div class="content-grid">
            <!-- Coluna Principal -->
            <div class="main-content">
                <!-- Biografia -->
                <?php if ($arbitro['biografia']): ?>
                    <div class="card">
                        <h2 class="card-title">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                            </svg>
                            Sobre
                        </h2>
                        <div class="biografia">
                            <?php echo nl2br(htmlspecialchars($arbitro['biografia'])); ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Avaliações -->
                <div class="card">
                    <h2 class="card-title">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                        </svg>
                        Avaliações
                    </h2>
                    
                    <?php if ($statsAvaliacoes['total_avaliacoes'] > 0): ?>
                        <div class="rating-overview">
                            <div class="rating-score">
                                <div class="rating-number">
                                    <?php echo number_format($statsAvaliacoes['media_geral'], 1); ?>
                                </div>
                                <div class="rating-stars">
                                    <?php 
                                    $fullStars = floor($statsAvaliacoes['media_geral']);
                                    $halfStar = $statsAvaliacoes['media_geral'] - $fullStars >= 0.5;
                                    
                                    for ($i = 0; $i < $fullStars; $i++) echo '★';
                                    if ($halfStar) echo '☆';
                                    for ($i = ceil($statsAvaliacoes['media_geral']); $i < 5; $i++) echo '☆';
                                    ?>
                                </div>
                                <div class="rating-count">
                                    <?php echo $statsAvaliacoes['total_avaliacoes']; ?> avaliações
                                </div>
                            </div>
                            
                            <div class="rating-bars">
                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                    <div class="rating-bar-item">
                                        <div class="rating-bar-label"><?php echo $i; ?> ★</div>
                                        <div class="rating-bar">
                                            <div class="rating-bar-fill" style="width: <?php 
                                                $count = $statsAvaliacoes[($i == 5 ? 'cinco' : ($i == 4 ? 'quatro' : ($i == 3 ? 'tres' : ($i == 2 ? 'duas' : 'uma')))) . '_estrelas'];
                                                echo $statsAvaliacoes['total_avaliacoes'] > 0 ? ($count / $statsAvaliacoes['total_avaliacoes'] * 100) : 0;
                                            ?>%"></div>
                                        </div>
                                        <div class="rating-bar-count">
                                            <?php echo $statsAvaliacoes[($i == 5 ? 'cinco' : ($i == 4 ? 'quatro' : ($i == 3 ? 'tres' : ($i == 2 ? 'duas' : 'uma')))) . '_estrelas']; ?>
                                        </div>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                        
                        <?php if (!empty($avaliacoes)): ?>
                            <div class="avaliacoes-list">
                                <?php foreach ($avaliacoes as $avaliacao): ?>
                                    <div class="avaliacao-item">
                                        <div class="avaliacao-header">
                                            <div class="avaliador-info">
                                                <div class="avaliador-avatar">
                                                    <?php echo strtoupper(substr($avaliacao['avaliador_nome'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <div class="avaliador-nome">
                                                        <?php echo htmlspecialchars($avaliacao['avaliador_nome']); ?>
                                                    </div>
                                                    <div class="avaliacao-data">
                                                        <?php echo formatDate($avaliacao['data_avaliacao']); ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="avaliacao-stars">
                                                <?php 
                                                for ($i = 0; $i < $avaliacao['nota']; $i++) echo '★';
                                                for ($i = $avaliacao['nota']; $i < 5; $i++) echo '☆';
                                                ?>
                                            </div>
                                        </div>
                                        <?php if ($avaliacao['comentario']): ?>
                                            <div class="avaliacao-texto">
                                                <?php echo nl2br(htmlspecialchars($avaliacao['comentario'])); ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="avaliacao-caso">
                                            Caso: <?php echo htmlspecialchars($avaliacao['codigo_caso']); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <p>Ainda não há avaliações disponíveis.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Sidebar -->
            <div class="sidebar">
                <!-- Qualificações -->
                <div class="card">
                    <h3 class="card-title">Qualificações</h3>
                    <div class="badges-list">
                        <?php if ($arbitro['pos_imobiliario']): ?>
                            <div class="badge badge-pos">
                                🎓 Pós em Direito Imobiliário
                            </div>
                        <?php endif; ?>
                        <?php if ($arbitro['experiencia_anos'] >= 10): ?>
                            <div class="badge badge-exp">
                                🏆 +10 anos de experiência
                            </div>
                        <?php endif; ?>
                        <?php if ($arbitro['perfil_premium']): ?>
                            <div class="badge badge-premium">
                                ⭐ Perfil Premium
                            </div>
                        <?php endif; ?>
                    </div>
                </div>