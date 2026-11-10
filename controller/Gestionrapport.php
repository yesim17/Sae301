<?php
// controller/Gestionrapport.php
// Génération du rapport d'activités individuel : validation des entrées, revérification du gabarit
// côté serveur, puis envoi du fichier en téléchargement forcé.

require_once __DIR__ . '/../model/rapport.php';

/** Lit les champs du formulaire de rapport. */
function lireFormulaireRapport(): array
{
    return [
        'date_debut' => post('date_debut'),
        'date_fin' => post('date_fin'),
        'gabarit' => post('gabarit'),
        'format' => post('format'),
    ];
}

/** Valide les entrées du formulaire. Renvoie ['champ' => message] ; vide si tout est correct. */
function validerRapport(array $valeurs): array
{
    $erreurs = [];

    if ($valeurs['date_debut'] === '') {
        $erreurs['date_debut'] = 'La date de début est obligatoire.';
    } elseif (!dateValide($valeurs['date_debut'])) {
        $erreurs['date_debut'] = "La date de début n'est pas valide.";
    }

    if ($valeurs['date_fin'] === '') {
        $erreurs['date_fin'] = 'La date de fin est obligatoire.';
    } elseif (!dateValide($valeurs['date_fin'])) {
        $erreurs['date_fin'] = "La date de fin n'est pas valide.";
    }

    if (!isset($erreurs['date_debut']) && !isset($erreurs['date_fin'])
        && $valeurs['date_debut'] > $valeurs['date_fin']) {
        $erreurs['date_fin'] = 'La date de fin doit être postérieure ou égale à la date de début.';
    }

    if ($valeurs['gabarit'] === '') {
        $erreurs['gabarit'] = 'Choisissez un gabarit.';
    }

    if (!isset(FORMATS_EXPORT[$valeurs['format']])) {
        $erreurs['format'] = "Choisissez un format d'export.";
    }

    return $erreurs;
}

/** Message d'alerte conservé en session et affiché à la page suivante. */
function enregistrerAlerteRapport(string $message): void
{
    flash('erreur', $message);
}

/**
 * Revérifie le gabarit côté serveur (le code reçu du formulaire n'est jamais cru) puis prépare
 * le fichier. Renvoie ['alerte' => ?string, 'export' => ?array] : un gabarit refusé laisse
 * « export » à null, donc aucun fichier n'est généré.
 */
function preparerRapport(PDO $pdo, array $utilisateur, array $valeurs): array
{
    $verification = verifierCompatibiliteGabarit(
        $pdo,
        $valeurs['gabarit'],
        (int) $utilisateur['id'],
        (string) $utilisateur['statut'],
        $valeurs['date_debut'],
        $valeurs['date_fin']
    );

    if (!$verification['autorise']) {
        return ['alerte' => $verification['message'], 'export' => null];
    }

    $activites = getActivitesConfirmees(
        $pdo,
        (int) $utilisateur['id'],
        $valeurs['date_debut'],
        $valeurs['date_fin']
    );

    return [
        'alerte' => null,
        'export' => genererExport($activites, [
            'personnel' => $utilisateur,
            'date_debut' => $valeurs['date_debut'],
            'date_fin' => $valeurs['date_fin'],
            'gabarit' => $verification['gabarit'],
        ], $valeurs['format']),
    ];
}

function afficherRapport(PDO $pdo): void
{
    $utilisateur = moi();
    $gabarits = gabaritsDisponibles($pdo, (int) $utilisateur['id'], (string) $utilisateur['statut']);
    $erreurs = [];
    $valeurs = ['date_debut' => '', 'date_fin' => '', 'gabarit' => '', 'format' => 'pdf'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Le jeton CSRF de toute requête POST est déjà vérifié par index.php avant le contrôleur.
        $valeurs = lireFormulaireRapport();
        $erreurs = validerRapport($valeurs);

        if (!$erreurs) {
            $preparation = preparerRapport($pdo, $utilisateur, $valeurs);

            if ($preparation['alerte'] !== null) {
                enregistrerAlerteRapport($preparation['alerte']);
                rediriger('generer_rapport');
            }

            $export = $preparation['export'];
            // Le fichier est la seule réponse : aucune sortie ne doit précéder les en-têtes.
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            foreach (entetesTelechargement($export) as $entete) {
                header($entete);
            }
            echo $export['contenu'];
            exit;
        }
    }

    afficher('Rapport/ViewRapport', [
        'valeurs' => $valeurs,
        'erreurs' => $erreurs,
        'gabarits' => $gabarits,
    ]);
}
