<?php
// ==========================================
// CONFIGURATION BDD
// ==========================================
$mbr_host = '127.0.0.1';
$mbr_db   = 'bibliotheque';
$mbr_user = 'root';
$mbr_pass = '';

try {
    $mbr_pdo = new PDO("mysql:host=$mbr_host;charset=utf8", $mbr_user, $mbr_pass);
    $mbr_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $mbr_pdo->exec("CREATE DATABASE IF NOT EXISTS `$mbr_db`");
    $mbr_pdo->exec("USE `$mbr_db`");

    // TABLE livres
    $mbr_pdo->exec("CREATE TABLE IF NOT EXISTS livres (
        id INT AUTO_INCREMENT PRIMARY KEY,
        titre VARCHAR(255),
        auteur VARCHAR(255),
        annee INT,
        disponible BOOLEAN DEFAULT TRUE
    )");

    // TABLE emprunts
    $mbr_pdo->exec("CREATE TABLE IF NOT EXISTS emprunts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        livre_id INT,
        emprunteur VARCHAR(255),
        telephone VARCHAR(50),
        date_emprunt DATE,
        date_retour_prevue DATE,
        statut VARCHAR(20) DEFAULT 'EN_COURS',
        FOREIGN KEY (livre_id) REFERENCES livres(id) ON DELETE CASCADE
    )");

} catch (PDOException $e) {
    die("Erreur BDD : " . $e->getMessage());
}

// ==========================================
// FONCTIONS METIER
// ==========================================

function ajouterLivre($pdo, $titre, $auteur, $annee) {
    $sql = "INSERT INTO livres (titre,auteur,annee) VALUES (?,?,?)";
    return $pdo->prepare($sql)->execute([$titre,$auteur,$annee]);
}

function modifierLivre($pdo, $id, $titre, $auteur, $annee) {
    $sql = "UPDATE livres SET titre=?, auteur=?, annee=? WHERE id=?";
    return $pdo->prepare($sql)->execute([$titre,$auteur,$annee,$id]);
}

function supprimerLivre($pdo, $id) {
    return $pdo->prepare("DELETE FROM livres WHERE id=?")->execute([$id]);
}

function enregistrerEmprunt($pdo, $livre_id, $nom, $tel, $jours) {
    $date1 = date('Y-m-d');
    $date2 = date('Y-m-d', strtotime("+$jours days"));

    $pdo->prepare("INSERT INTO emprunts (livre_id,emprunteur,telephone,date_emprunt,date_retour_prevue)
    VALUES (?,?,?,?,?)")->execute([$livre_id,$nom,$tel,$date1,$date2]);

    $pdo->prepare("UPDATE livres SET disponible=0 WHERE id=?")->execute([$livre_id]);
}

function enregistrerRetour($pdo, $id) {
    $pdo->prepare("UPDATE emprunts SET statut='RENDU' WHERE livre_id=?")->execute([$id]);
    $pdo->prepare("UPDATE livres SET disponible=1 WHERE id=?")->execute([$id]);
}

function listerLivres($pdo) {
    $sql = "SELECT l.*, e.emprunteur, e.telephone, e.date_retour_prevue
            FROM livres l
            LEFT JOIN emprunts e ON l.id=e.livre_id AND e.statut='EN_COURS'
            ORDER BY l.id DESC";

    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}
