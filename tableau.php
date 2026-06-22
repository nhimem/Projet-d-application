<?php
require_once __DIR__ . '/config/database.php';

// Obligatoire : ID de la division et ID du tour pour afficher le tableau
$div_id = $_GET['div_id'] ?? null;
$tour_id = $_GET['tour_id'] ?? null;

if (!$div_id || !$tour_id) {
    die("<div style='padding:20px; color:red; font-weight:bold;'>Erreur : Veuillez sélectionner un tour spécifique depuis l'accueil pour afficher le tableau.</div>");
}

try {
    // 1. RÉCUPÉRATION DES INFORMATIONS DE L'EN-TÊTE
    $sql_header = "
        SELECT 
            e.EPRV_LB AS Nom_Epreuve,
            d.DIV_LB AS Nom_Division,
            t.TOUR_LB AS Nom_Tour
        FROM TOUR t
        JOIN DIVISION d ON t.DIV_ID = d.DIV_ID
        LEFT JOIN EPREUVE e ON d.EPRV_ID = e.EPRV_ID
        WHERE d.DIV_ID = :div_id AND t.TOUR_ID = :tour_id
        LIMIT 1
    ";
    $stmtHeader = $pdo->prepare($sql_header);
    $stmtHeader->execute(['div_id' => $div_id, 'tour_id' => $tour_id]);
    $infos_en_tete = $stmtHeader->fetch();

    // 2. REQUÊTE POUR RÉCUPÉRER TOUS LES MATCHS DU TABLEAU
    $sql_tableau = "
        SELECT 
            tab.TAB_ID, tab.TAB_LB, 
            niv.NIV_ID,
            nref.NIVREF_LB AS Nom_Niveau,
            nref.NIVREF_NB AS Ordre_Niveau,
            tp.TABPART_ID_TABLEAU AS ID_Match,
            tp.TABPART_LB_PARTIE AS Label_Match, 
            p.PARTI_BL_FORFAIT AS Forfait,
            p.PARTI_BL_GAGNE1 AS Gagne1, 
            p.PARTI_BL_GAGNE2 AS Gagne2,
            
            -- Données du Joueur 1
            tp.INSC_ID AS J1_InscID,
            i1.INSC_BL_ABSENT AS J1_Absent,
            CONCAT(l1.PERS_LB_NOM, ' ', l1.PERS_LB_PRENOM) AS J1_Nom,
            CAST(l1.LIC_NB_POINT AS UNSIGNED) AS J1_Pts,
            c1.CLUB_LB_COURT AS J1_Club,
            
            -- Données du Joueur 2
            tp.INS_INSC_ID AS J2_InscID,
            i2.INSC_BL_ABSENT AS J2_Absent,
            CONCAT(l2.PERS_LB_NOM, ' ', l2.PERS_LB_PRENOM) AS J2_Nom,
            CAST(l2.LIC_NB_POINT AS UNSIGNED) AS J2_Pts,
            c2.CLUB_LB_COURT AS J2_Club,
            
            -- Scores des Manches
            MAX(CASE WHEN m.MANCH_NB_RANG = 1 THEN CASE WHEN m.MANCH_NB_SCORE1 > m.MANCH_NB_SCORE2 THEN m.MANCH_NB_SCORE2 ELSE -m.MANCH_NB_SCORE1 END END) AS S1,
            MAX(CASE WHEN m.MANCH_NB_RANG = 2 THEN CASE WHEN m.MANCH_NB_SCORE1 > m.MANCH_NB_SCORE2 THEN m.MANCH_NB_SCORE2 ELSE -m.MANCH_NB_SCORE1 END END) AS S2,
            MAX(CASE WHEN m.MANCH_NB_RANG = 3 THEN CASE WHEN m.MANCH_NB_SCORE1 > m.MANCH_NB_SCORE2 THEN m.MANCH_NB_SCORE2 ELSE -m.MANCH_NB_SCORE1 END END) AS S3,
            MAX(CASE WHEN m.MANCH_NB_RANG = 4 THEN CASE WHEN m.MANCH_NB_SCORE1 > m.MANCH_NB_SCORE2 THEN m.MANCH_NB_SCORE2 ELSE -m.MANCH_NB_SCORE1 END END) AS S4,
            MAX(CASE WHEN m.MANCH_NB_RANG = 5 THEN CASE WHEN m.MANCH_NB_SCORE1 > m.MANCH_NB_SCORE2 THEN m.MANCH_NB_SCORE2 ELSE -m.MANCH_NB_SCORE1 END END) AS S5,
            MAX(CASE WHEN m.MANCH_NB_RANG = 6 THEN CASE WHEN m.MANCH_NB_SCORE1 > m.MANCH_NB_SCORE2 THEN m.MANCH_NB_SCORE2 ELSE -m.MANCH_NB_SCORE1 END END) AS S6,
            MAX(CASE WHEN m.MANCH_NB_RANG = 7 THEN CASE WHEN m.MANCH_NB_SCORE1 > m.MANCH_NB_SCORE2 THEN m.MANCH_NB_SCORE2 ELSE -m.MANCH_NB_SCORE1 END END) AS S7
        FROM TABLEAU tab
        JOIN NIVEAU niv ON tab.TAB_ID = niv.TAB_ID
        JOIN NIVEAU_REF nref ON niv.NIVREF_ID = nref.NIVREF_ID
        JOIN TABLEAU_PARTIE tp ON niv.NIV_ID = tp.NIV_ID
        LEFT JOIN INSCRIPTION i1 ON tp.INSC_ID = i1.INSC_ID
        LEFT JOIN JOUEUR j1 ON i1.JOUE_ID = j1.JOUE_ID
        LEFT JOIN LICENCIE l1 ON j1.LIC_ID = l1.LIC_ID
        LEFT JOIN CLUB c1 ON l1.CLUB_ID = c1.CLUB_ID
        LEFT JOIN INSCRIPTION i2 ON tp.INS_INSC_ID = i2.INSC_ID
        LEFT JOIN JOUEUR j2 ON i2.JOUE_ID = j2.JOUE_ID
        LEFT JOIN LICENCIE l2 ON j2.LIC_ID = l2.LIC_ID
        LEFT JOIN CLUB c2 ON l2.CLUB_ID = c2.CLUB_ID
        LEFT JOIN PARTIE p ON tp.PARTI_ID = p.PARTI_ID
        LEFT JOIN MANCHE m ON tp.TABPART_ID_TABLEAU = m.TABPART_ID_TABLEAU
        WHERE tab.TOUR_ID = :tour_id
        GROUP BY 
            tab.TAB_ID, tab.TAB_LB, niv.NIV_ID, nref.NIVREF_LB, nref.NIVREF_NB,
            tp.TABPART_ID_TABLEAU, tp.TABPART_LB_PARTIE,
            p.PARTI_BL_FORFAIT, p.PARTI_BL_GAGNE1, p.PARTI_BL_GAGNE2,
            tp.INSC_ID, i1.INSC_BL_ABSENT, l1.PERS_LB_NOM, l1.PERS_LB_PRENOM, l1.LIC_NB_POINT, c1.CLUB_LB_COURT,
            tp.INS_INSC_ID, i2.INSC_BL_ABSENT, l2.PERS_LB_NOM, l2.PERS_LB_PRENOM, l2.LIC_NB_POINT, c2.CLUB_LB_COURT
    ";
    
    $stmtTableau = $pdo->prepare($sql_tableau);
    $stmtTableau->execute(['tour_id' => $tour_id]);
    $resultats = $stmtTableau->fetchAll();

    // VÉRIFICATION DU FORMAT DU TOURNOI (16 OU 32 JOUEURS)
    $est_32_joueurs = false;
    foreach ($resultats as $match) {
        $lbl = trim((string)$match['Label_Match']);
        if (in_array($lbl, ['1/32', '16/17', '9/24', '17/32', '25/32', '19/30'])) {
            $est_32_joueurs = true;
            break;
        }
    }

    // MAPPAGE DES DONNÉES SANS DOUBLONS DE CLÉS
    $matchs_mappes = [];
    if ($est_32_joueurs) {
        foreach ($resultats as $match) {
            $tv = strtolower(trim((string)$match['Nom_Niveau']));
            $lbl = trim((string)$match['Label_Match']);
            if (empty($lbl)) continue;

            $prefixe = '';
            if (strpos($tv, 'bar') !== false) $prefixe = 'b_';
            elseif (strpos($tv, '1/8') !== false) $prefixe = (strpos($tv, 'ko') !== false) ? 'l16_' : 'w16_';
            elseif (strpos($tv, '1/4') !== false) $prefixe = (strpos($tv, 'ko') !== false) ? 'l8_' : 'w8_';
            elseif (strpos($tv, '1/2') !== false) $prefixe = (strpos($tv, 'ko') !== false) ? 'l4_' : 'w4_';
            elseif (strpos($tv, 'fko') !== false || strpos($tv, 'ko') !== false) $prefixe = 'l2_'; 
            elseif (strpos($tv, 'final') !== false) $prefixe = 'w2_'; 
            if ($prefixe === '') $prefixe = 'u_'; 

            $matchs_mappes[$prefixe . $lbl] = $match;
        }
    } else {
        $compteur_fko = 0;
        $cles_fko = ['2/3', '6/7', '10/11', '14/15']; 
        foreach ($resultats as $match) {
            $label = trim((string)$match['Label_Match']);
            if (empty($label)) continue;

            if (strpos(strtolower($label), 'fko') !== false && !in_array($label, $cles_fko)) {
                if (isset($cles_fko[$compteur_fko])) {
                    $matchs_mappes[$cles_fko[$compteur_fko]] = $match;
                    $compteur_fko++;
                }
            } else {
                $matchs_mappes[$label] = $match;
            }
        }
    }

} catch (\PDOException $e) {
    // [PATCH SÉCURITÉ] : Log d'erreur côté serveur
    error_log("Database Error in tableau.php: " . $e->getMessage());
    die("<div style='padding:20px; color:red; font-weight:bold; background:#fee2e2; border-radius:5px;'>Le système est actuellement en maintenance ou rencontre un problème de connexion. Veuillez réessayer plus tard !</div>");
}

// =========================================================================
// DÉFINITION DES CLÉS ET DES DÉPENDANCES POUR SPIDD
// =========================================================================
$dependances = [];

if ($est_32_joueurs) {
    $CLES = [
        'b_1' => 'b_3/4', 'b_2' => 'b_5/6', 'b_3' => 'b_11/12', 'b_4' => 'b_13/14',
        'b_5' => 'b_19/20', 'b_6' => 'b_21/22', 'b_7' => 'b_27/28', 'b_8' => 'b_29/30',

        'w16_1' => 'w16_1/4', 'w16_2' => 'w16_5/8', 'w16_3' => 'w16_9/12', 'w16_4' => 'w16_13/16',
        'w16_5' => 'w16_17/20', 'w16_6' => 'w16_21/24', 'w16_7' => 'w16_25/28', 'w16_8' => 'w16_29/32',
        
        'w8_1' => 'w8_1/8', 'w8_2' => 'w8_9/16', 'w8_3' => 'w8_17/24', 'w8_4' => 'w8_25/32',
        'w8_5' => 'w8_4/5', 'w8_6' => 'w8_12/13', 'w8_7' => 'w8_20/21', 'w8_8' => 'w8_28/29',
        
        'w4_1' => 'w4_1/16', 'w4_2' => 'w4_17/32', 'w4_3' => 'w4_8/9', 'w4_4' => 'w4_24/25',
        'w4_5' => 'w4_5/12', 'w4_6' => 'w4_21/28', 'w4_7' => 'w4_4/13', 'w4_8' => 'w4_20/29',
        
        'f_1_2' => 'w2_1/32', 'f_3_4' => 'w2_16/17', 'f_5_6' => 'w2_9/24', 'f_7_8' => 'w2_8/25', 
        'f_9_10' => 'w2_5/28', 'f_11_12' => 'w2_12/21', 'f_13_14' => 'w2_13/20', 'f_15_16' => 'w2_4/29',

        'l16_1' => 'l16_2/3', 'l16_2' => 'l16_6/7', 'l16_3' => 'l16_10/11', 'l16_4' => 'l16_14/15',
        'l16_5' => 'l16_18/19', 'l16_6' => 'l16_22/23', 'l16_7' => 'l16_26/27', 'l16_8' => 'l16_30/31',
        
        'l8_1' => 'l8_3/6', 'l8_2' => 'l8_11/14', 'l8_3' => 'l8_19/22', 'l8_4' => 'l8_27/30',
        'l8_5' => 'l8_2/7', 'l8_6' => 'l8_10/15', 'l8_7' => 'l8_18/23', 'l8_8' => 'l8_26/31',
        
        'l4_1' => 'l4_3/14', 'l4_2' => 'l4_19/30', 'l4_3' => 'l4_6/11', 'l4_4' => 'l4_22/27', 
        'l4_5' => 'l4_7/10', 'l4_6' => 'l4_23/26', 'l4_7' => 'l4_2/15', 'l4_8' => 'l4_18/31',
        
        'f_17_18' => 'l2_3/30', 'f_19_20' => 'l2_14/19', 'f_21_22' => 'l2_11/22', 'f_23_24' => 'l2_6/27',
        'f_25_26' => 'l2_7/26', 'f_27_28' => 'l2_10/23', 'f_29_30' => 'l2_15/18', 'f_31_32' => 'l2_2/31',
    ];

    $dependances = [
        $CLES['w16_1'] => [null, $CLES['b_1']], $CLES['w16_2'] => [null, $CLES['b_2']],
        $CLES['w16_3'] => [null, $CLES['b_3']], $CLES['w16_4'] => [null, $CLES['b_4']],
        $CLES['w16_5'] => [null, $CLES['b_5']], $CLES['w16_6'] => [null, $CLES['b_6']],
        $CLES['w16_7'] => [null, $CLES['b_7']], $CLES['w16_8'] => [null, $CLES['b_8']],

        $CLES['l16_1'] => [null, $CLES['b_1']], $CLES['l16_2'] => [null, $CLES['b_2']],
        $CLES['l16_3'] => [null, $CLES['b_3']], $CLES['l16_4'] => [null, $CLES['b_4']],
        $CLES['l16_5'] => [null, $CLES['b_5']], $CLES['l16_6'] => [null, $CLES['b_6']],
        $CLES['l16_7'] => [null, $CLES['b_7']], $CLES['l16_8'] => [null, $CLES['b_8']],

        $CLES['w8_1'] => [$CLES['w16_1'], $CLES['w16_2']], $CLES['w8_2'] => [$CLES['w16_3'], $CLES['w16_4']],
        $CLES['w8_3'] => [$CLES['w16_5'], $CLES['w16_6']], $CLES['w8_4'] => [$CLES['w16_7'], $CLES['w16_8']],
        $CLES['w8_5'] => [$CLES['w16_1'], $CLES['w16_2']], $CLES['w8_6'] => [$CLES['w16_3'], $CLES['w16_4']],
        $CLES['w8_7'] => [$CLES['w16_5'], $CLES['w16_6']], $CLES['w8_8'] => [$CLES['w16_7'], $CLES['w16_8']],

        $CLES['l8_1'] => [$CLES['l16_1'], $CLES['l16_2']], $CLES['l8_2'] => [$CLES['l16_3'], $CLES['l16_4']],
        $CLES['l8_3'] => [$CLES['l16_5'], $CLES['l16_6']], $CLES['l8_4'] => [$CLES['l16_7'], $CLES['l16_8']],
        $CLES['l8_5'] => [$CLES['l16_1'], $CLES['l16_2']], $CLES['l8_6'] => [$CLES['l16_3'], $CLES['l16_4']],
        $CLES['l8_7'] => [$CLES['l16_5'], $CLES['l16_6']], $CLES['l8_8'] => [$CLES['l16_7'], $CLES['l16_8']],

        $CLES['w4_1'] => [$CLES['w8_1'], $CLES['w8_2']], $CLES['w4_2'] => [$CLES['w8_3'], $CLES['w8_4']],
        $CLES['w4_3'] => [$CLES['w8_1'], $CLES['w8_2']], $CLES['w4_4'] => [$CLES['w8_3'], $CLES['w8_4']],
        $CLES['w4_5'] => [$CLES['w8_5'], $CLES['w8_6']], $CLES['w4_6'] => [$CLES['w8_7'], $CLES['w8_8']],
        $CLES['w4_7'] => [$CLES['w8_5'], $CLES['w8_6']], $CLES['w4_8'] => [$CLES['w8_7'], $CLES['w8_8']],

        $CLES['l4_1'] => [$CLES['l8_1'], $CLES['l8_2']], $CLES['l4_2'] => [$CLES['l8_3'], $CLES['l8_4']],
        $CLES['l4_3'] => [$CLES['l8_1'], $CLES['l8_2']], $CLES['l4_4'] => [$CLES['l8_3'], $CLES['l8_4']],
        $CLES['l4_5'] => [$CLES['l8_5'], $CLES['l8_6']], $CLES['l4_6'] => [$CLES['l8_7'], $CLES['l8_8']],
        $CLES['l4_7'] => [$CLES['l8_5'], $CLES['l8_6']], $CLES['l4_8'] => [$CLES['l8_7'], $CLES['l8_8']],

        $CLES['f_1_2'] => [$CLES['w4_1'], $CLES['w4_2']], $CLES['f_3_4'] => [$CLES['w4_1'], $CLES['w4_2']],
        $CLES['f_5_6'] => [$CLES['w4_3'], $CLES['w4_4']], $CLES['f_7_8'] => [$CLES['w4_3'], $CLES['w4_4']],
        $CLES['f_9_10'] => [$CLES['w4_5'], $CLES['w4_6']], $CLES['f_11_12'] => [$CLES['w4_5'], $CLES['w4_6']],
        $CLES['f_13_14'] => [$CLES['w4_7'], $CLES['w4_8']], $CLES['f_15_16'] => [$CLES['w4_7'], $CLES['w4_8']],

        $CLES['f_17_18'] => [$CLES['l4_1'], $CLES['l4_2']], $CLES['f_19_20'] => [$CLES['l4_1'], $CLES['l4_2']],
        $CLES['f_21_22'] => [$CLES['l4_3'], $CLES['l4_4']], $CLES['f_23_24'] => [$CLES['l4_3'], $CLES['l4_4']],
        $CLES['f_25_26'] => [$CLES['l4_5'], $CLES['l4_6']], $CLES['f_27_28'] => [$CLES['l4_5'], $CLES['l4_6']],
        $CLES['f_29_30'] => [$CLES['l4_7'], $CLES['l4_8']], $CLES['f_31_32'] => [$CLES['l4_7'], $CLES['l4_8']],
    ];
} else {
    // ----------------- CONFIGURATION POUR 16 JOUEURS -----------------
    $dependances = [
        '1/4' => [null, '3/4'], '5/8' => ['5/6', null], '9/12' => [null, '11/12'], '13/16' => ['13/14', null],
        '2/3' => [null, '3/4'], '6/7' => ['5/6', null], '10/11' => [null, '11/12'], '14/15' => ['13/14', null],
        '1/8' => ['1/4', '5/8'], '9/16' => ['9/12', '13/16'],
        '4/5' => ['1/4', '5/8'], '12/13' => ['9/12', '13/16'],
        '3/6' => ['2/3', '6/7'], '11/14' => ['10/11', '14/15'],
        '2/7' => ['2/3', '6/7'], '10/15' => ['10/11', '14/15'],
        '1/16' => ['1/8', '9/16'], '8/9' => ['1/8', '9/16'],
        '5/12' => ['4/5', '12/13'], '4/13' => ['4/5', '12/13'],
        '3/14' => ['3/6', '11/14'], '6/11' => ['3/6', '11/14'],
        '7/10' => ['2/7', '10/15'], '2/15' => ['2/7', '10/15']
    ];
}

// --- SYSTÈME INTELLIGENT DE STATUT DES MATCHS ---
$statut_match = [];

function evaluerStatutMatch($label) {
    global $matchs_mappes, $dependances, $statut_match;
    if (isset($statut_match[$label])) return $statut_match[$label];
    
    if (!isset($matchs_mappes[$label])) {
        // Passe automatiquement pour les matchs inexistants
        $statut_match[$label] = ['pret' => true, 'termine' => true, 'j1_resolu' => true, 'j2_resolu' => true];
        return $statut_match[$label];
    }
    
    $j1_resolu = true;
    $j2_resolu = true;
    
    if (isset($dependances[$label])) {
        $dep_j1 = $dependances[$label][0] ?? null;
        $dep_j2 = $dependances[$label][1] ?? null;

        if ($dep_j1 !== null) {
            $j1_status = evaluerStatutMatch($dep_j1);
            $j1_resolu = $j1_status['termine'];
        }
        if ($dep_j2 !== null) {
            $j2_status = evaluerStatutMatch($dep_j2);
            $j2_resolu = $j2_status['termine'];
        }
    }
    
    $est_pret = ($j1_resolu && $j2_resolu);
    $est_termine = false;
    $match = $matchs_mappes[$label];
    
    if ($est_pret) {
        $sets = array_filter([$match['S1'], $match['S2'], $match['S3'], $match['S4'], $match['S5'], $match['S6'], $match['S7']], function($v) { return $v !== null && $v !== ''; });
        
        $j1_brut = trim((string)$match['J1_Nom']);
        $j2_brut = trim((string)$match['J2_Nom']);
        
        $est_j1_inconnu = ($j1_brut === '' || $j1_brut === 'Absent' || $j1_brut === 'Inconnu' || $match['J1_InscID'] == '-101');
        $est_j2_inconnu = ($j2_brut === '' || $j2_brut === 'Absent' || $j2_brut === 'Inconnu' || $match['J2_InscID'] == '-101');
        $est_j1_forfait = ($match['J1_Absent'] == 1 || $match['Forfait'] == 1);
        $est_j2_forfait = ($match['J2_Absent'] == 1 || $match['Forfait'] == 1);

        if (!empty($sets)) {
            $est_termine = true;
        } elseif ($est_j1_inconnu || $est_j2_inconnu || $est_j1_forfait || $est_j2_forfait) {
            $est_termine = true;
        } else {
            $est_termine = false;
        }
    }
    
    $statut_match[$label] = [
        'pret' => $est_pret, 
        'termine' => $est_termine,
        'j1_resolu' => $j1_resolu,
        'j2_resolu' => $j2_resolu
    ];
    return $statut_match[$label];
}

foreach (array_keys($matchs_mappes) as $lbl) evaluerStatutMatch($lbl);
foreach (array_keys($dependances) as $lbl) evaluerStatutMatch($lbl);


// --- FONCTION DE RENDU DE L'INTERFACE GRAPHIQUE ---
function renderMatchCard($label_match, $titre, $positions = '', $rangs_finaux = null) {
    global $matchs_mappes, $statut_match;
    
    $match = $matchs_mappes[$label_match] ?? null;
    $statut = $statut_match[$label_match] ?? ['pret' => true, 'termine' => true, 'j1_resolu' => true, 'j2_resolu' => true];
    
    $j1_resolu = $statut['j1_resolu'];
    $j2_resolu = $statut['j2_resolu'];
    $est_pret = $statut['pret'];

    $pos1 = ''; $pos2 = '';
    if ($positions) {
        $parts = explode('/', $positions);
        if (count($parts) == 2) {
            $pos1 = trim($parts[0]);
            $pos2 = trim($parts[1]);
        }
    }

    if (!$match) return "<div class='match-card empty-match'><div class='match-header'>{$titre}</div><div class='match-body'><div class='player-row' data-pid=''><div class='player-wrapper'><div class='player-info'><span class='name player-waiting'>À déterminer</span></div></div></div><div class='player-row' data-pid=''><div class='player-wrapper'><div class='player-info'><span class='name player-waiting'>À déterminer</span></div></div></div><i style='color:#94a3b8; font-size:11px; margin-top:5px; display:block; text-align:center;'>Non joué</i></div></div>";

    $j1_brut = trim((string)$match['J1_Nom']);
    $j2_brut = trim((string)$match['J2_Nom']);
    
    $est_j1_inconnu = ($j1_brut === '' || $j1_brut === 'Absent' || $j1_brut === 'Inconnu' || $match['J1_InscID'] == '-101');
    $est_j2_inconnu = ($j2_brut === '' || $j2_brut === 'Absent' || $j2_brut === 'Inconnu' || $match['J2_InscID'] == '-101');
    $est_j1_vrai_absent = ($match['J1_Absent'] == 1 && !$est_j1_inconnu);
    $est_j2_vrai_absent = ($match['J2_Absent'] == 1 && !$est_j2_inconnu);

    // [BẢN VÁ]: Hiển thị thêm dòng chữ (Absent) vào sau tên người chơi mà KHÔNG gạch bỏ tên
    if (!$j1_resolu) {
        $j1_affichage = 'À déterminer';
    } else {
        $j1_affichage = $est_j1_inconnu ? 'Absent' : htmlspecialchars($j1_brut);
        if ($est_j1_vrai_absent) {
            $j1_affichage .= " <span style='font-size: 0.85em; font-weight: normal;'>(Absent)</span>";
        }
    }

    if (!$j2_resolu) {
        $j2_affichage = 'À déterminer';
    } else {
        $j2_affichage = $est_j2_inconnu ? 'Absent' : htmlspecialchars($j2_brut);
        if ($est_j2_vrai_absent) {
            $j2_affichage .= " <span style='font-size: 0.85em; font-weight: normal;'>(Absent)</span>";
        }
    }

    // --- ID POUR L'EFFET DE SURVOL ---
    $id1 = $match['J1_InscID'] ?? '';
    $id2 = $match['J2_InscID'] ?? '';
    if ($j1_affichage === 'À déterminer' || $est_j1_inconnu || $est_j1_vrai_absent) $id1 = '';
    if ($j2_affichage === 'À déterminer' || $est_j2_inconnu || $est_j2_vrai_absent) $id2 = '';

    $sets = []; $s1 = 0; $s2 = 0; $gagne1 = false; $gagne2 = false; $match_termine = false;

    if (!$est_pret || $j1_affichage === 'À déterminer' || $j2_affichage === 'À déterminer') {
        $match_termine = false;
    } else {
        $sets = array_filter([$match['S1'], $match['S2'], $match['S3'], $match['S4'], $match['S5'], $match['S6'], $match['S7']], function($v) { return $v !== null && $v !== ''; });
        foreach ($sets as $s) {
            if ((int)$s > 0) $s1++; elseif ((int)$s < 0) $s2++;
        }
        
        $j1_manquant = ($est_j1_inconnu || $est_j1_vrai_absent);
        $j2_manquant = ($est_j2_inconnu || $est_j2_vrai_absent);
        $a_des_scores = !empty($sets);

        if ($a_des_scores) {
            if ($match['Gagne1'] == 1) { $gagne1 = true; $gagne2 = false; }
            elseif ($match['Gagne2'] == 1) { $gagne1 = false; $gagne2 = true; }
            else { $gagne1 = ($s1 > $s2); $gagne2 = ($s2 > $s1); }
            $match_termine = true;
        } elseif ($j1_manquant || $j2_manquant || $match['Forfait'] == 1) {
            if ($j1_manquant && !$j2_manquant) { $gagne1 = false; $gagne2 = true; $match_termine = true; }
            elseif ($j2_manquant && !$j1_manquant) { $gagne1 = true; $gagne2 = false; $match_termine = true; }
            elseif ($j1_manquant && $j2_manquant) { $gagne1 = false; $gagne2 = false; $match_termine = true; }
            elseif ($match['Forfait'] == 1) {
                if ($match['Gagne2'] == 1) { $gagne1 = false; $gagne2 = true; $est_j1_vrai_absent = true; $match_termine = true; }
                elseif ($match['Gagne1'] == 1) { $gagne1 = true; $gagne2 = false; $est_j2_vrai_absent = true; $match_termine = true; }
            }
        } else {
            $gagne1 = false;
            $gagne2 = false;
            $match_termine = false;
        }
    }
    
    $classe_p1 = '';
    if ($j1_affichage === 'À déterminer') $classe_p1 = 'player-waiting';
    elseif ($est_j1_vrai_absent) $classe_p1 = 'player-forfait'; 
    elseif ($est_j1_inconnu) $classe_p1 = 'player-absent'; 

    $classe_p2 = '';
    if ($j2_affichage === 'À déterminer') $classe_p2 = 'player-waiting';
    elseif ($est_j2_vrai_absent) $classe_p2 = 'player-forfait'; 
    elseif ($est_j2_inconnu) $classe_p2 = 'player-absent'; 

    $meta1 = (!empty($match['J1_Club']) && $j1_affichage !== 'À déterminer' && !$est_j1_inconnu && $match['J1_Club'] !== 'Inc') 
             ? "<span class='meta'>{$match['J1_Pts']} pts - " . htmlspecialchars($match['J1_Club']) . "</span>" : "";
    $meta2 = (!empty($match['J2_Club']) && $j2_affichage !== 'À déterminer' && !$est_j2_inconnu && $match['J2_Club'] !== 'Inc') 
             ? "<span class='meta'>{$match['J2_Pts']} pts - " . htmlspecialchars($match['J2_Club']) . "</span>" : "";

    $html_pos1 = ($pos1 && $j1_affichage !== 'À déterminer') ? "<span class='pos-badge'>{$pos1}</span>" : "";
    $html_pos2 = ($pos2 && $j2_affichage !== 'À déterminer') ? "<span class='pos-badge'>{$pos2}</span>" : "";

    $donnees_p1 = [
        'html_pos' => $html_pos1, 'name' => $j1_affichage, 'meta' => $meta1,
        'win' => $gagne1 ? 'winner' : '', 'rank_text' => '', 'css_class' => $classe_p1, 'pid' => $id1
    ];
    $donnees_p2 = [
        'html_pos' => $html_pos2, 'name' => $j2_affichage, 'meta' => $meta2,
        'win' => $gagne2 ? 'winner' : '', 'rank_text' => '', 'css_class' => $classe_p2, 'pid' => $id2
    ];

    // [BẢN VÁ]: Phân hạng (Rank) vô điều kiện cho cả người vắng mặt để họ có hạng thấp hơn
    if ($rangs_finaux !== null && $match_termine) {
        if ($gagne1) {
            $donnees_p1['rank_text'] = "<span class='final-rank rank-gold'>{$rangs_finaux[0]}</span>";
            $donnees_p2['rank_text'] = "<span class='final-rank rank-silver'>{$rangs_finaux[1]}</span>";
        } elseif ($gagne2) {
            $donnees_p1['rank_text'] = "<span class='final-rank rank-silver'>{$rangs_finaux[1]}</span>";
            $donnees_p2['rank_text'] = "<span class='final-rank rank-gold'>{$rangs_finaux[0]}</span>";
        }
    }

    $chaine_scores = !empty($sets) ? implode(' / ', $sets) : '';
    
    if (!$est_pret || $j1_affichage === 'À déterminer' || $j2_affichage === 'À déterminer') {
        $message_attente = "<i style='color:#94a3b8; font-size:11px; margin-top:5px; display:block; text-align:center;'>En attente...</i>";
    } elseif ($est_j1_inconnu || $est_j2_inconnu || $est_j1_vrai_absent || $est_j2_vrai_absent) {
        $message_attente = "<i style='color:#ef4444; font-size:11px; margin-top:5px; display:block; text-align:center;'>Forfait (Non joué)</i>";
    } else {
        $message_attente = "<i style='color:#94a3b8; font-size:11px; margin-top:5px; display:block; text-align:center;'>Score en attente...</i>";
    }

    return "
    <div class='match-card'>
        <div class='match-header'>{$titre}</div>
        <div class='match-body'>
            <div class='player-row {$donnees_p1['win']}' data-pid='{$donnees_p1['pid']}'>
                <div class='player-wrapper'>
                    {$donnees_p1['html_pos']}
                    <div class='player-info'>
                        <div style='display:flex; align-items:center;'>
                            <span class='name {$donnees_p1['css_class']}'>{$donnees_p1['name']}</span>
                            {$donnees_p1['rank_text']}
                        </div>
                        {$donnees_p1['meta']}
                    </div>
                </div>
            </div>
            <div class='player-row {$donnees_p2['win']}' data-pid='{$donnees_p2['pid']}'>
                <div class='player-wrapper'>
                    {$donnees_p2['html_pos']}
                    <div class='player-info'>
                        <div style='display:flex; align-items:center;'>
                            <span class='name {$donnees_p2['css_class']}'>{$donnees_p2['name']}</span>
                            {$donnees_p2['rank_text']}
                        </div>
                        {$donnees_p2['meta']}
                    </div>
                </div>
            </div>
            " . ($chaine_scores ? "<div class='match-score-bar'>{$chaine_scores}</div>" : $message_attente) . "
        </div>
    </div>";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau - SPIDD V2</title>
    <link rel="stylesheet" href="assets/css/global.css">
    <link rel="stylesheet" href="assets/css/poules.css">
    <link rel="stylesheet" href="assets/css/tableau.css"> <link rel="stylesheet" href="assets/css/loading.css"> <link rel="icon" type="image/jpeg" href="assets/images/logo_insa.png">
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
        <h2 class="section-header" style="font-size: 28px; margin-bottom: 10px;">
            <?= htmlspecialchars($infos_en_tete['Nom_Epreuve'] ?? 'Compétition') ?>
        </h2>
        <p style="font-size: 18px; color: var(--text-gray); margin-bottom: 20px; font-weight: bold;">
            <?= htmlspecialchars($infos_en_tete['Nom_Division'] ?? '') ?> - <?= htmlspecialchars($infos_en_tete['Nom_Tour'] ?? '') ?> 
            <?= $est_32_joueurs ? '(32 Joueurs)' : '(16 Joueurs)' ?>
        </p>

        <div class="tabs-container">
            <a href="poules?div_id=<?= urlencode($div_id) ?>&tour_id=<?= urlencode($tour_id) ?>" style="padding: 10px 20px; font-weight: bold; color: var(--text-gray); text-decoration: none;">Poules & Matchs</a>
            <a href="tableau?div_id=<?= urlencode($div_id) ?>&tour_id=<?= urlencode($tour_id) ?>" style="padding: 10px 20px; font-weight: bold; color: var(--primary-blue); border-bottom: 3px solid var(--primary-blue); text-decoration: none;">Tableau</a>
        </div>

        <?php if ($est_32_joueurs): ?>
            <div class="logic-container">
                <div class="logic-section sec-center">
                    <h3>1/16 Finale - Barrages</h3>
                    <div class="logic-grid">
                        <div class='logic-col'>
                            <?= renderMatchCard($CLES['b_1'], "Match 1", '3/4') ?>
                            <?= renderMatchCard($CLES['b_2'], "Match 2", '5/6') ?>
                            <?= renderMatchCard($CLES['b_3'], "Match 3", '11/12') ?>
                            <?= renderMatchCard($CLES['b_4'], "Match 4", '13/14') ?>
                        </div>
                        <div class='logic-col'>
                            <?= renderMatchCard($CLES['b_5'], "Match 5", '19/20') ?>
                            <?= renderMatchCard($CLES['b_6'], "Match 6", '21/22') ?>
                            <?= renderMatchCard($CLES['b_7'], "Match 7", '27/28') ?>
                            <?= renderMatchCard($CLES['b_8'], "Match 8", '29/30') ?>
                        </div>
                    </div>
                </div>

                <div class="logic-section sec-winner">
                    <h3>Places 1 à 16</h3>
                    <div class="logic-grid">
                        <div class="logic-col">
                            <div class="col-title">1/8 de Finale</div>
                            <?= renderMatchCard($CLES['w16_1'], '1/8 Finale (Match 1)', '1/4') ?>
                            <?= renderMatchCard($CLES['w16_2'], '1/8 Finale (Match 2)', '5/8') ?>
                            <?= renderMatchCard($CLES['w16_3'], '1/8 Finale (Match 3)', '9/12') ?>
                            <?= renderMatchCard($CLES['w16_4'], '1/8 Finale (Match 4)', '13/16') ?>
                            <?= renderMatchCard($CLES['w16_5'], '1/8 Finale (Match 5)', '17/20') ?>
                            <?= renderMatchCard($CLES['w16_6'], '1/8 Finale (Match 6)', '21/24') ?>
                            <?= renderMatchCard($CLES['w16_7'], '1/8 Finale (Match 7)', '25/28') ?>
                            <?= renderMatchCard($CLES['w16_8'], '1/8 Finale (Match 8)', '29/32') ?>
                        </div>
                        <div class="logic-col">
                            <div class="col-title">1/4 de Finale</div>
                            <?= renderMatchCard($CLES['w8_1'], '1/4 Finale (Match 1)', '1/8') ?>
                            <?= renderMatchCard($CLES['w8_2'], '1/4 Finale (Match 2)', '9/16') ?>
                            <?= renderMatchCard($CLES['w8_3'], '1/4 Finale (Match 3)', '17/24') ?>
                            <?= renderMatchCard($CLES['w8_4'], '1/4 Finale (Match 4)', '25/32') ?>
                            <div class="col-title" style="margin-top:15px;">Places 9 à 16</div>
                            <?= renderMatchCard($CLES['w8_5'], 'Class. 9-16 (Match 1)', '4/5') ?>
                            <?= renderMatchCard($CLES['w8_6'], 'Class. 9-16 (Match 2)', '12/13') ?>
                            <?= renderMatchCard($CLES['w8_7'], 'Class. 9-16 (Match 3)', '20/21') ?>
                            <?= renderMatchCard($CLES['w8_8'], 'Class. 9-16 (Match 4)', '28/29') ?>
                        </div>
                        <div class="logic-col">
                            <div class="col-title">1/2 Finale</div>
                            <?= renderMatchCard($CLES['w4_1'], '1/2 Finale (Match 1)', '1/16') ?>
                            <?= renderMatchCard($CLES['w4_2'], '1/2 Finale (Match 2)', '17/32') ?>
                            <div class="col-title" style="margin-top:15px;">Places 5 à 8</div>
                            <?= renderMatchCard($CLES['w4_3'], 'Class. 5-8 (Match 1)', '8/9') ?>
                            <?= renderMatchCard($CLES['w4_4'], 'Class. 5-8 (Match 2)', '24/25') ?>
                            <div class="col-title" style="margin-top:15px;">Places 9 à 12</div>
                            <?= renderMatchCard($CLES['w4_5'], 'Class. 9-12 (Match 1)', '5/12') ?>
                            <?= renderMatchCard($CLES['w4_6'], 'Class. 9-12 (Match 2)', '21/28') ?>
                            <div class="col-title" style="margin-top:15px;">Places 13 à 16</div>
                            <?= renderMatchCard($CLES['w4_7'], 'Class. 13-16 (Match 1)', '4/13') ?>
                            <?= renderMatchCard($CLES['w4_8'], 'Class. 13-16 (Match 2)', '20/29') ?>
                        </div>
                        <div class="logic-col">
                            <div class="col-title">Finales (1 à 16)</div>
                            <?= renderMatchCard($CLES['f_1_2'], 'Finale', '1/32', ['1er', '2ème']) ?>
                            <?= renderMatchCard($CLES['f_3_4'], 'Places 3ème/4ème', '16/17', ['3ème', '4ème']) ?>
                            <?= renderMatchCard($CLES['f_5_6'], 'Places 5ème/6ème', '9/24', ['5ème', '6ème']) ?>
                            <?= renderMatchCard($CLES['f_7_8'], 'Places 7ème/8ème', '8/25', ['7ème', '8ème']) ?>
                            <?= renderMatchCard($CLES['f_9_10'], 'Places 9ème/10ème', '5/28', ['9ème', '10ème']) ?>
                            <?= renderMatchCard($CLES['f_11_12'], 'Places 11ème/12ème', '12/21', ['11ème', '12ème']) ?>
                            <?= renderMatchCard($CLES['f_13_14'], 'Places 13ème/14ème', '13/20', ['13ème', '14ème']) ?>
                            <?= renderMatchCard($CLES['f_15_16'], 'Places 15ème/16ème', '4/29', ['15ème', '16ème']) ?>
                        </div>
                    </div>
                </div>

                <div class="logic-section sec-loser">
                    <h3>Places 17 à 32</h3>
                    <div class="logic-grid">
                        <div class="logic-col">
                            <div class="col-title">1/8 FKO</div>
                            <?= renderMatchCard($CLES['l16_1'], '1/8 FKO (Match 1)', '2/3') ?>
                            <?= renderMatchCard($CLES['l16_2'], '1/8 FKO (Match 2)', '6/7') ?>
                            <?= renderMatchCard($CLES['l16_3'], '1/8 FKO (Match 3)', '10/11') ?>
                            <?= renderMatchCard($CLES['l16_4'], '1/8 FKO (Match 4)', '14/15') ?>
                            <?= renderMatchCard($CLES['l16_5'], '1/8 FKO (Match 5)', '18/19') ?>
                            <?= renderMatchCard($CLES['l16_6'], '1/8 FKO (Match 6)', '22/23') ?>
                            <?= renderMatchCard($CLES['l16_7'], '1/8 FKO (Match 7)', '26/27') ?>
                            <?= renderMatchCard($CLES['l16_8'], '1/8 FKO (Match 8)', '30/31') ?>
                        </div>
                        <div class="logic-col">
                            <div class="col-title">1/4 FKO</div>
                            <?= renderMatchCard($CLES['l8_1'], '1/4 FKO (Match 1)', '3/6') ?>
                            <?= renderMatchCard($CLES['l8_2'], '1/4 FKO (Match 2)', '11/14') ?>
                            <?= renderMatchCard($CLES['l8_3'], '1/4 FKO (Match 3)', '19/22') ?>
                            <?= renderMatchCard($CLES['l8_4'], '1/4 FKO (Match 4)', '27/30') ?>
                            <div class="col-title" style="margin-top:15px;">Places 25 à 32</div>
                            <?= renderMatchCard($CLES['l8_5'], 'Class. 25-32 (Match 1)', '2/7') ?>
                            <?= renderMatchCard($CLES['l8_6'], 'Class. 25-32 (Match 2)', '10/15') ?>
                            <?= renderMatchCard($CLES['l8_7'], 'Class. 25-32 (Match 3)', '18/23') ?>
                            <?= renderMatchCard($CLES['l8_8'], 'Class. 25-32 (Match 4)', '26/31') ?>
                        </div>
                        <div class="logic-col">
                            <div class="col-title">1/2 FKO</div>
                            <?= renderMatchCard($CLES['l4_1'], '1/2 FKO (Match 1)', '3/14') ?>
                            <?= renderMatchCard($CLES['l4_2'], '1/2 FKO (Match 2)', '19/30') ?>
                            <div class="col-title" style="margin-top:15px;">Places 21 à 24</div>
                            <?= renderMatchCard($CLES['l4_3'], 'Class. 21-24 (Match 1)', '6/11') ?>
                            <?= renderMatchCard($CLES['l4_4'], 'Class. 21-24 (Match 2)', '22/27') ?>
                            <div class="col-title" style="margin-top:15px;">Places 25 à 28</div>
                            <?= renderMatchCard($CLES['l4_5'], 'Class. 25-28 (Match 1)', '7/10') ?>
                            <?= renderMatchCard($CLES['l4_6'], 'Class. 25-28 (Match 2)', '23/26') ?>
                            <div class="col-title" style="margin-top:15px;">Places 29 à 32</div>
                            <?= renderMatchCard($CLES['l4_7'], 'Class. 29-32 (Match 1)', '2/15') ?>
                            <?= renderMatchCard($CLES['l4_8'], 'Class. 29-32 (Match 2)', '18/31') ?>
                        </div>
                        <div class="logic-col">
                            <div class="col-title">Finales FKO (17 à 32)</div>
                            <?= renderMatchCard($CLES['f_17_18'], 'Places 17/18', '3/30', ['17ème', '18ème']) ?>
                            <?= renderMatchCard($CLES['f_19_20'], 'Places 19/20', '14/19', ['19ème', '20ème']) ?>
                            <?= renderMatchCard($CLES['f_21_22'], 'Places 21/22', '11/22', ['21ème', '22ème']) ?>
                            <?= renderMatchCard($CLES['f_23_24'], 'Places 23/24', '6/27', ['23ème', '24ème']) ?>
                            <?= renderMatchCard($CLES['f_25_26'], 'Places 25/26', '7/26', ['25ème', '26ème']) ?>
                            <?= renderMatchCard($CLES['f_27_28'], 'Places 27/28', '10/23', ['27ème', '28ème']) ?>
                            <?= renderMatchCard($CLES['f_29_30'], 'Places 29/30', '15/18', ['29ème', '30ème']) ?>
                            <?= renderMatchCard($CLES['f_31_32'], 'Places 31/32', '2/31', ['31ème', '32ème']) ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="logic-container">
                <div class="logic-section sec-center">
                    <h3>1/8 Final - Barrages 2e-3e</h3>
                    <div class="logic-grid">
                        <div class='logic-col'><?= renderMatchCard('3/4', "Barrage 1", '3/4') ?></div>
                        <div class='logic-col'><?= renderMatchCard('5/6', "Barrage 2", '5/6') ?></div>
                        <div class='logic-col'><?= renderMatchCard('11/12', "Barrage 3", '11/12') ?></div>
                        <div class='logic-col'><?= renderMatchCard('13/14', "Barrage 4", '13/14') ?></div>
                    </div>
                </div>

                <div class="logic-section sec-winner">
                    <h3>Places 1 à 8</h3>
                    <div class="logic-grid">
                        <div class="logic-col">
                            <div class="col-title">1/4 de Finale</div>
                            <?= renderMatchCard('1/4', '1/4 Finale (Match 1)', '1/4') ?>
                            <?= renderMatchCard('5/8', '1/4 Finale (Match 2)', '5/8') ?>
                            <?= renderMatchCard('9/12', '1/4 Finale (Match 3)', '9/12') ?>
                            <?= renderMatchCard('13/16', '1/4 Finale (Match 4)', '13/16') ?>
                        </div>
                        <div class="logic-col">
                            <div class="col-title">1/2 Finale</div>
                            <?= renderMatchCard('1/8', '1/2 Finale (Match 1)', '1/8') ?>
                            <?= renderMatchCard('9/16', '1/2 Finale (Match 2)', '9/16') ?>
                            <div class="col-title" style="margin-top:15px;">Places 5 à 8</div>
                            <?= renderMatchCard('4/5', 'Class. 5-8 (Match 1)', '4/5') ?>
                            <?= renderMatchCard('12/13', 'Class. 5-8 (Match 2)', '12/13') ?>
                        </div>
                        <div class="logic-col">
                            <div class="col-title">Finales (1 à 8)</div>
                            <?= renderMatchCard('1/16', 'Finale', '1/16', ['1er', '2ème']) ?>
                            <?= renderMatchCard('8/9', 'Places 3ème/4ème', '8/9', ['3ème', '4ème']) ?>
                            <?= renderMatchCard('5/12', 'Places 5ème/6ème', '5/12', ['5ème', '6ème']) ?>
                            <?= renderMatchCard('4/13', 'Places 7ème/8ème', '4/13', ['7ème', '8ème']) ?>
                        </div>
                    </div>
                </div>

                <div class="logic-section sec-loser">
                    <h3>Places 9 à 16</h3>
                    <div class="logic-grid">
                        <div class="logic-col">
                            <div class="col-title">1/4 FKO</div>
                            <?= renderMatchCard('2/3', '1/4 FKO (Match 1)', '2/3') ?>
                            <?= renderMatchCard('6/7', '1/4 FKO (Match 2)', '6/7') ?>
                            <?= renderMatchCard('10/11', '1/4 FKO (Match 3)', '10/11') ?>
                            <?= renderMatchCard('14/15', '1/4 FKO (Match 4)', '14/15') ?>
                        </div>
                        <div class="logic-col">
                            <div class="col-title">Demi (Places 9-12)</div>
                            <?= renderMatchCard('3/6', 'Class. 9-12 (Match 1)', '3/6') ?>
                            <?= renderMatchCard('11/14', 'Class. 9-12 (Match 2)', '11/14') ?>
                            <div class="col-title" style="margin-top:15px;">Demi (Places 13-16)</div>
                            <?= renderMatchCard('2/7', 'Class. 13-16 (Match 1)', '2/7') ?>
                            <?= renderMatchCard('10/15', 'Class. 13-16 (Match 2)', '10/15') ?>
                        </div>
                        <div class="logic-col">
                            <div class="col-title">Finales (9 à 16)</div>
                            <?= renderMatchCard('3/14', 'Places 9ème/10ème', '3/14', ['9ème', '10ème']) ?>
                            <?= renderMatchCard('6/11', 'Places 11ème/12ème', '6/11', ['11ème', '12ème']) ?>
                            <?= renderMatchCard('7/10', 'Places 13ème/14ème', '7/10', ['13ème', '14ème']) ?>
                            <?= renderMatchCard('2/15', 'Places 15ème/16ème', '2/15', ['15ème', '16ème']) ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <div id="network-toast">
        ⚠️ <span>Connexion perdue. Tentative de reconnexion...</span>
    </div>

    <script src="assets/js/app.js"></script>

</body>
</html>