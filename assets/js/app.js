/* assets/js/app.js */

// =========================================================================
// 1. FONCTION POUR L'EFFET DE SURVOL (SPÉCIFIQUE AU TABLEAU)
// =========================================================================
function initHighlightEffect() {
    const playerRows = document.querySelectorAll('.player-row[data-pid]');
    
    playerRows.forEach(row => {
        row.onmouseenter = function() {
            const pid = this.getAttribute('data-pid');
            if (pid && pid !== '') {
                document.querySelectorAll(`.player-row[data-pid="${pid}"]`).forEach(el => {
                    el.classList.add('highlight-path');
                    const card = el.closest('.match-card');
                    if (card) card.classList.add('highlight-card');
                });
            }
        };

        row.onmouseleave = function() {
            const pid = this.getAttribute('data-pid');
            if (pid && pid !== '') {
                document.querySelectorAll(`.player-row[data-pid="${pid}"]`).forEach(el => {
                    el.classList.remove('highlight-path');
                    const card = el.closest('.match-card');
                    if (card) card.classList.remove('highlight-card');
                });
            }
        };
    });
}

// =========================================================================
// 2. LOGIQUE PRINCIPALE AU CHARGEMENT DU DOM
// =========================================================================
document.addEventListener('DOMContentLoaded', () => {
    const toast = document.getElementById('network-toast');

    // --- A. VÉRIFICATION DE L'ÉTAT DU RÉSEAU ---
    if (toast) {
        window.addEventListener('offline', () => toast.classList.add('show'));
        window.addEventListener('online', () => toast.classList.remove('show'));
        if (!navigator.onLine) toast.classList.add('show');
    }

    // Initialisation des effets de survol
    initHighlightEffect();

    // --- B. AJAX POLLING (Mise à jour auto toutes les 10s) ---
    setInterval(function() {
        // Suspendre la requête si le réseau est coupé
        if (!navigator.onLine) return; 

        const currentUrl = window.location.href;

        // Force le navigateur à ignorer le cache
        fetch(currentUrl, { cache: "no-store" })
            .then(response => {
                if (!response.ok) throw new Error("Serveur injoignable");
                return response.text();
            })
            .then(html => {
                if (toast) toast.classList.remove('show');

                const parser = new DOMParser();
                const newDoc = parser.parseFromString(html, 'text/html');
                const mainContainer = newDoc.querySelector('main.container');
                
                if (mainContainer) {
                    document.querySelector('main.container').innerHTML = mainContainer.innerHTML;
                    // Réappliquer les événements JS sur le nouveau HTML
                    initHighlightEffect();
                }
            })
            .catch(error => {
                console.error('Erreur de mise à jour :', error);
                if (toast) toast.classList.add('show');
            });
    }, 10000);
});

// =========================================================================
// 3. GESTION DE L'ÉCRAN DE CHARGEMENT (PRELOADER)
// =========================================================================
const loaderTimeout = setTimeout(function() {
    const preloader = document.getElementById('preloader');
    if (preloader) preloader.classList.add('active');
}, 400);

window.addEventListener('load', function() {
    clearTimeout(loaderTimeout);
    const preloader = document.getElementById('preloader');
    if (preloader) preloader.classList.remove('active');
});