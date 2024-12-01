<?php

// Fonction pour lire récursivement les fichiers et dossiers passés en argument
function lire_fichiers($chemins) {
    $Data = []; // Tableau pour stocker les informations sur les fichiers et dossiers
    foreach ($chemins as $chemin) {
        // Si c'est un fichier
        if (is_file($chemin)) {
            $Data[] = [
                'nom' => $chemin, // Nom du fichier
                'taille' => filesize($chemin), // Taille du fichier
                'type' => 'fichier', // Type de l'élément (fichier)
                'contenu' => file_get_contents($chemin), // Contenu du fichier
            ];
        }
        // Si c'est un dossier
        elseif (is_dir($chemin)) {
            $Data[] = [
                'nom' => rtrim($chemin, '/') . '/', // Nom du dossier avec un "/" à la fin
                'type' => 'dossier', // Type de l'élément (dossier)
            ];
            // Récupère les fichiers et dossiers enfants de ce dossier
            $enfants = lire_fichiers(array_diff(scandir($chemin), ['.', '..']), $chemin);
            $Data = array_merge($Data, $enfants); // Fusionne les fichiers trouvés avec le tableau principal
        }
    }
    return $Data; // Retourne le tableau contenant les fichiers et dossiers
}

// Fonction pour créer l'en-tête d'un fichier TAR
function creer_entete_tar($nom, $taille, $type = '0') {
    // Utilise la fonction pack pour créer l'en-tête du fichier TAR
    return pack('a100a8a8a8a12a12a8a1a355',
        $nom, '0755', '0', '0', sprintf('%011o', $taille), time(), '', $type, ''
    );
}

// Fonction principale pour créer l'archive TAR
function creer_archive_tar($fichier_sortie, $fichiers) {
    // Supprime le fichier de sortie s'il existe déjà
    if (file_exists($fichier_sortie)) {
        unlink($fichier_sortie);
    }

    // Ouvre le fichier de sortie en mode écriture binaire
    $handle = fopen($fichier_sortie, 'wb');

    // Parcourt chaque fichier pour l'ajouter à l'archive
    foreach ($fichiers as $fichier) {
        if ($fichier['type'] === 'fichier') {
            $entete = creer_entete_tar($fichier['nom'], $fichier['taille']); // Crée l'en-tête pour le fichier
            fwrite($handle, $entete); // Écrit l'en-tête dans le fichier TAR
            fwrite($handle, $fichier['contenu']); // Écrit le contenu du fichier dans le TAR
            // Calcule le padding nécessaire pour aligner le contenu à 512 octets
            $padding = 512 - ($fichier['taille'] % 512);
            if ($padding < 512) {
                fwrite($handle, str_repeat("\0", $padding)); // Écrit des octets nuls pour le padding
            }
        } elseif ($fichier['type'] === 'dossier') {
            $entete = creer_entete_tar($fichier['nom'], 0, '5'); // Crée l'en-tête pour le dossier
            fwrite($handle, $entete); // Écrit l'en-tête du dossier dans le TAR
        }
    }

    // Écrit un en-tête final de 1024 octets pour terminer l'archive
    fwrite($handle, str_repeat("\0", 1024));
    fclose($handle); // Ferme le fichier de sortie
}

// Récupérer le nom de l'archive et les fichiers uploadés
$archive_name = $_POST['archive_name'] . '.mytar'; // Ajoute l'extension .mytar au nom de l'archive

$files = $_FILES['files']; // Récupère les fichiers uploadés

// Créer un tableau pour stocker les fichiers
$Data = [];

// Traiter chaque fichier uploadé
foreach ($files['tmp_name'] as $key => $tmp_name) {
    if (is_uploaded_file($tmp_name)) { // Vérifie si le fichier a été uploadé
        $Data[] = [
            'nom' => $files['name'][$key], // Nom du fichier
            'taille' => $files['size'][$key], // Taille du fichier
            'type' => 'fichier', // Type de l'élément (fichier)
 'contenu' => file_get_contents($tmp_name), // Contenu du fichier
        ];
    }
}

// Créer l'archive TAR avec les fichiers traités
creer_archive_tar($archive_name, $Data); // Appelle la fonction pour créer l'archive TAR

// Retourner une réponse JSON indiquant le succès de l'opération
echo json_encode([
    'success' => true, // Indique que l'archive a été créée avec succès
    'archive_url' => $archive_name // Fournit l'URL de l'archive créée
]);
?>