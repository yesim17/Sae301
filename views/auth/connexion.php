<section class="carte connexion">
    <h1>Connexion</h1>
    <form method="post" action="<?= e(lien('connexion')) ?>">
        <?= champCsrf() ?>

        <?php if (!empty($erreur)): ?>
            <div class="text-danger mb-3"><?= e($erreur) ?></div>
        <?php endif; ?>

        <div class="form-group mb-3">
            <label for="identifiant">Identifiant scientifique ou email</label>
            <input type="text" id="identifiant" name="identifiant" class="form-control" required 
                   value="<?= htmlspecialchars($valeurs['identifiant'] ?? '') ?>">
        </div>

        <div class="form-group mb-3">
            <label for="password">Mot de passe :*</label>
            <input type="password" id="password" name="password" class="form-control" required>
        </div>

        <button type="submit" class="bouton btn btn-primary">Se connecter</button>
    </form>
</section>