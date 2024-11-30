<?php

// Fonction pour lire récursivement les fichiers et dossiers rentrer en argument
function lire_fichiers($chemins, $chemin_de_base = '') {
    $Data = [];
    foreach ($chemins as $chemin) {
        $chemin_complet = $chemin_de_base ? $chemin_de_base . DIRECTORY_SEPARATOR . $chemin : $chemin; // Directory separator pour different os "/ windows" et "\linux"

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
                'nom' => rtrim($chemin_complet, '/') . '/', // on ajoute un "/" pour les dossiers
                'type' => 'dossier',
            ];

            // Lire récursivement les fichiers et sous-dossiers du dossier
            //array_diff() calcule la différence entre les tableaux. Elle compare les valeurs des tableaux et retourne un tableau contenant 
            //les valeurs qui existent dans le premier tableau mais pas dans les autres.
            //scandir() permet de lister les fichiers et dossiers d'un répertoire
            $enfants = lire_fichiers(array_diff(scandir($chemin_complet), ['.', '..']), $chemin_complet);
            $Data = array_merge($Data, $enfants); // Fusionner les fichiers trouvés
        }
    }
    return $Data;
}

// Fonction pour créer l'en-tête d'un fichier TAR '0' = fichier, '1' = dossier
// Pack () permet de compresser les données qui sera conforme au format TAR
function creer_entete_tar($nom, $taille, $type = '0') {
    return pack(
// Format de l'en-tête TAR : 
// a100 : nom du fichier (100 caractères)
// a8   : permissions (8 caractères)
// a8   : ID du propriétaire (8 caractères)
// a8   : ID du groupe (8 caractères)
// a12  : taille du fichier (12 caractères)
// a12  : date de modification (12 caractères)
// a8   : checksum (8 caractères)
// a1   : type de fichier (1 caractère : '0' pour fichier, '5' pour dossier)
// a355 : champs réservés (355 caractères)
        'a100a8a8a8a12a12a8a1a355',

// '0755' : Les permissions du fichier ou dossier, exprimées en mode octal (0755 = lecture, écriture, exécution pour le propriétaire, et lecture/exécution pour les autres).
// '0' : L'identifiant utilisateur (UID), ici défini à 0 pour indiquer l'utilisateur root.
// '0' : L'identifiant groupe (GID), ici défini à 0 pour indiquer le groupe root.
// sprintf('%011o', $taille) : La taille du fichier en octal (11 caractères), comme requis par le format TAR.
// time() : Le timestamp Unix actuel, représentant la dernière modification du fichier ou dossier.
// '' : La somme de contrôle (checksum), initialement vide, qui sera calculée et insérée plus tard.
// '' : Un espace réservé pour remplir le reste des champs inutilisés, garantissant que l'en-tête a la taille correcte.
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
    $handle = fopen($fichier_sortie, 'wb'); // Créer un nouveau fichier TAR w: écriture / b : mode binaire

    // Parcourir chaque fichier/dossier et les écrire dans l'archive
    foreach ($fichiers as $fichier) {
        if ($fichier['type'] === 'fichier') {
            // Créer l'en-tête pour le fichier
            $entete = creer_entete_tar($fichier['nom'], $fichier['taille']);
            fwrite($handle, $entete); // Écrire l'en-tête
            fwrite($handle, $fichier['contenu']); // Écrire le contenu du fichier

            // le format tar doit occuper un multiple de 512 octets ajout du padding pour les fichier qui ne sont pas un multiple de 512
            $padding = 512 - ($fichier['taille'] % 512);
            if ($padding < 512) {
                fwrite($handle, str_repeat("\0", $padding)); // Remplir avec des octets nuls
            }
        } elseif ($fichier['type'] === 'dossier') {
            // Créer l'en-tête pour le dossier
            $entete = creer_entete_tar($fichier['nom'], 0, '5'); // toujours 0 pour la taille d'un dossier
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