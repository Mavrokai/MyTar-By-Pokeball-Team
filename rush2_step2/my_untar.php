<?php

$fichier_tar = $argv[1];

// Fonction pour lire un en-tête TAR et extraire les métadonnées
function analyser_entete_tar($entete) {
    return [
        'nom_fichier' => trim(substr($entete, 0, 100)), // prend les 100 premiers caractères qui donne le nom du fichier
        // octdec convertie la valeur octale en un nombre décimal.
        'mode' => octdec(trim(substr($entete, 100, 8))), // prend les 8 caractères suivants qui donne le mode de permission en octal (0 à 8)
        'uid' => octdec(trim(substr($entete, 108, 8))), // idem
        'gid' => octdec(trim(substr($entete, 116, 8))), // idem
        'taille' => octdec(trim(substr($entete, 124, 12))), // prend les 12 caractères suivants qui donne la taille du fichier en octal
        'temps_modification' => octdec(trim(substr($entete, 136, 12))), // idem qu'au dessus
        // prend le caractère suivant qui indique le type de fichier (0 à 8) (0 : fichier, 1 : lien, 2 : répertoire, 
        // 3 : charactère spécial, 4 : bloc spécial, 5 : symlink, 6 : hardlink, 7 : fichier FIFO, 8 : socket)
        'typeflag' => trim(substr($entete, 156, 1)),
    ];
}

// Fonction pour supprimer un fichier ou un dossier existant
function supprimer_existant($nom_fichier) {
    if (is_file($nom_fichier)) {
        unlink($nom_fichier); // supprime le fichier
    } elseif (is_dir($nom_fichier)) {
        rmdir($nom_fichier);  // supprime le dossier
    }
}

// Fonction pour extraire un fichier à partir des données d'une archive
function extraire_fichier($nom_fichier, $contenu, &$choix_ecrasement_global) {
    // Si le fichier ou dossier existe déjà
    if (file_exists($nom_fichier)) {
        $choix = $choix_ecrasement_global;

        if ($choix_ecrasement_global === null) {
            // Si aucun choix global, demander à l'utilisateur
            $choix = demander_resolution_conflit($nom_fichier);

            if ($choix === 3) {
                $choix_ecrasement_global = 1; // Appliquer "Écraser tout" globalement
            } elseif ($choix === 4) {
                $choix_ecrasement_global = 2; // Appliquer "Ne pas écraser tout" globalement
            } elseif ($choix === 5) {
                echo "Abandon.\n";
                exit(0);
            }
        }

        // Gérer selon le choix global ou individuel
        if ($choix_ecrasement_global === 1 || $choix === 1) {
            supprimer_existant($nom_fichier); // Écraser
        } elseif ($choix_ecrasement_global === 2 || $choix === 2) {
            echo "Ignoré : $nom_fichier\n";
            return; // Ne pas extraire
        }
    }

    // Crée les dossiers nécessaires
    $dossier = dirname($nom_fichier);
    if (!is_dir($dossier)) {
        mkdir($dossier, 0777, true); // 0777 pour les droits d'accès comme avec le chmod
    }

    // Écrit le fichier
    file_put_contents($nom_fichier, $contenu);
    echo "Extrait : $nom_fichier\n";
}

// Fonction pour demander la résolution des conflits
function demander_resolution_conflit($nom_fichier) {
    echo "Le fichier ou dossier '$nom_fichier' existe déjà. Choisissez une option :\n";
    echo "1. Écraser\n";
    echo "2. Ne pas écraser\n";
    echo "3. Écraser tous\n";
    echo "4. Ne pas écraser tous\n";
    echo "5. Abandonner\n";

    // Lire la réponse de l'utilisateur
    $choix = trim(fgets(STDIN)); // fgets(STDIN) pour lire la ligne quand l'utilisateur aura tapé un choix
    while (!in_array($choix, [1, 2, 3, 4, 5])) {
        echo "Choix invalide. Veuillez entrer une option valide (1-5) :\n";
        $choix = trim(fgets(STDIN)); // fgets(STDIN) pour lire la ligne quand l'utilisateur aura tapé un choix
    }
    return (int)$choix;
}

// Fonction pour extraire une archive TAR
function extraire_tar($fichier_tar) {
    if (!file_exists($fichier_tar)) {
        fwrite(STDERR, "Erreur : Le fichier '$fichier_tar' n'existe pas.\n"); // ERREUR = STDERR, SI le fichier n'existe pas, affiche un message d'erreur
        exit(1); // Sortir du programme avec un code d'erreur
    }

    $handle = fopen($fichier_tar, 'rb');
    if (!$handle) {
        fwrite(STDERR, "Erreur : Impossible d'ouvrir '$fichier_tar' en lecture.\n");
        exit(1);// Sortir du programme avec un code d'erreur
    }

    // Initialisation du choix d'écrasement global (null = pas encore défini)
    $choix_ecrasement_global = null;

    while (!feof($handle)) { // feof permet de lire tout le contenue du fichier
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

        if ($typeflag === '0' || $typeflag === '') { // Si c'est un fichier
            $contenu = fread($handle, $taille);
            extraire_fichier($nom_fichier, $contenu, $choix_ecrasement_global);

            // Sauter les octets de padding
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

// si l'argument est inférieur à 2 caractéres, affiche un messgae d'erreur
if ($argc < 2) {
    fwrite(STDERR, "Erreur : Vous devez spécifier un fichier TAR à extraire.\n");
    exit(1);
}

extraire_tar($fichier_tar);