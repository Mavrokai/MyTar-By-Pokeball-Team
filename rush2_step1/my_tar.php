<?php

// Fonction pour lire récursivement les fichiers et dossiers spécifiés
function lire_fichiers($chemins, $chemin_de_base = '') {
    $Data = [];
    foreach ($chemins as $chemin) {
        $chemin_complet = $chemin_de_base ? $chemin_de_base . DIRECTORY_SEPARATOR . $chemin : $chemin;

        // Si c'est un fichier
        if (is_file($chemin_complet)) {
            $Data[] = [
                'nom' => $chemin_complet,
                'taille' => filesize($chemin_complet),
                'type' => 'fichier',
                'contenu' => file_get_contents($chemin_complet),
            ];
        }
        // Si c'est un dossier
        elseif (is_dir($chemin_complet)) {
            $Data[] = [
                'nom' => rtrim($chemin_complet, '/') . '/', // Ajouter un "/" pour les dossiers
                'type' => 'dossier',
            ];

            // Lire récursivement les fichiers et sous-dossiers du dossier
            $enfants = lire_fichiers(array_diff(scandir($chemin_complet), ['.', '..']), $chemin_complet);
            $Data = array_merge($Data, $enfants); // Fusionner les fichiers trouvés
        }
    }
    return $Data;
}

// Fonction pour créer l'en-tête d'un fichier TAR
function creer_entete_tar($nom, $taille, $type = '0') {
    return pack(
        'a100a8a8a8a12a12a8a1a355',
        $nom, '0755', '0', '0', sprintf('%011o', $taille), time(), '', $type, ''
    );
}

// Fonction principale pour créer l'archive
function creer_archive_tar($fichier_sortie, $fichiers) {
    // Vérifier si le fichier existe, et le supprimer
    if (file_exists($fichier_sortie)) {
        echo "Suppression de l'archive existante : $fichier_sortie\n";
        unlink($fichier_sortie); // Supprime l'ancien fichier
    }

    // Ouvrir le fichier en mode binaire pour l'écriture
    $handle = fopen($fichier_sortie, 'wb'); // Créer un nouveau fichier TAR

    // Parcourir chaque fichier/dossier et les écrire dans l'archive
    foreach ($fichiers as $fichier) {
        if ($fichier['type'] === 'fichier') {
            // Créer l'en-tête pour le fichier
            $entete = creer_entete_tar($fichier['nom'], $fichier['taille']);
            fwrite($handle, $entete); // Écrire l'en-tête
            fwrite($handle, $fichier['contenu']); // Écrire le contenu du fichier

            // Ajouter du padding pour aligner à 512 octets
            $padding = 512 - ($fichier['taille'] % 512);
            if ($padding < 512) {
                fwrite($handle, str_repeat("\0", $padding)); // Remplir avec des octets nuls
            }
        } elseif ($fichier['type'] === 'dossier') {
            // Créer l'en-tête pour le dossier
            $entete = creer_entete_tar($fichier['nom'], 0, '5');
            fwrite($handle, $entete); // Écrire l'en-tête du dossier
        }
    }

    // Ajouter des octets de fin à l'archive pour marquer la fin
    fwrite($handle, str_repeat("\0", 1024));
    fclose($handle); // Fermer le fichier de l'archive
}


// Récupérer les chemins des fichiers et dossiers passés en argument
$chemins = array_slice($argv, 1);

// Lire tous les fichiers et dossiers spécifiés
$Data = lire_fichiers($chemins);

// Créer l'archive TAR
creer_archive_tar('output.mytar', $Data);

echo "Archive créée : output.mytar\n";