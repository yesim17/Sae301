<?php
require_once __DIR__ . '/../model/campagne.php';
require_once __DIR__ . '/../model/comparaison.php';

function dureeEnJours(array $campagne): int
{
    return (new DateTime($campagne['date_debut']))->diff(new DateTime($campagne['date_fin']))->days + 1;
}

/** Transforme les lignes SQL en tableau [type => total]. */
function regrouperParType(array $lignes): array
{
    $res = [];
    foreach ($lignes as $l) {
        $res[$l['type']] = (int) $l['total'];
    }
    return $res;
}

function pageComparaison(PDO $pdo): void
{
    $campagnes = listerCampagnes($pdo);
    $parId = array_column($campagnes, null, 'id');
    $idA = idDepuis(get('a'));
    $idB = idDepuis(get('b'));
    $soumis = get('comparer') !== '';

    $erreurs = [];
    $alertes = [];
    $resultat = null;
    $insuffisant = false;

    if ($soumis) {
        if (!isset($parId[$idA]) || !isset($parId[$idB])) {
            $erreurs[] = 'Choisissez deux campagnes à comparer.';
        } else {
            $a = $parId[$idA];
            $b = $parId[$idB];

            if ($idA === $idB || ($a['date_debut'] === $b['date_debut'] && $a['date_fin'] === $b['date_fin'])) {
                $erreurs[] = 'Les deux périodes sont identiques : choisissez deux périodes différentes.';
            } else {
                $dureeA = dureeEnJours($a);
                $dureeB = dureeEnJours($b);
                if ($dureeA !== $dureeB) {
                    $alertes[] = "Attention : les deux campagnes n'ont pas la même durée ($dureeA jours contre $dureeB jours). La comparaison peut être biaisée.";
                }

                $parTypeA = regrouperParType(agregerActivitesParType($pdo, $a['date_debut'], $a['date_fin']));
                $parTypeB = regrouperParType(agregerActivitesParType($pdo, $b['date_debut'], $b['date_fin']));

                if (!$parTypeA || !$parTypeB) {
                    $insuffisant = true;
                } else {
                    $types = array_unique(array_merge(array_keys($parTypeA), array_keys($parTypeB)));
                    sort($types);

                    $serieA = [];
                    $serieB = [];
                    foreach ($types as $type) {
                        $serieA[] = $parTypeA[$type] ?? 0;
                        $serieB[] = $parTypeB[$type] ?? 0;
                    }

                    $resultat = [
                        'a' => $a,
                        'b' => $b,
                        'types' => array_values($types),
                        'serie_a' => $serieA,
                        'serie_b' => $serieB,
                        'total_a' => array_sum($serieA),
                        'total_b' => array_sum($serieB),
                    ];
                }
            }
        }
    }

    afficher('comparaison/index', [
        'campagnes' => $campagnes,
        'idA' => $idA,
        'idB' => $idB,
        'erreurs' => $erreurs,
        'alertes' => $alertes,
        'resultat' => $resultat,
        'insuffisant' => $insuffisant,
    ]);
}