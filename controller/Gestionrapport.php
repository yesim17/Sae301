<?php
// controller/Gestionrapport.php

require_once __DIR__ . '/../model/rapport.php';

function afficherRapport($pdo) {
    $moi = moi(); 
    $userStatut = $moi['statut'] ?? 'Personnel standard';
    $userId = $moi['id'];

    $messageErreur = $_SESSION['erreur_rapport'] ?? null;
    $messageSucces = $_SESSION['succes_rapport'] ?? null;
    unset($_SESSION['erreur_rapport'], $_SESSION['succes_rapport']);

    $gabarits = getGabaritsCompatibles($pdo, $userStatut);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $dateDebut = $_POST['date_debut'] ?? '';
        $dateFin = $_POST['date_fin'] ?? '';
        $gabaritCode = $_POST['gabarit'] ?? '';
        $format = $_POST['format'] ?? 'pdf';

        $verification = verifierCompatibiliteGabarit($pdo, $gabaritCode, $userStatut);
        
        if (!$verification['autorise']) {
            $_SESSION['erreur_rapport'] = $verification['message'];
            rediriger('generer_rapport');
        }

        $activites = getActivitesConfirmees($pdo, $userId, $dateDebut, $dateFin);

        if ($format === 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="rapport_activites_' . date('Y-m-d') . '.csv"');
            
            $output = fopen('php://output', 'w');
            fputcsv($output, ['ID', 'Titre', 'Description', 'Date', 'Type', 'Statut'], ',', '"', '\\');
            
            foreach ($activites as $act) {
                fputcsv($output, [
                    $act['id'],
                    $act['titre'],
                    $act['description'],
                    $act['date_activite'],
                    $act['type_libelle'] ?? '',
                    $act['statut']
                ], ',', '"', '\\');
            }
            fclose($output);
            exit;
        } else {
            // --- EXPORT PDF COMPLET ET STRUCTURÉ ---
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="rapport_activites_' . date('Y-m-d') . '.pdf"');
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');

            // Construction d'un flux PDF structuré avec la liste des activités
            $pdfContent = "%PDF-1.4\n";
            $pdfContent .= "1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n";
            $pdfContent .= "2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj\n";
            $pdfContent .= "3 0 obj << /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /MediaBox [0 0 595 842] /Contents 5 0 R >> endobj\n";
            $pdfContent .= "4 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj\n";
            
            // Texte et contenu du rapport détaillé
            $stream = "BT /F1 14 Tf 50 780 Td (Rapport d'activites individuel) Tj ";
            $stream .= "0 -20 Td /F1 10 Tf (Periode : Du " . $dateDebut . " au " . $dateFin . ") Tj ";
            $stream .= "0 -15 Td (Statut : " . addslashes($userStatut) . ") Tj ";
            $stream .= "0 -15 Td (Nombre d'activites confirmees : " . count($activites) . ") Tj ";
            
            $y = 700;
            foreach ($activites as $act) {
                $ligne = "- [" . $act['date_activite'] . "] " . $act['titre'] . " (" . ($act['type_libelle'] ?? 'Autre') . ") - Statut : " . $act['statut'];
                $stream .= "0 -25 Td (" . addslashes($ligne) . ") Tj ";
                $y -= 25;
            }
            $stream .= "ET";

            $pdfContent .= "5 0 obj << /Length " . strlen($stream) . " >> stream\n" . $stream . "\nendstream\nendobj\n";
            $pdfContent .= "xref\n0 6\n0000000000 65535 f \n0000000009 00000 n \n0000000058 00000 n \n0000000115 00000 n \n0000000235 00000 n \n0000000305 00000 n \n";
            $pdfContent .= "trailer << /Size 6 /Root 1 0 R >>\nstartxref\n395\n%%EOF";

            echo $pdfContent;
            exit;
        }
    }

    require_once __DIR__ . '/../views/layout/entete.php';
    require_once __DIR__ . '/../views/Rapport/ViewRapport.php';
    require_once __DIR__ . '/../views/layout/pied.php';
}