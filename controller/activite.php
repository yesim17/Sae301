<?php
// controller/activite.php
// Dossier personnel (recherche, filtres, tri), déclaration d'activité et bilan d'équipe.

require_once __DIR__ . '/../model/activite.php';
require_once __DIR__ . '/../model/personnel.php';
require_once __DIR__ . '/../model/reference.php';

const CLES_FILTRES = ['q', 'du', 'au', 'nature', 'type', 'statut', 'tri', 'sens'];

// ---------- Dossier personnel ----------

/** Lit les filtres de l'URL. Une valeur invalide est ignorée plutôt que de provoquer une erreur SQL. */
function lireFiltres(): array
{
    $filtres = [];
    foreach (CLES_FILTRES as $cle) {
        $filtres[$cle] = get($cle);
    }
    if (!dateValide($filtres['du'])) {
        $filtres['du'] = '';
    }
    if (!dateValide($filtres['au'])) {
        $filtres['au'] = '';
    }
    if (!in_array($filtres['nature'], ['individuelle', 'collective'], true)) {
        $filtres['nature'] = '';
    }
    if (idDepuis($filtres['type']) === 0) {
        $filtres['type'] = '';
    }
    if (!isset(STATUTS_ACTIVITE[$filtres['statut']])) {
        $filtres['statut'] = '';
    }
    if (!isset(TRIS_ACTIVITE[$filtres['tri']])) {
        $filtres['tri'] = 'date';
    }
    if ($filtres['sens'] !== 'asc') {
        $filtres['sens'] = 'desc';
    }
    return $filtres;
}

function pageMesActivites(PDO $pdo): void
{
    $filtres = lireFiltres();
    $filtresActifs = $filtres['q'] !== '' || $filtres['du'] !== '' || $filtres['au'] !== ''
        || $filtres['nature'] !== '' || $filtres['type'] !== '' || $filtres['statut'] !== '';

    afficher('activite/liste', [
        'activites' => rechercherMesActivites($pdo, moi()['id'], $filtres),
        'filtres' => $filtres,
        'filtresActifs' => $filtresActifs,
        'types' => typesActivite($pdo),
    ]);
}

/** Action groupée : soumet les brouillons cochés, puis revient à la liste avec les mêmes filtres et le même tri. */
function soumettreSelection(PDO $pdo): void
{
    exigerPost();

    $selection = is_array($_POST['selection'] ?? null) ? $_POST['selection'] : [];
    $ids = [];
    foreach ($selection as $valeur) {
        $id = is_string($valeur) ? idDepuis($valeur) : 0;
        if ($id > 0) {
            $ids[] = $id;
        }
    }

    if (!$ids) {
        flash('erreur', 'Cochez au moins une activité à soumettre.');
    } else {
        $nombre = soumettreActivites($pdo, $ids, moi()['id']);
        if ($nombre === 0) {
            flash('erreur', "Aucune activité soumise : seuls vos brouillons peuvent l'être.");
        } else {
            flash('succes', $nombre === 1 ? '1 activité soumise.' : $nombre . ' activités soumises.');
        }
    }

    $retour = is_array($_POST['retour'] ?? null) ? $_POST['retour'] : [];
    rediriger('activites', array_intersect_key($retour, array_flip(CLES_FILTRES)));
}

// ---------- Déclaration ----------

function lireFormulaireActivite(): array
{
    $specifiques = is_array($_POST['specifiques'] ?? null) ? $_POST['specifiques'] : [];
    $specifiques = array_map(function ($valeur) {
        return is_string($valeur) ? trim($valeur) : '';
    }, $specifiques);

    // Les lignes de co-participants laissées entièrement vides sont ignorées.
    $participants = [];
    $lignes = is_array($_POST['participants'] ?? null) ? $_POST['participants'] : [];
    foreach ($lignes as $ligne) {
        if (!is_array($ligne)) {
            continue;
        }
        $personnel = is_string($ligne['personnel_id'] ?? null) ? idDepuis($ligne['personnel_id']) : 0;
        $role = is_string($ligne['role'] ?? null) ? trim($ligne['role']) : '';
        if ($personnel !== 0 || $role !== '') {
            $participants[] = ['personnel_id' => $personnel, 'role' => $role];
        }
    }

    return [
        'mode' => post('mode') === 'collective' ? 'collective' : 'individuelle',
        'type_activite_id' => idDepuis(post('type_activite_id')),
        'titre' => post('titre'),
        'date_activite' => post('date_activite'),
        'description' => post('description'),
        'structure_id' => idDepuis(post('structure_id')),
        'specifiques' => $specifiques,
        'participants' => $participants,
    ];
}

/** Renvoie la ligne du catalogue correspondant à l'identifiant reçu, ou null. */
function typeChoisi(array $types, int $id): ?array
{
    foreach ($types as $type) {
        if ((int) $type['id'] === $id) {
            return $type;
        }
    }
    return null;
}

/** Une personne n'est valable que si elle figure dans la liste des collègues actifs. */
function estCollegue(array $collegues, int $id): bool
{
    return in_array($id, array_map('intval', array_column($collegues, 'id')), true);
}

function validerActivite(array $v, array $types, array $structures, array $collegues): array
{
    $erreurs = [];

    $type = typeChoisi($types, $v['type_activite_id']);
    if ($type === null) {
        $erreurs['type_activite_id'] = "Choisissez un type d'activité dans le catalogue.";
    }
    if ($v['titre'] === '') {
        $erreurs['titre'] = 'Le titre est obligatoire.';
    } elseif (tropLong($v['titre'], 255)) {
        $erreurs['titre'] = 'Le titre ne doit pas dépasser 255 caractères.';
    }
    if ($v['date_activite'] === '') {
        $erreurs['date_activite'] = 'La date est obligatoire.';
    } elseif (!dateValide($v['date_activite'])) {
        $erreurs['date_activite'] = "La date n'est pas valide.";
    }
    if ($v['structure_id'] !== 0
        && !in_array($v['structure_id'], array_map('intval', array_column($structures, 'id')), true)) {
        $erreurs['structure_id'] = "Cette structure n'existe pas.";
    }

    // Seuls les champs du type sélectionné sont contrôlés : ceux des autres types sont ignorés.
    if ($type !== null) {
        foreach (CHAMPS_PAR_TYPE[$type['code']] ?? [] as $nom => $champ) {
            $valeur = $v['specifiques'][$nom] ?? '';
            if ($champ['obligatoire'] && $valeur === '') {
                $erreurs['specifique_' . $nom] = 'Le champ « ' . $champ['libelle'] . ' » est obligatoire.';
            } elseif ($champ['format'] === 'nombre' && $valeur !== ''
                && (!is_numeric($valeur) || (float) $valeur <= 0)) {
                $erreurs['specifique_' . $nom] = 'Le champ « ' . $champ['libelle'] . ' » doit être un nombre positif.';
            } elseif (tropLong($valeur, 500)) {
                $erreurs['specifique_' . $nom] = 'Le champ « ' . $champ['libelle'] . ' » est trop long.';
            }
        }
    }

    if ($v['mode'] === 'collective') {
        $erreurs += validerParticipants($v['participants'], $collegues);
    }
    return $erreurs;
}

function validerParticipants(array $participants, array $collegues): array
{
    if (!$participants) {
        return ['participants' => 'Une activité collective doit avoir au moins un co-participant.'];
    }

    $erreurs = [];
    $dejaVus = [];
    foreach ($participants as $i => $participant) {
        $numero = 'Co-participant ' . ($i + 1) . ' : ';
        if (!estCollegue($collegues, $participant['personnel_id'])) {
            $erreurs['participant_' . $i] = $numero . 'sélectionnez un membre du personnel.';
        } elseif (in_array($participant['personnel_id'], $dejaVus, true)) {
            $erreurs['participant_' . $i] = $numero . 'cette personne est déjà dans la liste.';
        } elseif ($participant['role'] === '') {
            $erreurs['participant_' . $i] = $numero . 'le rôle est obligatoire.';
        } elseif (tropLong($participant['role'], 100)) {
            $erreurs['participant_' . $i] = $numero . 'le rôle ne doit pas dépasser 100 caractères.';
        }
        $dejaVus[] = $participant['personnel_id'];
    }
    return $erreurs;
}

/** La BDD n'a pas de colonnes pour les champs propres à un type : ils sont placés en tête de la description. */
function composerDescription(array $champs, array $specifiques, string $description): ?string
{
    $lignes = [];
    foreach ($champs as $nom => $champ) {
        $valeur = $specifiques[$nom] ?? '';
        if ($valeur !== '') {
            $lignes[] = $champ['libelle'] . ' : ' . $valeur;
        }
    }
    $texte = implode("\n", $lignes);
    if ($description !== '') {
        $texte .= ($texte !== '' ? "\n\n" : '') . $description;
    }
    return $texte !== '' ? $texte : null;
}

function pageDeclarer(PDO $pdo): void
{
    $utilisateur = moi();
    $types = typesActivite($pdo);
    $structures = structures($pdo);
    $collegues = personnelsActifs($pdo, $utilisateur['id']);
    $erreurs = [];
    $valeurs = [
        'mode' => 'individuelle',
        'type_activite_id' => 0,
        'titre' => '',
        'date_activite' => '',
        'description' => '',
        'structure_id' => (int) $utilisateur['structure_id'],
        'specifiques' => [],
        'participants' => [],
    ];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $valeurs = lireFormulaireActivite();
        $erreurs = validerActivite($valeurs, $types, $structures, $collegues);

        if (!$erreurs) {
            $estCollective = $valeurs['mode'] === 'collective';
            $type = typeChoisi($types, $valeurs['type_activite_id']);
            $id = creerActivite($pdo, [
                'personnel_id' => $utilisateur['id'],
                'titre' => $valeurs['titre'],
                'description' => composerDescription(
                    CHAMPS_PAR_TYPE[$type['code']] ?? [],
                    $valeurs['specifiques'],
                    $valeurs['description']
                ),
                'date_activite' => $valeurs['date_activite'],
                'est_collective' => $estCollective,
                'type_activite_id' => $valeurs['type_activite_id'],
                'structure_id' => $valeurs['structure_id'] !== 0 ? $valeurs['structure_id'] : null,
            ], $estCollective ? $valeurs['participants'] : [], nomComplet($utilisateur));

            flash('succes', $estCollective
                ? 'Activité déclarée. Les co-participants ont reçu une alerte pour confirmer leur participation.'
                : 'Activité déclarée et ajoutée à votre dossier.');
            rediriger('activite', ['id' => $id]);
        }
    }

    afficher('activite/formulaire', [
        'valeurs' => $valeurs,
        'erreurs' => $erreurs,
        'types' => $types,
        'structures' => $structures,
        'collegues' => $collegues,
    ]);
}

// ---------- Détail d'une activité et co-participants ----------

function pageDetail(PDO $pdo): void
{
    $utilisateur = moi();
    $id = idDepuis(get('id'));
    $activite = trouverActiviteVisible($pdo, $id, $utilisateur['id'], $utilisateur['structure_id']);
    if ($activite === null) {
        erreur(404, "Cette activité n'existe pas ou vous n'y avez pas accès.");
    }

    $estDeclarant = (int) $activite['personnel_id'] === (int) $utilisateur['id'];
    afficher('activite/detail', [
        'activite' => $activite,
        'participants' => participantsDesActivites($pdo, [$id])[$id] ?? [],
        'estDeclarant' => $estDeclarant,
        'peutModifierParticipants' => $estDeclarant && $activite['est_collective'] && peut('ACTIVITE_CREATE'),
        'collegues' => personnelsActifs($pdo, $utilisateur['id']),
    ]);
}

function ajouterCoParticipant(PDO $pdo): void
{
    exigerPost();
    $utilisateur = moi();
    $id = idDepuis(post('activite_id'));
    $activite = trouverActiviteVisible($pdo, $id, $utilisateur['id'], $utilisateur['structure_id']);
    if ($activite === null || (int) $activite['personnel_id'] !== (int) $utilisateur['id'] || !$activite['est_collective']) {
        erreur(403, "Seul le déclarant d'une activité collective peut ajouter un co-participant.");
    }

    $participant = ['personnel_id' => idDepuis(post('personnel_id')), 'role' => post('role')];
    $erreurs = validerParticipants([$participant], personnelsActifs($pdo, $utilisateur['id']));
    $existants = array_column(participantsDesActivites($pdo, [$id])[$id] ?? [], 'personnel_id');
    if (in_array($participant['personnel_id'], array_map('intval', $existants), true)) {
        $erreurs[] = 'Cette personne participe déjà à cette activité.';
    }

    if ($erreurs) {
        foreach ($erreurs as $message) {
            flash('erreur', str_replace('Co-participant 1 : ', '', $message));
        }
    } else {
        $pdo->beginTransaction();
        ajouterParticipant(
            $pdo,
            $id,
            $activite['titre'],
            $participant['personnel_id'],
            $participant['role'],
            nomComplet($utilisateur)
        );
        $pdo->commit();
        flash('succes', 'Co-participant ajouté. Une alerte lui a été envoyée.');
    }
    rediriger('activite', ['id' => $id]);
}

function retirerCoParticipant(PDO $pdo): void
{
    exigerPost();
    if (retirerParticipant($pdo, idDepuis(post('participation_id')), moi()['id'])) {
        flash('succes', 'Co-participant retiré.');
    } else {
        flash('erreur', "Retrait impossible : seul le déclarant peut retirer un co-participant, et uniquement s'il n'a pas confirmé.");
    }
    rediriger('activite', ['id' => idDepuis(post('activite_id'))]);
}

// ---------- Bilan d'équipe ----------

function pageBilanEquipe(PDO $pdo): void
{
    $utilisateur = moi();
    $activites = bilanEquipe($pdo, $utilisateur['id'], $utilisateur['structure_id']);
    afficher('activite/bilan', [
        'activites' => $activites,
        'participants' => participantsDesActivites($pdo, array_column($activites, 'id')),
    ]);
}
