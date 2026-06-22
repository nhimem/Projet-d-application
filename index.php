<?php 
require_once __DIR__ . '/config/database.php';

$divisions_tours = [];
$error = null;

try {
    // PATCH ULTIME : Supprime automatiquement les données invalides et les compétitions sans match
    $sql = "SELECT DISTINCT
                d.DIV_ID, d.DIV_LB, e.EPRV_LB, 
                t.TOUR_ID, t.TOUR_LB 
            FROM DIVISION d 
            LEFT JOIN EPREUVE e ON d.EPRV_ID = e.EPRV_ID 
            INNER JOIN TOUR t ON d.DIV_ID = t.DIV_ID 
            WHERE d.DIV_LB IS NOT NULL 
              AND TRIM(d.DIV_LB) != '' 
              -- Supprime toutes les variantes d'Inconnu/Inconnue
              AND UPPER(d.DIV_LB) NOT LIKE '%INCONNU%'
              AND UPPER(t.TOUR_LB) NOT LIKE '%INCONNU%'
              -- Condition obligatoire : Doit avoir au moins 1 match (Tableau) tiré au sort
              AND EXISTS (
                  SELECT 1 
                  FROM TABLEAU tab
                  JOIN NIVEAU niv ON tab.TAB_ID = niv.TAB_ID
                  JOIN TABLEAU_PARTIE tp ON niv.NIV_ID = tp.NIV_ID
                  WHERE tab.TOUR_ID = t.TOUR_ID
              )
            ORDER BY e.EPRV_LB ASC, d.DIV_LB ASC, t.TOUR_ID ASC";
            
    $stmt = $pdo->query($sql);
    $results = $stmt->fetchAll();

    foreach ($results as $row) {
        $div_id = $row['DIV_ID'];
        
        // Deuxième barrière de sécurité en PHP au cas où des données invalides passeraient SQL
        $div_lb = strtoupper(trim((string)$row['DIV_LB']));
        $tour_lb = strtoupper(trim((string)$row['TOUR_LB']));
        if (strpos($div_lb, 'INCONNU') !== false || strpos($tour_lb, 'INCONNU') !== false) {
            continue; 
        }

        if (!isset($divisions_tours[$div_id])) {
            $divisions_tours[$div_id] = [
                'DIV_LB' => $row['DIV_LB'],
                'EPRV_LB' => $row['EPRV_LB'],
                'tours' => []
            ];
        }
        
        // Ajoute le Tour au tableau
        if (!empty($row['TOUR_ID'])) {
            $divisions_tours[$div_id]['tours'][$row['TOUR_ID']] = [
                'TOUR_ID' => $row['TOUR_ID'],
                'TOUR_LB' => $row['TOUR_LB']
            ];
        }
    }
} catch (\PDOException $e) { 
    // [PATCH DE SÉCURITÉ] : Enregistre l'erreur dans le log du serveur (Apache error.log) pour le dev
    error_log("Database Error in index.php: " . $e->getMessage());
    
    // Affiche uniquement un message générique, sécurisé pour l'utilisateur final
    $error = "Le système est actuellement en maintenance ou rencontre un problème de connexion. Veuillez réessayer plus tard !"; 
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SPIDD V2 - Compétitions FFTT</title>
    <link rel="stylesheet" href="assets/css/global.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/loading.css"> 
    <link rel="icon" type="image/jpeg" href="assets/images/logo_insa.png">
</head>
<body>

    <div id="preloader">
        <div class="spinner"></div>
        <div class="loading-text">CHARGEMENT...</div>
    </div>

    <header class="navbar">
        <div class="navbar-brand" style="display: flex !important; flex-direction: row !important; flex-wrap: nowrap !important; align-items: center !important; gap: 20px;">
            <img src="assets/images/logo-fftt.jpg" alt="FFTT Logo" style="height: 55px; width: auto !important; max-width: none !important; display: inline-block !important;">
            <img src="assets/images/logo-INSA-CVL.jpg" alt="INSA Logo" style="height: 45px; width: auto !important; max-width: none !important; display: inline-block !important;">
        </div>
    </header>

    <div class="hero-banner">
        <div class="hero-floating-box">
            Toutes les infos sur les championnats de France 2026
        </div>
    </div>

    <main class="container">
        <h2 class="section-header">Gestion des Compétitions</h2>
        
        <?php if (!empty($error)): ?>
            <div style="color:red; margin-bottom:20px; font-weight:bold; padding: 15px; background-color: #fee2e2; border-radius: 5px;">
                ⚠️ <?= htmlspecialchars($error) ?>
            </div>
        <?php else: ?>
            <div class="dashboard-grid">
                <?php foreach ($divisions_tours as $div_id => $div): ?>
                    <?php 
                    // Si aucun Tour ne passe la vérification, cette Division sera masquée
                    if (empty($div['tours'])) continue; 
                    ?>
                    
                    <div class="epreuve-card">
                        <div>
                            <h3><?= htmlspecialchars($div['DIV_LB']) ?></h3>
                            <div class="epreuve-badge">
                                <?= htmlspecialchars($div['EPRV_LB'] ?? 'Non défini') ?>
                            </div>
                        </div>
                        
                        <div class="action-buttons" style="display: flex; flex-wrap: wrap; gap: 10px; margin-top: 15px;">
                            <?php foreach ($div['tours'] as $tour): ?>
                                <a href="poules?div_id=<?= urlencode($div_id) ?>&tour_id=<?= urlencode($tour['TOUR_ID']) ?>" class="btn btn-primary" style="padding: 8px 12px; font-size: 14px;">
                                    <?= htmlspecialchars($tour['TOUR_LB']) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <div id="network-toast">
        ⚠️ <span>Connexion perdue. Tentative de reconnexion...</span>
    </div>

    <script src="assets/js/app.js"></script>

</body>
</html>