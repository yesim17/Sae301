<?php // Récapitulatif des erreurs de validation d'un formulaire. Variable attendue : $erreurs. ?>
<?php if (!empty($erreurs)): ?>
    <div class="message message-erreur" role="alert">
        <p>Le formulaire n'a pas été enregistré. Corrigez les points suivants :</p>
        <ul>
            <?php foreach ($erreurs as $texte): ?>
                <li><?= e($texte) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
