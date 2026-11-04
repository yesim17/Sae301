<?php
// config/helpers.php
// Petites fonctions utilisées par toute l'application.

// ---------- Affichage et navigation ----------

/** Échappe un texte avant de l'afficher dans le HTML (protection XSS). */
function e($texte): string
{
    return htmlspecialchars((string) ($texte ?? ''), ENT_QUOTES, 'UTF-8');
}

/** Construit l'URL d'une page. Exemple : lien('activite', ['id' => 3]). */
function lien(string $page, array $params = []): string
{
    return 'index.php?' . http_build_query(['page' => $page] + $params);
}

function rediriger(string $page, array $params = []): void
{
    header('Location: ' . lien($page, $params));
    exit;
}

/** Affiche une vue du dossier views/ dans le gabarit commun. */
function afficher(string $vue, array $variablesDeLaVue = []): void
{
    extract($variablesDeLaVue, EXTR_SKIP);
    require __DIR__ . '/../views/layout/entete.php';
    require __DIR__ . '/../views/' . $vue . '.php';
    require __DIR__ . '/../views/layout/pied.php';
}

/** Arrête le traitement et affiche une page d'erreur. */
function erreur(int $code, string $message): void
{
    http_response_code($code);
    afficher('erreur', ['code' => $code, 'message' => $message]);
    exit;
}

/** Message à afficher une seule fois, à la prochaine page. Types : succes, erreur. */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function lireFlashs(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

function dateFr(?string $date): string
{
    return $date ? date('d/m/Y', strtotime($date)) : '';
}

function dateHeureFr(?string $date): string
{
    return $date ? date('d/m/Y à H:i', strtotime($date)) : '';
}

/** Nom affiché d'une personne : le nom d'usage remplace le nom s'il existe. */
function nomComplet(array $p): string
{
    return trim($p['prenom'] . ' ' . (($p['nom_usage'] ?? '') ?: $p['nom']));
}

/** Libellé d'une personne dans une liste déroulante : l'identifiant départage les homonymes. */
function libellePersonnel(array $p): string
{
    $identifiant = $p['identifiant_scientifique'] ?? '';
    return nomComplet($p) . ($identifiant !== '' ? ' (' . $identifiant . ')' : '');
}

// ---------- Lecture et validation des données reçues ----------

/** Lit un champ texte de formulaire. Une valeur qui n'est pas du texte devient une chaîne vide. */
function post(string $nom): string
{
    $valeur = $_POST[$nom] ?? '';
    return is_string($valeur) ? trim($valeur) : '';
}

function get(string $nom): string
{
    $valeur = $_GET[$nom] ?? '';
    return is_string($valeur) ? trim($valeur) : '';
}

/** Convertit un identifiant reçu en entier. Renvoie 0 s'il est invalide ou trop grand pour la BDD. */
function idDepuis(string $valeur): int
{
    return (ctype_digit($valeur) && strlen($valeur) <= 9) ? (int) $valeur : 0;
}

function dateValide(string $date): bool
{
    $objet = DateTime::createFromFormat('Y-m-d', $date);
    return $objet !== false && $objet->format('Y-m-d') === $date;
}

/** Valide le format envoyé par un champ <input type="datetime-local">. */
function dateHeureValide(string $date): bool
{
    $objet = DateTime::createFromFormat('Y-m-d\TH:i', $date);
    return $objet !== false && $objet->format('Y-m-d\TH:i') === $date;
}

function tropLong(string $texte, int $maximum): bool
{
    return mb_strlen($texte, 'UTF-8') > $maximum;
}

function exigerPost(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        erreur(405, 'Cette action doit être lancée depuis un formulaire.');
    }
}

// ---------- Protection CSRF ----------

function jetonCsrf(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function champCsrf(): string
{
    return '<input type="hidden" name="csrf" value="' . e(jetonCsrf()) . '">';
}

function csrfValide(): bool
{
    $jeton = $_POST['csrf'] ?? '';
    return is_string($jeton) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $jeton);
}

// ---------- Utilisateur connecté et droits ----------

/** Renvoie le personnel connecté (avec ses rôles et permissions), ou null. */
function moi(): ?array
{
    return $GLOBALS['moi'] ?? null;
}

function peut(string $permission): bool
{
    $utilisateur = moi();
    return $utilisateur !== null && in_array($permission, $utilisateur['permissions'], true);
}

/** Vrai si le rôle est détenu en propre, et non reçu par délégation. */
function estTitulaire(string $codeRole): bool
{
    $utilisateur = moi();
    return $utilisateur !== null && in_array($codeRole, $utilisateur['roles_titulaires'], true);
}

/** Applique la règle "droit requis" décrite dans config/routes.php. */
function droitAccorde(string $droit): bool
{
    if ($droit === 'public') {
        return true;
    }
    if (moi() === null) {
        return false;
    }
    if ($droit === 'connecte') {
        return true;
    }
    if (strpos($droit, 'titulaire:') === 0) {
        return estTitulaire(substr($droit, strlen('titulaire:')));
    }
    return peut($droit);
}

/** Sert à masquer les liens et les boutons vers les pages non autorisées. */
function peutAcceder(string $page): bool
{
    $route = $GLOBALS['routes'][$page] ?? null;
    return $route !== null && droitAccorde($route[2]);
}
