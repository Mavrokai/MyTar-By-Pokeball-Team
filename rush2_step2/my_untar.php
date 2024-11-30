<?php

$fichier_tar = $argv[1];

// Fonction pour lire un en-tête TAR et extraire les métadonnées
function analyser_entete_tar($entete) {
    return [
        'nom_fichier' => trim(substr($entete, 0, 100)),
        'mode' => octdec(trim(substr($entete, 100, 8))),
        'uid' => octdec(trim(substr($entete, 108, 8))),
        'gid' => octdec(trim(substr($entete, 116, 8))),
        'taille' => octdec(trim(substr($entete, 124, 12))),
        'temps_modification' => octdec(trim(substr($entete, 136, 12))),
        'typeflag' => trim(substr($entete, 156, 1)),
    ];
}

// Fonction pour supprimer un fichier ou un dossier existant
function supprimer_existant($nom_fichier) {
    if (is_file($nom_fichier)) {
        unlink($nom_fichier);  // Supprimer le fichier
    } elseif (is_dir($nom_fichier)) {
        rmdir($nom_fichier);  // Supprimer le dossier vide
    }
}

// Fonction pour extraire un fichier à partir des données d'une archive
function extraire_fichier($nom_fichier, $contenu, &$choix_ecrasement) {
    // Si le fichier ou dossier existe déjà, demander à l'utilisateur
    if (file_exists($nom_fichier)) {
        // Réinitialiser le choix d'écrasement à chaque fichier
        $choix_ecrasement = null;

        // Demander à l'utilisateur comment gérer les conflits pour ce fichier
        $choix_ecrasement = demander_resolution_conflit($nom_fichier);

        switch ($choix_ecrasement) {
            case 1:
                // Écraser : supprimer l'existant avant d'extraire
                supprimer_existant($nom_fichier);
                break;
            case 2:
                // Ne pas écraser
                echo "Ignoré : $nom_fichier\n";
                return; // Ne pas extraire
            case 3:
                // Écraser pour tous : appliquer pour tous les prochains fichiers
                $choix_ecrasement = 1; // Forcer écrasement pour tous les prochains fichiers
                supprimer_existant($nom_fichier);
                break;
            case 4:
                // Ne pas écraser pour tous : appliquer pour tous les prochains fichiers
                $choix_ecrasement = 2; // Forcer non-écrasement pour tous les prochains fichiers
                echo "Ignoré : $nom_fichier\n";
                return; // Ne pas extraire
            case 5:
                // Arrêter et quitter
                echo "Abandon.\n";
                exit(0);
        }
    }

    // Crée les dossiers nécessaires
    $dossier = dirname($nom_fichier);
    if (!is_dir($dossier)) {
        mkdir($dossier, 0777, true);
    }

    // Écrit le fichier
    file_put_contents($nom_fichier, $contenu);
    echo "Extrait : $nom_fichier\n";
}

// Fonction pour gérer la résolution des conflits (demander à l'utilisateur)
function demander_resolution_conflit($nom_fichier) {
    echo "Le fichier ou dossier '$nom_fichier' existe déjà. Choisissez une option :\n";
    echo "1. Écraser\n";
    echo "2. Ne pas écraser\n";
    echo "3. Écraser tous\n";
    echo "4. Ne pas écraser tous\n";
    echo "5. Abandonner\n";

    // Lire la réponse de l'utilisateur
    $choix = trim(fgets(STDIN));

    // Vérifier que l'utilisateur a entré une option valide
    while (!in_array($choix, [1, 2, 3, 4, 5])) {
        echo "Choix invalide. Veuillez entrer une option valide (1-5) :\n";
        $choix = trim(fgets(STDIN));
    }

    return (int)$choix;
}

// Fonction pour extraire une archive TAR
function extraire_tar($fichier_tar) {
    if (!file_exists($fichier_tar)) {
        fwrite(STDERR, "Erreur : Le fichier '$fichier_tar' n'existe pas.\n");
        exit(1);
    }

    $handle = fopen($fichier_tar, 'rb');
    if (!$handle) {
        fwrite(STDERR, "Erreur : Impossible d'ouvrir '$fichier_tar' en lecture.\n");
        exit(1);
    }

    // Initialisation du choix d'écrasement global
    $choix_ecrasement = null;

    while (!feof($handle)) {
        // Lire l'en-tête (512 octets)
        $entete = fread($handle, 512);
        if (trim($entete) === '') {
            // Fin de l'archive
            break;
        }

        // Décoder l'en-tête
        $meta = analyser_entete_tar($entete);
        $nom_fichier = $meta['nom_fichier'];
        $taille = $meta['taille'];
        $typeflag = $meta['typeflag'];

        // Si c'est un fichier
        if ($typeflag === '0' || $typeflag === '') {
            // Lire le contenu du fichier
            $contenu = fread($handle, $taille);

            // Extraire le fichier avec gestion des conflits
            extraire_fichier($nom_fichier, $contenu, $choix_ecrasement);

            // Sauter les octets de padding (si nécessaire)
            $padding = 512 - ($taille % 512);
            if ($padding < 512) {
                fread($handle, $padding);
            }
        } elseif ($typeflag === '5') { // Si c'est un dossier
            echo "Création du dossier : $nom_fichier\n";
            if (!is_dir($nom_fichier)) {
                mkdir($nom_fichier, 0777, true);
            }
        }
    }

    fclose($handle);
    echo "Extraction terminée.\n";
}

extraire_tar($fichier_tar);