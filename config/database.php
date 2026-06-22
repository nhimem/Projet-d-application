<?php
// Paramètres de connexion à la base de données
$host = '127.0.0.1'; 
$port = '3307';        
$user = 'root';        
$pass = '';            
$db   = 'spidd';    
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Active les exceptions pour capturer les erreurs
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Retourne les résultats sous forme de tableau associatif
    PDO::ATTR_EMULATE_PREPARES   => false,                  // [SÉCURITÉ] : Désactive l'émulation pour éviter les injections SQL
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // =========================================================================
    // [PATCH DE SÉCURITÉ] : GESTION DES ERREURS DE CONNEXION
    // =========================================================================
    
    // 1. Enregistrer l'erreur technique détaillée dans les logs du serveur Apache/PHP
    error_log("Erreur critique de connexion BDD (SPIDD V2) : " . $e->getMessage());
    
    // 2. Renvoyer un code HTTP 500 (Internal Server Error)
    http_response_code(500);
    
    // 3. Afficher une page d'erreur HTML propre et harmonisée avec la charte graphique
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Erreur Système - SPIDD V2</title>
        
        <link rel="icon" type="image/jpeg" href="assets/images/logo_insa.png">
        
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            body {
                font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
                background-color: #f8f9fa;
                color: #1a1a1a;
                display: flex;
                flex-direction: column;
                min-height: 100vh;
            }
            /* Style de la barre de navigation identique aux autres pages */
            .navbar {
                background-color: #ffffff;
                padding: 15px 40px;
                display: flex;
                justify-content: space-between;
                align-items: center;
                border-bottom: 1px solid #eaeaea;
                width: 100%;
            }
            .navbar-brand {
                display: flex !important;
                flex-direction: row !important;
                flex-wrap: nowrap !important;
                align-items: center !important;
                gap: 20px;
            }
            .navbar-brand img.logo-fftt {
                height: 55px;
                width: auto !important;
                max-width: none !important;
                display: inline-block !important;
            }
            .navbar-brand img.logo-insa {
                height: 45px;
                width: auto !important;
                max-width: none !important;
                display: inline-block !important;
            }
            /* Conteneur principal pour centrer la carte d'erreur */
            .error-container {
                flex: 1;
                display: flex;
                justify-content: center;
                align-items: center;
                padding: 40px 20px;
            }
            .error-card {
                background: #ffffff;
                padding: 40px;
                border-radius: 8px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.08);
                text-align: center;
                max-width: 450px;
                border-top: 5px solid #ef4444; /* Ligne rouge d'alerte */
            }
            .error-icon {
                font-size: 50px;
                margin-bottom: 20px;
            }
            .error-title {
                font-size: 22px;
                font-weight: 800;
                color: #132968; /* var(--fftt-navy) */
                margin-bottom: 15px;
            }
            .error-message {
                color: #666666;
                font-size: 15px;
                line-height: 1.6;
                margin-bottom: 30px;
            }
            .btn-retry {
                display: inline-block;
                background: #0044cc; /* var(--fftt-blue) */
                color: white;
                padding: 12px 25px;
                border-radius: 4px;
                text-decoration: none;
                font-weight: bold;
                font-size: 14px;
                transition: all 0.3s;
                border: none;
                cursor: pointer;
            }
            .btn-retry:hover {
                background: #003399;
                box-shadow: 0 5px 15px rgba(0, 68, 204, 0.3);
            }
        </style>
    </head>
    <body>

        <header class="navbar">
            <div class="navbar-brand">
                <img src="assets/images/logo-fftt.jpg" alt="FFTT Logo" class="logo-fftt">
                <img src="assets/images/logo-INSA-CVL.jpg" alt="INSA Logo" class="logo-insa">
            </div>
        </header>

        <div class="error-container">
            <div class="error-card">
                <div class="error-icon">⚠️</div>
                <h1 class="error-title">Service Temporairement Indisponible</h1>
                <p class="error-message">
                    Une erreur critique est survenue lors de la tentative de connexion à la base de données. Nos équipes techniques ont été alertées.<br><br>
                    Veuillez vérifier l'état du serveur local ou réessayer dans quelques instants.
                </p>
                <a href="javascript:window.location.reload(true)" class="btn-retry">Réessayer la connexion</a>
            </div>
        </div>

    </body>
    </html>
    <?php
    exit; 
}
?>