<!-- views/campagne/creer.php -->
<div class="container">
    <h2>Créer une nouvelle campagne</h2>
    
    <!-- L'affichage des messages flash (succès/erreur) est géré par ta fonction habituelle -->

    <form method="POST" action="index.php?page=creer-campagne">
        <!-- Ton index.php vérifie la fonction csrfValide(), il faut donc envoyer le token -->
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
        
        <div class="form-group">
            <label for="titre">Titre de la campagne :</label>
            <input type="text" id="titre" name="titre" required>
        </div>

        <div class="form-group">
            <label for="periode_reference">Période de référence (ex: 2026-2027) :</label>
            <input type="text" id="periode_reference" name="periode_reference" required>
        </div>

        <div class="form-group">
            <label for="nature">Nature de la campagne :</label>
            <select id="nature" name="nature" required>
                <option value="">-- Sélectionner --</option>
                <option value="Saisie des activités">Saisie des activités</option>
                <option value="Validation annuelle">Validation annuelle</option>
                <option value="Autre">Autre</option>
            </select>
        </div>

        <div class="form-group">
            <label for="date_debut">Date d'ouverture :</label>
            <input type="date" id="date_debut" name="date_debut" required>
        </div>

        <div class="form-group">
            <label for="date_fin">Date de clôture :</label>
            <input type="date" id="date_fin" name="date_fin" required>
        </div>

        <div class="form-group">
            <label for="description">Description (optionnelle) :</label>
            <textarea id="description" name="description" rows="4"></textarea>
        </div>

        <button type="submit" class="btn btn-primary">Enregistrer la campagne</button>
    </form>
</div>