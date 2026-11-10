<?php
// model/rapport.php
// Rapport d'activités individuel : activités confirmées sur une période, gabarits liés au statut,
// contrôle de compatibilité et génération des exports.

/** Statut d'activité exclu du rapport définitif : un brouillon n'a pas encore été soumis. */
const STATUT_ACTIVITE_BROUILLON = 'brouillon';

/**
 * Activités confirmées d'un personnel sur la période [dateDebut, dateFin], bornes incluses.
 * Les brouillons sont exclus et le libellé du type d'activité est joint à chaque ligne.
 *
 * Les dates sont au format AAAA-MM-JJ ; une période inversée ou mal formée lève une exception
 * (le contrôleur valide les entrées avant l'appel, cette vérification reste une garde).
 */
function getActivitesConfirmees(PDO $pdo, int $personnelId, string $dateDebut, string $dateFin): array
{
    if (!dateValide($dateDebut) || !dateValide($dateFin)) {
        throw new InvalidArgumentException('Les dates du rapport ne sont pas valides.');
    }
    if ($dateDebut > $dateFin) {
        throw new InvalidArgumentException('La date de début doit précéder ou égaler la date de fin.');
    }

    $requete = $pdo->prepare(
        'SELECT a.id, a.titre, a.description, a.date_activite, a.est_collective, a.statut,
                t.libelle AS type_libelle
         FROM activite a
         LEFT JOIN type_activite t ON t.id = a.type_activite_id
         WHERE a.personnel_id = :personnel
           AND a.date_activite BETWEEN :debut AND :fin
           AND a.statut <> :brouillon
         ORDER BY a.date_activite ASC, a.id ASC'
    );
    $requete->execute([
        'personnel' => $personnelId,
        'debut' => $dateDebut,
        'fin' => $dateFin,
        'brouillon' => STATUT_ACTIVITE_BROUILLON,
    ]);
    return $requete->fetchAll();
}
