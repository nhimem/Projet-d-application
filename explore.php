<?php
// Inclusion du fichier de configuration et de connexion à la base de données
require_once __DIR__ . '/config/database.php';

// 1. Récupération de la liste de toutes les tables
$tables = [];
try {
    $stmt = $pdo->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }
} catch (\PDOException $e) {
    // [SÉCURITÉ] Journalisation de l'erreur sur le serveur et masquage des détails techniques
    error_log("Database Error (SHOW TABLES): " . $e->getMessage());
    die("<div style='padding:20px; color:red; font-weight:bold;'>Erreur lors de la lecture de la liste des tables. Veuillez réessayer ultérieurement.</div>");
}

// 2. Récupération du nom de la table sélectionnée via l'URL (si elle existe)
$selected_table = $_GET['table'] ?? null;
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Explorateur de Données - SPIDD V2</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; display: flex; margin: 0; background: #f4f6f9; height: 100vh; }
        
        /* Menu latéral gauche (Sidebar) */
        .sidebar { width: 250px; background: #2c3e50; color: white; overflow-y: auto; padding: 15px; }
        .sidebar h3 { text-transform: uppercase; font-size: 14px; color: #bdc3c7; border-bottom: 1px solid #34495e; padding-bottom: 10px; }
        .sidebar a { color: #ecf0f1; text-decoration: none; display: block; padding: 8px 0; border-bottom: 1px dashed #34495e; font-size: 14px; }
        .sidebar a:hover, .sidebar a.active { color: #3498db; font-weight: bold; padding-left: 5px; transition: 0.3s; }
        
        /* Zone d'affichage des données (Main Content) */
        .content { flex: 1; padding: 20px; overflow-y: auto; }
        .content h2 { color: #2980b9; margin-top: 0; }
        table { width: 100%; border-collapse: collapse; background: white; margin-top: 15px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        th, td { border: 1px solid #e0e0e0; padding: 10px; text-align: left; font-size: 13px; }
        th { background-color: #3498db; color: white; position: sticky; top: 0; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        tr:hover { background-color: #f1f2f6; }
    </style>
</head>
<body>

    <div class="sidebar">
        <h3>Liste des Tables</h3>
        <?php foreach ($tables as $t): ?>
            <a href="?table=<?= urlencode($t) ?>" class="<?= ($t === $selected_table) ? 'active' : '' ?>">
                📁 <?= htmlspecialchars($t) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="content">
        <?php 
        // Vérification si une table a été sélectionnée et si elle existe bien dans la base de données
        if ($selected_table && in_array($selected_table, $tables)): 
        ?>
            <h2>Contenu de la table : <span><?= htmlspecialchars($selected_table) ?></span></h2>
            <p><em>* Affichage des 50 premières lignes maximum pour optimiser les performances.</em></p>
            
            <?php
            try {
                // Requête de sélection des données avec une limite de sécurité
                $stmt = $pdo->query("SELECT * FROM `$selected_table` LIMIT 50");
                
                // Récupération en mode associatif pour obtenir les noms des colonnes
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (count($rows) > 0) {
                    echo "<table><thead><tr>";
                    
                    // Génération des en-têtes à partir des clés du premier enregistrement
                    foreach (array_keys($rows[0]) as $colName) {
                        echo "<th>" . htmlspecialchars($colName) . "</th>";
                    }
                    echo "</tr></thead><tbody>";
                    
                    // Itération et affichage de chaque ligne de données
                    foreach ($rows as $row) {
                        echo "<tr>";
                        foreach ($row as $cellData) {
                            echo "<td>" . htmlspecialchars((string)$cellData) . "</td>";
                        }
                        echo "</tr>";
                    }
                    echo "</tbody></table>";
                } else {
                    echo "<p style='color: orange;'>⚠️ Cette table est actuellement vide (aucune donnée trouvée).</p>";
                }
            } catch (\PDOException $e) {
                // [SÉCURITÉ] Enregistrement silencieux de l'erreur dans les logs du serveur
                error_log("Query Error on table {$selected_table}: " . $e->getMessage());
                echo "<p style='color:red;'>⚠️ Impossible de charger les données de cette table. Veuillez vérifier les logs du serveur.</p>";
            }
            ?>

        <?php else: ?>
            <h2>Bienvenue sur l'Explorateur de Données SPIDD</h2>
            <p>👈 Veuillez sélectionner une table dans le menu latéral gauche pour explorer ses données brutes.</p>
            <p><strong>Tables importantes à consulter en priorité :</strong></p>
            <ul>
                <li><strong>LICENCIE</strong> : Registre des joueurs et compétiteurs inscrits.</li>
                <li><strong>CLUB</strong> : Base de données des clubs de tennis de table.</li>
                <li><strong>PARTIE / MANCHE</strong> : Historique des matchs et scores détaillés par set.</li>
            </ul>
        <?php endif; ?>
    </div>

</body>
</html>