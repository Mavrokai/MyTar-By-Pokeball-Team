// Récupération des éléments DOM nécessaires
const dropZone = document.getElementById('drop-zone'); // Zone où les fichiers peuvent être déposés
const fileInput = document.getElementById('file-input'); // Champ d'entrée pour sélectionner des fichiers
const fileList = document.getElementById('file-list'); // Liste où les fichiers sélectionnés seront affichés
const generateButton = document.getElementById('generate-button'); // Bouton pour générer l'archive
const downloadButton = document.getElementById('download-button'); // Bouton pour télécharger l'archive
const statusMessage = document.getElementById('status-message'); // Élément pour afficher les messages de statut

let files = []; // Tableau pour stocker les fichiers sélectionnés

// Fonctionnalité de drag-and-drop
dropZone.addEventListener('dragover', (event) => {
    event.preventDefault(); // Empêche le comportement par défaut pour permettre le dépôt
    dropZone.classList.add('drag-over'); // Ajoute une classe pour indiquer que l'on peut déposer des fichiers
});

dropZone.addEventListener('dragleave', () => {
    dropZone.classList.remove('drag-over'); // Enlève la classe lorsque le fichier quitte la zone de dépôt
});

dropZone.addEventListener('drop', (event) => {
    event.preventDefault(); // Empêche le comportement par défaut lors du dépôt
    dropZone.classList.remove('drag-over'); // Enlève la classe de survol

    const droppedFiles = Array.from(event.dataTransfer.files); // Récupère les fichiers déposés
    files = [...files, ...droppedFiles]; // Ajoute les fichiers déposés au tableau
    updateFileList(); // Met à jour l'affichage de la liste des fichiers
});

// Ouvre le sélecteur de fichiers lorsque la zone de dépôt est cliquée
dropZone.addEventListener('click', () => fileInput.click());

// Écouteur d'événement pour le changement du champ de sélection de fichiers
fileInput.addEventListener('change', () => {
    const selectedFiles = Array.from(fileInput.files); // Récupère les fichiers sélectionnés
    files = [...files, ...selectedFiles]; // Ajoute les fichiers sélectionnés au tableau
    updateFileList(); // Met à jour l'affichage de la liste des fichiers
});

// Fonction pour mettre à jour la liste des fichiers affichés
function updateFileList() {
    fileList.innerHTML = ''; // Efface la liste actuelle
    files.forEach((file, index) => {
        const li = document.createElement('li'); // Crée un nouvel élément de liste
        li.textContent = file.name; // Définit le nom du fichier comme contenu de l'élément

        const removeButton = document.createElement('button'); // Crée un bouton pour supprimer le fichier
        removeButton.textContent = 'Supprimer'; // Définit le texte du bouton
        removeButton.style.marginLeft = '10px'; // Ajoute un espacement à gauche

        // Écouteur d'événement pour supprimer le fichier de la liste
        removeButton.addEventListener('click', () => {
            files.splice(index, 1); // Supprime le fichier du tableau
            updateFileList(); // Met à jour l'affichage de la liste des fichiers
        });

        li.appendChild(removeButton); // Ajoute le bouton de suppression à l'élément de liste
        fileList.appendChild(li); // Ajoute l'élément de liste à la liste affichée
    });
}

// Écouteur d'événement pour le bouton de génération d'archive
generateButton.addEventListener('click', () => {
    const archiveName = document.getElementById('archive-name').value; // Récupère le nom de l'archive

    // Vérifie si des fichiers ont été ajoutés
    if (files.length === 0) {
        alert("Ajoutez au moins un fichier !"); // Alerte si aucun fichier n'est sélectionné
        return;
    }

    // Vérifie si le champ nom d'archive est vide
    if (archiveName.trim() === '') {
        alert("Le nom de l'archive est obligatoire !"); // Alerte si le nom est vide
        return;
    }

    const formData = new FormData(); // Crée un nouvel objet FormData pour envoyer les fichiers
    formData.append('archive_name', archiveName); // Ajoute le nom de l'archive à l'objet FormData
    files.forEach((file) => formData.append('files[]', file)); // Ajoute chaque fichier à l'objet FormData

    // Envoie les données au serveur pour générer l'archive
    fetch('generate_archive.php', {
        method: 'POST', // Méthode d'envoi des données
        body: formData, // Corps de la requête contenant les fichiers
    })
        .then((response) => response.json()) // Convertit la réponse en JSON
        .then((data) => {
            // Vérifie si la création de l'archive a réussi
            if (data.success) {
                statusMessage.textContent = "L'archive a été créée avec succès !"; // Affiche un message de succès
                statusMessage.style.display = 'block'; // Affiche le message
                downloadButton.style.display = 'inline-block'; // Affiche le bouton de téléchargement
                downloadButton.onclick = () => {
                    window.location.href = data.archive_url; // Redirige vers l'URL de l'archive
                };
            } else {
                alert("Erreur lors de la création de l'archive."); // Alerte en cas d'erreur
            }
        })
        .catch((error) => {
            console.error("Erreur :", error); // Affiche l'erreur dans la console
            alert("Erreur de communication avec le serveur."); // Alerte en cas de problème de communication
        });
});