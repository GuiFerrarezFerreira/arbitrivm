<?php
session_start();
require_once 'config/database.php';
require_once 'includes/auth_check.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Verificar se foram passados os parâmetros necessários
if (!isset($_GET['disputa_id']) || !isset($_GET['arbitro_id'])) {
    $_SESSION['error'] = "Parâmetros inválidos para avaliação.";
    header('Location: minhas-disputas.php');
    exit();
}

$disputa_id = intval($_GET['disputa_id']);
$arbitro_id = intval($_GET['arbitro_id']);
$user_id = $_SESSION['user_id'];

// Verificar se o usuário é parte da disputa
$sql_check = "SELECT d.*, u.nome as arbitro_nome, u.email as arbitro_email,
              CASE 
                WHEN d.reclamante_id = ? THEN 'reclamante'
                WHEN d.reclamado_id = ? THEN 'reclamado'
                ELSE NULL
              END as tipo_parte
              FROM disputas d
              INNER JOIN usuarios u ON u.id = d.arbitro_id
              WHERE d.id = ? AND d.arbitro_id = ? 
              AND d.status = 'concluida'
              AND (d.reclamante_id = ? OR d.reclamado_id = ?)";

$stmt = $conn->prepare($sql_check);
$stmt->bind_param("iiiiii", $user_id, $user_id, $disputa_id, $arbitro_id, $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    $_SESSION['error'] = "Você não tem permissão para avaliar este árbitro ou a disputa ainda não foi concluída.";
    header('Location: minhas-disputas.php');
    exit();
}

$disputa = $result->fetch_assoc();
$tipo_parte = $disputa['tipo_parte'];

// Verificar se já existe uma avaliação
$sql_avaliacao = "SELECT * FROM avaliacoes_arbitros 
                  WHERE disputa_id = ? AND arbitro_id = ? AND avaliador_id = ?";
$stmt_check = $conn->prepare($sql_avaliacao);
$stmt_check->bind_param("iii", $disputa_id, $arbitro_id, $user_id);
$stmt_check->execute();
$result_check = $stmt_check->get_result();

if ($result_check->num_rows > 0) {
    $_SESSION['error'] = "Você já avaliou este árbitro para esta disputa.";
    header('Location: minhas-disputas.php');
    exit();
}

// Processar formulário de avaliação
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nota = intval($_POST['nota']);
    $comentario = trim($_POST['comentario']);
    
    // Validar nota
    if ($nota < 1 || $nota > 5) {
        $error = "A nota deve ser entre 1 e 5.";
    } else {
        // Inserir avaliação
        $sql_insert = "INSERT INTO avaliacoes_arbitros 
                       (disputa_id, arbitro_id, avaliador_id, tipo_avaliador, nota, comentario, data_avaliacao) 
                       VALUES (?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt_insert = $conn->prepare($sql_insert);
        $stmt_insert->bind_param("iiisis", $disputa_id, $arbitro_id, $user_id, $tipo_parte, $nota, $comentario);
        
        if ($stmt_insert->execute()) {
            // Atualizar estatísticas do árbitro
            $sql_update = "UPDATE arbitros_perfil 
                           SET 
                               total_avaliacoes = total_avaliacoes + 1,
                               soma_notas = soma_notas + ?,
                               nota_media = (soma_notas + ?) / (total_avaliacoes + 1)
                           WHERE usuario_id = ?";
            
            $stmt_update = $conn->prepare($sql_update);
            $stmt_update->bind_param("iii", $nota, $nota, $arbitro_id);
            $stmt_update->execute();
            
            $_SESSION['success'] = "Avaliação enviada com sucesso!";
            header('Location: minhas-disputas.php');
            exit();
        } else {
            $error = "Erro ao enviar avaliação. Tente novamente.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Avaliar Árbitro - Arbitrivm</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .rating-container {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 30px;
            margin-top: 20px;
        }
        
        .star-rating {
            font-size: 2rem;
            color: #ddd;
            cursor: pointer;
            transition: color 0.2s;
        }
        
        .star-rating .bi-star-fill {
            color: #ffc107;
        }
        
        .star-rating i:hover,
        .star-rating i.hover {
            color: #ffc107;
        }
        
        .arbitro-info {
            background-color: #e9ecef;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .disputa-info {
            background-color: #fff;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .btn-submit {
            background-color: #0d6efd;
            color: white;
            padding: 10px 30px;
            font-weight: 500;
        }
        
        .btn-submit:hover {
            background-color: #0b5ed7;
            color: white;
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <h2 class="mb-4"><i class="bi bi-star"></i> Avaliar Árbitro</h2>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Informações do Árbitro -->
                <div class="arbitro-info">
                    <h5>Árbitro</h5>
                    <p class="mb-1"><strong><?php echo htmlspecialchars($disputa['arbitro_nome']); ?></strong></p>
                    <p class="text-muted mb-0"><?php echo htmlspecialchars($disputa['arbitro_email']); ?></p>
                </div>
                
                <!-- Informações da Disputa -->
                <div class="disputa-info">
                    <h6>Disputa #<?php echo $disputa_id; ?></h6>
                    <p class="mb-1"><strong>Tipo:</strong> <?php echo htmlspecialchars($disputa['tipo_disputa']); ?></p>
                    <p class="mb-0"><strong>Data de conclusão:</strong> <?php echo date('d/m/Y', strtotime($disputa['data_conclusao'])); ?></p>
                </div>
                
                <!-- Formulário de Avaliação -->
                <div class="rating-container">
                    <form method="POST" action="">
                        <div class="mb-4">
                            <label class="form-label"><strong>Como você avalia o desempenho do árbitro?</strong></label>
                            <div class="star-rating" id="starRating">
                                <i class="bi bi-star" data-rating="1"></i>
                                <i class="bi bi-star" data-rating="2"></i>
                                <i class="bi bi-star" data-rating="3"></i>
                                <i class="bi bi-star" data-rating="4"></i>
                                <i class="bi bi-star" data-rating="5"></i>
                            </div>
                            <input type="hidden" name="nota" id="nota" value="0" required>
                            <small class="text-muted d-block mt-2">Clique nas estrelas para avaliar (1 a 5)</small>
                        </div>
                        
                        <div class="mb-4">
                            <label for="comentario" class="form-label"><strong>Comentários (opcional)</strong></label>
                            <textarea class="form-control" id="comentario" name="comentario" rows="4" 
                                      placeholder="Compartilhe sua experiência com este árbitro..."></textarea>
                            <small class="text-muted">Seus comentários ajudam outros usuários e o árbitro a melhorar</small>
                        </div>
                        
                        <div class="alert alert-info" role="alert">
                            <i class="bi bi-info-circle"></i> 
                            <strong>Importante:</strong> Você só pode avaliar o árbitro uma vez por disputa. 
                            Sua avaliação será mantida em sigilo e contribuirá para a reputação do árbitro na plataforma.
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="minhas-disputas.php" class="btn btn-secondary">
                                <i class="bi bi-arrow-left"></i> Voltar
                            </a>
                            <button type="submit" class="btn btn-submit" id="btnSubmit" disabled>
                                <i class="bi bi-send"></i> Enviar Avaliação
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sistema de avaliação por estrelas
        const stars = document.querySelectorAll('.star-rating i');
        const notaInput = document.getElementById('nota');
        const btnSubmit = document.getElementById('btnSubmit');
        
        stars.forEach((star, index) => {
            star.addEventListener('click', () => {
                const rating = index + 1;
                notaInput.value = rating;
                updateStars(rating);
                btnSubmit.disabled = false;
            });
            
            star.addEventListener('mouseenter', () => {
                const rating = index + 1;
                updateHover(rating);
            });
        });
        
        document.getElementById('starRating').addEventListener('mouseleave', () => {
            const currentRating = parseInt(notaInput.value);
            if (currentRating > 0) {
                updateStars(currentRating);
            } else {
                clearStars();
            }
        });
        
        function updateStars(rating) {
            stars.forEach((star, index) => {
                if (index < rating) {
                    star.classList.remove('bi-star');
                    star.classList.add('bi-star-fill');
                } else {
                    star.classList.remove('bi-star-fill');
                    star.classList.add('bi-star');
                }
            });
        }
        
        function updateHover(rating) {
            stars.forEach((star, index) => {
                if (index < rating) {
                    star.classList.add('hover');
                } else {
                    star.classList.remove('hover');
                }
            });
        }
        
        function clearStars() {
            stars.forEach(star => {
                star.classList.remove('bi-star-fill', 'hover');
                star.classList.add('bi-star');
            });
        }
        
        // Validação do formulário
        document.querySelector('form').addEventListener('submit', (e) => {
            const nota = parseInt(notaInput.value);
            if (nota < 1 || nota > 5) {
                e.preventDefault();
                alert('Por favor, selecione uma nota de 1 a 5 estrelas.');
            }
        });
    </script>
</body>
</html>