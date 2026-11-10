<?php
// model/relance.php
// US-22 : relances d'inactivité et rappels d'échéance.
//
// Règles :
//  - un compte désactivé ne reçoit jamais de relance ;
//  - une seule relance récente par personne (tous types confondus) ;
//  - plus de rappel quand l'échéance est passée ou quand la campagne est clôturée avant terme.
//
const SEUIL_INACTIVITE_JOURS = 30;   // sans nouvelle activité depuis ... jours => relance d'inactivité
const FENETRE_RAPPEL_JOURS = 7;      // rappel pour les échéances qui tombent dans les ... prochains jours
const DELAI_ENTRE_RELANCES_JOURS = 7; // pas de seconde relance avant ... jours

/** Personnels actifs sans activité récente et sans relance récente. */
function personnelsInactifsARelancer(PDO $pdo): array
{
    $requete = $pdo->prepare(
        "SELECT p.id, p.nom, p.nom_usage, p.prenom, p.identifiant_scientifique,
                (SELECT MAX(a.cree_le) FROM activite a WHERE a.personnel_id = p.id) AS derniere_activite
         FROM personnel p
         WHERE p.est_actif = TRUE
           AND NOT EXISTS (
               SELECT 1 FROM activite a
               WHERE a.personnel_id = p.id
                 AND a.cree_le >= NOW() - make_interval(days => CAST(:seuil AS integer)))
           AND NOT EXISTS (
               SELECT 1 FROM relance r
               WHERE r.personnel_id = p.id
                 AND r.envoyee_le >= NOW() - make_interval(days => CAST(:delai AS integer)))
         ORDER BY p.nom, p.prenom"
    );
    $requete->execute(['seuil' => SEUIL_INACTIVITE_JOURS, 'delai' => DELAI_ENTRE_RELANCES_JOURS]);
    return $requete->fetchAll();
}

/**
 * Personnels à qui rappeler une échéance : campagne ouverte, non clôturée, qui se termine bientôt,
 * et personne n'a encore soumis d'activité sur sa période. Une ligne par personne (échéance la plus proche).
 */
function rappelsEcheanceARelancer(PDO $pdo): array
{
    $requete = $pdo->prepare(
        "SELECT * FROM (
             SELECT DISTINCT ON (p.id)
                    p.id, p.nom, p.nom_usage, p.prenom, p.identifiant_scientifique,
                    c.id AS campagne_id, c.titre AS campagne_titre, c.date_fin
             FROM campagne c
             JOIN personnel p ON p.est_actif = TRUE
             WHERE c.est_actif = TRUE
               AND c.date_debut <= CURRENT_DATE
               AND c.date_fin >= CURRENT_DATE
               AND c.date_fin <= CURRENT_DATE + CAST(:fenetre AS integer)
               AND NOT EXISTS (
                   SELECT 1 FROM activite a
                   WHERE a.personnel_id = p.id AND a.statut = 'soumise'
                     AND a.date_activite BETWEEN c.date_debut AND c.date_fin)
               AND NOT EXISTS (
                   SELECT 1 FROM relance r
                   WHERE r.personnel_id = p.id
                     AND r.envoyee_le >= NOW() - make_interval(days => CAST(:delai AS integer)))
             ORDER BY p.id, c.date_fin
         ) t
         ORDER BY t.date_fin, t.nom, t.prenom"
    );
    $requete->execute(['fenetre' => FENETRE_RAPPEL_JOURS, 'delai' => DELAI_ENTRE_RELANCES_JOURS]);
    return $requete->fetchAll();
}

/**
 * Enregistre la relance et crée l'alerte de la personne. Renvoie false si elle n'a pas été envoyée
 * (compte désactivé, relance récente déjà envoyée, campagne clôturée ou échue).
 * Les conditions sont revérifiées dans la requête : l'état de la base fait foi, pas la page affichée.
 */
function envoyerRelance(PDO $pdo, int $personnelId, string $type, ?int $campagneId, string $titre, string $message, int $envoyePar): bool
{
    $sql = "INSERT INTO relance (personnel_id, campagne_id, type_relance, envoyee_par)
            SELECT p.id, CAST(:campagne AS integer), :type, :par
            FROM personnel p
            WHERE p.id = :id
              AND p.est_actif = TRUE
              AND NOT EXISTS (
                  SELECT 1 FROM relance r
                  WHERE r.personnel_id = p.id
                    AND r.envoyee_le >= NOW() - make_interval(days => CAST(:delai AS integer)))";
    $params = [
        'campagne' => $campagneId,
        'type' => $type,
        'par' => $envoyePar,
        'id' => $personnelId,
        'delai' => DELAI_ENTRE_RELANCES_JOURS,
    ];
    if ($campagneId !== null) {
        $sql .= " AND EXISTS (
                      SELECT 1 FROM campagne c
                      WHERE c.id = :campagne2 AND c.est_actif = TRUE AND c.date_fin >= CURRENT_DATE)";
        $params['campagne2'] = $campagneId;
    }

    $pdo->beginTransaction();
    $insertion = $pdo->prepare($sql);
    $insertion->execute($params);
    if ($insertion->rowCount() === 0) {
        $pdo->rollBack();
        return false;
    }

    $notification = $pdo->prepare(
        'INSERT INTO notification (personnel_id, titre, message, type_notification)
         VALUES (:personnel, :titre, :message, :type)'
    );
    $notification->execute([
        'personnel' => $personnelId,
        'titre' => $titre,
        'message' => $message,
        'type' => $type === 'INACTIVITE' ? 'RELANCE_INACTIVITE' : 'RAPPEL_ECHEANCE',
    ]);
    $pdo->commit();
    return true;
}

function dernieresRelances(PDO $pdo, int $limite = 20): array
{
    $requete = $pdo->prepare(
        'SELECT r.id, r.type_relance, r.envoyee_le, c.titre AS campagne_titre,
                p.nom, p.nom_usage, p.prenom, p.identifiant_scientifique
         FROM relance r
         JOIN personnel p ON p.id = r.personnel_id
         LEFT JOIN campagne c ON c.id = r.campagne_id
         ORDER BY r.envoyee_le DESC, r.id DESC
         LIMIT ' . (int) $limite
    );
    $requete->execute();
    return $requete->fetchAll();
}
