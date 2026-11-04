<section class="carte connexion">
    <h1>Connexion</h1>
    <p class="aide">Mode développement : la base ne contient pas encore de mots de passe.</p>
    <form method="post" action="<?= e(lien('connexion')) ?>">
        <?= champCsrf() ?>
        <label for="personnel_id">Profil</label>
        <select id="personnel_id" name="personnel_id" required>
            <option value="">Choisir un profil</option>
            <?php foreach ($personnels as $personnel): ?>
                <option value="<?= (int) $personnel['id'] ?>"><?= e(libellePersonnel($personnel)) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="bouton">Se connecter</button>
    </form>
</section>
