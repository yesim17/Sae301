<?php
require_once __DIR__ . '/../model/campagne.php';
require_once __DIR__ . '/../model/comparaison.php';

function dureeEnJours(array $campagne): int
{
    return (new DateTime($campagne['date_debut']))->diff(new DateTime($campagne['date_fin']))->days + 1;
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

                $donneesA = agregerActivites($pdo, $a['date_debut'], $a['date_fin']);
                $donneesB = agregerActivites($pdo, $b['date_debut'], $b['date_fin']);

                if (!$donneesA || !$donneesB) {
                    $insuffisant = true;
                } else {
                    $nbMois = max(array_column(array_merge($donneesA, $donneesB), 'mois'));
                    $totauxA = array_column($donneesA, 'total', 'mois');
                    $totauxB = array_column($donneesB, 'total', 'mois');
                    $libelles = [];
                    $serieA = [];
                    $serieB = [];
                    for ($m = 1; $m <= $nbMois; $m++) {
                        $libelles[] = 'Mois ' . $m;
                        $serieA[] = (int) ($totauxA[$m] ?? 0);
                        $serieB[] = (int) ($totauxB[$m] ?? 0);
                    }
                    $resultat = [
                        'a' => $a,
                        'b' => $b,
                        'total_a' => array_sum($serieA),
                        'total_b' => array_sum($serieB),
                        'individuelles_a' => array_sum(array_column($donneesA, 'individuelles')),
                        'individuelles_b' => array_sum(array_column($donneesB, 'individuelles')),
                        'collectives_a' => array_sum(array_column($donneesA, 'collectives')),
                        'collectives_b' => array_sum(array_column($donneesB, 'collectives')),
                        'libelles' => $libelles,
                        'serie_a' => $serieA,
                        'serie_b' => $serieB,
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
