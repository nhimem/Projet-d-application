<?php
require_once __DIR__ . '/config/database.php';

// Récupération de l'ID de la division depuis l'URL (tour_id peut être null)
$div_id = $_GET['div_id'] ?? null;
$tour_id = $_GET['tour_id'] ?? null;

if (!$div_id) {
    die("<div style='padding:20px; color:red; font-weight:bold;'>Erreur : Code de la division (div_id) introuvable.</div>");
}

try {
    // 1. REQUÊTE DYNAMIQUE AVEC LA JONCTION STANDARD : POULE -> TABLEAU -> TOUR
    $sql_poules = "
        SELECT 
            e.EPRV_ID AS ID_Epreuve, e.EPRV_LB AS Nom_Epreuve,
            d.DIV_ID AS ID_Division, d.DIV_LB AS Nom_Division,
            t.TOUR_ID AS ID_Tour, t.TOUR_LB AS Nom_Tour,
            p.POUL_ID AS ID_Poule, p.POUL_LB AS Nom_Poule
        FROM POULE p
        JOIN DIVISION d ON p.DIV_ID = d.DIV_ID
        LEFT JOIN EPREUVE e ON d.EPRV_ID = e.EPRV_ID
        LEFT JOIN TABLEAU tab ON p.TAB_ID = tab.TAB_ID
        LEFT JOIN TOUR t ON tab.TOUR_ID = t.TOUR_ID
        WHERE d.DIV_ID = :div_id
    ";
    
    $params = ['div_id' => $div_id];

    if ($tour_id) {
        $sql_poules .= " AND t.TOUR_ID = :tour_id ";
        $params['tour_id'] = $tour_id;
    }

    $sql_poules .= " ORDER BY p.POUL_LB ASC";

    $stmt_poules = $pdo->prepare($sql_poules);
    $stmt_poules->execute($params);
    $liste_poules = $stmt_poules->fetchAll();

    $joueurs_par_poule = [];
    $matchs_par_poule = [];
    $classements_par_poule = [];

    if (!empty($liste_poules)) {
        $ids_poules = array_filter(array_column($liste_poules, 'ID_Poule'));
        
        if (!empty($ids_poules)) {
            // Protection absolue contre l'injection SQL avec une requête préparée pour la clause IN
            $requete_in = implode(',', array_fill(0, count($ids_poules), '?'));

            // Extraction de l'ensemble des joueurs liés aux poules de la division
            $sql_all_players = "
                SELECT 
                    i.POUL_ID, l.LIC_ID,
                    CONCAT(l.PERS_LB_NOM, ' ', l.PERS_LB_PRENOM) AS Nom_Prenom,
                    i.INSC_NB_DOSSARD AS Num_Dossard, l.LIC_NB_LICENCE AS Num_Licence,
                    tc.TCLST_CD AS Classement_Tech, CAST(l.LIC_NB_POINT AS UNSIGNED) AS Points_Cumules,
                    c.CLUB_NM AS Num_Club, c.CLUB_LB_COURT AS Nom_Court_Club,
                    cs.CAT_LB AS Cat_Age
                FROM INSCRIPTION i
                JOIN JOUEUR j ON i.JOUE_ID = j.JOUE_ID
                JOIN LICENCIE l ON j.LIC_ID = l.LIC_ID
                LEFT JOIN CLUB c ON l.CLUB_ID = c.CLUB_ID
                LEFT JOIN TYPE_CLASSEMENT tc ON l.TCLST_ID = tc.TCLST_ID
                LEFT JOIN CAT_SPORT cs ON l.CAT_SPORT_ID = cs.CAT_SPORT_ID
                WHERE i.POUL_ID IN ($requete_in)
            ";
            $stmt_tous_joueurs = $pdo->prepare($sql_all_players);
            $stmt_tous_joueurs->execute($ids_poules);
            foreach ($stmt_tous_joueurs->fetchAll() as $p) {
                $joueurs_par_poule[$p['POUL_ID']][] = $p;
            }

            // Extraction de l'ensemble des matchs et linéarisation des manches (S1 à S7 via Pivot SQL)
            $sql_all_matches = "
                SELECT 
                    tp.POUL_ID,
                    tp.TABPART_LB_PARTIE AS Position_Match, tp.TABPART_NB_TABLE AS Num_Table,
                    DATE_FORMAT(tp.TABPART_DT_HEURE, '%H:%i') AS Heure_Match,
                    p.PARTI_BL_FORFAIT AS Forfait, p.PARTI_BL_GAGNE1, p.PARTI_BL_GAGNE2,
                    CONCAT(l1.PERS_LB_NOM, ' ', l1.PERS_LB_PRENOM) AS DT1,
                    CONCAT(l2.PERS_LB_NOM, ' ', l2.PERS_LB_PRENOM) AS DT2,
                    p.PARTI_NB_POINT1 AS Pts_Match_Adv1, p.PARTI_NB_POINT2 AS Pts_Match_Adv2,
                    MAX(CASE WHEN m.MANCH_NB_RANG = 1 THEN CASE WHEN m.MANCH_NB_SCORE1 > m.MANCH_NB_SCORE2 THEN m.MANCH_NB_SCORE2 ELSE -m.MANCH_NB_SCORE1 END END) AS S1,
                    MAX(CASE WHEN m.MANCH_NB_RANG = 2 THEN CASE WHEN m.MANCH_NB_SCORE1 > m.MANCH_NB_SCORE2 THEN m.MANCH_NB_SCORE2 ELSE -m.MANCH_NB_SCORE1 END END) AS S2,
                    MAX(CASE WHEN m.MANCH_NB_RANG = 3 THEN CASE WHEN m.MANCH_NB_SCORE1 > m.MANCH_NB_SCORE2 THEN m.MANCH_NB_SCORE2 ELSE -m.MANCH_NB_SCORE1 END END) AS S3,
                    MAX(CASE WHEN m.MANCH_NB_RANG = 4 THEN CASE WHEN m.MANCH_NB_SCORE1 > m.MANCH_NB_SCORE2 THEN m.MANCH_NB_SCORE2 ELSE -m.MANCH_NB_SCORE1 END END) AS S4,
                    MAX(CASE WHEN m.MANCH_NB_RANG = 5 THEN CASE WHEN m.MANCH_NB_SCORE1 > m.MANCH_NB_SCORE2 THEN m.MANCH_NB_SCORE2 ELSE -m.MANCH_NB_SCORE1 END END) AS S5,
                    MAX(CASE WHEN m.MANCH_NB_RANG = 6 THEN CASE WHEN m.MANCH_NB_SCORE1 > m.MANCH_NB_SCORE2 THEN m.MANCH_NB_SCORE2 ELSE -m.MANCH_NB_SCORE1 END END) AS S6,
                    MAX(CASE WHEN m.MANCH_NB_RANG = 7 THEN CASE WHEN m.MANCH_NB_SCORE1 > m.MANCH_NB_SCORE2 THEN m.MANCH_NB_SCORE2 ELSE -m.MANCH_NB_SCORE1 END END) AS S7,
                    l1.LIC_ID AS ID_Adversaire1, l2.LIC_ID AS ID_Adversaire2
                FROM TABLEAU_PARTIE tp
                LEFT JOIN PARTIE p ON tp.PARTI_ID = p.PARTI_ID
                LEFT JOIN LICENCIE l1 ON p.LIC_ID = l1.LIC_ID
                LEFT JOIN LICENCIE l2 ON p.LIC_LIC_ID = l2.LIC_ID
                LEFT JOIN MANCHE m ON tp.TABPART_ID_TABLEAU = m.TABPART_ID_TABLEAU
                WHERE tp.POUL_ID IN ($requete_in)
                GROUP BY 
                    tp.POUL_ID, tp.TABPART_ID_TABLEAU, tp.TABPART_LB_PARTIE, tp.TABPART_NB_TABLE, tp.TABPART_DT_HEURE, 
                    p.PARTI_BL_FORFAIT, p.PARTI_BL_GAGNE1, p.PARTI_BL_GAGNE2, p.PARTI_NB_POINT1, p.PARTI_NB_POINT2,
                    l1.PERS_LB_NOM, l1.PERS_LB_PRENOM, l1.LIC_ID, l2.PERS_LB_NOM, l2.PERS_LB_PRENOM, l2.LIC_ID
            ";
            $stmt_tous_matchs = $pdo->prepare($sql_all_matches);
            $stmt_tous_matchs->execute($ids_poules);
            foreach ($stmt_tous_matchs->fetchAll() as $m) {
                $matchs_par_poule[$m['POUL_ID']][] = $m;
            }

            // Extraction des classements finaux consolidés des poules
            $sql_all_ranks = "
                SELECT 
                    c.POUL_ID, CONCAT(l.PERS_LB_NOM, ' ', l.PERS_LB_PRENOM) AS Joueur,
                    c.CLST_NB_RANG AS Rang,
                    CASE WHEN i.INSC_BL_ABSENT = 1 OR c.CLST_NB_FORFAITS > 0 THEN 1 ELSE 0 END AS Forfait
                FROM CLASSEMENT c
                JOIN LICENCIE l ON c.LIC_ID = l.LIC_ID
                LEFT JOIN JOUEUR j ON l.LIC_ID = j.LIC_ID
                LEFT JOIN INSCRIPTION i ON c.POUL_ID = i.POUL_ID AND j.JOUE_ID = i.JOUE_ID
                WHERE c.POUL_ID IN ($requete_in)
                ORDER BY c.CLST_NB_RANG ASC
            ";
            $stmt_tous_classements = $pdo->prepare($sql_all_ranks);
            $stmt_tous_classements->execute($ids_poules);
            foreach ($stmt_tous_classements->fetchAll() as $r) {
                $classements_par_poule[$r['POUL_ID']][] = $r;
            }
        }
    }
} catch (\PDOException $e) {
    // [PATCH DE SÉCURITÉ] : Enregistre l'erreur dans le log interne et protège l'utilisateur final
    error_log("Database Error in poules.php: " . $e->getMessage());
    die("<div style='padding:20px; color:red; font-weight:bold; background:#fee2e2; border-radius:5px;'>Le système est actuellement en maintenance ou rencontre un problème de connexion. Veuillez réessayer plus tard !</div>");
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails des Poules - SPIDD V2</title>
    <link rel="stylesheet" href="assets/css/global.css">
    <link rel="stylesheet" href="assets/css/poules.css">
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
        <nav class="navbar-menu">
            <a href="./"><b>←</b> Retour Accueil</a>
        </nav>
    </header>

    <main class="container">
        <?php if (empty($liste_poules)): ?>
            <div class="error-container" style="color: red; padding: 20px; background: #fee2e2; border-left: 5px solid #ef4444; border-radius: 6px;">
                ⚠️ Aucune donnée de poule disponible pour cette catégorie.
            </div>
            
            <div class="tabs-container" style="display: flex; gap: 15px; margin-top: 30px; border-bottom: 2px solid #e2e8f0;">
                <a href="poules?div_id=<?= urlencode($div_id) ?><?= $tour_id ? '&tour_id='.urlencode($tour_id) : '' ?>" 
                   style="padding: 10px 20px; font-weight: bold; color: var(--primary-blue); border-bottom: 3px solid var(--primary-blue); text-decoration: none;">
                    Poules & Matchs
                </a>
                <a href="tableau?div_id=<?= urlencode($div_id) ?><?= $tour_id ? '&tour_id='.urlencode($tour_id) : '' ?>" 
                   style="padding: 10px 20px; font-weight: bold; color: var(--text-gray); text-decoration: none;">
                    Tableau 
                </a>
            </div>

        <?php else: ?>
            
            <h2 class="section-header" style="font-size: 28px; margin-bottom: 10px;">
                <?= htmlspecialchars($liste_poules[0]['Nom_Epreuve'] ?? '') ?>
            </h2>
            <p style="font-size: 18px; color: var(--text-gray); margin-bottom: 20px; font-weight: bold;">
                <?= htmlspecialchars($liste_poules[0]['Nom_Division'] ?? '') ?> - 
                <?= !empty($liste_poules[0]['Nom_Tour']) ? htmlspecialchars($liste_poules[0]['Nom_Tour']) : '<span class="empty-slot">Tour non défini</span>' ?>
            </p>

            <div class="tabs-container" style="display: flex; gap: 15px; margin-bottom: 30px; border-bottom: 2px solid #e2e8f0;">
                <a href="poules?div_id=<?= urlencode($div_id) ?><?= $tour_id ? '&tour_id='.urlencode($tour_id) : '' ?>" 
                   style="padding: 10px 20px; font-weight: bold; color: var(--primary-blue); border-bottom: 3px solid var(--primary-blue); text-decoration: none;">
                    Poules & Matchs
                </a>
                <a href="tableau?div_id=<?= urlencode($div_id) ?><?= $tour_id ? '&tour_id='.urlencode($tour_id) : '' ?>" 
                   style="padding: 10px 20px; font-weight: bold; color: var(--text-gray); text-decoration: none;">
                    Tableau 
                </a>
            </div>

            <?php foreach ($liste_poules as $poule): ?>
                <?php 
                    $current_poul_id = $poule['ID_Poule']; 
                    $liste_joueurs = $joueurs_par_poule[$current_poul_id] ?? [];
                    $liste_matchs = $matchs_par_poule[$current_poul_id] ?? [];
                    $liste_classements = $classements_par_poule[$current_poul_id] ?? [];

                    // --- CORRECTIF SUPRÊME : TRIS DES MATCHS SELON LE RÈGLEMENT OFFICIEL FFTT (ANTI-ESPACES INVISIBLES) ---
                    $vong_fftt = [
                        3 => ['1/3' => 1, '2/3' => 2, '1/2' => 3],
                        4 => ['1/4' => 1, '2/3' => 2, '1/3' => 3, '2/4' => 4, '1/2' => 5, '3/4' => 6],
                        5 => ['1/5' => 1, '2/4' => 2, '1/4' => 3, '3/5' => 4, '1/3' => 5, '2/5' => 6, '1/2' => 7, '3/4' => 8, '2/3' => 9, '4/5' => 10],
                        6 => ['1/6' => 1, '2/5' => 2, '3/4' => 3, '1/5' => 4, '2/6' => 5, '1/4' => 6, '3/5' => 7, '2/4' => 8, '3/6' => 9, '1/3' => 10, '2/3' => 11, '4/5' => 12, '1/2' => 13, '4/6' => 14, '5/6' => 15]
                    ];

                    $type_poule = count($liste_joueurs) ?: 4; // Détection dynamique du type de poule (nombre de participants)

                    usort($liste_matchs, function($a, $b) use ($vong_fftt, $type_poule) {
                        // Nettoyage complet des caractères invisibles et espaces parasites (ex: "3 / 4" -> "3/4")
                        $keyA = preg_replace('/[^0-9\/]/', '', $a['Position_Match']);
                        $keyB = preg_replace('/[^0-9\/]/', '', $b['Position_Match']);
                        
                        $posA = $vong_fftt[$type_poule][$keyA] ?? ($vong_fftt[4][$keyA] ?? 999);
                        $posB = $vong_fftt[$type_poule][$keyB] ?? ($vong_fftt[4][$keyB] ?? 999);
                        
                        return $posA <=> $posB;
                    });

                    // --- RECONSTITUER LES POSITIONS DU TIRAGE AU SORT DEPUIS LE CALENDRIER ---
                    $positions_reelles = [];
                    foreach ($liste_matchs as $m) {
                        $parts = explode('/', $m['Position_Match']);
                        if (count($parts) == 2) {
                            $pos1 = (int)trim($parts[0]);
                            $pos2 = (int)trim($parts[1]);
                            if (!empty($m['ID_Adversaire1'])) $positions_reelles[$m['ID_Adversaire1']] = $pos1;
                            if (!empty($m['ID_Adversaire2'])) $positions_reelles[$m['ID_Adversaire2']] = $pos2;
                        }
                    }

                    // --- TRIER LA LISTE DES JOUEURS SELON LES INDEX REELS J1, J2, J3, J4 ---
                    usort($liste_joueurs, function($a, $b) use ($positions_reelles) {
                        $posA = $positions_reelles[$a['LIC_ID']] ?? 99;
                        $posB = $positions_reelles[$b['LIC_ID']] ?? 99;
                        return $posA <=> $posB;
                    });
                ?>

                <div class="poule-card">
                    <div class="poule-title">
                         <?= mb_strtoupper(htmlspecialchars($poule['Nom_Poule'] ?? ''), 'UTF-8') ?>
                    </div>

                    <div class="table-responsive">
                        <table class="web-table">
                            <thead>
                                <tr>
                                    <th style="width: 70px;">Rang</th>
                                    <th class="left-align">Nom</th>
                                    <th>Dossard</th>
                                    <th>N°Licence</th>
                                    <th>Clst</th>
                                    <th>Points</th>
                                    <th>N°club</th>
                                    <th class="left-align">Club</th>
                                    <th>Cat.</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $carte_locale_joueurs = [];
                                $index_dans_poule = 1;

                                foreach ($liste_joueurs as $row) {
                                    $carte_locale_joueurs[$row['LIC_ID']] = $index_dans_poule; 
                                    $safe_name = htmlspecialchars($row['Nom_Prenom']);
                                    $safe_club = htmlspecialchars($row['Nom_Court_Club']);
                                    $safe_cat = htmlspecialchars($row['Cat_Age']);
                                    
                                    echo "<tr>
                                        <td><span class='rank-badge'>{$index_dans_poule}</span></td>
                                        <td class='left-align' style='font-weight: 700; color: var(--primary-navy);'>{$safe_name}</td>
                                        <td>{$row['Num_Dossard']}</td>
                                        <td>{$row['Num_Licence']}</td>
                                        <td><span class='badge-score'>{$row['Classement_Tech']}</span></td>
                                        <td>{$row['Points_Cumules']}</td>
                                        <td>{$row['Num_Club']}</td>
                                        <td class='left-align'>{$safe_club}</td>
                                        <td style='color: var(--text-muted); font-size: 12px;'>{$safe_cat}</td>
                                    </tr>";
                                    $index_dans_poule++;
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="table-responsive">
                        <table class="web-table">
                            <thead>
                                <tr>
                                    <th style="width: 50px;"></th><th>Table</th><th>Heure</th>
                                    <th>F</th>
                                    <th class="left-align" style="width: 200px;">Joueur 1</th>
                                    <th class="left-align" style="width: 200px;">Joueur 2</th>
                                    <th>F</th>
                                    <th style="width:30px;">1</th><th style="width:30px;">2</th><th style="width:30px;">3</th><th style="width:30px;">4</th><th style="width:30px;">5</th><th style="width:30px;">6</th><th style="width:30px;">7</th>
                                    
                                    <?php 
                                    $nb_joueurs_poule = count($liste_joueurs) ?: 3;
                                    foreach ($liste_matchs as $match) {
                                        $match_fallback = explode('/', $match['Position_Match']);
                                        $f1 = isset($match_fallback[0]) ? (int)trim($match_fallback[0]) : 0;
                                        $f2 = isset($match_fallback[1]) ? (int)trim($match_fallback[1]) : 0;
                                        $nb_joueurs_poule = max($nb_joueurs_poule, $f1, $f2);
                                    }

                                    for ($i = 1; $i <= $nb_joueurs_poule; $i++): ?>
                                        <th style="background:#f4f6f9; color: var(--primary-blue); font-weight: 700; width: 35px;">J<?= $i ?></th>
                                    <?php endfor; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $total_scores_j = array_fill(1, $nb_joueurs_poule, 0);

                                foreach ($liste_matchs as $row) {
                                    $tab_display = (!empty($row['Num_Table']) && $row['Num_Table'] != '0') ? htmlspecialchars($row['Num_Table']) : '<span class="empty-slot">-</span>';
                                    $heure_display = (!empty($row['Heure_Match']) && $row['Heure_Match'] != '00:00') ? htmlspecialchars($row['Heure_Match']) : '<span class="empty-slot">-</span>';
                                    
                                    $id1 = $row['ID_Adversaire1'];
                                    $id2 = $row['ID_Adversaire2'];
                                    
                                    $safe_dt1 = htmlspecialchars($row['DT1']);
                                    $safe_dt2 = htmlspecialchars($row['DT2']);
                                    
                                    $match_fallback = explode('/', $row['Position_Match']);
                                    $idx_1 = (isset($match_fallback[0]) && is_numeric(trim($match_fallback[0]))) ? (int)trim($match_fallback[0]) : 0;
                                    $idx_2 = (isset($match_fallback[1]) && is_numeric(trim($match_fallback[1]))) ? (int)trim($match_fallback[1]) : 0;

                                    if ($idx_1 == 0 && !empty($id1)) $idx_1 = $carte_locale_joueurs[$id1] ?? 0;
                                    if ($idx_2 == 0 && !empty($id2)) $idx_2 = $carte_locale_joueurs[$id2] ?? 0;

                                    // Gestion de l'affichage sémantique des forfaits (F)
                                    $f1_box = ''; $f2_box = '';
                                    if ($row['Forfait'] == 1) {
                                        if ($row['PARTI_BL_GAGNE1'] == 1) $f2_box = '<span class="badge-forfait" title="Forfait" style="padding:2px 6px;">F</span>';
                                        elseif ($row['PARTI_BL_GAGNE2'] == 1) $f1_box = '<span class="badge-forfait" title="Forfait" style="padding:2px 6px;">F</span>';
                                    }

                                    $pts_p1 = '';
                                    $pts_p2 = '';

                                    if (isset($row['Pts_Match_Adv1']) && $row['Pts_Match_Adv1'] !== null) {
                                        $pts_p1 = $row['Pts_Match_Adv1'];
                                        $pts_p2 = $row['Pts_Match_Adv2'];
                                    } elseif (isset($row['PARTI_BL_GAGNE1']) && ($row['PARTI_BL_GAGNE1'] == 1 || $row['PARTI_BL_GAGNE2'] == 1)) {
                                        $pts_p1 = ($row['PARTI_BL_GAGNE1'] == 1) ? 2 : 1;
                                        $pts_p2 = ($row['PARTI_BL_GAGNE2'] == 1) ? 2 : 1;
                                        if ($row['Forfait'] == 1) {
                                            if ($row['PARTI_BL_GAGNE1'] == 1) $pts_p2 = 0;
                                            if ($row['PARTI_BL_GAGNE2'] == 1) $pts_p1 = 0;
                                        }
                                    }

                                    // COMPARAISON DES POINTS POUR METTRE EN GRAS LE VAINQUEUR (EN VERT)
                                    $style_dt1 = "";
                                    $style_dt2 = "";
                                    if ($pts_p1 !== '' && $pts_p2 !== '') {
                                        if ($pts_p1 > $pts_p2) {
                                            $style_dt1 = "font-weight: 800; color: #16a34a;"; 
                                        } elseif ($pts_p2 > $pts_p1) {
                                            $style_dt2 = "font-weight: 800; color: #16a34a;"; 
                                        }
                                    }

                                    echo "<tr>
                                        <td style='font-weight: 800; color: var(--primary-blue);'>".htmlspecialchars($row['Position_Match'])."</td>
                                        <td>{$tab_display}</td>
                                        <td>{$heure_display}</td>
                                        <td>{$f1_box}</td>
                                        <td class='left-align' style='{$style_dt1}'>{$safe_dt1}</td>
                                        <td class='left-align' style='{$style_dt2}'>{$safe_dt2}</td>
                                        <td>{$f2_box}</td>
                                        <td class='set-score'>{$row['S1']}</td><td class='set-score'>{$row['S2']}</td><td class='set-score'>{$row['S3']}</td><td class='set-score'>{$row['S4']}</td><td class='set-score'>{$row['S5']}</td><td class='set-score'>{$row['S6']}</td><td class='set-score'>{$row['S7']}</td>";
                                        
                                        for ($j = 1; $j <= $nb_joueurs_poule; $j++) {
                                            if ($j === $idx_1) {
                                                echo "<td style='background:#ffffff; font-weight:800; color: var(--primary-navy);'>{$pts_p1}</td>";
                                                $total_scores_j[$j] += (int)$pts_p1;
                                            } elseif ($j === $idx_2) {
                                                echo "<td style='background:#ffffff; font-weight:800; color: var(--primary-navy);'>{$pts_p2}</td>";
                                                $total_scores_j[$j] += (int)$pts_p2;
                                            } else {
                                                echo "<td style='background:#cbd5e1; border-color:#cbd5e1;'></td>";
                                            }
                                        }
                                    echo "</tr>";
                                }
                                ?>
                                <tr>
                                    <td colspan="14" style="text-align: right; font-weight: 800; border-bottom: none; font-size: 11px; letter-spacing: 0.5px; padding-right: 20px;">TOTAL DES POINTS :</td>
                                    <?php for ($i = 1; $i <= $nb_joueurs_poule; $i++): ?>
                                        <td style="border-bottom: none; background: #f8fafc;"><span class="badge-total"><?= $total_scores_j[$i] ?></span></td>
                                    <?php endfor; ?>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="table-responsive" style="max-width: 450px; margin-top: 15px;">
                        <table class="web-table">
                            <thead>
                                <tr>
                                    <th class="left-align" style="padding: 16px 24px;">Joueur</th>
                                    <th style="width: 90px;">Rang</th>
                                    <th style="width: 100px;">Forfait</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                foreach ($liste_classements as $row) {
                                    $f_badge = ($row['Forfait'] == 1) ? '<span class="badge-forfait" title="Forfait" style="padding:2px 6px;">F</span>' : '';
                                    $safe_joueur = htmlspecialchars($row['Joueur']);
                                    echo "<tr>
                                        <td class='left-align' style='padding: 16px 24px; font-weight: 700; color: var(--primary-navy);'>{$safe_joueur}</td>
                                        <td><span class='rank-badge' style='background-color:#fff1f2; color:#ef4444;'>{$row['Rang']}</span></td>
                                        <td>{$f_badge}</td>
                                    </tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>

                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>

    <div id="network-toast">
        ⚠️ <span>Connexion perdue. Tentative de reconnexion...</span>
    </div>
    
    <script src="assets/js/app.js"></script>
</body>
</html>